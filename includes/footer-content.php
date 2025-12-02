<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-section footer-about">
            <div class="footer-logo-title">
                <img src="<?php echo $basePath; ?>assets/images/logo.png" alt="NeighborNet Logo" class="footer-logo">
                <h3 class="footer-title">NeighborNet</h3>
            </div>
            <p class="footer-description">Deine Plattform für Nachbarschaftshilfe, Angebote und Gemeinschaft.</p>
        </div>
        
        <div class="footer-section footer-links">
            <h4 class="footer-heading">Navigation</h4>
            <ul class="footer-list">
                <li><a href="<?php echo $basePath; ?>index.php" class="footer-link">Home</a></li>
                <li><a href="<?php echo $basePath; ?>angebote-karte.php" class="footer-link">Angebote & Karte</a></li>
                <li><a href="<?php echo $basePath; ?>flohmarkt.php" class="footer-link">Flohmarkt</a></li>
                <li><a href="<?php echo $basePath; ?>qa.php" class="footer-link">Über uns</a></li>
                <?php if (isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])): ?>
                <li><a href="<?php echo $basePath; ?>profile.php" class="footer-link">Profil</a></li>
                <li><a href="<?php echo $basePath; ?>finanzen.php" class="footer-link">Finanzen</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="footer-section footer-contact">
            <h4 class="footer-heading">Kontakt</h4>
            <ul class="footer-list">
                <li><a href="<?php echo $basePath; ?>qa.php" class="footer-link">Hilfe & Support</a></li>
                <li><a href="<?php echo $basePath; ?>qa.php" class="footer-link">FAQ</a></li>
            </ul>
        </div>
        
        <div class="footer-section footer-legal">
            <h4 class="footer-heading">Rechtliches</h4>
            <ul class="footer-list">
                <li><a href="#" class="footer-link">Impressum</a></li>
                <li><a href="#" class="footer-link">Datenschutz</a></li>
                <li><a href="#" class="footer-link">AGB</a></li>
            </ul>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <p class="footer-copyright">&copy; <?php echo date('Y'); ?> NeighborNet. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</footer>

