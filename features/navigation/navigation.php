<?php
$isLoggedIn = false;
$isGuest = false;
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_guest'])) {
        $isGuest = true;
    } else {
        $isLoggedIn = true;
    }
}
?>

<?php
// basePath sollte bereits von header.php gesetzt sein
// Falls nicht gesetzt, verwende leeren String als Fallback
if (!isset($basePath)) {
    $basePath = '';
}
?>
<nav class="navigation-bar">
    <div class="nav-logo">
        <a href="<?php echo $basePath; ?>index.php">
            <img src="<?php echo $basePath; ?>assets/images/logo.png" alt="NeighborNet Logo" class="logo-img">
        </a>
    </div>
    
    <!-- Hamburger Menu Button (Mobile) -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>
    
    <div class="nav-right">
        <div class="nav-menu" id="navMenu">
            <a href="<?php echo $basePath; ?>index.php" class="nav-link">Home</a>
            <a href="<?php echo $basePath; ?>qa.php" class="nav-link">Q&A</a>
            <a href="<?php echo $basePath; ?>angebote-karte.php" class="nav-link">Anfragen & Karte</a>
            <a href="<?php echo $basePath; ?>flohmarkt.php" class="nav-link">Flohmarkt</a>
        </div>
    </div>
</nav>
<!-- Mobile Menu - outside navigation bar for proper positioning -->
<div class="nav-menu-mobile" id="navMenuMobile">
    <!-- Logo and Website Name -->
    <div class="nav-menu-mobile-header">
        <div class="nav-menu-mobile-logo">
            <img src="<?php echo $basePath; ?>assets/images/logo.png" alt="NeighborNet Logo" class="nav-menu-mobile-logo-img">
        </div>
        <div class="nav-menu-mobile-title">NeighborNet</div>
    </div>
    
    <!-- Navigation Links -->
    <div class="nav-menu-mobile-links">
        <div class="nav-menu-mobile-section">
            <a href="<?php echo $basePath; ?>index.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Home</span>
            </a>
            <a href="<?php echo $basePath; ?>qa.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <span>Q&A</span>
            </a>
            <a href="<?php echo $basePath; ?>angebote-karte.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>Anfragen & Karte</span>
            </a>
            <a href="<?php echo $basePath; ?>flohmarkt.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span>Flohmarkt</span>
            </a>
        </div>
        
        <div class="nav-menu-mobile-divider"></div>
        
        <div class="nav-menu-mobile-section">
            <?php
            // Prüfe ob Benutzer Premium hat (später aus Datenbank)
            $hasPremium = false; // TODO: Aus Datenbank laden
            if ($isLoggedIn && !$hasPremium):
            ?>
            <button class="nav-menu-mobile-premium-btn" id="mobilePremiumCrownBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5z"></path>
                    <path d="M12 18v4"></path>
                    <path d="M8 22h8"></path>
                </svg>
                <span>Premium</span>
            </button>
            <?php endif; ?>
            <button class="nav-menu-mobile-finanz-btn" id="mobileFinanzenBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Unsere Finanzen</span>
            </button>
        </div>
    </div>
    
    <!-- User Actions (Profile, etc.) -->
    <div class="nav-menu-mobile-user-actions">
        <?php
        // Include user actions but with mobile-specific wrapper
        $isLoggedIn = false;
        $isGuest = false;
        if (isset($_SESSION['user_id'])) {
            if (isset($_SESSION['is_guest'])) {
                $isGuest = true;
            } else {
                $isLoggedIn = true;
            }
        }
        
        $userName = (!empty($_SESSION['user_name'])) ? $_SESSION['user_name'] : 'Benutzer';
        $userAvatar = $_SESSION['user_avatar'] ?? $basePath . 'assets/images/profile-placeholder.svg';
        ?>
        
        <?php if ($isLoggedIn): ?>
            <div class="nav-menu-mobile-profile">
                <div class="nav-menu-mobile-profile-top">
                    <a href="<?php echo $basePath; ?>profile.php" class="nav-menu-mobile-profile-info">
                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Profilbild" class="nav-menu-mobile-profile-img">
                        <span class="nav-menu-mobile-profile-name"><?php echo htmlspecialchars($userName); ?></span>
                    </a>
                    <button class="nav-menu-mobile-logout-btn" id="mobileLogoutBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </button>
                </div>
                <div class="nav-menu-mobile-profile-menu">
                    <button class="nav-menu-mobile-profile-item" id="mobileDarkModeToggle">
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
                </div>
            </div>
        <?php elseif ($isGuest): ?>
            <div class="nav-menu-mobile-guest">
                <div class="nav-menu-mobile-guest-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <div class="nav-menu-mobile-guest-actions">
                    <button class="nav-menu-mobile-guest-btn" id="mobileGuestRegisterBtn">Registrieren</button>
                    <button class="nav-menu-mobile-guest-btn" id="mobileGuestLogoutBtn">Abmelden</button>
                </div>
                <div class="nav-menu-mobile-profile-menu">
                    <button class="nav-menu-mobile-profile-item" id="mobileDarkModeToggle">
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
                </div>
            </div>
        <?php else: ?>
            <div class="nav-menu-mobile-auth">
                <button class="nav-menu-mobile-auth-btn" id="mobileGuestBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Gast
                </button>
                <div class="nav-menu-mobile-auth-actions">
                    <button class="nav-menu-mobile-auth-btn-secondary" id="mobileOpenLoginModal">Anmelden</button>
                    <button class="nav-menu-mobile-auth-btn-primary" id="mobileOpenRegisterModal">Registrieren</button>
                </div>
                <div class="nav-menu-mobile-profile-menu">
                    <button class="nav-menu-mobile-profile-item" id="mobileDarkModeToggle">
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
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="nav-menu-overlay" id="navMenuOverlay"></div>


