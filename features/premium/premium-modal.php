<?php
// basePath sollte bereits von header.php gesetzt sein
if (!isset($basePath)) {
    $basePath = '';
}
?>
<!-- Premium Modal -->
<div class="premium-modal-overlay" id="premiumModalOverlay">
    <div class="premium-modal-container" id="premiumModalContainer">
        <button class="premium-modal-close" id="premiumModalClose">×</button>
        
        <div class="premium-modal-content">
            <div class="premium-modal-header">
                <div class="premium-crown-icon-large">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5z"></path>
                        <path d="M12 18v4"></path>
                        <path d="M8 22h8"></path>
                    </svg>
                </div>
                <h2 class="premium-modal-title">Premium-Mitgliedschaft</h2>
                <p class="premium-modal-subtitle">Erhalten Sie Zugang zu exklusiven Features und Vorteilen</p>
            </div>
            
            <div class="premium-features-list">
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Mehr aktive Anfragen</h3>
                        <p class="premium-feature-description">5-10 statt 1-2 gleichzeitig</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Mehr Bilder</h3>
                        <p class="premium-feature-description">10-20 statt 5 pro Angebot</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Priorität</h3>
                        <p class="premium-feature-description">Angebote erscheinen zuerst</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Längere Laufzeit</h3>
                        <p class="premium-feature-description">Angebote länger sichtbar</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Highlight-Angebote</h3>
                        <p class="premium-feature-description">Farbige Markierung & Badge</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Größere Uploads</h3>
                        <p class="premium-feature-description">200 MB statt 100 MB</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Mehr Dateien</h3>
                        <p class="premium-feature-description">15 statt 10 pro Nachricht</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Längerer Chat-Verlauf</h3>
                        <p class="premium-feature-description">Längere Speicherung</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Premium-Badge</h3>
                        <p class="premium-feature-description">Exklusives Badge im Profil</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="3" y1="9" x2="21" y2="9"></line>
                            <line x1="9" y1="21" x2="9" y2="9"></line>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Werbefrei</h3>
                        <p class="premium-feature-description">Keine Werbung</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Prioritäts-Support</h3>
                        <p class="premium-feature-description">Schnellerer Support</p>
                    </div>
                </div>
                
                <div class="premium-feature-item">
                    <div class="premium-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <div class="premium-feature-content">
                        <h3 class="premium-feature-title">Früherer Zugang</h3>
                        <p class="premium-feature-description">Neue Features zuerst</p>
                    </div>
                </div>
            </div>
            
            <div class="premium-modal-footer">
                <button class="premium-upgrade-btn" id="premiumUpgradeBtn">
                    <span class="premium-btn-price">9,99€/Monat</span>
                    <span class="premium-btn-text">Coming Soon</span>
                </button>
                <p class="premium-modal-note">* Premium-Features werden in Kürze verfügbar sein</p>
            </div>
        </div>
    </div>
</div>

