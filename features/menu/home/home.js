document.addEventListener('DOMContentLoaded', function() {
    const homeRegisterBtn = document.getElementById('homeRegisterBtn');
    
    if (homeRegisterBtn) {
        homeRegisterBtn.addEventListener('click', function() {
            if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('register');
            }
        });
    }
    
    loadStatistics();
});

function loadStatistics() {
    const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
    fetch(basePath + 'api/get-statistics.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats = data.data;
                
                // Animate banner user count
                const bannerUserCountEl = document.getElementById('bannerUserCount');
                if (bannerUserCountEl) {
                    animateValue('bannerUserCount', 0, stats.total_users, 2000);
                }
                
                // Animate statistics section
                animateValue('statUsers', 0, stats.total_users, 2000);
                animateValue('statOffers', 0, stats.total_offers, 2000);
                animateValue('statMessages', 0, stats.total_messages, 2000);
                
                updateGrowth('statUserGrowth', stats.user_growth);
                updateGrowth('statOfferGrowth', stats.offer_growth);
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der Statistiken:', error);
        });
}

function formatCompactNumber(num) {
    if (num >= 1000000) {
        // Millionen
        const millions = num / 1000000;
        return millions % 1 === 0 ? millions.toFixed(0).replace('.', ',') + 'M' : millions.toFixed(1).replace('.', ',') + 'M';
    } else if (num >= 1000) {
        // Tausende
        const thousands = num / 1000;
        return thousands % 1 === 0 ? thousands.toFixed(0).replace('.', ',') + 'k' : thousands.toFixed(1).replace('.', ',') + 'k';
    } else {
        // Unter 1000 - normale Anzeige
        return num.toLocaleString('de-DE');
    }
}

function animateValue(id, start, end, duration) {
    const element = document.getElementById(id);
    if (!element) return;
    
    // No animation - set value directly
    element.textContent = formatCompactNumber(end);
}

function updateGrowth(id, growth) {
    const element = document.getElementById(id);
    if (!element) return;
    
    const growthValue = element.querySelector('.growth-value');
    if (growthValue) {
        growthValue.textContent = '+' + growth.toFixed(1) + '%';
    }
}
