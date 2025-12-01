# Datenbank Setup

## Initialisierung

Um die Datenbank zu initialisieren, führen Sie aus:

```bash
php sql/init.php
```

Oder verwenden Sie das universelle Schema für alle Datenbanken:

```bash
php sql/universal-schema.php
```

Oder importieren Sie die `schema.sql` Datei direkt in Ihre MySQL-Datenbank.

## Universal Schema

Das **universelle Schema** (`universal-schema.php`) stellt sicher, dass alle benötigten Tabellen in **jeder Datenbank** vorhanden sind, unabhängig davon, ob MySQL, PostgreSQL oder SQLite verwendet wird.

Siehe [SCHEMA-README.md](SCHEMA-README.md) für Details.

## Konfiguration

Die Datenbankverbindung kann in `sql/db.php` angepasst werden:

- `DB_HOST`: Datenbank-Host (Standard: localhost)
- `DB_NAME`: Datenbank-Name (Standard: neighbornet)
- `DB_USER`: Datenbank-Benutzer (Standard: root)
- `DB_PASS`: Datenbank-Passwort (Standard: leer)

## Tabellen

Die Datenbank enthält folgende Tabellen:

- `users`: Benutzer-Accounts
- `angebote`: Angebote/Anzeigen
- `angebote_images`: Bilder zu Angeboten
- `messages`: Chat-Nachrichten

