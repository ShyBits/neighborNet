document.addEventListener('DOMContentLoaded', function() {
    // Markiere aktiven Link basierend auf der aktuellen URL
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        const linkPage = linkHref.split('/').pop() || 'index.php';
        
        // Normalisiere die Dateinamen (entferne .php falls vorhanden für Vergleich)
        const currentPageNormalized = currentPage.replace('.php', '');
        const linkPageNormalized = linkPage.replace('.php', '');
        
        // Wenn die aktuelle Seite mit dem Link übereinstimmt
        if (currentPageNormalized === linkPageNormalized || 
            (currentPageNormalized === '' && linkPageNormalized === 'index') ||
            (currentPageNormalized === 'index' && linkPageNormalized === 'index')) {
            link.classList.add('active');
        }
    });
    
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    const logoutBtn = document.getElementById('logoutBtn');
    const openLoginModal = document.getElementById('openLoginModal');
    const openRegisterModal = document.getElementById('openRegisterModal');
    const guestBtn = document.getElementById('guestBtn');
    const guestLoginBtn = document.getElementById('guestLoginBtn');
    
    if (guestBtn) {
        guestBtn.addEventListener('click', function() {
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            fetch(basePath + 'api/guest-login.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }
    
    const guestRegisterBtn = document.getElementById('guestRegisterBtn');
    if (guestRegisterBtn) {
        guestRegisterBtn.addEventListener('click', function() {
            if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('register');
            }
        });
    }
    
    const guestLogoutBtn = document.getElementById('guestLogoutBtn');
    if (guestLogoutBtn) {
        guestLogoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Stop heartbeat before logout
            if (typeof stopHeartbeat === 'function') {
                stopHeartbeat();
            }
            
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            fetch(basePath + 'api/logout.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = basePath + 'index.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = basePath + 'index.php';
            });
        });
    }
    
    if (openLoginModal) {
        openLoginModal.addEventListener('click', function() {
            if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('login');
            }
        });
    }
    
    if (openRegisterModal) {
        openRegisterModal.addEventListener('click', function() {
            if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('register');
            }
        });
    }
    
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });
    }
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Stop heartbeat before logout
            if (typeof stopHeartbeat === 'function') {
                stopHeartbeat();
            }
            
            const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
            fetch(basePath + 'api/logout.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = basePath + 'index.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = basePath + 'index.php';
            });
        });
    }
    
    // Dark Mode Toggle
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (darkModeToggle) {
        // Initialize dark mode from localStorage
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            updateDarkModeUI(true);
            loadDarkModeCSS();
        }
        
        darkModeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isDarkMode = document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', isDarkMode);
            updateDarkModeUI(isDarkMode);
            loadDarkModeCSS();
        });
    }
    
    function updateDarkModeUI(isDarkMode) {
        const sunIcon = document.querySelector('.dark-mode-icon-sun');
        const moonIcon = document.querySelector('.dark-mode-icon-moon');
        const darkModeText = document.querySelector('.dark-mode-text');
        
        if (sunIcon && moonIcon) {
            if (isDarkMode) {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
                if (darkModeText) darkModeText.textContent = 'Light Mode';
            } else {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
                if (darkModeText) darkModeText.textContent = 'Dark Mode';
            }
        }
    }
    
    function loadDarkModeCSS() {
        const isDarkMode = document.body.classList.contains('dark-mode');
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        const darkModePath = basePath + 'assets/css/dark-mode/';
        
        // Remove all existing dark mode stylesheets
        document.querySelectorAll('link[data-dark-mode]').forEach(link => link.remove());
        
        if (isDarkMode) {
            // Get current page to load appropriate dark mode CSS
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            const pageTitle = document.title;
            const currentPath = window.location.pathname;
            
            // Always load base dark mode CSS
            loadDarkModeCSSFile(darkModePath + 'style.css', 'style');
            
            // Load navigation dark mode CSS (always loaded)
            loadDarkModeCSSFile(darkModePath + 'navigation.css', 'navigation');
            loadDarkModeCSSFile(darkModePath + 'user-actions.css', 'user-actions');
            loadDarkModeCSSFile(darkModePath + 'auth-modal.css', 'auth-modal');
            
            // Load page-specific dark mode CSS based on pageTitle or path
            if (pageTitle.includes('Home') || pageTitle.includes('Nachbarschaftshilfe') || currentPath.includes('index.php') || currentPage === 'index.php' || currentPage === '') {
                loadDarkModeCSSFile(darkModePath + 'home.css', 'home');
            }
            if (pageTitle.includes('Über uns') || pageTitle.includes('Q&A') || currentPath.includes('qa.php')) {
                loadDarkModeCSSFile(darkModePath + 'ueber-uns.css', 'ueber-uns');
            }
            if (pageTitle.includes('Angebote') || pageTitle.includes('Karte') || currentPath.includes('angebote-karte.php')) {
                loadDarkModeCSSFile(darkModePath + 'angebote-karte.css', 'angebote-karte');
                loadDarkModeCSSFile(darkModePath + 'add-modal.css', 'add-modal');
            }
            if (pageTitle.includes('Profil') || currentPath.includes('profile.php')) {
                loadDarkModeCSSFile(darkModePath + 'profile.css', 'profile');
            }
            if (pageTitle.includes('Anmelden') || pageTitle.includes('Registrieren') || currentPath.includes('login.php') || currentPath.includes('register.php')) {
                loadDarkModeCSSFile(darkModePath + 'auth.css', 'auth');
            }
            if (pageTitle.includes('Flohmarkt') || currentPath.includes('flohmarkt.php')) {
                loadDarkModeCSSFile(darkModePath + 'flohmarkt.css', 'flohmarkt');
            }
            
            // Load chat dark mode CSS if chat box exists or user is logged in
            if (document.querySelector('.chat-box-container') || document.querySelector('.chat-box')) {
                loadDarkModeCSSFile(darkModePath + 'chat-box.css', 'chat-box');
            }
            
            // Always load finanzen dark mode CSS
            loadDarkModeCSSFile(darkModePath + 'finanzen.css', 'finanzen');
        }
    }
    
    function loadDarkModeCSSFile(href, id) {
        // Check if already loaded
        if (document.querySelector(`link[data-dark-mode="${id}"]`)) {
            return;
        }
        
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.setAttribute('data-dark-mode', id);
        document.head.appendChild(link);
    }
    
    // Initialize dark mode on page load - run immediately
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        updateDarkModeUI(true);
    }
    
    // Load dark mode CSS after a short delay to ensure all light mode CSS is loaded first
    setTimeout(function() {
        if (document.body.classList.contains('dark-mode')) {
            loadDarkModeCSS();
        }
    }, 100);
});
