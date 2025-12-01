document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');
    
    if (!loginForm) {
        return;
    }
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        loginError.classList.remove('show');
        loginError.textContent = '';
        
        const email = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value;
        
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
                const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
                window.location.href = basePath + 'index.php';
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

