// Definiere openAuthModal sofort, damit es verfügbar ist, bevor DOMContentLoaded ausgelöst wird
let authModalOverlay, loginFormContainer, registerFormContainer;

function openAuthModal(mode) {
    // Warte auf DOM falls noch nicht geladen
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            openAuthModal(mode);
        });
        return;
    }
    
    // Hole Elemente wenn noch nicht gesetzt
    if (!authModalOverlay) {
        authModalOverlay = document.getElementById('authModalOverlay');
    }
    if (!loginFormContainer) {
        loginFormContainer = document.getElementById('loginFormContainer');
    }
    if (!registerFormContainer) {
        registerFormContainer = document.getElementById('registerFormContainer');
    }
    
    if (!authModalOverlay) {
        console.error('authModalOverlay nicht gefunden');
        return;
    }
    authModalOverlay.classList.add('active');
    if (mode === 'register') {
        if (loginFormContainer) loginFormContainer.style.display = 'none';
        if (registerFormContainer) registerFormContainer.style.display = 'block';
    } else {
        if (loginFormContainer) loginFormContainer.style.display = 'block';
        if (registerFormContainer) registerFormContainer.style.display = 'none';
    }
}

// Setze window.openAuthModal sofort
window.openAuthModal = openAuthModal;

document.addEventListener('DOMContentLoaded', function() {
    authModalOverlay = document.getElementById('authModalOverlay');
    const authModalClose = document.getElementById('authModalClose');
    loginFormContainer = document.getElementById('loginFormContainer');
    registerFormContainer = document.getElementById('registerFormContainer');
    const switchToRegister = document.getElementById('switchToRegister');
    const switchToLogin = document.getElementById('switchToLogin');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const loginError = document.getElementById('loginError');
    const registerError = document.getElementById('registerError');
    
    function closeAuthModal() {
        if (authModalOverlay) authModalOverlay.classList.remove('active');
        if (loginError) loginError.classList.remove('show');
        if (registerError) registerError.classList.remove('show');
        if (loginForm) loginForm.reset();
        if (registerForm) registerForm.reset();
    }
    
    function showLogin() {
        if (loginFormContainer) loginFormContainer.style.display = 'block';
        if (registerFormContainer) registerFormContainer.style.display = 'none';
    }
    
    function showRegister() {
        if (loginFormContainer) loginFormContainer.style.display = 'none';
        if (registerFormContainer) registerFormContainer.style.display = 'block';
    }
    
    // Aktualisiere openAuthModal mit den DOM-Elementen
    window.openAuthModal = function(mode) {
        if (!authModalOverlay) {
            console.error('authModalOverlay nicht gefunden');
            return;
        }
        authModalOverlay.classList.add('active');
        if (mode === 'register') {
            showRegister();
        } else {
            showLogin();
        }
    };
    
    if (authModalClose) {
        authModalClose.addEventListener('click', closeAuthModal);
    }
    
    if (authModalOverlay) {
        let mouseDownOnOverlay = false;
        
        authModalOverlay.addEventListener('mousedown', function(e) {
            if (e.target === authModalOverlay) {
                mouseDownOnOverlay = true;
            } else {
                mouseDownOnOverlay = false;
            }
        });
        
        authModalOverlay.addEventListener('mouseup', function(e) {
            if (mouseDownOnOverlay && e.target === authModalOverlay) {
                closeAuthModal();
            }
            mouseDownOnOverlay = false;
        });
    }
    
    if (switchToRegister) {
        switchToRegister.addEventListener('click', function(e) {
            e.preventDefault();
            showRegister();
        });
    }
    
    if (switchToLogin) {
        switchToLogin.addEventListener('click', function(e) {
            e.preventDefault();
            showLogin();
        });
    }
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            loginError.classList.remove('show');
            loginError.textContent = '';
            
            const email = document.getElementById('modalLoginEmail').value.trim();
            const password = document.getElementById('modalLoginPassword').value;
            
            if (!email || !password) {
                loginError.textContent = 'Bitte füllen Sie alle Felder aus';
                loginError.classList.add('show');
                return;
            }
            
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            fetch(basePath + 'api/login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAuthModal();
                    // Verzögerung um sicherzustellen dass Session-Cookie gesetzt ist
                    // Dies ist besonders wichtig bei Online-Umgebungen
                    // Erhöht auf 200ms für bessere Kompatibilität
                    setTimeout(function() {
                        // Hard reload um sicherzustellen dass alles neu geladen wird
                        window.location.href = window.location.href.split('?')[0];
                    }, 200);
                } else {
                    loginError.textContent = data.message || 'Fehler beim Anmelden';
                    loginError.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loginError.textContent = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
                loginError.classList.add('show');
            });
        });
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            registerError.classList.remove('show');
            registerError.textContent = '';
            
            const username = document.getElementById('modalRegisterUsername').value.trim();
            const email = document.getElementById('modalRegisterEmail').value.trim();
            const firstName = document.getElementById('modalRegisterFirstName').value.trim();
            const lastName = document.getElementById('modalRegisterLastName').value.trim();
            const street = document.getElementById('modalRegisterStreet').value.trim();
            const houseNumber = document.getElementById('modalRegisterHouseNumber').value.trim();
            const postcode = document.getElementById('modalRegisterPostcode').value.trim();
            const city = document.getElementById('modalRegisterCity').value.trim();
            const password = document.getElementById('modalRegisterPassword').value;
            const passwordConfirm = document.getElementById('modalRegisterPasswordConfirm').value;
            
            if (!username || !email || !password || !passwordConfirm) {
                registerError.textContent = 'Bitte füllen Sie alle Pflichtfelder aus (Benutzername, E-Mail, Passwort)';
                registerError.classList.add('show');
                return;
            }
            
            if (password !== passwordConfirm) {
                registerError.textContent = 'Passwörter stimmen nicht überein';
                registerError.classList.add('show');
                return;
            }
            
            const formData = new FormData();
            formData.append('username', username);
            formData.append('email', email);
            formData.append('first_name', firstName);
            formData.append('last_name', lastName);
            formData.append('street', street);
            formData.append('house_number', houseNumber);
            formData.append('postcode', postcode);
            formData.append('city', city);
            formData.append('password', password);
            formData.append('password_confirm', passwordConfirm);
            
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            fetch(basePath + 'api/register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAuthModal();
                    // Verzögerung um sicherzustellen dass Session-Cookie gesetzt ist
                    // Dies ist besonders wichtig bei Online-Umgebungen
                    // Erhöht auf 200ms für bessere Kompatibilität
                    setTimeout(function() {
                        // Hard reload um sicherzustellen dass alles neu geladen wird
                        window.location.href = window.location.href.split('?')[0];
                    }, 200);
                } else {
                    registerError.textContent = data.message || 'Fehler bei der Registrierung';
                    registerError.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                registerError.textContent = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
                registerError.classList.add('show');
            });
        });
    }
    
    
    const passwordResetModalOverlay = document.getElementById('passwordResetModalOverlay');
    const passwordResetModalClose = document.getElementById('passwordResetModalClose');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const resetEmailContainer = document.getElementById('resetEmailContainer');
    const resetPasswordContainer = document.getElementById('resetPasswordContainer');
    const resetEmailForm = document.getElementById('resetEmailForm');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const resetEmailError = document.getElementById('resetEmailError');
    const resetPasswordError = document.getElementById('resetPasswordError');
    
    function openPasswordResetModal() {
        passwordResetModalOverlay.classList.add('active');
        resetEmailContainer.style.display = 'block';
        resetPasswordContainer.style.display = 'none';
        resetEmailForm.reset();
        resetPasswordForm.reset();
        resetEmailError.classList.remove('show');
        resetPasswordError.classList.remove('show');
    }
    
    function closePasswordResetModal() {
        passwordResetModalOverlay.classList.remove('active');
        resetEmailContainer.style.display = 'block';
        resetPasswordContainer.style.display = 'none';
        resetEmailForm.reset();
        resetPasswordForm.reset();
        resetEmailError.classList.remove('show');
        resetPasswordError.classList.remove('show');
    }
    
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            closeAuthModal();
            openPasswordResetModal();
        });
    }
    
    if (passwordResetModalClose) {
        passwordResetModalClose.addEventListener('click', closePasswordResetModal);
    }
    
    if (passwordResetModalOverlay) {
        let mouseDownOnResetOverlay = false;
        
        passwordResetModalOverlay.addEventListener('mousedown', function(e) {
            if (e.target === passwordResetModalOverlay) {
                mouseDownOnResetOverlay = true;
            } else {
                mouseDownOnResetOverlay = false;
            }
        });
        
        passwordResetModalOverlay.addEventListener('mouseup', function(e) {
            if (mouseDownOnResetOverlay && e.target === passwordResetModalOverlay) {
                closePasswordResetModal();
            }
            mouseDownOnResetOverlay = false;
        });
    }
    
    if (resetEmailForm) {
        resetEmailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            resetEmailError.classList.remove('show');
            resetEmailError.textContent = '';
            
            const email = document.getElementById('resetEmail').value.trim();
            
            if (!email) {
                resetEmailError.textContent = 'Bitte geben Sie Ihre E-Mail-Adresse ein';
                resetEmailError.classList.add('show');
                return;
            }
            
            const formData = new FormData();
            formData.append('email', email);
            
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            fetch(basePath + 'api/reset-password-request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('resetEmailHidden').value = email;
                    resetEmailContainer.style.display = 'none';
                    resetPasswordContainer.style.display = 'block';
                    resetEmailError.classList.remove('show');
                } else {
                    resetEmailError.textContent = data.message || 'Fehler beim Zurücksetzen';
                    resetEmailError.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resetEmailError.textContent = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
                resetEmailError.classList.add('show');
            });
        });
    }
    
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            resetPasswordError.classList.remove('show');
            resetPasswordError.textContent = '';
            
            const email = document.getElementById('resetEmailHidden').value;
            const password = document.getElementById('newPassword').value;
            const passwordConfirm = document.getElementById('newPasswordConfirm').value;
            
            if (!password || !passwordConfirm) {
                resetPasswordError.textContent = 'Bitte füllen Sie alle Felder aus';
                resetPasswordError.classList.add('show');
                return;
            }
            
            if (password.length < 6) {
                resetPasswordError.textContent = 'Passwort muss mindestens 6 Zeichen lang sein';
                resetPasswordError.classList.add('show');
                return;
            }
            
            if (password !== passwordConfirm) {
                resetPasswordError.textContent = 'Passwörter stimmen nicht überein';
                resetPasswordError.classList.add('show');
                return;
            }
            
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            formData.append('password_confirm', passwordConfirm);
            
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            fetch(basePath + 'api/reset-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Passwort erfolgreich geändert! Sie können sich jetzt anmelden.');
                    closePasswordResetModal();
                    openAuthModal('login');
                } else {
                    resetPasswordError.textContent = data.message || 'Fehler beim Ändern des Passworts';
                    resetPasswordError.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resetPasswordError.textContent = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
                resetPasswordError.classList.add('show');
            });
        });
    }
    
    // Password toggle functionality
    function initPasswordToggles() {
        const toggleButtons = document.querySelectorAll('.password-toggle-btn');
        
        toggleButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const targetId = this.dataset.target;
                const passwordInput = document.getElementById(targetId);
                if (!passwordInput) return;
                
                const eyeIcon = this.querySelector('.password-toggle-icon-eye');
                const eyeOffIcon = this.querySelector('.password-toggle-icon-eye-off');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    if (eyeIcon) eyeIcon.style.display = 'none';
                    if (eyeOffIcon) eyeOffIcon.style.display = 'block';
                    this.setAttribute('aria-label', 'Passwort verstecken');
                } else {
                    passwordInput.type = 'password';
                    if (eyeIcon) eyeIcon.style.display = 'block';
                    if (eyeOffIcon) eyeOffIcon.style.display = 'none';
                    this.setAttribute('aria-label', 'Passwort anzeigen');
                }
            });
        });
    }
    
    // Initialize password toggles
    initPasswordToggles();
    
    // Re-initialize when modals are opened (in case they're dynamically added)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                initPasswordToggles();
            }
        });
    });
    
    // Observe auth modal overlays for changes
    const authModalOverlayForObserver = document.getElementById('authModalOverlay');
    const passwordResetModalOverlayForObserver = document.getElementById('passwordResetModalOverlay');
    if (authModalOverlayForObserver) {
        observer.observe(authModalOverlayForObserver, { childList: true, subtree: true });
    }
    if (passwordResetModalOverlayForObserver) {
        observer.observe(passwordResetModalOverlayForObserver, { childList: true, subtree: true });
    }
});

