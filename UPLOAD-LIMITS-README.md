# PHP Upload-Limits für Video-Uploads

## Problem
Beim Hochladen großer Videos (z.B. 24MB) erscheint der Fehler:
```
POST Content-Length of X bytes exceeds the limit of 8388608 bytes
```

## Lösung

### Option 1: .htaccess (empfohlen für Apache)
Die `.htaccess` Datei im Projekt-Root wurde bereits erstellt mit:
- `upload_max_filesize = 100M`
- `post_max_size = 100M`
- `max_execution_time = 300`
- `max_input_time = 300`
- `memory_limit = 256M`

**WICHTIG:** PHP-Server muss **neu gestartet** werden, damit die Änderungen wirksam werden!

### Option 2: php.ini direkt bearbeiten
1. Öffne die `php.ini` Datei (Pfad siehe: `php -i | findstr "Loaded Configuration File"`)
2. Suche nach folgenden Einstellungen und ändere sie:
   ```ini
   upload_max_filesize = 100M
   post_max_size = 100M
   max_execution_time = 300
   max_input_time = 300
   memory_limit = 256M
   ```
3. **PHP-Server neu starten**

### Option 3: Für PHP Built-in Server
Wenn du `php -S localhost:3001` verwendest:
- Die `.htaccess` wird **nicht** gelesen
- Du musst die `php.ini` direkt bearbeiten (siehe Option 2)
- **ODER** starte den Server mit erhöhten Limits:
  ```bash
  php -d upload_max_filesize=10M -d post_max_size=10M -S localhost:3001
  ```
- **WICHTIG:** Auch mit Chunked Upload (1MB Chunks) sollten die Limits mindestens 2-3MB sein für Sicherheit

## Server neu starten
Nach Änderungen an `.htaccess` oder `php.ini`:
- **Apache:** Service neu starten oder `httpd -k restart`
- **PHP Built-in Server:** Prozess beenden (Ctrl+C) und neu starten
- **XAMPP/WAMP:** Control Panel → Apache → Restart

## Überprüfen
Nach Neustart prüfen mit:
```bash
php -i | findstr "post_max_size upload_max_filesize"
```

Sollte zeigen:
```
post_max_size => 100M => 100M
upload_max_filesize => 100M => 100M
```

## Aktuelle Limits im Projekt
- **Frontend:** Max. 100MB pro Datei
- **Backend:** Max. 100MB pro Datei
- **Max. Dateien pro Nachricht:** 10
- **Video-Formate:** mp4, webm, ogg, quicktime, x-msvideo
- **Chunked Upload:** Videos > 2MB werden in 1MB Chunks aufgeteilt

## Schnellstart für PHP Built-in Server
Wenn du den PHP Built-in Server verwendest und große Videos hochladen möchtest:

```bash
# Server mit erhöhten Limits starten
php -d upload_max_filesize=10M -d post_max_size=10M -S localhost:3001
```

Dies ermöglicht Chunked Uploads mit 1MB Chunks auch bei Standard-PHP-Konfigurationen.

