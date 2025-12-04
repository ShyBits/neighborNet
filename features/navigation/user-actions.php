<?php
// Sicherstellen dass Session gestartet ist
// header.php lädt config.php, aber zur Sicherheit prüfen
if (session_status() === PHP_SESSION_NONE) {
    // Fallback: Session sollte bereits durch header.php gestartet sein
    require_once __DIR__ . '/../../config/config.php';
}

$isLoggedIn = false;
$isGuest = false;

// Prüfe ob Session-Variablen gesetzt sind
// WICHTIG: Prüfe sowohl isset() als auch ob der Wert nicht leer ist
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Prüfe ob es ein Gast-Login ist
    if (isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true) {
        $isGuest = true;
    } else {
        // Normales Login - user_id ist gesetzt und kein Gast
        $isLoggedIn = true;
    }
}

// Benutzername aus Session holen
$userName = (!empty($_SESSION['user_name'])) ? $_SESSION['user_name'] : 'Benutzer';

// Avatar aus Session holen
// basePath sollte bereits von header.php gesetzt sein
if (!isset($basePath)) {
    $basePath = '';
}

$userAvatar = $_SESSION['user_avatar'] ?? $basePath . 'assets/images/profile-placeholder.svg';
?>

<div class="user-actions-wrapper">
        <?php if ($isLoggedIn): ?>
            <!-- Premium Crown Icon (nur wenn kein Premium) -->
            <?php
            // Prüfe ob Benutzer Premium hat (später aus Datenbank)
            $hasPremium = false; // TODO: Aus Datenbank laden
            if (!$hasPremium):
            ?>
            <button class="premium-crown-btn" id="premiumCrownBtn" title="Premium-Mitgliedschaft">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5z"></path>
                    <path d="M12 18v4"></path>
                    <path d="M8 22h8"></path>
                </svg>
            </button>
            <?php endif; ?>
            
            <div class="user-actions user-actions-logged-in">
            <div class="profile-dropdown-container">
                <button class="profile-btn" id="profileBtn">
                    <span><?php echo htmlspecialchars($userName); ?></span>
                    <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Profilbild" class="profile-img">
                </button>
                
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="<?php echo $basePath; ?>profile.php" class="profile-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Profil
                    </a>
                    <button class="profile-dropdown-item" id="darkModeToggle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="dark-mode-icon-sun">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="dark-mode-icon-moon" style="display: none;">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                        <span class="dark-mode-text">Dark Mode</span>
                    </button>
                    <button class="profile-dropdown-item" id="logoutBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        Abmelden
                    </button>
                </div>
            </div>
        </div>
    <?php elseif ($isGuest): ?>
        <div class="user-actions">
        <div class="guest-status">
            <div class="guest-icon-circle">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>
            <button class="auth-action-btn" id="guestRegisterBtn">Registrieren</button>
            <button class="auth-action-btn guest-logout-btn" id="guestLogoutBtn">Abmelden</button>
        </div>
        </div>
    <?php else: ?>
        <div class="guest-auth-container">
            <button class="guest-btn" id="guestBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span class="guest-btn-text">Gast</span>
            </button>
            
            <div class="user-actions">
                <button class="auth-action-btn" id="openLoginModal">Anmelden</button>
                <button class="auth-action-btn register-action-btn" id="openRegisterModal">Registrieren</button>
            </div>
        </div>
    <?php endif; ?>
</div>
