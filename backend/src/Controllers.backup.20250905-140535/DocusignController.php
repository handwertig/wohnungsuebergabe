<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Flash;
use App\Settings;

final class DocusignController
{
    public function send(): void
    {
        Auth::requireAuth();
        $protocolId = (string)($_GET['protocol_id'] ?? '');
        if ($protocolId === '') { Flash::add('error','protocol_id fehlt.'); header('Location: /protocols'); return; }

        // Konfig prüfen
        $req = ['docusign.account_id','docusign.base_url','docusign.client_id','docusign.client_secret','docusign.redirect_uri'];
        $missing = [];
        foreach ($req as $k) if (!Settings::get($k)) $missing[] = $k;
        if ($missing) {
            Flash::add('error','DocuSign-Konfiguration unvollständig: '.implode(', ', $missing).'.');
            header('Location: /signatures?protocol_id='.$protocolId); return;
        }

        // An dieser Stelle wird künftig die Envelope-Erzeugung erfolgen.
        // Für jetzt markieren wir „Versand ausgelöst“ (Stub).
        Flash::add('success','DocuSign-Versand (Stub) ausgelöst – API-Anbindung folgt im nächsten Schritt.');
        header('Location: /signatures?protocol_id='.$protocolId);
    }
}
