document.addEventListener('DOMContentLoaded', function() {
    const premiumCrownBtn = document.getElementById('premiumCrownBtn');
    const mobilePremiumCrownBtn = document.getElementById('mobilePremiumCrownBtn');
    const premiumModalOverlay = document.getElementById('premiumModalOverlay');
    const premiumModalClose = document.getElementById('premiumModalClose');
    const premiumUpgradeBtn = document.getElementById('premiumUpgradeBtn');
    
    // Funktion zum Öffnen des Premium Modals
    function openPremiumModal() {
        if (premiumModalOverlay) {
            premiumModalOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Schließe Mobile-Menü wenn es offen ist
            const navMenuMobile = document.getElementById('navMenuMobile');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            if (navMenuMobile && navMenuMobile.classList.contains('active')) {
                navMenuMobile.classList.remove('active');
                if (mobileMenuToggle) {
                    mobileMenuToggle.classList.remove('active');
                }
                const navMenuOverlay = document.getElementById('navMenuOverlay');
                if (navMenuOverlay) {
                    navMenuOverlay.classList.remove('active');
                }
            }
        }
    }
    
    // Öffne Premium Modal (Desktop)
    if (premiumCrownBtn && premiumModalOverlay) {
        premiumCrownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            openPremiumModal();
        });
    }
    
    // Öffne Premium Modal (Mobile)
    if (mobilePremiumCrownBtn && premiumModalOverlay) {
        mobilePremiumCrownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            openPremiumModal();
        });
    }
    
    // Schließe Premium Modal
    function closePremiumModal() {
        if (premiumModalOverlay) {
            premiumModalOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    if (premiumModalClose) {
        premiumModalClose.addEventListener('click', function(e) {
            e.stopPropagation();
            closePremiumModal();
        });
    }
    
    if (premiumModalOverlay) {
        premiumModalOverlay.addEventListener('click', function(e) {
            if (e.target === premiumModalOverlay) {
                closePremiumModal();
            }
        });
    }
    
    // ESC-Taste zum Schließen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && premiumModalOverlay && premiumModalOverlay.classList.contains('active')) {
            closePremiumModal();
        }
    });
    
    // Upgrade Button (später implementieren)
    if (premiumUpgradeBtn) {
        premiumUpgradeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            // TODO: Implementiere Upgrade-Funktionalität
            alert('Premium-Upgrade wird in Kürze verfügbar sein!');
        });
    }
});

