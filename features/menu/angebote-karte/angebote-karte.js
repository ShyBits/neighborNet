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
    // Prüfe ob Gast-Account (nur wenn angeboteItems existiert, da Gäste auch angeboteItems sehen können)
    const isGuest = angeboteItems ? angeboteItems.dataset.isGuest === 'true' : false;
    
    const addBtn = document.getElementById('addBtn');
    const mainFilterBtn = document.getElementById('mainFilterBtn');
    const mainFilterMenu = document.getElementById('mainFilterMenu');
    const filterDropdowns = document.querySelectorAll('.filter-dropdown');
    
    // Main Filter Dropdown öffnen/schließen
    if (mainFilterBtn && mainFilterMenu) {
        mainFilterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            mainFilterMenu.classList.toggle('active');
        });
        
        // Schließe beim Klicken außerhalb
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.main-filter-dropdown')) {
                mainFilterMenu.classList.remove('active');
            }
        });
    }
    
    let activeFilters = {
        category: null,
        date: null,
        timeFrom: null,
        timeTo: null,
        image: null,
        persons: null,
        location: {
            enabled: false,
            lat: null,
            lng: null,
            radius: 10 // km
        }
    };
    
    let userLocation = null;
    
    // Filter-Dropdown Handler
    filterDropdowns.forEach(dropdown => {
        const btn = dropdown.querySelector('.filter-dropdown-btn');
        const menu = dropdown.querySelector('.filter-dropdown-menu');
        const options = menu.querySelectorAll('.filter-dropdown-option');
        const filterType = btn.dataset.filterType;
        
        // Dropdown öffnen/schließen
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Schließe andere Dropdowns (nur innerhalb des Main Filter Menüs)
            filterDropdowns.forEach(d => {
                if (d !== dropdown && d.closest('.main-filter-menu')) {
                    d.classList.remove('active');
                }
            });
            dropdown.classList.toggle('active');
        });
        
        // Option auswählen
        options.forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const filterValue = this.dataset.filterValue;
                const isCurrentlyActive = this.classList.contains('active');
                
                // Wenn die Option bereits aktiv ist, entferne sie (Abwählen)
                if (isCurrentlyActive) {
                    this.classList.remove('active');
                    activeFilters[filterType] = null;
                    btn.classList.remove('active');
                    
                    // Setze Button-Text zurück
                    const btnText = btn.querySelector('.filter-dropdown-text');
                    const originalText = btn.dataset.filterType === 'category' ? 'Kategorie' :
                                       btn.dataset.filterType === 'date' ? 'Datum' :
                                       btn.dataset.filterType === 'time' ? 'Zeit' :
                                       btn.dataset.filterType === 'image' ? 'Bild' :
                                       btn.dataset.filterType === 'persons' ? 'Personen' : 'Alle';
                    btnText.textContent = originalText;
                } else {
                    // Entferne active von allen Optionen in diesem Dropdown
                    options.forEach(opt => opt.classList.remove('active'));
                    
                    // Setze active auf ausgewählte Option
                    this.classList.add('active');
                    activeFilters[filterType] = filterValue;
                    btn.classList.add('active');
                    
                    // Update Button Text - keep it short
                    const btnText = btn.querySelector('.filter-dropdown-text');
                    let displayText = this.textContent;
                    if (displayText.length > 15) {
                        displayText = displayText.substring(0, 12) + '...';
                    }
                    btnText.textContent = displayText;
                }
                
                // Schließe Dropdown
                dropdown.classList.remove('active');
                
                // Wende Filter an
                applyFilters();
            });
        });
    });
    
    // Personen-Input Filter
    const filterPersonsInput = document.getElementById('filterPersons');
    if (filterPersonsInput) {
        filterPersonsInput.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value && value > 0) {
                activeFilters.persons = value;
                this.classList.add('active');
            } else {
                activeFilters.persons = null;
                this.classList.remove('active');
            }
            applyFilters();
        });
    }
    
    // Datum-Filter (Kalender)
    const filterDateInput = document.getElementById('filterDate');
    if (filterDateInput) {
        // Klick auf Label öffnet den Kalender
        const dateLabel = document.querySelector('.filter-date-label');
        if (dateLabel) {
            dateLabel.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Direkt den Kalender öffnen
                if (filterDateInput.showPicker) {
                    filterDateInput.showPicker();
                } else {
                    // Fallback: Input direkt klicken
                    filterDateInput.focus();
                    filterDateInput.click();
                }
            });
        }
        
        // Auch direkt auf den Input klicken können
        filterDateInput.addEventListener('click', function(e) {
            e.stopPropagation();
            if (this.showPicker) {
                this.showPicker();
            }
        });
        
        filterDateInput.addEventListener('change', function() {
            if (this.value) {
                activeFilters.date = this.value;
                this.classList.add('active');
                // Update Label Text - mit Jahr
                const date = new Date(this.value + 'T00:00:00');
                const dateStr = date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
                const label = document.querySelector('.filter-date-text');
                if (label) label.textContent = dateStr;
            } else {
                activeFilters.date = null;
                this.classList.remove('active');
                const label = document.querySelector('.filter-date-text');
                if (label) label.textContent = 'Datum';
            }
            applyFilters();
        });
        
        // Doppelklick zum Zurücksetzen
        filterDateInput.addEventListener('dblclick', function() {
            this.value = '';
            activeFilters.date = null;
            this.classList.remove('active');
            const label = document.querySelector('.filter-date-text');
            if (label) label.textContent = 'Datum';
            applyFilters();
        });
        
        // Auch auf Label doppelklick zum Zurücksetzen
        if (dateLabel) {
            dateLabel.addEventListener('dblclick', function(e) {
                e.preventDefault();
                e.stopPropagation();
                filterDateInput.value = '';
                activeFilters.date = null;
                filterDateInput.classList.remove('active');
                const label = document.querySelector('.filter-date-text');
                if (label) label.textContent = 'Datum';
                applyFilters();
            });
        }
    }
    
    // Zeit-Filter (Time Range: Von-Bis) - Two Inputs
    const filterTimeFrom = document.getElementById('filterTimeFrom');
    const filterTimeTo = document.getElementById('filterTimeTo');
    
    if (filterTimeFrom && filterTimeTo) {
        function updateTimeFilter() {
            const hasFrom = filterTimeFrom.value !== '';
            const hasTo = filterTimeTo.value !== '';
            
            if (hasFrom || hasTo) {
                activeFilters.timeFrom = filterTimeFrom.value || null;
                activeFilters.timeTo = filterTimeTo.value || null;
                
                // Markiere aktive Inputs
                if (hasFrom) {
                    filterTimeFrom.classList.add('active');
                } else {
                    filterTimeFrom.classList.remove('active');
                }
                
                if (hasTo) {
                    filterTimeTo.classList.add('active');
                } else {
                    filterTimeTo.classList.remove('active');
                }
            } else {
                activeFilters.timeFrom = null;
                activeFilters.timeTo = null;
                filterTimeFrom.classList.remove('active');
                filterTimeTo.classList.remove('active');
            }
            applyFilters();
        }
        
        filterTimeFrom.addEventListener('change', updateTimeFilter);
        filterTimeTo.addEventListener('change', updateTimeFilter);
        
        // Klick auf bereits aktiven Input entfernt den Wert
        filterTimeFrom.addEventListener('click', function(e) {
            if (this.value && this.classList.contains('active')) {
                // Wenn bereits ein Wert gesetzt ist, entferne ihn beim Klick
                this.value = '';
                updateTimeFilter();
            }
        });
        
        filterTimeTo.addEventListener('click', function(e) {
            if (this.value && this.classList.contains('active')) {
                // Wenn bereits ein Wert gesetzt ist, entferne ihn beim Klick
                this.value = '';
                updateTimeFilter();
            }
        });
        
        // Doppelklick zum Zurücksetzen beider
        filterTimeFrom.addEventListener('dblclick', function() {
            filterTimeFrom.value = '';
            filterTimeTo.value = '';
            updateTimeFilter();
        });
        
        filterTimeTo.addEventListener('dblclick', function() {
            filterTimeFrom.value = '';
            filterTimeTo.value = '';
            updateTimeFilter();
        });
    }
    
    // Standort-Filter (kompakt)
    const filterLocationBtn = document.getElementById('filterLocationBtn');
    const filterRadiusInput = document.getElementById('filterRadius');
    
    if (filterLocationBtn && filterRadiusInput) {
        // Standort-Button: Aktiviert/Deaktiviert den Filter
        filterLocationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            if (!activeFilters.location.enabled) {
                // Aktiviere Filter - hole Standort
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            userLocation = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            };
                            const radius = parseInt(filterRadiusInput.value) || 10;
                            activeFilters.location.enabled = true;
                            activeFilters.location.lat = userLocation.lat;
                            activeFilters.location.lng = userLocation.lng;
                            activeFilters.location.radius = radius;
                            filterLocationBtn.classList.add('active');
                            const locationText = filterLocationBtn.querySelector('.filter-location-text');
                            if (locationText) locationText.textContent = 'On';
                            applyFilters();
                        },
                        function(error) {
                            alert('Standort konnte nicht ermittelt werden. Bitte erlauben Sie den Zugriff auf Ihren Standort.');
                        }
                    );
                } else {
                    alert('Standort-Funktion wird von Ihrem Browser nicht unterstützt.');
                }
            } else {
                // Deaktiviere Filter
                activeFilters.location.enabled = false;
                activeFilters.location.lat = null;
                activeFilters.location.lng = null;
                filterLocationBtn.classList.remove('active');
                const locationText = filterLocationBtn.querySelector('.filter-location-text');
                if (locationText) locationText.textContent = 'Off';
                applyFilters();
            }
        });
        
        // Radius-Input: Aktualisiert den Radius und wendet Filter an
        filterRadiusInput.addEventListener('input', function() {
            const radius = parseInt(this.value) || 10;
            activeFilters.location.radius = radius;
            if (activeFilters.location.enabled) {
                applyFilters();
            }
        });
    }
    
    // Reset-Button: Setzt alle Filter zurück
    const filterResetBtn = document.getElementById('filterResetBtn');
    if (filterResetBtn) {
        filterResetBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Setze alle Filter zurück
            activeFilters.category = null;
            activeFilters.date = null;
            activeFilters.timeFrom = null;
            activeFilters.timeTo = null;
            activeFilters.image = null;
            activeFilters.persons = null;
            activeFilters.location.enabled = false;
            activeFilters.location.lat = null;
            activeFilters.location.lng = null;
            
            // Reset UI Elements
            // Category
            document.querySelectorAll('.filter-dropdown-option.active').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelectorAll('.filter-dropdown-btn.active').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Date
            const filterDateInput = document.getElementById('filterDate');
            if (filterDateInput) {
                filterDateInput.value = '';
                filterDateInput.classList.remove('active');
                const dateLabel = document.querySelector('.filter-date-text');
                if (dateLabel) dateLabel.textContent = 'Datum';
            }
            
            // Time
            const filterTimeFrom = document.getElementById('filterTimeFrom');
            const filterTimeTo = document.getElementById('filterTimeTo');
            if (filterTimeFrom) {
                filterTimeFrom.value = '';
                filterTimeFrom.classList.remove('active');
            }
            if (filterTimeTo) {
                filterTimeTo.value = '';
                filterTimeTo.classList.remove('active');
            }
            
            // Persons
            const filterPersons = document.getElementById('filterPersons');
            if (filterPersons) {
                filterPersons.value = '';
                filterPersons.classList.remove('active');
            }
            
            // Location
            if (filterLocationBtn) {
                filterLocationBtn.classList.remove('active');
                const locationText = filterLocationBtn.querySelector('.filter-location-text');
                if (locationText) locationText.textContent = 'Off';
            }
            
            // Apply filters (which will clear everything)
            applyFilters();
        });
    }
    
    // Schließe Dropdowns beim Klicken außerhalb
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.filter-dropdown')) {
            filterDropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
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
    
    // Mache loadAngebote global verfügbar, damit es vom Add-Modal aufgerufen werden kann
    window.loadAngebote = loadAngebote;
    
    function getFilteredAngebote() {
        let filtered = [...angebote];
        
        // Filter nach Kategorie
        if (activeFilters.category) {
            filtered = filtered.filter(angebot => {
                return angebot.category === activeFilters.category;
            });
        }
        
        // Filter nach Datum (spezifisches Datum)
        if (activeFilters.date) {
            filtered = filtered.filter(angebot => {
                if (!angebot.start_date) return false;
                const filterDate = new Date(activeFilters.date + 'T00:00:00');
                filterDate.setHours(0, 0, 0, 0);
                const startDate = new Date(angebot.start_date + 'T00:00:00');
                startDate.setHours(0, 0, 0, 0);
                return startDate.getTime() === filterDate.getTime();
            });
        }
        
        // Filter nach Zeit (Von-Bis Bereich)
        if (activeFilters.timeFrom || activeFilters.timeTo) {
            filtered = filtered.filter(angebot => {
                if (!angebot.start_time) return false;
                const angebotTime = angebot.start_time.substring(0, 5); // HH:MM
                
                if (activeFilters.timeFrom && activeFilters.timeTo) {
                    // Beide Zeiten gesetzt: Prüfe ob Angebot-Zeit im Bereich liegt
                    return angebotTime >= activeFilters.timeFrom && angebotTime <= activeFilters.timeTo;
                } else if (activeFilters.timeFrom) {
                    // Nur "Von" gesetzt: Angebot muss ab dieser Zeit sein
                    return angebotTime >= activeFilters.timeFrom;
                } else if (activeFilters.timeTo) {
                    // Nur "Bis" gesetzt: Angebot muss bis zu dieser Zeit sein
                    return angebotTime <= activeFilters.timeTo;
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
        
        // Filter nach benötigten Personen (Minimum)
        if (activeFilters.persons) {
            filtered = filtered.filter(angebot => {
                const requiredPersons = parseInt(angebot.required_persons) || 1;
                return requiredPersons >= activeFilters.persons;
            });
        }
        
        // Filter nach Standort (Radius)
        if (activeFilters.location.enabled && activeFilters.location.lat && activeFilters.location.lng) {
            filtered = filtered.filter(angebot => {
                if (!angebot.lat || !angebot.lng) return false;
                
                const distance = calculateDistance(
                    activeFilters.location.lat,
                    activeFilters.location.lng,
                    parseFloat(angebot.lat),
                    parseFloat(angebot.lng)
                );
                
                return distance <= activeFilters.location.radius;
            });
        }
        
        return filtered;
    }
    
    // Hilfsfunktion: Berechnet Entfernung zwischen zwei Koordinaten (Haversine-Formel)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Radius der Erde in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c; // Entfernung in km
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
                    ${isLoggedIn && !isGuest && !isOwner && angebot.user_id ? `
                    <div class="map-popup-actions">
                        <button class="map-popup-contact-btn" data-user-id="${angebot.user_id}" data-username="${escapeHtml(angebot.author || 'Unbekannt')}" data-angebot-id="${angebot.id}">
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
                        const angebotId = parseInt(this.dataset.angebotId);
                        contactUser(userId, username, angebot.title, angebotId);
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
                                ${isLoggedIn && !isGuest && !isOwner && angebot.user_id ? `
                                <button class="angebote-contact-btn" data-user-id="${angebot.user_id}" data-username="${escapeHtml(angebot.author || 'Unbekannt')}" data-angebot-id="${angebot.id}" title="Kontaktieren">
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
                    const angebotId = parseInt(this.dataset.angebotId);
                    contactUser(userId, username, angebot.title, angebotId);
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
        // Prüfe ob irgendein Filter aktiv ist
        const hasActiveFilter = 
            activeFilters.category !== null ||
            activeFilters.date !== null ||
            activeFilters.timeFrom !== null ||
            activeFilters.timeTo !== null ||
            activeFilters.image !== null ||
            activeFilters.persons !== null ||
            activeFilters.location.enabled;
        
        // Update Main Filter Button Status
        if (mainFilterBtn) {
            if (hasActiveFilter) {
                mainFilterBtn.classList.add('active');
            } else {
                mainFilterBtn.classList.remove('active');
            }
        }
        
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
    
    // Track if event listeners are already attached to prevent duplicates
    let mapControlsInitialized = false;
    
    // Helper-Funktion zum Setzen des Consent-Cookies
    function setMapConsent(accepted) {
        // Get current path for cookie
        const path = window.location.pathname;
        const cookiePath = path.substring(0, path.lastIndexOf('/') + 1) || '/';
        
        if (accepted) {
            // Set cookie with proper path and SameSite attribute
            document.cookie = 'map_consent=accepted; path=' + cookiePath + '; max-age=' + (365 * 24 * 60 * 60) + '; SameSite=Lax';
        } else {
            // Delete cookie
            document.cookie = 'map_consent=; path=' + cookiePath + '; max-age=0';
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
                                // Reset flag damit Controls initialisiert werden können
                                mapControlsInitialized = false;
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
    
    // Funktion zum Initialisieren der Map-Controls (wird einmalig aufgerufen)
    function initMapControls() {
        // Verhindere doppelte Initialisierung
        if (mapControlsInitialized) {
            return;
        }
        
        const themeBtn = document.getElementById('themeBtn');
        const themeMenu = document.getElementById('themeMenu');
        const locationBtn = document.getElementById('locationBtn');
        const revokeBtn = document.getElementById('revokeBtn');
        
        // Prüfe ob map verfügbar ist (entweder map oder window.mapInstance)
        const currentMap = map || window.mapInstance;
        if (!currentMap) {
            return;
        }
        
        // Theme Button Handler
        if (themeBtn && themeMenu && !themeBtn.dataset.listenerAttached) {
            themeBtn.dataset.listenerAttached = 'true';
            themeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                themeMenu.classList.toggle('active');
            });
            
            // Schließe Theme-Menu beim Klicken außerhalb
            let themeMenuClickHandler = function(e) {
                if (!e.target.closest('.map-theme-dropdown')) {
                    themeMenu.classList.remove('active');
                }
            };
            document.addEventListener('click', themeMenuClickHandler);
            
            const themeOptions = themeMenu.querySelectorAll('.theme-option');
            for (let i = 0; i < themeOptions.length; i++) {
                if (!themeOptions[i].dataset.listenerAttached) {
                    themeOptions[i].dataset.listenerAttached = 'true';
                    themeOptions[i].addEventListener('click', function(e) {
                        e.stopPropagation();
                        const theme = this.dataset.theme;
                        if (currentMap && window.currentTileLayer) {
                            currentMap.removeLayer(window.currentTileLayer);
                            window.currentTileLayer = L.tileLayer(themes[theme].url, {
                                attribution: themes[theme].attribution,
                                maxZoom: 19
                            }).addTo(currentMap);
                            currentTileLayer = window.currentTileLayer;
                        }
                        themeMenu.classList.remove('active');
                    });
                }
            }
        }
        
         // Location Button Handler
         if (locationBtn && !locationBtn.dataset.listenerAttached) {
             locationBtn.dataset.listenerAttached = 'true';
             locationBtn.addEventListener('click', function() {
                 if (!locationEnabled) {
                     // Versuche Geolocation zu verwenden - Browser zeigt eigene Fehlermeldung bei HTTP
                     if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            function(position) {
                                const lat = position.coords.latitude;
                                const lng = position.coords.longitude;
                                
                                if (currentMap) {
                                    if (userLocationMarker) {
                                        currentMap.removeLayer(userLocationMarker);
                                    }
                                    
                                    userLocationMarker = L.marker([lat, lng], {
                                        icon: L.icon({
                                            iconUrl: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiIGZpbGw9IiM4NEJGNUUiIHN0cm9rZT0id2hpdGUiIHN0cm9rZS13aWR0aD0iMiIvPgo8L3N2Zz4K',
                                            iconSize: [24, 24],
                                            iconAnchor: [12, 12]
                                        })
                                    }).addTo(currentMap);
                                    
                                    currentMap.setView([lat, lng], 13);
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
                            },
                            function(error) {
                                let errorMsg = 'Standort konnte nicht ermittelt werden';
                                if (error.code === error.PERMISSION_DENIED) {
                                    errorMsg = 'Standort-Zugriff wurde verweigert. Bitte erlauben Sie den Zugriff in Ihren Browser-Einstellungen.';
                                } else if (error.code === error.POSITION_UNAVAILABLE) {
                                    errorMsg = 'Standort-Informationen sind nicht verfügbar.';
                                } else if (error.code === error.TIMEOUT) {
                                    errorMsg = 'Zeitüberschreitung beim Abrufen des Standorts.';
                                }
                                alert(errorMsg);
                            },
                            {
                                enableHighAccuracy: true,
                                timeout: 10000,
                                maximumAge: 0
                            }
                        );
                    } else {
                        alert('Geolocation wird von Ihrem Browser nicht unterstützt');
                    }
                } else {
                    if (watchId !== null) {
                        navigator.geolocation.clearWatch(watchId);
                        watchId = null;
                    }
                    if (currentMap && userLocationMarker) {
                        currentMap.removeLayer(userLocationMarker);
                        userLocationMarker = null;
                    }
                    locationBtn.classList.remove('active');
                    locationEnabled = false;
                }
            });
        }
        
        // Revoke Button Handler
        if (revokeBtn && !revokeBtn.dataset.listenerAttached) {
            revokeBtn.dataset.listenerAttached = 'true';
            revokeBtn.addEventListener('click', function() {
                if (confirm('Möchten Sie die Einwilligung wirklich widerrufen? Die Karte wird dann nicht mehr angezeigt.')) {
                    setMapConsent(false);
                    
                    // Stoppe Geolocation-Watching falls aktiv
                    if (watchId !== null) {
                        navigator.geolocation.clearWatch(watchId);
                        watchId = null;
                    }
                    locationEnabled = false;
                    
                    // Entferne Karte und Controls vollständig
                    const mapElement = document.getElementById('map');
                    const mapControls = document.querySelector('.map-controls');
                    
                    if (mapElement) {
                        if (window.mapInstance) {
                            try {
                                window.mapInstance.remove();
                            } catch (e) {
                                console.log('Map instance already removed');
                            }
                            window.mapInstance = null;
                        }
                        if (map) {
                            try {
                                map.remove();
                            } catch (e) {
                                console.log('Map already removed');
                            }
                            map = null;
                        }
                        mapElement.remove();
                    }
                    if (mapControls) {
                        mapControls.remove();
                    }
                    
                    // Entferne alle Marker
                    if (userLocationMarker) {
                        userLocationMarker = null;
                    }
                    
                    // Leere den Container und zeige Consent-Dialog wieder an
                    const karteContainer = document.querySelector('.karte-container');
                    if (karteContainer) {
                        // Entferne alle vorhandenen Inhalte
                        karteContainer.innerHTML = '';
                        
                        // Erstelle Consent-Dialog
                        const consentDialog = document.createElement('div');
                        consentDialog.className = 'map-consent-dialog';
                        consentDialog.id = 'mapConsentDialog';
                        consentDialog.style.display = 'flex'; // Stelle sicher, dass es sichtbar ist
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
                    
                    // Reset initialization flag
                    mapControlsInitialized = false;
                }
            });
        }
        
        mapControlsInitialized = true;
    }
    
    // Initialisiere Map-Controls wenn Buttons bereits vorhanden sind (bei Seitenladung mit Consent)
    if (locationBtn || revokeBtn || themeBtn) {
        // Warte kurz, damit die Karte initialisiert ist
        setTimeout(function() {
            initMapControls();
        }, 100);
    }
    
    // Funktion zum Kontaktieren eines Benutzers (Chat öffnen)
    function contactUser(userId, username, angebotTitle = null, angebotId = null) {
        if (!isLoggedIn) {
            if (typeof window.openAuthModal === 'function') {
                window.openAuthModal('login');
            }
            return;
        }
        
        // Prüfe ob Chat-Box verfügbar ist
        if (typeof window.createChatFromContact === 'function') {
            window.createChatFromContact(userId, username, angebotTitle, angebotId);
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
                        window.createChatFromContact(userId, username, angebotTitle, angebotId);
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
    
    // Mobile: Dynamic scroll-based height adjustment
    function isMobileScreen() {
        return window.innerWidth <= 768;
    }
    
    function setupMobileScrollAdjustment() {
        if (!isMobileScreen()) return;
        
        const karteContainer = document.querySelector('.karte-container');
        const angeboteItems = document.querySelector('.angebote-items');
        
        if (!karteContainer || !angeboteItems) return;
        
        let lastScrollTop = 0;
        let currentMapHeight = 45; // Start at 45vh (standard: 45% Karte, 50% Anfragen, 5% Banner)
        const minMapHeight = 25; // Minimum 25vh
        const maxMapHeight = 70; // Maximum 70vh
        const scrollSensitivity = 0.3; // How much height changes per scroll
        
        // Listen to scroll on the items container
        angeboteItems.addEventListener('scroll', function() {
            const scrollTop = this.scrollTop;
            
            // Determine scroll direction
            if (scrollTop > lastScrollTop) {
                // Scrolling down - decrease map height, increase list height
                currentMapHeight = Math.max(minMapHeight, currentMapHeight - scrollSensitivity);
            } else if (scrollTop < lastScrollTop) {
                // Scrolling up - increase map height, decrease list height
                currentMapHeight = Math.min(maxMapHeight, currentMapHeight + scrollSensitivity);
            }
            
            // Apply the height change
            karteContainer.style.height = currentMapHeight + 'vh';
            karteContainer.style.minHeight = (currentMapHeight * 8) + 'px';
            
            // Update map size if Leaflet is loaded (debounced)
            clearTimeout(window.mapResizeTimeout);
            window.mapResizeTimeout = setTimeout(() => {
                if (map && typeof map.invalidateSize === 'function') {
                    map.invalidateSize();
                }
            }, 150);
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }, { passive: true });
    }
    
    // Initialize on load
    setTimeout(() => {
        if (isMobileScreen()) {
            setupMobileScrollAdjustment();
        }
    }, 500); // Wait a bit for DOM to be ready
    
    // Re-initialize on window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (isMobileScreen()) {
                setupMobileScrollAdjustment();
            }
            if (map && typeof map.invalidateSize === 'function') {
                map.invalidateSize();
            }
        }, 250);
    });
});
