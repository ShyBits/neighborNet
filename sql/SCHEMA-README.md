# Universal Database Schema für NeighborNet

## Übersicht

Das `universal-schema.php` Skript stellt sicher, dass **alle benötigten Tabellen** in jeder Datenbank vorhanden sind, unabhängig davon, ob MySQL, PostgreSQL oder SQLite verwendet wird.

## Unterstützte Datenbanken

- **MySQL** (5.7+, MariaDB 10.2+)
- **PostgreSQL** (9.5+)
- **SQLite** (3.6.19+)

## Tabellen

Das Schema erstellt folgende Tabellen in der richtigen Reihenfolge:

### 1. **users** - Benutzeraccounts
- `id` - Primärschlüssel
- `username` - Eindeutiger Benutzername
- `email` - Eindeutige E-Mail
- `password` - Passwort-Hash
- `first_name`, `last_name` - Name
- `street`, `house_number`, `postcode`, `city` - Adresse
- `avatar` - Avatar-Pfad
- `completed_helps` - Anzahl abgeschlossener Hilfen
- `encryption_public_key` - Verschlüsselungsschlüssel
- `created_at` - Erstellungsdatum

### 2. **angebote** - Angebote/Anzeigen
- `id` - Primärschlüssel
- `user_id` - FK zu users
- `title`, `description` - Titel und Beschreibung
- `category` - Kategorie
- `start_date`, `end_date`, `start_time`, `end_time` - Zeitraum
- `address`, `lat`, `lng` - Adresse und Koordinaten
- `required_persons` - Benötigte Personen
- `created_at` - Erstellungsdatum

### 3. **angebote_images** - Bilder zu Angeboten
- `id` - Primärschlüssel
- `angebot_id` - FK zu angebote
- `image_path` - Pfad zum Bild
- `created_at` - Erstellungsdatum

### 4. **chats** - Chat-Gruppen
- `id` - Primärschlüssel
- `created_at`, `updated_at` - Zeitstempel

### 5. **chat_participants** - Chat-Teilnehmer
- `id` - Primärschlüssel
- `chat_id` - FK zu chats
- `user_id` - FK zu users
- `last_read_message_id` - Letzte gelesene Nachricht
- `last_read_at` - Zeitpunkt des letzten Lesens
- `joined_at` - Beitrittsdatum
- UNIQUE(`chat_id`, `user_id`)

### 6. **chat_metadata** - Chat-Metadaten
- `chat_id` - Primärschlüssel, FK zu chats
- `last_message_id` - ID der letzten Nachricht
- `last_message_at` - Zeitpunkt der letzten Nachricht
- `unread_count_user_1`, `unread_count_user_2` - Ungelesene Nachrichten

### 7. **messages** - Chat-Nachrichten
- `id` - Primärschlüssel
- `chat_id` - FK zu chats (optional)
- `sender_id`, `receiver_id` - FK zu users
- `message` - Nachrichtentext (LONGTEXT für base64 Bilder)
- `encrypted` - Verschlüsselt (Boolean)
- `file_path`, `file_type` - Datei-Informationen
- `read_at` - Zeitpunkt des Lesens
- `created_at` - Erstellungsdatum

### 8. **anfragen** - Anfragen auf Angebote
- `id` - Primärschlüssel
- `angebot_id` - FK zu angebote
- `user_id` - FK zu users
- `message` - Nachricht
- `status` - Status (pending, confirmed, etc.)
- `confirmed_at` - Bestätigungszeitpunkt
- `completed_by_helper`, `completed_by_requester` - Abschlusszeitpunkte
- `created_at`, `updated_at` - Zeitstempel
- UNIQUE(`angebot_id`, `user_id`)

### 9. **chat_requests** - Chat-Anfragen
- `id` - Primärschlüssel
- `anfrage_id` - FK zu anfragen
- `chat_id` - FK zu chats (optional)
- `requester_id`, `helper_id` - FK zu users
- `status` - Status
- `created_at`, `updated_at` - Zeitstempel
- UNIQUE(`anfrage_id`)

### 10. **user_activity** - User-Aktivität für Online-Status
- `user_id` - Primärschlüssel, FK zu users
- `last_activity` - Letzte Aktivität

## Verwendung

### 1. Via Command Line
```bash
php sql/universal-schema.php
```

### 2. Via init.php
```bash
php sql/init.php
```

### 3. Via ensure-schema.php (Web oder CLI)
```bash
php sql/ensure-schema.php
```
oder im Browser: `http://your-domain/sql/ensure-schema.php`

### 4. Via PHP Include
```php
require_once 'sql/universal-schema.php';
$result = ensureAllTables();
```

### 5. Automatisch in Datenbank-View
Die Datenbank-View (`sql/datenbank-view/`) ruft automatisch `ensureAllTables()` auf, wenn die Seite geladen wird.

## Features

- ✅ **Datenbankunabhängig**: Funktioniert mit MySQL, PostgreSQL und SQLite
- ✅ **Idempotent**: Kann mehrfach ausgeführt werden ohne Fehler
- ✅ **Automatische Migrationen**: Fügt fehlende Spalten hinzu
- ✅ **Foreign Keys**: Werden automatisch erstellt (wenn unterstützt)
- ✅ **Indizes**: Optimierte Indizes für Performance
- ✅ **UNIQUE Constraints**: Verhindern Duplikate
- ✅ **Konsistente Struktur**: Gleiche Tabellen in jeder Datenbank

## Datenbank-spezifische Anpassungen

### MySQL
- `INT AUTO_INCREMENT` für IDs
- `LONGTEXT` für große Nachrichten
- `TINYINT(1)` für Booleans
- `ENGINE=InnoDB` mit utf8mb4
- `ON UPDATE CURRENT_TIMESTAMP`

### PostgreSQL
- `SERIAL` für Auto-Increment
- `TEXT` für alle Text-Typen
- `BOOLEAN` für Booleans
- Keine Engine/Charset-Angaben
- Kein `ON UPDATE CURRENT_TIMESTAMP` (verwendet Triggers)

### SQLite
- `INTEGER PRIMARY KEY AUTOINCREMENT` für IDs
- `TEXT` für alle Text-Typen
- `INTEGER` für Booleans
- Keine Engine/Charset-Angaben
- Kein `ON UPDATE CURRENT_TIMESTAMP`

## Rückgabewert

Die Funktion `ensureAllTables()` gibt ein Array zurück:

```php
[
    'success' => true,
    'created' => ['users', 'angebote', 'messages', ...],
    'errors' => [],
    'driver' => 'mysql'
]
```

## Fehlerbehandlung

- Fehler beim Erstellen von Tabellen werden gesammelt, aber nicht abgebrochen
- Foreign Keys werden nur erstellt, wenn die Datenbank sie unterstützt
- Fehlende Spalten werden automatisch hinzugefügt
- Bestehende Tabellen werden nicht überschrieben

## Wartung

Bei Schema-Änderungen:
1. Tabellendefinition in `universal-schema.php` aktualisieren
2. Migrations-Array für neue Spalten erweitern
3. `ensureAllTables()` ausführen

Die Funktion ist idempotent und kann sicher mehrfach ausgeführt werden.

