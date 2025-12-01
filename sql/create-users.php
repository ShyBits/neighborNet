<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/create-tables.php';

// Anzahl der zu erstellenden Benutzer
$totalUsers = 23283;

// Deutsche Vornamen und Nachnamen
$firstNames = [
    'Max', 'Sophia', 'Lukas', 'Emma', 'Noah', 'Mia', 'Ben', 'Hannah', 'Jonas', 'Anna',
    'Finn', 'Emilia', 'Leon', 'Lina', 'Paul', 'Marie', 'Henry', 'Lea', 'Felix', 'Lena',
    'Anton', 'Amelie', 'Theo', 'Clara', 'Emil', 'Luisa', 'Oskar', 'Ida', 'Louis', 'Greta',
    'Julian', 'Frieda', 'Matteo', 'Mila', 'David', 'Ella', 'Liam', 'Maja', 'Milan', 'Lilly',
    'Alexander', 'Nora', 'Samuel', 'Zoe', 'Tim', 'Lia', 'Jakob', 'Mira', 'Elias', 'Lara',
    'Aaron', 'Elisa', 'Rafael', 'Paula', 'Luca', 'Mathilda', 'Nico', 'Charlotte', 'Moritz', 'Luise',
    'Johannes', 'Amelia', 'Philipp', 'Hanna', 'Sebastian', 'Nele', 'Daniel', 'Johanna', 'Fabian', 'Sarah',
    'Simon', 'Laura', 'Tobias', 'Julia', 'Michael', 'Lisa', 'Andreas', 'Katharina', 'Stefan', 'Nina',
    'Thomas', 'Stephanie', 'Christian', 'Melanie', 'Markus', 'Jessica', 'Martin', 'Jennifer', 'Peter', 'Nicole'
];

$lastNames = [
    'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann',
    'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Zimmermann',
    'Braun', 'Krüger', 'Hofmann', 'Hartmann', 'Lange', 'Schmitt', 'Werner', 'Schmitz', 'Krause', 'Meier',
    'Lehmann', 'Schmid', 'Schulze', 'Maier', 'Köhler', 'Herrmann', 'König', 'Walter', 'Mayer', 'Huber',
    'Kaiser', 'Fuchs', 'Peters', 'Lang', 'Scholz', 'Möller', 'Weiß', 'Jung', 'Hahn', 'Schubert',
    'Vogel', 'Friedrich', 'Keller', 'Günther', 'Frank', 'Berger', 'Winkler', 'Roth', 'Beck', 'Lorenz',
    'Baumann', 'Franke', 'Albrecht', 'Schuster', 'Simon', 'Ludwig', 'Böhm', 'Winter', 'Kraus', 'Martin',
    'Schumacher', 'Krämer', 'Vogt', 'Stein', 'Jäger', 'Otto', 'Sommer', 'Groß', 'Seidel', 'Heinrich',
    'Brandt', 'Haas', 'Schreiber', 'Graf', 'Schulte', 'Dietrich', 'Ziegler', 'Kuhn', 'Kühn', 'Pohl',
    'Engel', 'Busch', 'Horn', 'Bergmann', 'Pfeiffer', 'Voigt', 'Götz', 'Seifert', 'Lenz', 'Jahn'
];

// Deutsche Städte und Postleitzahlen
$cities = [
    ['name' => 'Berlin', 'postcode' => '10115'],
    ['name' => 'Hamburg', 'postcode' => '20095'],
    ['name' => 'München', 'postcode' => '80331'],
    ['name' => 'Köln', 'postcode' => '50667'],
    ['name' => 'Frankfurt', 'postcode' => '60311'],
    ['name' => 'Stuttgart', 'postcode' => '70173'],
    ['name' => 'Düsseldorf', 'postcode' => '40213'],
    ['name' => 'Dortmund', 'postcode' => '44135'],
    ['name' => 'Essen', 'postcode' => '45127'],
    ['name' => 'Leipzig', 'postcode' => '04109'],
    ['name' => 'Bremen', 'postcode' => '28195'],
    ['name' => 'Dresden', 'postcode' => '01067'],
    ['name' => 'Hannover', 'postcode' => '30159'],
    ['name' => 'Nürnberg', 'postcode' => '90402'],
    ['name' => 'Duisburg', 'postcode' => '47051'],
    ['name' => 'Bochum', 'postcode' => '44787'],
    ['name' => 'Wuppertal', 'postcode' => '42103'],
    ['name' => 'Bielefeld', 'postcode' => '33602'],
    ['name' => 'Bonn', 'postcode' => '53111'],
    ['name' => 'Münster', 'postcode' => '48143']
];

$streets = [
    'Hauptstraße', 'Bahnhofstraße', 'Kirchstraße', 'Dorfstraße', 'Gartenstraße',
    'Schulstraße', 'Bergstraße', 'Waldstraße', 'Lindenstraße', 'Rosenstraße',
    'Parkstraße', 'Mühlenstraße', 'Birkenweg', 'Eichenweg', 'Tannenweg',
    'Ahornstraße', 'Buchenweg', 'Fichtenweg', 'Kastanienallee', 'Ulmenstraße'
];

// Verbindung zur Datenbank
$conn = getDBConnection();

// Stelle sicher, dass die Tabellen existieren
createTables();

echo "Erstelle {$totalUsers} Benutzerkonten...\n";
echo "Dies kann einige Minuten dauern...\n\n";

$startTime = microtime(true);
$batchSize = 100; // Insert in Batches für bessere Performance
$inserted = 0;
$errors = 0;

// Bereite das INSERT-Statement vor
$stmt = $conn->prepare("
    INSERT INTO users (username, email, password, first_name, last_name, street, house_number, postcode, city, completed_helps) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// Generiere eindeutige Usernames und E-Mails
$usedUsernames = [];
$usedEmails = [];

try {
    $conn->beginTransaction();
    
    for ($i = 1; $i <= $totalUsers; $i++) {
        // Generiere eindeutigen Username
        $username = '';
        $attempts = 0;
        do {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $randomNum = rand(1000, 9999);
            $username = strtolower($firstName . $lastName . $randomNum);
            $attempts++;
        } while (isset($usedUsernames[$username]) && $attempts < 100);
        
        if ($attempts >= 100) {
            // Fallback: Verwende eindeutige Nummer
            $username = 'user' . $i . '_' . uniqid();
        }
        
        $usedUsernames[$username] = true;
        
        // Generiere eindeutige E-Mail
        $email = '';
        $attempts = 0;
        do {
            $emailDomain = ['gmail.com', 'web.de', 'gmx.de', 'yahoo.de', 'outlook.com', 'hotmail.com'][rand(0, 5)];
            $email = $username . '@' . $emailDomain;
            $attempts++;
        } while (isset($usedEmails[$email]) && $attempts < 100);
        
        if ($attempts >= 100) {
            $email = 'user' . $i . '_' . uniqid() . '@example.com';
        }
        
        $usedEmails[$email] = true;
        
        // Generiere Passwort (mindestens 6 Zeichen)
        $password = 'Password' . $i . '!';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Zufällige Adressdaten
        $cityData = $cities[array_rand($cities)];
        $street = $streets[array_rand($streets)];
        $houseNumber = rand(1, 200);
        
        // Zufällige Anzahl abgeschlossener Hilfen (0-50)
        $completedHelps = rand(0, 50);
        
        try {
            $stmt->execute([
                $username,
                $email,
                $hashedPassword,
                $firstName,
                $lastName,
                $street,
                (string)$houseNumber,
                $cityData['postcode'],
                $cityData['name'],
                $completedHelps
            ]);
            
            $inserted++;
            
            // Progress anzeigen
            if ($inserted % 1000 === 0) {
                $elapsed = microtime(true) - $startTime;
                $rate = $inserted / $elapsed;
                $remaining = ($totalUsers - $inserted) / $rate;
                echo sprintf(
                    "Fortschritt: %d/%d (%.1f%%) - Geschätzte verbleibende Zeit: %.1f Sekunden\n",
                    $inserted,
                    $totalUsers,
                    ($inserted / $totalUsers) * 100,
                    $remaining
                );
            }
            
            // Commit in Batches für bessere Performance
            if ($inserted % $batchSize === 0) {
                $conn->commit();
                $conn->beginTransaction();
            }
            
        } catch (PDOException $e) {
            // Überspringe Duplikate oder andere Fehler
            $errors++;
            if ($errors <= 10) {
                echo "Warnung bei Benutzer {$i}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Finaler Commit
    $conn->commit();
    
    $elapsed = microtime(true) - $startTime;
    
    echo "\n";
    echo "========================================\n";
    echo "Fertig!\n";
    echo "Erfolgreich erstellt: {$inserted} Benutzer\n";
    echo "Fehler: {$errors}\n";
    echo "Benötigte Zeit: " . round($elapsed, 2) . " Sekunden\n";
    echo "Durchschnitt: " . round($inserted / $elapsed, 2) . " Benutzer/Sekunde\n";
    echo "========================================\n";
    
    // Zeige Statistiken
    $statsStmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats = $statsStmt->fetch();
    echo "Gesamtanzahl Benutzer in der Datenbank: " . $stats['total'] . "\n";
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo "\nFehler beim Erstellen der Benutzer: " . $e->getMessage() . "\n";
    echo "Erfolgreich erstellt vor Fehler: {$inserted} Benutzer\n";
    exit(1);
}

?>

