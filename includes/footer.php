    <!-- JavaScript Dateien laden -->
    <?php
    // basePath sollte bereits von header.php gesetzt sein
    if (!isset($basePath)) {
        $basePath = '';
    }
    ?>
    <script src="<?php echo $basePath; ?>assets/js/main.js"></script>
    <script src="<?php echo $basePath; ?>features/auth/auth-modal.js"></script>
    <script src="<?php echo $basePath; ?>features/navigation/navigation.js"></script>
    <?php
    // Lade JavaScript nur wenn benötigt
    if (isset($pageTitle)) {
        if ($pageTitle === 'Nachbarschaftshilfe' || $pageTitle === 'Home') {
            echo '<script src="' . $basePath . 'features/menu/home/home.js"></script>';
        } elseif ($pageTitle === 'Q&A' || $pageTitle === 'Über uns') {
            echo '<script src="' . $basePath . 'features/menu/ueber-uns/ueber-uns.js"></script>';
        } elseif ($pageTitle === 'Anfragen & Karte' || $pageTitle === 'Angebote & Karte') {
            echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>';
            echo '<script src="' . $basePath . 'features/menu/angebote-karte/angebote-karte.js"></script>';
            echo '<script src="' . $basePath . 'features/menu/angebote-karte/add-modal/add-modal.js"></script>';
        } elseif ($pageTitle === 'Profil') {
            echo '<script src="' . $basePath . 'features/profile/profile.js"></script>';
        } elseif ($pageTitle === 'Anmelden') {
            echo '<script src="' . $basePath . 'features/auth/login.js"></script>';
        } elseif ($pageTitle === 'Registrieren') {
            echo '<script src="' . $basePath . 'features/auth/register.js"></script>';
        }
        
        // Load chat scripts if user is logged in
        if (isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
            echo '<script src="' . $basePath . 'features/chat/chat-box.js"></script>';
        }
    }
    
        // Include chat box if user is logged in
        if (isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
            include 'features/chat/chat-box.php';
        }
        
        // Include finanzen button globally
        include 'includes/finanzen-button.php';
        
        // Load finanzen script globally
        echo '<script src="' . $basePath . 'features/menu/finanzen/finanzen.js"></script>';
    ?>
</body>
</html>



