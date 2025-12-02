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
            </div>
                <?php if ($isLoggedIn): ?>
                <div class="angebote-filters-actions">
                    <button class="angebote-add-btn" id="addBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Hinzufügen
                    </button>
                    <div class="main-filter-dropdown">
                        <button class="main-filter-btn" id="mainFilterBtn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                </svg>
                                Filter
                            </button>
                        <div class="main-filter-menu" id="mainFilterMenu">
                            <div class="main-filter-content">
                                <div class="filter-section">
                                    <div class="filter-section-label">Kategorie</div>
                                    <div class="filter-dropdown">
                                        <button class="filter-dropdown-btn" data-filter-type="category">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="filter-icon">
                                                <rect x="3" y="3" width="7" height="7"></rect>
                                                <rect x="14" y="3" width="7" height="7"></rect>
                                                <rect x="14" y="14" width="7" height="7"></rect>
                                                <rect x="3" y="14" width="7" height="7"></rect>
                                            </svg>
                                            <span class="filter-dropdown-text">Kategorie</span>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                                <div class="filter-section">
                                    <div class="filter-section-label">Datum</div>
                                    <div class="filter-date-wrapper">
                                        <input type="date" class="filter-date-input" id="filterDate" data-filter-type="date">
                                        <label for="filterDate" class="filter-date-label">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                            <span class="filter-date-text">Datum</span>
                                    </label>
                                    </div>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-label">Zeit</div>
                                    <div class="filter-time-range-wrapper">
                                        <div class="filter-time-input-wrapper">
                                            <input type="time" class="filter-time-input" id="filterTimeFrom" data-filter-type="time-from">
                                            <label for="filterTimeFrom" class="filter-time-label">Von</label>
                                        </div>
                                        <div class="filter-time-separator">-</div>
                                        <div class="filter-time-input-wrapper">
                                            <input type="time" class="filter-time-input" id="filterTimeTo" data-filter-type="time-to">
                                            <label for="filterTimeTo" class="filter-time-label">Bis</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-label">Bild</div>
                                    <div class="filter-dropdown">
                                        <button class="filter-dropdown-btn" data-filter-type="image">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="filter-icon">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                <polyline points="21 15 16 10 5 21"></polyline>
                                            </svg>
                                            <span class="filter-dropdown-text">Bild</span>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </button>
                                        <div class="filter-dropdown-menu">
                                            <button class="filter-dropdown-option" data-filter-value="with-image">Mit Bild</button>
                                            <button class="filter-dropdown-option" data-filter-value="without-image">Ohne Bild</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-label">Personen</div>
                                    <div class="filter-input-wrapper">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="filter-icon">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                        <input type="number" class="filter-input-number" id="filterPersons" min="1" max="100" placeholder="Anzahl Personen" data-filter-type="persons">
                                    </div>
                                </div>
                                <div class="filter-section">
                                    <div class="filter-section-label">Standort</div>
                                    <div class="filter-location-wrapper">
                                        <div class="filter-radius-wrapper">
                                            <input type="number" class="filter-radius-input" id="filterRadius" min="1" max="100" value="10" placeholder="10">
                                            <span class="filter-radius-unit">km</span>
                                        </div>
                                        <button class="filter-location-btn" id="filterLocationBtn" data-filter-type="location">
                                            <span class="filter-location-text">Off</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="filter-reset-section">
                                <button class="filter-reset-btn" id="filterResetBtn">
                                    Reset
                                </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            
            <div class="angebote-items" id="angeboteItems" data-is-guest="<?php echo $isGuest ? 'true' : 'false'; ?>">
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
