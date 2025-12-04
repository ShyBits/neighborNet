// Profile Statistics Management
(function() {
    'use strict';
    
    // Load profile statistics
    function loadProfileStats() {
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        
        fetch(basePath + 'api/get-profile-stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    updateProfileStats(data.data);
                } else {
                    console.error('Fehler beim Laden der Statistiken:', data.message || 'Unbekannter Fehler');
                }
            })
            .catch(error => {
                console.error('Fehler beim Laden der Statistiken:', error);
            });
    }
    
    // Update profile statistics display
    function updateProfileStats(stats) {
        const laufendeAnfragenEl = document.getElementById('laufendeAnfragen');
        const amHelfenEl = document.getElementById('amHelfen');
        const geholfenEl = document.getElementById('geholfen');
        
        if (laufendeAnfragenEl) {
            animateValue('laufendeAnfragen', parseInt(laufendeAnfragenEl.textContent) || 0, stats.laufende_anfragen, 500);
        }
        if (amHelfenEl) {
            animateValue('amHelfen', parseInt(amHelfenEl.textContent) || 0, stats.am_helfen, 500);
        }
        if (geholfenEl) {
            animateValue('geholfen', parseInt(geholfenEl.textContent) || 0, stats.geholfen, 500);
        }
    }
    
    // Animate value change
    function animateValue(elementId, start, end, duration) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const range = end - start;
        const increment = range / (duration / 16); // 60fps
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            element.textContent = Math.round(current);
        }, 16);
    }
    
    // Expose function to update stats from other scripts
    window.updateProfileStats = loadProfileStats;
    
    // Load stats when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadProfileStats);
    } else {
        loadProfileStats();
    }
    
    // Reload stats periodically (every 30 seconds) to catch automatic changes
    setInterval(loadProfileStats, 30000);
})();

