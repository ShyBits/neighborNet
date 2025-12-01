<?php
$isLoggedIn = isset($_SESSION['user_id']) && !isset($_SESSION['is_guest']);
$isGuest = isset($_SESSION['is_guest']);
$canView = $isLoggedIn || $isGuest;
?>

<div class="angebote-karte-banner">
    <div class="banner-content">
        <h2 class="banner-title">Anfragen & Karte</h2>
        <p class="banner-text">Entdecken Sie Anfragen in Ihrer Nachbarschaft</p>
    </div>
</div>

<div class="angebote-karte-menu">
    <div class="angebote-list">
        <?php if ($canView): ?>
            <div class="angebote-list-header">
                <h3 class="angebote-list-title">Verfügbare Anfragen</h3>
                <?php if ($isLoggedIn): ?>
                    <div class="angebote-list-actions">
                        <button class="angebote-add-btn" id="addBtn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Hinzufügen
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($isLoggedIn): ?>
                <div class="angebote-filter-section">
                    <div class="filter-dropdown-wrapper">
                        <label class="filter-dropdown-label">Kategorie</label>
                        <div class="filter-dropdown">
                            <button class="filter-dropdown-btn" data-filter-type="category">
                                <span class="filter-dropdown-text">Alle Kategorien</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu">
                                <button class="filter-dropdown-option" data-filter-value="gartenarbeit">Gartenarbeit</button>
                                <button class="filter-dropdown-option" data-filter-value="haushalt">Haushalt</button>
                                <button class="filter-dropdown-option" data-filter-value="umzug">Umzug</button>
                                <button class="filter-dropdown-option" data-filter-value="reparatur">Reparatur</button>
                                <button class="filter-dropdown-option" data-filter-value="betreuung">Betreuung</button>
                                <button class="filter-dropdown-option" data-filter-value="einkauf">Einkauf</button>
                                <button class="filter-dropdown-option" data-filter-value="sonstiges">Sonstiges</button>
                            </div>
                        </div>
                    </div>
                    <div class="filter-dropdown-wrapper">
                        <label class="filter-dropdown-label">Datum</label>
                        <div class="filter-dropdown">
                            <button class="filter-dropdown-btn" data-filter-type="date">
                                <span class="filter-dropdown-text">Alle Daten</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu">
                                <button class="filter-dropdown-option" data-filter-value="today">Heute</button>
                                <button class="filter-dropdown-option" data-filter-value="week">Diese Woche</button>
                                <button class="filter-dropdown-option" data-filter-value="month">Dieser Monat</button>
                            </div>
                        </div>
                    </div>
                    <div class="filter-dropdown-wrapper">
                        <label class="filter-dropdown-label">Zeit</label>
                        <div class="filter-dropdown">
                            <button class="filter-dropdown-btn" data-filter-type="time">
                                <span class="filter-dropdown-text">Alle Zeiten</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu">
                                <button class="filter-dropdown-option" data-filter-value="morning">Morgen (06:00-12:00)</button>
                                <button class="filter-dropdown-option" data-filter-value="afternoon">Nachmittag (12:00-18:00)</button>
                                <button class="filter-dropdown-option" data-filter-value="evening">Abend (18:00-24:00)</button>
                                <button class="filter-dropdown-option" data-filter-value="night">Nacht (00:00-06:00)</button>
                            </div>
                        </div>
                    </div>
                    <div class="filter-dropdown-wrapper">
                        <label class="filter-dropdown-label">Bild</label>
                        <div class="filter-dropdown">
                            <button class="filter-dropdown-btn" data-filter-type="image">
                                <span class="filter-dropdown-text">Alle</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu">
                                <button class="filter-dropdown-option" data-filter-value="with-image">Mit Bild</button>
                                <button class="filter-dropdown-option" data-filter-value="without-image">Ohne Bild</button>
                            </div>
                        </div>
                    </div>
                    <div class="filter-dropdown-wrapper">
                        <label class="filter-dropdown-label">Benötigte Personen</label>
                        <div class="filter-dropdown">
                            <button class="filter-dropdown-btn" data-filter-type="persons">
                                <span class="filter-dropdown-text">Alle</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="filter-dropdown-menu">
                                <button class="filter-dropdown-option" data-filter-value="1">1 Person</button>
                                <button class="filter-dropdown-option" data-filter-value="2">2 Personen</button>
                                <button class="filter-dropdown-option" data-filter-value="3">3+ Personen</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="angebote-items" id="angeboteItems">
                <p class="no-angebote">Aktuell sind keine Anfragen verfügbar.</p>
            </div>
        <?php else: ?>
            <div class="login-prompt">
                <h3 class="login-prompt-title">Anfragen anzeigen</h3>
                <p class="login-prompt-text">Melde dich an, um Anfragen zu sehen oder selber zu erstellen.</p>
                <button class="login-prompt-btn" id="loginPromptBtn">Jetzt anmelden</button>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="karte-container">
        <?php if ($hasConsent): ?>
            <div id="map" class="map"></div>
            
            <div class="map-controls">
                <div class="map-theme-dropdown">
                    <button class="map-theme-btn" id="themeBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="3" x2="9" y2="21"></line>
                        </svg>
                    </button>
                    <div class="map-theme-menu" id="themeMenu">
                        <button class="theme-option" data-theme="osm">OpenStreetMap</button>
                        <button class="theme-option" data-theme="dark">Dunkel</button>
                        <button class="theme-option" data-theme="satellite">Satellit</button>
                    </div>
                </div>
                
                <button class="map-location-btn" id="locationBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </button>
                
                <button class="map-revoke-btn" id="revokeBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18"></path>
                        <path d="M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        <?php else: ?>
            <div class="map-consent-dialog" id="mapConsentDialog">
                <div class="consent-content">
                    <h3>Karten-Einwilligung</h3>
                    <p>Um die interaktive Karte zu nutzen, benötigen wir Ihre Einwilligung zur Anzeige von Kartenmaterial.</p>
                    <button type="button" class="consent-accept-btn" id="mapConsentAcceptBtn">Einwilligen</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'add-modal/add-modal.php'; ?>
