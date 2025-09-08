<?php
/**
 * EINFACHES DATABASE REPAIR SCRIPT - OHNE ABHÄNGIGKEITEN
 * Behebt die kritischen Datenbankprobleme direkt
 */

echo "=== SIMPLE DATABASE REPAIR ===\n";

// Direkte Datenbankverbindung ohne Framework
try {
    // Docker-Container Standardwerte
    $host = 'db';
    $port = 3306;
    $dbname = 'app';
    $user = 'app'; 
    $pass = 'app';
    
    echo "🔌 Verbinde zur Datenbank...\n";
    echo "Host: $host:$port\n";
    echo "Database: $dbname\n";
    echo "User: $user\n";
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Datenbankverbindung erfolgreich\n\n";
    
    // 1. Prüfe protocols.type ENUM
    echo "1. 🔍 Prüfe protocols.type ENUM...\n";
    $stmt = $pdo->query("
        SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'protocols' 
          AND COLUMN_NAME = 'type'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo "❌ protocols Tabelle oder type Spalte nicht gefunden!\n";
        exit(1);
    }
    
    echo "   Aktueller ENUM: " . $result['COLUMN_TYPE'] . "\n";
    
    if (strpos($result['COLUMN_TYPE'], 'zwischenprotokoll') === false) {
        echo "   ❌ 'zwischenprotokoll' fehlt - erweitere ENUM...\n";
        
        $pdo->exec("
            ALTER TABLE protocols 
            MODIFY COLUMN type ENUM('einzug','auszug','zwischen','zwischenprotokoll') NOT NULL
        ");
        
        echo "   ✅ ENUM erfolgreich erweitert\n";
    } else {
        echo "   ✅ 'zwischenprotokoll' bereits vorhanden\n";
    }
    
    // 2. Prüfe protocol_events Tabelle
    echo "\n2. 🔍 Prüfe protocol_events Tabelle...\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'protocol_events'");
    if ($stmt->rowCount() === 0) {
        echo "   ❌ protocol_events Tabelle fehlt - erstelle...\n";
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS protocol_events (
              id CHAR(36) PRIMARY KEY,
              protocol_id CHAR(36) NOT NULL,
              type ENUM('signed_by_tenant','signed_by_owner','sent_owner','sent_manager','sent_tenant','other') NOT NULL,
              message VARCHAR(255) NULL,
              meta JSON NULL,
              created_by VARCHAR(255) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              CONSTRAINT fk_events_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_protocol ON protocol_events (protocol_id, created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_created_by ON protocol_events (created_by)");
        
        echo "   ✅ protocol_events Tabelle erstellt\n";
    } else {
        echo "   ✅ protocol_events Tabelle existiert\n";
        
        // Prüfe created_by Spalte
        $stmt = $pdo->query("SHOW COLUMNS FROM protocol_events LIKE 'created_by'");
        if ($stmt->rowCount() === 0) {
            echo "   ❌ created_by Spalte fehlt - füge hinzu...\n";
            
            $pdo->exec("ALTER TABLE protocol_events ADD COLUMN created_by VARCHAR(255) NULL AFTER message");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_events_created_by ON protocol_events (created_by)");
            
            echo "   ✅ created_by Spalte hinzugefügt\n";
        } else {
            echo "   ✅ created_by Spalte vorhanden\n";
        }
        
        // Prüfe meta Spalte (optional aber oft erwartet)
        $stmt = $pdo->query("SHOW COLUMNS FROM protocol_events LIKE 'meta'");
        if ($stmt->rowCount() === 0) {
            echo "   ⚠️  meta Spalte fehlt - füge hinzu...\n";
            
            $pdo->exec("ALTER TABLE protocol_events ADD COLUMN meta JSON NULL AFTER message");
            
            echo "   ✅ meta Spalte hinzugefügt\n";
        } else {
            echo "   ✅ meta Spalte vorhanden\n";
        }
    }
    
    // 3. Test Event-Insert
    echo "\n3. 🧪 Teste Event-Logging...\n";
    
    $testId = 'repair-test-' . uniqid();
    $stmt = $pdo->prepare("
        INSERT INTO protocol_events (id, protocol_id, type, message, created_by, created_at)
        VALUES (?, 'test-protocol-id', 'other', 'Repair test event', 'repair-script', NOW())
    ");
    
    if ($stmt->execute([$testId])) {
        echo "   ✅ Test-Event erfolgreich erstellt\n";
        
        // Test-Event wieder löschen
        $pdo->prepare("DELETE FROM protocol_events WHERE id = ?")->execute([$testId]);
        echo "   ✅ Test-Event wieder entfernt\n";
    } else {
        echo "   ❌ Event-Logging fehlgeschlagen\n";
    }
    
    // 4. Final verification
    echo "\n4. 🔍 Finale Verifikation...\n";
    
    // Prüfe finalen ENUM Status
    $stmt = $pdo->query("
        SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'protocols' 
          AND COLUMN_NAME = 'type'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ENUM Status: " . $result['COLUMN_TYPE'] . "\n";
    
    // Prüfe protocol_events Struktur
    $stmt = $pdo->query("DESCRIBE protocol_events");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   protocol_events Spalten: " . implode(', ', $columns) . "\n";
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎉 REPARATUR ERFOLGREICH ABGESCHLOSSEN!\n";
    echo "\n✅ Was behoben wurde:\n";
    echo "   • protocols.type unterstützt jetzt 'zwischenprotokoll'\n";
    echo "   • protocol_events Tabelle ist vollständig\n";
    echo "   • Event-Logging funktioniert\n";
    echo "\n🎯 JETZT TESTEN:\n";
    echo "   1. Öffnen Sie: http://localhost:8080/protocols/edit?id=82cc7de7-7d1e-11f0-89a6-822b82242c5d\n";
    echo "   2. Ändern Sie 'Typ des Vorgangs' auf 'Zwischenprotokoll'\n";
    echo "   3. Klicken Sie 'Speichern'\n";
    echo "   4. Events sollten unter 'Ereignisse & Änderungen' erscheinen\n";
    
} catch (PDOException $e) {
    echo "❌ DATENBANKFEHLER: " . $e->getMessage() . "\n";
    echo "\nMögliche Lösungen:\n";
    echo "1. Prüfen Sie ob Docker-Container laufen: ./docker-manage.sh status\n";
    echo "2. Starten Sie Services neu: ./docker-manage.sh restart\n";
    echo "3. Prüfen Sie Datenbankverbindung in .env Datei\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
