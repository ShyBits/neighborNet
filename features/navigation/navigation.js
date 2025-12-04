document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navMenu = document.getElementById('navMenu');
    const navMenuMobile = document.getElementById('navMenuMobile');
    const navMenuOverlay = document.getElementById('navMenuOverlay');
    
    // Use mobile menu if on mobile, otherwise use desktop menu
    const activeMenu = navMenuMobile || navMenu;
    
    function closeMobileMenu() {
        if (mobileMenuToggle) {
            mobileMenuToggle.classList.remove('active');
        }
        if (navMenuMobile) {
            navMenuMobile.classList.remove('active');
        }
        if (navMenuOverlay) {
            navMenuOverlay.classList.remove('active');
        }
        document.body.style.overflow = '';
    }
    
    function openMobileMenu() {
        if (mobileMenuToggle) {
            mobileMenuToggle.classList.add('active');
        }
        if (navMenuMobile) {
            navMenuMobile.classList.add('active');
        }
        if (navMenuOverlay) {
            navMenuOverlay.classList.add('active');
        }
        document.body.style.overflow = 'hidden';
    }
    
    function toggleMobileMenu() {
        if (navMenuMobile && navMenuMobile.classList.contains('active')) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    }
    
    // Initialize mobile menu functionality
    if (mobileMenuToggle && navMenuMobile && navMenuOverlay) {
        // Toggle menu on hamburger button click
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            toggleMobileMenu();
        });
        
        // Close menu when clicking on overlay
        navMenuOverlay.addEventListener('click', function(e) {
            e.stopPropagation();
            closeMobileMenu();
        });
        
        // Close menu when clicking on a link
        const navLinks = navMenuMobile.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });
        
        // Close menu when clicking on profile info link
        const profileInfoLink = navMenuMobile.querySelector('.nav-menu-mobile-profile-info');
        if (profileInfoLink) {
            profileInfoLink.addEventListener('click', function() {
                closeMobileMenu();
            });
        }
        
        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && navMenuMobile && navMenuMobile.classList.contains('active')) {
                closeMobileMenu();
            }
        });
        
        // Prevent body scroll when menu is open
        document.addEventListener('touchmove', function(e) {
            if (navMenuMobile && navMenuMobile.classList.contains('active')) {
                e.preventDefault();
            }
        }, { passive: false });
    }
    
    // Mark active link based on current URL (for both desktop and mobile menus)
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        const linkPage = linkHref.split('/').pop() || 'index.php';
        
        // Normalize filenames (remove .php if present for comparison)
        const currentPageNormalized = currentPage.replace('.php', '');
        const linkPageNormalized = linkPage.replace('.php', '');
        
        // If current page matches the link
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
    const mobileGuestBtn = document.getElementById('mobileGuestBtn');
    const guestLoginBtn = document.getElementById('guestLoginBtn');
    
    function handleGuestLogin() {
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
    }
    
    if (guestBtn) {
        guestBtn.addEventListener('click', handleGuestLogin);
    }
    
    if (mobileGuestBtn) {
        mobileGuestBtn.addEventListener('click', handleGuestLogin);
    }
    
    const guestRegisterBtn = document.getElementById('guestRegisterBtn');
    const mobileGuestRegisterBtn = document.getElementById('mobileGuestRegisterBtn');
    
    function handleGuestRegister() {
        if (typeof window.openAuthModal === 'function') {
            window.openAuthModal('register');
        }
    }
    
    if (guestRegisterBtn) {
        guestRegisterBtn.addEventListener('click', handleGuestRegister);
    }
    
    if (mobileGuestRegisterBtn) {
        mobileGuestRegisterBtn.addEventListener('click', handleGuestRegister);
    }
    
    const guestLogoutBtn = document.getElementById('guestLogoutBtn');
    const mobileGuestLogoutBtn = document.getElementById('mobileGuestLogoutBtn');
    
    function handleGuestLogout(e) {
        if (e) e.preventDefault();
        
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
    }
    
    if (guestLogoutBtn) {
        guestLogoutBtn.addEventListener('click', handleGuestLogout);
    }
    
    if (mobileGuestLogoutBtn) {
        mobileGuestLogoutBtn.addEventListener('click', handleGuestLogout);
    }
    
    const mobileOpenLoginModal = document.getElementById('mobileOpenLoginModal');
    
    function handleLogin() {
        if (typeof window.openAuthModal === 'function') {
            window.openAuthModal('login');
        }
    }
    
    if (openLoginModal) {
        openLoginModal.addEventListener('click', handleLogin);
    }
    
    if (mobileOpenLoginModal) {
        mobileOpenLoginModal.addEventListener('click', handleLogin);
    }
    
    const mobileOpenRegisterModal = document.getElementById('mobileOpenRegisterModal');
    
    function handleRegister() {
        if (typeof window.openAuthModal === 'function') {
            window.openAuthModal('register');
        }
    }
    
    if (openRegisterModal) {
        openRegisterModal.addEventListener('click', handleRegister);
    }
    
    if (mobileOpenRegisterModal) {
        mobileOpenRegisterModal.addEventListener('click', handleRegister);
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
    
    const mobileLogoutBtn = document.getElementById('mobileLogoutBtn');
    
    function handleLogout(e) {
        if (e) e.preventDefault();
        
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
    }
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', handleLogout);
    }
    
    // Dark Mode Toggle
    const darkModeToggle = document.getElementById('darkModeToggle');
    const mobileDarkModeToggle = document.getElementById('mobileDarkModeToggle');
    
    function handleDarkModeToggle(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        const isDarkMode = document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', isDarkMode);
        updateDarkModeUI(isDarkMode);
        loadDarkModeCSS();
    }
    
    if (darkModeToggle) {
        // Initialize dark mode from localStorage
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            updateDarkModeUI(true);
            loadDarkModeCSS();
        }
        
        darkModeToggle.addEventListener('click', handleDarkModeToggle);
    }
    
    if (mobileDarkModeToggle) {
        // Initialize dark mode from localStorage
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            updateDarkModeUI(true);
            loadDarkModeCSS();
        }
        
        mobileDarkModeToggle.addEventListener('click', handleDarkModeToggle);
    }
    
    function updateDarkModeUI(isDarkMode) {
        const sunIcons = document.querySelectorAll('.dark-mode-icon-sun');
        const moonIcons = document.querySelectorAll('.dark-mode-icon-moon');
        const darkModeTexts = document.querySelectorAll('.dark-mode-text');
        
        sunIcons.forEach(sunIcon => {
            if (isDarkMode) {
                sunIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'block';
            }
        });
        
        moonIcons.forEach(moonIcon => {
            if (isDarkMode) {
                moonIcon.style.display = 'block';
            } else {
                moonIcon.style.display = 'none';
            }
        });
        
        darkModeTexts.forEach(darkModeText => {
            if (isDarkMode) {
                darkModeText.textContent = 'Light Mode';
            } else {
                darkModeText.textContent = 'Dark Mode';
            }
        });
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
            loadDarkModeCSSFile(darkModePath + 'premium-modal.css', 'premium-modal');
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
