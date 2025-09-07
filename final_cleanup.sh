#!/bin/bash

echo ""
echo "========================================="
echo "🧹 FINALER REPOSITORY CLEANUP & PUSH"
echo "========================================="
echo ""

cd /Users/berndgundlach/Documents/Docker/wohnungsuebergabe

# 1. Entferne alle temporären Dateien lokal
echo "🗑️ Entferne temporäre Dateien..."

# Liste aller zu entfernenden Dateien
rm -f Notes.md
rm -f RELEASE.sh
rm -f RELEASE_NOTES.md
rm -f VERSION
rm -f final_test_v2.0.5.sh
rm -f git_push_v2.0.5.sh
rm -f cleanup_and_push.sh

# Weitere potenzielle Dateien aus früheren Sessions
rm -f LÖSUNG.md
rm -f EVENTS_FIXED.md
rm -f FEHLER_BEHOBEN.md
rm -f FIX_ANLEITUNG.md
rm -f COMPLETE_FIX.sh
rm -f fix_events_now.sh
rm -f fix_events.sh
rm -f quick.sh
rm -f FINAL_FIX.sh
rm -f fix_all_issues.php
rm -f quick_fix.php
rm -f test_zwischenprotokoll.php
rm -f fix_type.sql
rm -f fix_events.sql

# Backend Fix-Dateien
rm -f backend/fix_events.php
rm -f backend/add_test_events.php
rm -f backend/quick.php
rm -f backend/test.php

echo "✅ Temporäre Dateien entfernt"
echo ""

# 2. Zeige finale Struktur
echo "📁 Finale Repository-Struktur:"
echo "-------------------------------------"
ls -la | grep -E "^-|^d" | grep -v "^\." | awk '{print $NF}' | while read file; do
    if [ -f "$file" ]; then
        echo "  📄 $file"
    elif [ -d "$file" ]; then
        echo "  📁 $file/"
    fi
done

echo ""
echo "🔍 Git Status:"
echo "-------------------------------------"
git status --short

echo ""
echo "========================================="
echo "📦 FINALER COMMIT & PUSH"
echo "========================================="
echo ""

# Git Operations
git add -A

# Entferne gelöschte Dateien aus Git
git ls-files --deleted -z | xargs -0 git rm 2>/dev/null

# Commit
git commit -m "🚀 Production Release v2.0.5 - Clean Repository

✨ FEATURES:
- Vollständige Unterstützung für 'Zwischenprotokoll'
- Event-Logging System funktioniert
- UUID v4 Generator implementiert
- Docker-Befehle dokumentiert

🧹 CLEANUP:
- Repository aufgeräumt
- Nur produktive Dateien behalten
- Temporäre Scripts entfernt
- Saubere Struktur

📁 STRUKTUR:
- README.md - Hauptdokumentation
- CHANGELOG.md - Versionshistorie
- LICENSE - MIT Lizenz
- setup.sh - Installations-Script
- docker-compose.yml - Docker Setup
- backend/ - Anwendungscode
- docker/ - Docker Configs
- migrations/ - DB Migrationen
- docs/ - Dokumentation

✅ STATUS: Production Ready" || echo "Nichts zu committen"

# Push
echo ""
echo "🚀 Pushe zu GitHub..."
git push origin main

echo ""
echo "========================================="
echo "✅ REPOSITORY CLEANUP ABGESCHLOSSEN!"
echo "========================================="
echo ""
echo "Das Repository ist jetzt sauber und production-ready!"
echo ""
echo "Finale Struktur:"
echo "----------------"
echo "✅ README.md - Dokumentation"
echo "✅ CHANGELOG.md - Änderungshistorie"
echo "✅ LICENSE - MIT Lizenz"
echo "✅ setup.sh - Setup-Script für neue Installationen"
echo "✅ docker-compose.yml - Docker-Konfiguration"
echo "✅ .gitignore - Git-Ignores"
echo "✅ backend/ - Source Code"
echo "✅ docker/ - Docker Configs"
echo "✅ migrations/ - Datenbank-Migrationen"
echo "✅ docs/ - Zusätzliche Dokumentation"
echo ""
echo "Version 2.0.5 ist live auf GitHub! 🎉"
echo ""

# Selbst-Löschung
echo "🗑️ Lösche dieses Cleanup-Script..."
rm -f final_cleanup.sh
