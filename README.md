# NeighborNet

Eine Website für Nachbarschaftshilfen.

## Projektstruktur

```
NeighborNet/
├── index.php                    # Hauptseite
├── ueber-uns.php               # Über uns Seite
├── angebote-karte.php          # Angebote & Karte Seite
│
├── features/                   # Feature-basierte Module
│   ├── auth/                   # Authentifizierung
│   │   ├── login.php          # Anmeldeseite
│   │   └── register.php       # Registrierungsseite
│   ├── chat/                   # Chat-Feature
│   │   ├── chatbox.php        # Chatbox-Komponente
│   │   ├── chatbox.js         # Chatbox JavaScript
│   │   └── chatbox.css        # Chatbox Styles
│   └── navigation/            # Navigation
│       ├── navigation.php     # Navigation-Komponente
│       ├── navigation.js      # Navigation JavaScript
│       └── navigation.css     # Navigation Styles
│
├── includes/                   # Wiederverwendbare Komponenten
│   ├── header.php             # HTML Header
│   └── footer.php             # HTML Footer
│
├── assets/                     # Statische Assets
│   ├── css/
│   │   └── style.css          # Hauptstylesheet
│   ├── js/
│   │   └── main.js            # Haupt-JavaScript
│   └── images/                 # Bilder
│
└── config/                     # Konfiguration
    └── config.php             # Konfigurationsdatei
```

## Features

- **Feature-basierte Struktur**: Jedes Feature hat seinen eigenen Ordner mit PHP, CSS und JS
- **Navigation Bar**: Mit Logo, Menü und Auth-Buttons
- **Chatbox**: Mit Drag & Drop und Resize-Funktionalität
- **Responsive Design**: Mobile-optimiert
- **Session-Management**: Für Login/Logout vorbereitet

## Design

- Hauptfarbe: #84BF5E
- Font: Roboto (festgelegt im Header)
- Minimalistisches Design mit wenig Boxen und Outlines

## Verwendung

1. PHP-Server starten (z.B. `php -S localhost:8000`)
2. Im Browser öffnen: `http://localhost:8000`

## Feature-Entwicklung

Neue Features können einfach hinzugefügt werden:
1. Neuen Ordner in `features/` erstellen
2. PHP, CSS und JS Dateien im Feature-Ordner erstellen
3. Feature in `includes/header.php` einbinden (CSS)
4. Feature in `includes/footer.php` einbinden (JS)
5. Feature-Komponente in Seiten einbinden (PHP)
