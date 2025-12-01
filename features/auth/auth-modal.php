<?php
?>

<div class="auth-modal-overlay" id="authModalOverlay">
    <div class="auth-modal" id="authModal">
        <button class="auth-modal-close" id="authModalClose">×</button>
        
        <div class="auth-form-container" id="loginFormContainer">
            <h2 class="auth-modal-title">Anmelden</h2>
            <p class="auth-modal-subtitle">Melden Sie sich bei NeighborNet an</p>
            
            <form id="loginForm" class="auth-form">
                <div class="form-group">
                    <label for="modalLoginEmail">E-Mail oder Benutzername</label>
                    <input type="text" id="modalLoginEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="modalLoginPassword">Passwort</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="modalLoginPassword" name="password" required>
                        <button type="button" class="password-toggle-btn" data-target="modalLoginPassword" aria-label="Passwort anzeigen">
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
                    <a href="#" class="forgot-password-link" id="forgotPasswordLink">Passwort vergessen</a>
                </div>
                
                <div class="form-error" id="loginError"></div>
                
                <button type="submit" class="auth-submit-btn">Anmelden</button>
                
                <p class="auth-switch-text">
                    Noch kein Konto? <a href="#" id="switchToRegister">Jetzt registrieren</a>
                </p>
            </form>
        </div>
        
        <div class="auth-form-container" id="registerFormContainer" style="display: none;">
            <h2 class="auth-modal-title">Registrieren</h2>
            <p class="auth-modal-subtitle">Erstellen Sie ein Konto bei NeighborNet</p>
            
            <form id="registerForm" class="auth-form">
                <div class="form-group">
                    <label for="modalRegisterUsername">Benutzername</label>
                    <input type="text" id="modalRegisterUsername" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="modalRegisterEmail">E-Mail</label>
                    <input type="email" id="modalRegisterEmail" name="email" required>
                </div>
                
                <div class="form-group-row">
                    <div class="form-group form-group-half">
                        <label for="modalRegisterFirstName">Vorname</label>
                        <input type="text" id="modalRegisterFirstName" name="first_name">
                    </div>
                    <div class="form-group form-group-half">
                        <label for="modalRegisterLastName">Nachname</label>
                        <input type="text" id="modalRegisterLastName" name="last_name">
                    </div>
                </div>
                
                <div class="form-group-row">
                    <div class="form-group form-group-street">
                        <label for="modalRegisterStreet">Straße</label>
                        <input type="text" id="modalRegisterStreet" name="street">
                    </div>
                    <div class="form-group form-group-house">
                        <label for="modalRegisterHouseNumber">Hausnummer</label>
                        <input type="text" id="modalRegisterHouseNumber" name="house_number">
                    </div>
                </div>
                
                <div class="form-group-row">
                    <div class="form-group form-group-postcode">
                        <label for="modalRegisterPostcode">Postleitzahl</label>
                        <input type="text" id="modalRegisterPostcode" name="postcode">
                    </div>
                    <div class="form-group form-group-city">
                        <label for="modalRegisterCity">Stadt</label>
                        <input type="text" id="modalRegisterCity" name="city">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalRegisterPassword">Passwort</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="modalRegisterPassword" name="password" required>
                        <button type="button" class="password-toggle-btn" data-target="modalRegisterPassword" aria-label="Passwort anzeigen">
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
                    <label for="modalRegisterPasswordConfirm">Passwort bestätigen</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="modalRegisterPasswordConfirm" name="password_confirm" required>
                        <button type="button" class="password-toggle-btn" data-target="modalRegisterPasswordConfirm" aria-label="Passwort anzeigen">
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
                
                <p class="auth-switch-text">
                    Bereits ein Konto? <a href="#" id="switchToLogin">Jetzt anmelden</a>
                </p>
            </form>
        </div>
    </div>
</div>

<div class="auth-modal-overlay" id="passwordResetModalOverlay">
    <div class="auth-modal" id="passwordResetModal">
        <button class="auth-modal-close" id="passwordResetModalClose">×</button>
        
        <div class="auth-form-container" id="resetEmailContainer">
            <h2 class="auth-modal-title">Passwort zurücksetzen</h2>
            <p class="auth-modal-subtitle">Geben Sie Ihre E-Mail-Adresse ein</p>
            
            <form id="resetEmailForm" class="auth-form">
                <div class="form-group">
                    <label for="resetEmail">E-Mail</label>
                    <input type="email" id="resetEmail" name="email" required>
                </div>
                
                <div class="form-error" id="resetEmailError"></div>
                
                <button type="submit" class="auth-submit-btn">Weiter</button>
            </form>
        </div>
        
        <div class="auth-form-container" id="resetPasswordContainer" style="display: none;">
            <h2 class="auth-modal-title">Neues Passwort</h2>
            <p class="auth-modal-subtitle">Geben Sie Ihr neues Passwort ein</p>
            
            <form id="resetPasswordForm" class="auth-form">
                <input type="hidden" id="resetEmailHidden" name="email">
                
                <div class="form-group">
                    <label for="newPassword">Neues Passwort</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="newPassword" name="password" required>
                        <button type="button" class="password-toggle-btn" data-target="newPassword" aria-label="Passwort anzeigen">
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
                    <label for="newPasswordConfirm">Passwort bestätigen</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="newPasswordConfirm" name="password_confirm" required>
                        <button type="button" class="password-toggle-btn" data-target="newPasswordConfirm" aria-label="Passwort anzeigen">
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
                
                <div class="form-error" id="resetPasswordError"></div>
                
                <button type="submit" class="auth-submit-btn">Passwort ändern</button>
            </form>
        </div>
    </div>
</div>

