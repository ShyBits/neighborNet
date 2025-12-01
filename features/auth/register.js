document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const registerError = document.getElementById('registerError');
    
    if (!registerForm) {
        return;
    }
    
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        registerError.classList.remove('show');
        registerError.textContent = '';
        
        const username = document.getElementById('registerUsername').value.trim();
        const email = document.getElementById('registerEmail').value.trim();
        const password = document.getElementById('registerPassword').value;
        const passwordConfirm = document.getElementById('registerPasswordConfirm').value;
        
        if (!username || !email || !password || !passwordConfirm) {
            registerError.textContent = 'Bitte füllen Sie alle Felder aus';
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
                const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
                window.location.href = basePath + 'index.php';
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
});

