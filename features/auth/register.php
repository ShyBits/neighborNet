<?php
// Session wird durch header.php initialisiert

if (isset($_SESSION['user_id'])) {
    // Bestimme basePath für Redirect - wird von header.php gesetzt
    // Für jetzt verwenden wir einen relativen Pfad
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Registrieren';
include '../../includes/header.php';
?>
    <div class="top-container">
        <?php include '../../features/navigation/navigation.php'; ?>
    </div>

    <div class="auth-container">
        <div class="auth-box">
            <h2 class="auth-title">Registrieren</h2>
            <p class="auth-subtitle">Erstellen Sie ein Konto bei NeighborNet</p>
            
            <form id="registerForm" class="auth-form">
                <div class="form-group">
                    <label for="registerUsername">Benutzername</label>
                    <input type="text" id="registerUsername" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="registerEmail">E-Mail</label>
                    <input type="email" id="registerEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="registerPassword">Passwort</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="registerPassword" name="password" required>
                        <button type="button" class="password-toggle-btn" data-target="registerPassword" aria-label="Passwort anzeigen">
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
                
                <div class="form-group">
                    <label for="registerPasswordConfirm">Passwort bestätigen</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="registerPasswordConfirm" name="password_confirm" required>
                        <button type="button" class="password-toggle-btn" data-target="registerPasswordConfirm" aria-label="Passwort anzeigen">
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
                
                <div class="form-error" id="registerError"></div>
                
                <button type="submit" class="auth-submit-btn">Registrieren</button>
                
                <p class="auth-link-text">
                    Bereits ein Konto? <a href="<?php echo isset($basePath) ? $basePath : ''; ?>login.php">Jetzt anmelden</a>
                </p>
            </form>
        </div>
    </div>

<?php include '../../includes/footer.php'; ?>
