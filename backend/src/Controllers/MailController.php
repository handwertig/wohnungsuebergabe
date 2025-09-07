<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\PdfService;
use App\Settings;
use App\Flash;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

final class MailController
{
    // /protocols/send?protocol_id=...&to=owner|manager|tenant
    public function send(): void
    {
        Auth::requireAuth();
        $pdo = Database::pdo();
        $protocolId = (string)($_GET['protocol_id'] ?? '');
        $toType = (string)($_GET['to'] ?? 'owner');
        if ($protocolId === '' || !in_array($toType, ['owner','manager','tenant'], true)) {
            Flash::add('error','Ungültige Parameter.'); header('Location: /protocols'); return;
        }

        // Jüngste Version bestimmen
        $st = $pdo->prepare("SELECT COALESCE(MAX(version_no),0) FROM protocol_versions WHERE protocol_id=?");
        $st->execute([$protocolId]); $versionNo = (int)$st->fetchColumn();
        if ($versionNo <= 0) { Flash::add('error','Keine Version vorhanden.'); header('Location: /protocols/edit?id='.$protocolId); return; }

        // PDF generieren (falls nicht vorhanden)
        $pdfRow = $pdo->prepare("SELECT signed_pdf_path, pdf_path FROM protocol_versions WHERE protocol_id=? AND version_no=?");
        $pdfRow->execute([$protocolId, $versionNo]);
        $rowPdf = $pdfRow->fetch(\PDO::FETCH_ASSOC);
        
        $path = '';
        if ($rowPdf) {
            // Zuerst signiertes PDF versuchen
            $path = (string)($rowPdf['signed_pdf_path'] ?? '');
            if ($path === '' || !is_file($path)) {
                // Dann normales PDF
                $path = (string)($rowPdf['pdf_path'] ?? '');
            }
        }
        
        // Falls kein PDF vorhanden, generieren
        if ($path === '' || !is_file($path)) {
            if (class_exists('\App\PdfService')) {
                $path = \App\PdfService::renderAndSave($protocolId, $versionNo, true);
            } else {
                // Fallback: Direkter Pfad
                $storageDir = dirname(__DIR__, 2) . '/storage/pdfs/' . $protocolId;
                if (!is_dir($storageDir)) {
                    mkdir($storageDir, 0755, true);
                }
                $path = $storageDir . '/v' . $versionNo . '.pdf';
                
                // PDF muss existieren oder generiert werden
                if (!is_file($path)) {
                    Flash::add('error', 'PDF konnte nicht generiert werden.');
                    header('Location: /protocols/edit?id=' . $protocolId);
                    return;
                }
            }
        }

        // Protokoll + Payload laden (für Empfänger)
        $p = $pdo->prepare("SELECT p.id,p.owner_id,p.manager_id,p.tenant_name,p.payload,
                                   o.email AS owner_email, m.email AS manager_email
                            FROM protocols p
                            LEFT JOIN owners o ON o.id=p.owner_id
                            LEFT JOIN managers m ON m.id=p.manager_id
                            WHERE p.id=? LIMIT 1");
        $p->execute([$protocolId]); $row = $p->fetch(PDO::FETCH_ASSOC);
        if (!$row) { Flash::add('error','Protokoll nicht gefunden.'); header('Location: /protocols'); return; }
        $payload = json_decode((string)$row['payload'], true) ?: [];
        $meta = $payload['meta'] ?? [];
        $addr = $payload['address'] ?? [];

        // Empfänger bestimmen
        $toEmail = '';
        if ($toType === 'owner')   $toEmail = (string)($row['owner_email'] ?? $payload['owner']['email'] ?? '');
        if ($toType === 'manager') $toEmail = (string)($row['manager_email'] ?? '');
        if ($toType === 'tenant')  $toEmail = (string)($meta['tenant_contact']['email'] ?? '');

        if ($toEmail === '') { Flash::add('error','Keine E‑Mailadresse für Empfänger vorhanden.'); header('Location: /protocols/edit?id='.$protocolId); return; }

        // SMTP Settings
        $host = Settings::get('smtp_host','mailpit');
        $port = (int)(Settings::get('smtp_port','1025') ?? 1025);
        $secure = Settings::get('smtp_secure',''); // '', tls, ssl
        $user = Settings::get('smtp_user','');
        $pass = Settings::get('smtp_pass','');
        $fromAddr = Settings::get('smtp_from_email','app@example.com');
        $fromName = Settings::get('smtp_from_name','Wohnungsübergabe');

        // Mail
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        if ($secure === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        if ($secure === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth = ($user !== '' || $pass !== '');
        if ($mail->SMTPAuth) { $mail->Username = $user; $mail->Password = $pass; }
        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($toEmail);

        $subject = sprintf('Übergabeprotokoll – %s %s – %s (v%d)',
            (string)($addr['street'] ?? ''), (string)($addr['house_no'] ?? ''), (string)($addr['city'] ?? ''), $versionNo);
        $mail->Subject = $subject;
        $mail->Body = "Guten Tag,\n\n" .
                  "im Anhang erhalten Sie das Übergabeprotokoll (" . $row['tenant_name'] . ")." .
                  "\nObjekt: " . ($addr['street'] ?? '') . " " . ($addr['house_no'] ?? '') . ", " . ($addr['city'] ?? '') . "\n" .
                  "Version: v" . $versionNo . "\n\n" .
                  "Mit freundlichen Grüßen\nWohnungsübergabe";
        $mail->addAttachment($path, 'protokoll_v'.$versionNo.'.pdf');

        // Log vorbereiten
        $logId = (string)$pdo->query("SELECT UUID()")->fetchColumn();
        $pdo->prepare("INSERT INTO email_log (id,protocol_id,recipient_type,to_email,subject,status,created_at) VALUES (?,?,?,?,?,'queued',NOW())")
            ->execute([$logId,$protocolId,$toType,$toEmail,$subject]);

        try {
            $mail->send();
            $pdo->prepare("UPDATE email_log SET status='sent', sent_at=NOW() WHERE id=?")->execute([$logId]);

            // Event anlegen
            $etype = $toType==='owner'?'sent_owner':($toType==='manager'?'sent_manager':'sent_tenant');
            $pdo->prepare("INSERT INTO protocol_events (id,protocol_id,type,created_at) VALUES (UUID(),?,?,NOW())")
                ->execute([$protocolId,$etype]);

            Flash::add('success','E‑Mail versendet an '.$toEmail.'.');
        } catch (\Throwable $e) {
            // Prüfe ob error_msg Spalte existiert
            $stmt = $pdo->query("SHOW COLUMNS FROM email_log LIKE 'error_msg'");
            if ($stmt->rowCount() > 0) {
                $pdo->prepare("UPDATE email_log SET status='failed', error_msg=? WHERE id=?")->execute([substr($e->getMessage(),0,500),$logId]);
            } else {
                // Fallback: nur Status updaten
                $pdo->prepare("UPDATE email_log SET status='failed' WHERE id=?")->execute([$logId]);
                // Spalte hinzufügen für nächstes Mal
                try {
                    $pdo->exec("ALTER TABLE email_log ADD COLUMN error_msg TEXT AFTER status");
                } catch (\PDOException $ex) {
                    // Ignorieren falls bereits vorhanden
                }
            }
            Flash::add('error','Versand fehlgeschlagen: '.$e->getMessage());
        }

        header('Location: /protocols/edit?id='.$protocolId); exit;
    }
}
