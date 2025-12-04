    <!-- Footer einbinden -->
    <?php
    // basePath sollte bereits von header.php gesetzt sein
    if (!isset($basePath)) {
        $basePath = '';
    }
    // Bestimme den korrekten Pfad für footer-content.php
    // footer.php ist in includes/, footer-content.php ist auch in includes/
    $footerContentPath = __DIR__ . '/footer-content.php';
    if (file_exists($footerContentPath)) {
        include $footerContentPath;
    }
    ?>
    
    <!-- JavaScript Dateien laden -->
    <?php
    // basePath sollte bereits von header.php gesetzt sein
    if (!isset($basePath)) {
        $basePath = '';
    }
    
    // Include premium modal if user is logged in
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
        include __DIR__ . '/../features/premium/premium-modal.php';
    }
    ?>
    <script src="<?php echo $basePath; ?>assets/js/main.js"></script>
    <script src="<?php echo $basePath; ?>features/auth/auth-modal.js"></script>
    <script src="<?php echo $basePath; ?>features/navigation/navigation.js"></script>
    <?php
    // Load premium modal JS if user is logged in
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
        echo '<script src="' . $basePath . 'features/premium/premium-modal.js"></script>';
    }
    ?>
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
        
        // Mobile Chat Button (only visible on mobile, fixed bottom right)
        if (isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
            echo '<button class="chat-toggle-btn-mobile" id="chatToggleBtnMobile" aria-label="Chat öffnen">';
            echo '<svg class="chat-icon-outline" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            echo '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>';
            echo '</svg>';
            echo '<svg class="chat-icon-filled" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">';
            echo '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>';
            echo '</svg>';
            echo '</button>';
        }
    ?>
</body>
</html>



