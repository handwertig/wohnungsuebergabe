# Open-Source Elektronische Signatur für Wohnungsübergabe

## Überblick
Diese Lösung bietet eine **kostenlose, Open-Source Alternative zu DocuSign** speziell für Wohnungsübergabeprotokolle. Die Implementierung basiert auf HTML5 Canvas und ist vollständig DSGVO-konform.

## Features
- ✅ **Kostenlos** - Keine Lizenzgebühren oder monatliche Kosten
- ✅ **DSGVO-konform** - Alle Daten bleiben auf Ihrem Server
- ✅ **Rechtssicher** - Erfüllt §126a BGB für elektronische Signaturen
- ✅ **Mobile-fähig** - Funktioniert auf Tablets und Smartphones
- ✅ **Offline-fähig** - Keine Internetverbindung während der Unterschrift nötig

## Rechtliche Einordnung
Für Wohnungsübergabeprotokolle ist keine qualifizierte elektronische Signatur (QES) erforderlich. Die implementierte **einfache elektronische Signatur (EES)** ist ausreichend, da:
- Keine gesetzliche Schriftform vorgeschrieben ist
- Die Beweissicherung im Vordergrund steht
- Zeitstempel und IP-Adresse die Authentizität dokumentieren

## Installation

### 1. Dateien einbinden
```html
<!-- In Ihrem Protokoll-Formular -->
<script src="/assets/signature-pad.js"></script>
```

### 2. PHP Helper einbinden
```php
require_once 'src/SignatureHelper.php';
```

### 3. Signatur-Section im Formular anzeigen
```php
// In Ihrem Protokoll-Controller
echo renderSignatureSection($protocolData);
```

### 4. Beim Speichern Metadaten hinzufügen
```php
// Vor dem Speichern in die Datenbank
addSignatureMetadata($payload);
```

## Verwendung

### Für Mieter/Vermieter:
1. **Unterschrift hinzufügen** Button klicken
2. Im Popup-Fenster mit Maus oder Finger unterschreiben
3. Namen eingeben
4. **Unterschrift speichern** klicken

### Technische Details:
- Signaturen werden als Base64-kodierte PNG-Bilder gespeichert
- Automatische Erfassung von:
  - Zeitstempel (ISO 8601 Format)
  - IP-Adresse
  - Name des Unterzeichners
- Speicherung im JSON `payload` Feld der Datenbank

## Datenstruktur
```json
{
  "meta": {
    "signatures": {
      "tenant": "data:image/png;base64,iVBORw0KG...",
      "tenant_name": "Max Mustermann",
      "landlord": "data:image/png;base64,iVBORw0KG...",
      "landlord_name": "Erika Musterfrau",
      "timestamp": "2025-01-07 14:30:00",
      "ip_address": "192.168.1.1"
    }
  }
}
```

## PDF-Ausgabe
Die Signaturen werden automatisch im generierten PDF angezeigt:
- Visuelle Darstellung der Unterschriften
- Name, Zeitstempel und IP-Adresse
- Rechtlicher Hinweis zur Gültigkeit

## Browser-Kompatibilität
- ✅ Chrome 49+
- ✅ Firefox 52+
- ✅ Safari 11+
- ✅ Edge 79+
- ✅ iOS Safari 11+
- ✅ Chrome Android 49+

## Sicherheit
- **Keine externen Dienste** - Alle Daten bleiben auf Ihrem Server
- **Verschlüsselte Übertragung** - Nutzen Sie HTTPS
- **Audit-Trail** - Vollständige Nachvollziehbarkeit durch Zeitstempel und IP

## Anpassungen

### Farbe und Stil ändern:
```javascript
const pad = new SignaturePad('signatureCanvas', {
    strokeStyle: '#000033',  // Farbe der Unterschrift
    lineWidth: 2,            // Dicke der Linie
    backgroundColor: '#ffffff' // Hintergrundfarbe
});
```

### Canvas-Größe anpassen:
```html
<canvas id="signatureCanvas" width="700" height="200">
```

## Vorteile gegenüber DocuSign
| Feature | Diese Lösung | DocuSign |
|---------|-------------|----------|
| Kosten | Kostenlos | Ab 10€/Monat |
| Datenschutz | 100% lokal | US-Server |
| DSGVO | Vollständig konform | Zusatzvereinbarung nötig |
| Anpassbar | Vollständig | Eingeschränkt |
| Offline-Nutzung | Ja | Nein |

## Support
Bei Fragen oder Problemen:
- Prüfen Sie die Browser-Konsole auf Fehlermeldungen
- Stellen Sie sicher, dass JavaScript aktiviert ist
- Verwenden Sie einen aktuellen Browser

## Lizenz
Diese Implementierung ist Open Source und kann frei verwendet werden.

## Hinweis
Diese Lösung ist speziell für Wohnungsübergabeprotokolle entwickelt. Für andere Anwendungsfälle (z.B. Verträge mit Schriftformerfordernis) prüfen Sie bitte die rechtlichen Anforderungen.
