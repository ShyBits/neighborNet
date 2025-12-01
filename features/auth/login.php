<?php
// Session wird durch header.php initialisiert

if (isset($_SESSION['user_id'])) {
    // Bestimme basePath für Redirect - wird von header.php gesetzt
    // Für jetzt verwenden wir einen relativen Pfad
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Anmelden';
include '../../includes/header.php';
?>
    <div class="top-container">
        <?php include '../../features/navigation/navigation.php'; ?>
    </div>

    <div class="auth-container">
        <div class="auth-box">
            <h2 class="auth-title">Anmelden</h2>
            <p class="auth-subtitle">Melden Sie sich bei NeighborNet an</p>
            
            <form id="loginForm" class="auth-form">
                <div class="form-group">
                    <label for="loginEmail">E-Mail oder Benutzername</label>
                    <input type="text" id="loginEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="loginPassword">Passwort</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="loginPassword" name="password" required>
                        <button type="button" class="password-toggle-btn" data-target="loginPassword" aria-label="Passwort anzeigen">
                            <svg class="password-toggle-icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="password-toggle-icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-error" id="loginError"></div>
                
                <button type="submit" class="auth-submit-btn">Anmelden</button>
                
                <p class="auth-link-text">
                    Noch kein Konto? <a href="<?php echo isset($basePath) ? $basePath : ''; ?>register.php">Jetzt registrieren</a>
                </p>
            </form>
        </div>
    </div>

<?php include '../../includes/footer.php'; ?>
