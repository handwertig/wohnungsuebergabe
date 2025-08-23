<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Database;
use App\Flash;

final class DocusignWebhookController
{
    /** POST: /docusign/webhook (vereinfachter JSON-Webhook) */
    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(400); echo json_encode(['status'=>'error','message'=>'invalid JSON']); return;
        }
        $protocolId = (string)($json['protocol_id'] ?? '');
        $versionNo  = (int)($json['version_no'] ?? 0);
        $envelopeId = (string)($json['envelope_id'] ?? '');
        $pdf64      = (string)($json['pdf_base64'] ?? '');

        if ($protocolId === '' || $versionNo <= 0) {
            http_response_code(400); echo json_encode(['status'=>'error','message'=>'protocol_id/version_no missing']); return;
        }

        $pdo = Database::pdo();

        // optional: PDF speichern
        $signedPath = null;
        if ($pdf64 !== '') {
            $pdfBin = base64_decode($pdf64, true);
            if ($pdfBin === false || strlen($pdfBin) < 100) {
                http_response_code(400); echo json_encode(['status'=>'error','message'=>'invalid pdf_base64']); return;
            }
            $base = realpath(__DIR__.'/../../storage/pdfs') ?: (__DIR__.'/../../storage/pdfs');
            if (!is_dir($base)) @mkdir($base, 0775, true);
            $dir = $base . '/' . $protocolId;
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $signedPath = $dir . '/v'.$versionNo.'-signed.pdf';
            file_put_contents($signedPath, $pdfBin);
        }

        // protocol_versions aktualisieren
        $sql = "UPDATE protocol_versions
                   SET signed_pdf_path = COALESCE(?, signed_pdf_path),
                       signed_at = COALESCE(signed_at, NOW())
                 WHERE protocol_id=? AND version_no=?";
        $pdo->prepare($sql)->execute([$signedPath, $protocolId, $versionNo]);

        // Eventlog optional (falls Tabelle vorhanden)
        try {
            $pdo->prepare("INSERT INTO protocol_events (id,protocol_id,type,message,created_at) VALUES (UUID(),?, 'signed_by_owner', ?, NOW())")
                ->execute([$protocolId, 'Envelope '.$envelopeId.' completed']);
        } catch (\Throwable $e) { /* ignore */ }

        echo json_encode(['status'=>'ok','signed_pdf_path'=>$signedPath]);
    }
}
