document.addEventListener('DOMContentLoaded', function() {
    
    // Helper-Funktion zum Escapen von HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Prüfe ob Karte bereits existiert (wenn Consent bereits gegeben wurde)
    const mapElement = document.getElementById('map');
    let map = null;
    let currentTileLayer = null;
    
    if (mapElement && typeof L !== 'undefined') {
        // Karte existiert bereits, initialisiere sie
        map = L.map('map').setView([51.1657, 10.4515], 6);
        window.mapInstance = map;
        
        currentTileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);
        window.currentTileLayer = currentTileLayer;
    } else if (typeof L === 'undefined') {
        // Leaflet nicht geladen, beende
        return;
    }
    
    let userLocationMarker = null;
    let watchId = null;
    let locationEnabled = false;
    
    const angebote = [];
    const markers = [];
    
    const angeboteItems = document.getElementById('angeboteItems');
    const isLoggedIn = angeboteItems !== null;
    
    const addBtn = document.getElementById('addBtn');
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    let activeFilters = {
        categories: [],
        date: null,
        image: null
    };
    
    // Filter-Button Klick-Handler
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const filterType = this.dataset.filterType;
            const filterValue = this.dataset.filterValue;
            
            // Toggle button active state
            this.classList.toggle('active');
            
            if (filterType === 'category') {
                // Kategorien können mehrere sein
                if (this.classList.contains('active')) {
                    if (!activeFilters.categories.includes(filterValue)) {
                        activeFilters.categories.push(filterValue);
                    }
                } else {
                    activeFilters.categories = activeFilters.categories.filter(cat => cat !== filterValue);
                }
            } else if (filterType === 'date') {
                // Datum ist exklusiv - nur einer kann aktiv sein
                filterButtons.forEach(b => {
                    if (b.dataset.filterType === 'date' && b !== this) {
                        b.classList.remove('active');
                    }
                });
                if (this.classList.contains('active')) {
                    activeFilters.date = filterValue;
                } else {
                    activeFilters.date = null;
                }
            } else if (filterType === 'image') {
                // Bild-Filter ist exklusiv
                filterButtons.forEach(b => {
                    if (b.dataset.filterType === 'image' && b !== this) {
                        b.classList.remove('active');
                    }
                });
                if (this.classList.contains('active')) {
                    activeFilters.image = filterValue;
                } else {
                    activeFilters.image = null;
                }
            }
            
            applyFilters();
        });
    });
    
    function loadAngebote() {
        const angeboteItems = document.getElementById('angeboteItems');
        if (!angeboteItems) {
            return;
        }
        
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        fetch(basePath + 'api/get-angebote.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    angebote.length = 0;
                    for (let i = 0; i < data.data.length; i++) {
                        angebote.push(data.data[i]);
                    }
                    displayAngebote();
                } else {
                    console.error('Fehler beim Laden:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }
    
    function getFilteredAngebote() {
        let filtered = [...angebote];
        
        // Filter nach Kategorien
        if (activeFilters.categories.length > 0) {
            filtered = filtered.filter(angebot => {
                return activeFilters.categories.includes(angebot.category);
            });
        }
        
        // Filter nach Datum
        if (activeFilters.date) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            filtered = filtered.filter(angebot => {
                if (!angebot.start_date) return false;
                const startDate = new Date(angebot.start_date + 'T00:00:00');
                startDate.setHours(0, 0, 0, 0);
                
                if (activeFilters.date === 'today') {
                    return startDate.getTime() === today.getTime();
                } else if (activeFilters.date === 'week') {
                    const weekAgo = new Date(today);
                    weekAgo.setDate(today.getDate() - 7);
                    return startDate >= weekAgo && startDate <= today;
                } else if (activeFilters.date === 'month') {
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(today.getMonth() - 1);
                    return startDate >= monthAgo && startDate <= today;
                }
                return true;
            });
        }
        
        // Filter nach Bild
        if (activeFilters.image) {
            filtered = filtered.filter(angebot => {
                const hasImage = angebot.images && angebot.images.length > 0;
                if (activeFilters.image === 'with-image') {
                    return hasImage;
                } else if (activeFilters.image === 'without-image') {
                    return !hasImage;
                }
                return true;
            });
        }
        
        return filtered;
    }
    
    function displayAngebote() {
        const angeboteItems = document.getElementById('angeboteItems');
        if (!angeboteItems) {
            return;
        }
        
        angeboteItems.innerHTML = '';
        
        if (map) {
            for (let i = 0; i < markers.length; i++) {
                map.removeLayer(markers[i]);
            }
        }
        markers.length = 0;
        
        const filteredAngebote = getFilteredAngebote();
        
        for (let i = 0; i < filteredAngebote.length; i++) {
            const angebot = filteredAngebote[i];
            const isOwner = angebot.is_owner === true;
            
            // Formatiere Datum und Zeit
            const startTime = angebot.start_time ? angebot.start_time.substring(0, 5) : '';
            const endTime = angebot.end_time ? angebot.end_time.substring(0, 5) : '';
            const timeRange = startTime && endTime ? `${startTime} - ${endTime} Uhr` : '';
            
            const startDate = angebot.start_date ? new Date(angebot.start_date + 'T00:00:00') : null;
            const endDate = angebot.end_date ? new Date(angebot.end_date + 'T00:00:00') : null;
            
            let dateRange = '';
            if (startDate && endDate) {
                const startDateStr = startDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
                const endDateStr = endDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
                dateRange = startDateStr === endDateStr ? startDateStr : `${startDateStr} - ${endDateStr}`;
            } else if (startDate) {
                dateRange = startDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }
            
            // Erstelle Popup-Inhalt
            const popupContent = `
                <div class="map-popup-content">
                    <h3 class="map-popup-title">${escapeHtml(angebot.title)}</h3>
                    <div class="map-popup-section">
                        <div class="map-popup-info-row">
                            <span class="map-popup-label">Adresse:</span>
                            <span class="map-popup-value">${escapeHtml(angebot.address || 'Keine Adresse angegeben')}</span>
                        </div>
                    </div>
                    <div class="map-popup-section">
                        <div class="map-popup-info-row">
                            <span class="map-popup-label">Beschreibung:</span>
                        </div>
                        <p class="map-popup-description">${escapeHtml(angebot.description)}</p>
                    </div>
                    ${dateRange ? `
                    <div class="map-popup-section">
                        <div class="map-popup-info-row">
                            <span class="map-popup-label">Zeitraum:</span>
                            <span class="map-popup-value">${dateRange}</span>
                        </div>
                        ${timeRange ? `
                        <div class="map-popup-info-row">
                            <span class="map-popup-label">Uhrzeit:</span>
                            <span class="map-popup-value">${timeRange}</span>
                        </div>
                        ` : ''}
                    </div>
                    ` : ''}
                    <div class="map-popup-section">
                        <div class="map-popup-info-row">
                            <span class="map-popup-label">Benötigt:</span>
                            <span class="map-popup-value">${angebot.required_persons || 1} ${(angebot.required_persons || 1) === 1 ? 'Person' : 'Personen'}</span>
                        </div>
                        <div class="map-popup-info-row">
                            <span class="map-popup-label">Teilnehmer:</span>
                            <span class="map-popup-value">${angebot.anfragen_count || 0} von ${angebot.required_persons || 1}</span>
                        </div>
                    </div>
                    ${isLoggedIn && !isOwner && angebot.user_id ? `
                    <div class="map-popup-actions">
                        <button class="map-popup-contact-btn" data-user-id="${angebot.user_id}" data-username="${escapeHtml(angebot.author || 'Unbekannt')}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            Kontaktieren
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
            
            const marker = L.marker([parseFloat(angebot.lat), parseFloat(angebot.lng)]).addTo(map);
            const popup = L.popup({ maxWidth: 350, className: 'custom-popup' })
                .setContent(popupContent);
            marker.bindPopup(popup);
            
            // Event-Listener für Kontakt-Button im Popup hinzufügen
            marker.on('popupopen', function() {
                const contactBtn = document.querySelector('.map-popup-contact-btn[data-user-id="' + angebot.user_id + '"]');
                if (contactBtn) {
                    contactBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const userId = parseInt(this.dataset.userId);
                        const username = this.dataset.username;
                        contactUser(userId, username);
                    });
                }
            });
            
            markers.push(marker);
            
            const angebotDiv = document.createElement('div');
            angebotDiv.className = 'angebote-item';
            angebotDiv.classList.add('kategorie-' + angebot.category);
            angebotDiv.dataset.angebotId = angebot.id;
            
            let anfrageBadge = '';
            let deleteButton = '';
            if (angebot.has_user_anfrage && angebot.user_anfrage_id) {
                anfrageBadge = '<span class="anfrage-badge">Anfrage gesendet</span>';
                deleteButton = `<button class="anfrage-delete-btn" data-anfrage-id="${angebot.user_anfrage_id}" title="Anfrage löschen">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18"></path>
                        <path d="M6 6l12 12"></path>
                    </svg>
                </button>`;
            }
            
            const firstImage = angebot.images && angebot.images.length > 0 && angebot.images[0] !== '' ? angebot.images[0] : null;
            
            const categoryLabels = {
                'gartenarbeit': 'Gartenarbeit',
                'haushalt': 'Haushalt',
                'umzug': 'Umzug',
                'reparatur': 'Reparatur',
                'betreuung': 'Betreuung',
                'einkauf': 'Einkauf',
                'sonstiges': 'Sonstiges'
            };
            const categoryLabel = categoryLabels[angebot.category] || angebot.category;
            
            let imageHtml = '';
            if (firstImage) {
                // Entferne führendes ../ falls vorhanden
                const imagePath = firstImage.startsWith('../') ? firstImage.substring(3) : firstImage;
                const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
                imageHtml = `
                    <div class="angebote-item-image-container">
                        <img src="${basePath + imagePath}" alt="Anfrage Bild" class="angebote-item-main-image">
                        ${isOwner ? `<button class="angebote-image-delete-btn" data-image-path="${firstImage}" data-angebot-id="${angebot.id}" title="Bild löschen">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>
                        </button>` : ''}
                        <div class="angebote-item-category-badge kategorie-${angebot.category}">
                            <span>${categoryLabel}</span>
                        </div>
                    </div>
                `;
            } else {
                imageHtml = '<div class="angebote-item-image-placeholder">Kein Bild</div>';
            }
            
            // Erstelle dateStr für die Item-Anzeige
            const dateStr = startDate ? startDate.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
            
            angebotDiv.innerHTML = `
                <div class="angebote-item-content">
                    <div class="angebote-item-left">
                        <div class="angebote-item-header">
                            <div class="angebote-item-title-wrapper">
                                <h4 class="angebote-item-title">${angebot.title}</h4>
                                ${timeRange ? `<span class="angebote-item-time">${timeRange}</span>` : ''}
                            </div>
                            ${dateStr ? `<span class="angebote-item-date">${dateStr}</span>` : ''}
                        </div>
                        ${anfrageBadge ? `<div class="angebote-item-anfrage-badge-wrapper">${anfrageBadge}</div>` : ''}
                        <p class="angebote-item-description">${angebot.description}</p>
                        <div class="angebote-item-footer">
                            <span class="angebote-item-author">Von ${angebot.author || 'Unbekannt'}</span>
                            <div class="angebote-item-actions">
                                ${isLoggedIn && !isOwner && angebot.user_id ? `
                                <button class="angebote-contact-btn" data-user-id="${angebot.user_id}" data-username="${escapeHtml(angebot.author || 'Unbekannt')}" title="Kontaktieren">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                </button>
                                ` : ''}
                                ${deleteButton}
                            </div>
                        </div>
                    </div>
                    <div class="angebote-item-right">
                        ${imageHtml}
                    </div>
                </div>
            `;
            
            angebotDiv.addEventListener('click', function(e) {
                // Nur Delete-Buttons und Kontakt-Buttons sollen das Item-Click verhindern
                if (!e.target.closest('.anfrage-delete-btn') && !e.target.closest('.angebote-image-delete-btn') && !e.target.closest('.angebote-contact-btn')) {
                    if (map) {
                        map.setView([parseFloat(angebot.lat), parseFloat(angebot.lng)], 15);
                        marker.openPopup();
                    }
                }
            });
            
            // Event-Listener für Kontakt-Button im Item
            const contactBtn = angebotDiv.querySelector('.angebote-contact-btn');
            if (contactBtn) {
                contactBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const userId = parseInt(this.dataset.userId);
                    const username = this.dataset.username;
                    contactUser(userId, username);
                });
            }
            
            const deleteBtn = angebotDiv.querySelector('.anfrage-delete-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const anfrageId = this.dataset.anfrageId;
                    deleteAnfrage(anfrageId, angebotDiv);
                });
            }
            
            const imageDeleteBtns = angebotDiv.querySelectorAll('.angebote-image-delete-btn');
            for (let j = 0; j < imageDeleteBtns.length; j++) {
                imageDeleteBtns[j].addEventListener('click', function(e) {
                    e.stopPropagation();
                    const imagePath = this.dataset.imagePath;
                    const angebotId = this.dataset.angebotId;
                    deleteImage(imagePath, angebotId);
                });
            }
            
            angeboteItems.appendChild(angebotDiv);
        }
        
        if (filteredAngebote.length === 0) {
            angeboteItems.innerHTML = '<p class="no-angebote">Keine Anfragen entsprechen den gewählten Filtern.</p>';
        }
    }
    
    function applyFilters() {
        displayAngebote();
    }
    
    function deleteAnfrage(anfrageId, angebotDiv) {
        if (!confirm('Möchten Sie diese Anfrage wirklich löschen?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('anfrage_id', anfrageId);
        
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        fetch(basePath + 'api/delete-anfrage.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAngebote();
            } else {
                alert('Fehler: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Fehler beim Löschen der Anfrage');
        });
    }
    
    function deleteImage(imagePath, angebotId) {
        if (!confirm('Möchten Sie dieses Bild wirklich löschen?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('image_path', imagePath);
        formData.append('angebot_id', angebotId);
        
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        fetch(basePath + 'api/delete-image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAngebote();
            } else {
                alert('Fehler: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Fehler beim Löschen des Bildes');
        });
    }
    
    const themeBtn = document.getElementById('themeBtn');
    const themeMenu = document.getElementById('themeMenu');
    const locationBtn = document.getElementById('locationBtn');
    const revokeBtn = document.getElementById('revokeBtn');
    const mapConsentAcceptBtn = document.getElementById('mapConsentAcceptBtn');
    const mapConsentDialog = document.getElementById('mapConsentDialog');
    
    // Helper-Funktion zum Setzen des Consent-Cookies
    function setMapConsent(accepted) {
        if (accepted) {
            document.cookie = 'map_consent=accepted; path=/; max-age=' + (365 * 24 * 60 * 60);
        } else {
            document.cookie = 'map_consent=; path=/; max-age=0';
        }
    }
    
    // Helper-Funktion zum Prüfen des Consent-Cookies
    function hasMapConsent() {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim();
            if (cookie.startsWith('map_consent=accepted')) {
                return true;
            }
        }
        return false;
    }
    
    // Handle Karten-Einwilligung auf der Hauptseite
    if (mapConsentAcceptBtn) {
        mapConsentAcceptBtn.addEventListener('click', function() {
            setMapConsent(true);
            
            // Verstecke Consent-Dialog
            if (mapConsentDialog) {
                mapConsentDialog.style.display = 'none';
            }
            
            // Erstelle Karten-Container
            const karteContainer = document.querySelector('.karte-container');
            if (karteContainer && !document.getElementById('map')) {
                const mapDiv = document.createElement('div');
                mapDiv.id = 'map';
                mapDiv.className = 'map';
                
                // Erstelle Map-Controls
                const mapControls = document.createElement('div');
                mapControls.className = 'map-controls';
                mapControls.innerHTML = `
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
                `;
                
                karteContainer.appendChild(mapDiv);
                karteContainer.appendChild(mapControls);
                
                // Initialisiere Karte nach kurzer Verzögerung
                if (typeof L !== 'undefined') {
                    setTimeout(function() {
                        const newMapElement = document.getElementById('map');
                        if (newMapElement && !window.mapInstance) {
                            // Initialisiere die Karte (Code aus dem bestehenden Script)
                            window.mapInstance = L.map('map').setView([51.1657, 10.4515], 6);
                            
                            window.currentTileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '© OpenStreetMap contributors',
                                maxZoom: 19
                            }).addTo(window.mapInstance);
                            
                            // Lade Angebote und zeige sie auf der Karte
                            loadAngebote();
                            
                            // Setze map und currentTileLayer für bestehende Funktionen
                            map = window.mapInstance;
                            currentTileLayer = window.currentTileLayer;
                            
                            // Lade Angebote und zeige sie auf der Karte
                            loadAngebote();
                            
                            // Initialisiere Theme-Button und Location-Button
                            // Warte kurz, damit die Controls gerendert sind
                            setTimeout(function() {
                                initMapControls();
                            }, 100);
                        }
                    }, 150);
                }
            }
        });
    }
    
    const themes = {
        osm: {
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            attribution: '© OpenStreetMap contributors'
        },
        dark: {
            url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
            attribution: '© OpenStreetMap contributors © CARTO'
        },
        satellite: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            attribution: '© Esri'
        }
    };
    
    if (themeBtn && themeMenu) {
        themeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            themeMenu.classList.toggle('active');
        });
        
        document.addEventListener('click', function() {
            themeMenu.classList.remove('active');
        });
        
        const themeOptions = themeMenu.querySelectorAll('.theme-option');
        for (let i = 0; i < themeOptions.length; i++) {
            themeOptions[i].addEventListener('click', function(e) {
                e.stopPropagation();
                const theme = this.dataset.theme;
                if (map && currentTileLayer) {
                    map.removeLayer(currentTileLayer);
                    currentTileLayer = L.tileLayer(themes[theme].url, {
                        attribution: themes[theme].attribution,
                        maxZoom: 19
                    }).addTo(map);
                    window.currentTileLayer = currentTileLayer;
                }
                themeMenu.classList.remove('active');
            });
        }
    }
    
    if (locationBtn) {
        locationBtn.addEventListener('click', function() {
            if (!locationEnabled) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        if (map) {
                            if (userLocationMarker) {
                                map.removeLayer(userLocationMarker);
                            }
                            
                            userLocationMarker = L.marker([lat, lng], {
                                icon: L.icon({
                                    iconUrl: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiIGZpbGw9IiM4NEJGNUUiIHN0cm9rZT0id2hpdGUiIHN0cm9rZS13aWR0aD0iMiIvPgo8L3N2Zz4K',
                                    iconSize: [24, 24],
                                    iconAnchor: [12, 12]
                                })
                            }).addTo(map);
                            
                            map.setView([lat, lng], 13);
                        }
                        locationBtn.classList.add('active');
                        locationEnabled = true;
                        
                        watchId = navigator.geolocation.watchPosition(function(pos) {
                            const newLat = pos.coords.latitude;
                            const newLng = pos.coords.longitude;
                            if (userLocationMarker) {
                                userLocationMarker.setLatLng([newLat, newLng]);
                            }
                        });
                    }, function(error) {
                        alert('Standort konnte nicht ermittelt werden');
                    });
                } else {
                    alert('Geolocation wird von Ihrem Browser nicht unterstützt');
                }
            } else {
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                if (map && userLocationMarker) {
                    map.removeLayer(userLocationMarker);
                    userLocationMarker = null;
                }
                locationBtn.classList.remove('active');
                locationEnabled = false;
            }
        });
    }
    
    if (revokeBtn) {
        revokeBtn.addEventListener('click', function() {
            if (confirm('Möchten Sie die Einwilligung wirklich widerrufen? Die Karte wird dann nicht mehr angezeigt.')) {
                setMapConsent(false);
                
                // Entferne Karte und Controls
                const mapElement = document.getElementById('map');
                const mapControls = document.querySelector('.map-controls');
                if (mapElement) {
                    if (window.mapInstance) {
                        window.mapInstance.remove();
                        window.mapInstance = null;
                    }
                    mapElement.remove();
                }
                if (mapControls) {
                    mapControls.remove();
                }
                
                // Zeige Consent-Dialog wieder an
                const karteContainer = document.querySelector('.karte-container');
                if (karteContainer) {
                    const consentDialog = document.createElement('div');
                    consentDialog.className = 'map-consent-dialog';
                    consentDialog.id = 'mapConsentDialog';
                    consentDialog.innerHTML = `
                        <div class="consent-content">
                            <h3>Karten-Einwilligung</h3>
                            <p>Um die interaktive Karte zu nutzen, benötigen wir Ihre Einwilligung zur Anzeige von Kartenmaterial.</p>
                            <button type="button" class="consent-accept-btn" id="mapConsentAcceptBtn">Einwilligen</button>
                        </div>
                    `;
                    karteContainer.appendChild(consentDialog);
                    
                    // Füge Event-Listener zum neuen Button hinzu
                    const newConsentBtn = document.getElementById('mapConsentAcceptBtn');
                    if (newConsentBtn) {
                        newConsentBtn.addEventListener('click', function() {
                            setMapConsent(true);
                            location.reload(); // Lade Seite neu, um Karte zu initialisieren
                        });
                    }
                }
            }
        });
    }
    
    // Funktion zum Initialisieren der Map-Controls (wird nach Karteninitialisierung aufgerufen)
    function initMapControls() {
        const themeBtn = document.getElementById('themeBtn');
        const themeMenu = document.getElementById('themeMenu');
        const locationBtn = document.getElementById('locationBtn');
        const revokeBtn = document.getElementById('revokeBtn');
        
        if (themeBtn && themeMenu && window.mapInstance) {
            themeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                themeMenu.classList.toggle('active');
            });
            
            document.addEventListener('click', function() {
                themeMenu.classList.remove('active');
            });
            
            const themeOptions = themeMenu.querySelectorAll('.theme-option');
            for (let i = 0; i < themeOptions.length; i++) {
                themeOptions[i].addEventListener('click', function(e) {
                    e.stopPropagation();
                    const theme = this.dataset.theme;
                    window.mapInstance.removeLayer(window.currentTileLayer);
                    window.currentTileLayer = L.tileLayer(themes[theme].url, {
                        attribution: themes[theme].attribution,
                        maxZoom: 19
                    }).addTo(window.mapInstance);
                    themeMenu.classList.remove('active');
                });
            }
        }
        
        if (locationBtn && window.mapInstance) {
            locationBtn.addEventListener('click', function() {
                if (!locationEnabled) {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(function(position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            
                            if (userLocationMarker) {
                                window.mapInstance.removeLayer(userLocationMarker);
                            }
                            
                            userLocationMarker = L.marker([lat, lng], {
                                icon: L.icon({
                                    iconUrl: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiIGZpbGw9IiM4NEJGNUUiIHN0cm9rZT0id2hpdGUiIHN0cm9rZS13aWR0aD0iMiIvPgo8L3N2Zz4K',
                                    iconSize: [24, 24],
                                    iconAnchor: [12, 12]
                                })
                            }).addTo(window.mapInstance);
                            
                            window.mapInstance.setView([lat, lng], 13);
                            locationBtn.classList.add('active');
                            locationEnabled = true;
                            
                            watchId = navigator.geolocation.watchPosition(function(pos) {
                                const newLat = pos.coords.latitude;
                                const newLng = pos.coords.longitude;
                                if (userLocationMarker) {
                                    userLocationMarker.setLatLng([newLat, newLng]);
                                }
                            });
                        }, function(error) {
                            alert('Standort konnte nicht ermittelt werden');
                        });
                    } else {
                        alert('Geolocation wird von Ihrem Browser nicht unterstützt');
                    }
                } else {
                    if (watchId !== null) {
                        navigator.geolocation.clearWatch(watchId);
                        watchId = null;
                    }
                    if (userLocationMarker) {
                        window.mapInstance.removeLayer(userLocationMarker);
                        userLocationMarker = null;
                    }
                    locationBtn.classList.remove('active');
                    locationEnabled = false;
                }
            });
        }
    }
    
    // Funktion zum Kontaktieren eines Benutzers (Chat öffnen)
    function contactUser(userId, username) {
        if (!isLoggedIn) {
            if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('login');
            }
            return;
        }
        
        // Prüfe ob Chat-Box verfügbar ist
        if (typeof window.createChatFromContact === 'function') {
            window.createChatFromContact(userId, username);
        } else if (typeof window.openChat === 'function') {
            // Fallback: Versuche Chat direkt zu öffnen
            window.openChat(null, userId, username);
        } else {
            // Fallback: Öffne Chat-Box falls verfügbar
            const chatToggleBtn = document.getElementById('chatToggleBtn');
            if (chatToggleBtn) {
                chatToggleBtn.click();
                // Warte kurz und versuche dann Chat zu öffnen
                setTimeout(function() {
                    if (typeof window.createChatFromContact === 'function') {
                        window.createChatFromContact(userId, username);
                    }
                }, 500);
            } else {
                alert('Chat-Funktion ist nicht verfügbar. Bitte laden Sie die Seite neu.');
            }
        }
    }
    
    if (angeboteItems) {
        loadAngebote();
    }
    
    const loginPromptBtn = document.getElementById('loginPromptBtn');
    if (loginPromptBtn) {
        loginPromptBtn.addEventListener('click', function() {
            if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('login');
            }
        });
    }
});
