document.addEventListener('DOMContentLoaded', function() {
    
    const addBtn = document.getElementById('addBtn');
    const addModalOverlay = document.getElementById('addModalOverlay');
    const addModalClose = document.getElementById('addModalClose');
    const formCancelBtn = document.getElementById('formCancelBtn');
    const formSubmitBtn = document.getElementById('formSubmitBtn');
    const angebotFileInput = document.getElementById('angebotFileInput');
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    const addModalMap = document.getElementById('addModalMap');
    
    let uploadedFiles = [];
    let addModalMapInstance = null;
    let selectedMarker = null;
    let selectedLat = null;
    let selectedLng = null;
    let addressMode = 'auto';
    let manualAddressFields = {};
    
    const addressToggleBtn = document.getElementById('addressToggleBtn');
    const addressModeLabel = document.getElementById('addressModeLabel');
    const addressAutoContainer = document.getElementById('addressAutoContainer');
    const addressManualContainer = document.getElementById('addressManualContainer');
    const addressManualFields = document.getElementById('addressManualFields');
    const addModalConsentBtn = document.getElementById('addModalConsentBtn');
    
    if (!addBtn || !addModalOverlay) {
        return;
    }
    
    // Helper-Funktion zum Setzen des Consent-Cookies für Add-Modal (separat von Hauptkarte)
    function setAddModalMapConsent(accepted) {
        const path = window.location.pathname;
        const cookiePath = path.substring(0, path.lastIndexOf('/') + 1) || '/';
        
        if (accepted) {
            document.cookie = 'add_modal_map_consent=accepted; path=' + cookiePath + '; max-age=' + (365 * 24 * 60 * 60) + '; SameSite=Lax';
        } else {
            document.cookie = 'add_modal_map_consent=; path=' + cookiePath + '; max-age=0';
        }
    }
    
    // Handle Karten-Einwilligung im Modal
    if (addModalConsentBtn) {
        addModalConsentBtn.addEventListener('click', function() {
            setAddModalMapConsent(true);
            
            // Ersetze Einwilligungsmeldung durch Karte
            const addModalMapContainer = document.querySelector('.add-modal-map');
            if (addModalMapContainer) {
                const consentMessage = addModalMapContainer.querySelector('.add-modal-map-consent');
                if (consentMessage) {
                    // Erstelle Karten-Container
                    const mapContainer = document.createElement('div');
                    mapContainer.id = 'addModalMap';
                    mapContainer.className = 'add-modal-map-container';
                    
                    // Erstelle Widerruf-Button
                    const revokeBtn = document.createElement('button');
                    revokeBtn.className = 'add-modal-revoke-btn';
                    revokeBtn.id = 'addModalRevokeBtn';
                    revokeBtn.title = 'Einwilligung widerrufen';
                    revokeBtn.innerHTML = `
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18"></path>
                            <path d="M6 6l12 12"></path>
                        </svg>
                    `;
                    
                    // Ersetze Consent-Message durch Karten-Container
                    consentMessage.replaceWith(mapContainer);
                    addModalMapContainer.appendChild(revokeBtn);
                    
                    // Initialisiere Karte nach kurzer Verzögerung
                    if (typeof L !== 'undefined' && addModalMapInstance === null) {
                        setTimeout(function() {
                            const newAddModalMap = document.getElementById('addModalMap');
                            if (newAddModalMap && !addModalMapInstance) {
                                initMap();
                            }
                        }, 150);
                    }
                    
                    // Füge Event-Listener zum Widerruf-Button hinzu
                    const newRevokeBtn = document.getElementById('addModalRevokeBtn');
                    if (newRevokeBtn) {
                        newRevokeBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            if (confirm('Möchten Sie die Einwilligung wirklich widerrufen? Die Karte wird dann nicht mehr angezeigt.')) {
                                setAddModalMapConsent(false);
                                
                                // Entferne Karte und Button
                                if (addModalMapInstance) {
                                    addModalMapInstance.remove();
                                    addModalMapInstance = null;
                                }
                                const mapContainer = document.getElementById('addModalMap');
                                if (mapContainer) {
                                    mapContainer.remove();
                                }
                                const revokeBtn = document.getElementById('addModalRevokeBtn');
                                if (revokeBtn) {
                                    revokeBtn.remove();
                                }
                                
                                // Zeige Consent-Dialog wieder an
                                const addModalMapContainer = document.querySelector('.add-modal-map');
                                if (addModalMapContainer) {
                                    const consentDialog = document.createElement('div');
                                    consentDialog.className = 'add-modal-map-consent';
                                    consentDialog.innerHTML = `
                                        <div class="consent-content">
                                            <h3>Karten-Einwilligung</h3>
                                            <p>Um die interaktive Karte zu nutzen, benötigen wir Ihre Einwilligung zur Anzeige von Kartenmaterial.</p>
                                            <button type="button" class="consent-accept-btn" id="addModalConsentBtn">Einwilligen</button>
                                        </div>
                                    `;
                                    addModalMapContainer.appendChild(consentDialog);
                                    
                                    // Füge Event-Listener zum neuen Consent-Button hinzu
                                    const newConsentBtn = document.getElementById('addModalConsentBtn');
                                    if (newConsentBtn) {
                                        newConsentBtn.addEventListener('click', function() {
                                            setAddModalMapConsent(true);
                                            location.reload();
                                        });
                                    }
                                }
                            }
                        });
                    }
                }
            }
        });
    }
    
    // Handle Widerruf-Button (wenn bereits vorhanden beim Öffnen des Modals)
    const addModalRevokeBtn = document.getElementById('addModalRevokeBtn');
    if (addModalRevokeBtn) {
        addModalRevokeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm('Möchten Sie die Einwilligung wirklich widerrufen? Die Karte wird dann nicht mehr angezeigt.')) {
                setAddModalMapConsent(false);
                
                // Entferne Karte und Button
                if (addModalMapInstance) {
                    addModalMapInstance.remove();
                    addModalMapInstance = null;
                }
                const mapContainer = document.getElementById('addModalMap');
                if (mapContainer) {
                    mapContainer.remove();
                }
                const revokeBtn = document.getElementById('addModalRevokeBtn');
                if (revokeBtn) {
                    revokeBtn.remove();
                }
                
                // Zeige Consent-Dialog wieder an
                const addModalMapContainer = document.querySelector('.add-modal-map');
                if (addModalMapContainer) {
                    const consentDialog = document.createElement('div');
                    consentDialog.className = 'add-modal-map-consent';
                    consentDialog.innerHTML = `
                        <div class="consent-content">
                            <h3>Karten-Einwilligung</h3>
                            <p>Um die interaktive Karte zu nutzen, benötigen wir Ihre Einwilligung zur Anzeige von Kartenmaterial.</p>
                            <button type="button" class="consent-accept-btn" id="addModalConsentBtn">Einwilligen</button>
                        </div>
                    `;
                    addModalMapContainer.appendChild(consentDialog);
                    
                    // Füge Event-Listener zum neuen Consent-Button hinzu
                    const newConsentBtn = document.getElementById('addModalConsentBtn');
                    if (newConsentBtn) {
                        newConsentBtn.addEventListener('click', function() {
                            setAddModalMapConsent(true);
                            location.reload();
                        });
                    }
                }
            }
        });
    }
    
    function initManualFields() {
        if (addressManualFields.children.length === 0) {
            // Erste Zeile: Straße und Hausnummer
            const row1 = document.createElement('div');
            row1.className = 'address-manual-row';
            
            const streetField = createManualField('manualStreet', 'Straße', 'z.B. Hauptstraße');
            const houseNumberField = createManualField('manualHouseNumber', 'Hausnummer', 'z.B. 123');
            
            row1.appendChild(streetField);
            row1.appendChild(houseNumberField);
            addressManualFields.appendChild(row1);
            
            // Zweite Zeile: Postleitzahl und Stadt
            const row2 = document.createElement('div');
            row2.className = 'address-manual-row';
            
            const postcodeField = createManualField('manualPostcode', 'Postleitzahl', 'z.B. 12345');
            const cityField = createManualField('manualCity', 'Stadt', 'z.B. Berlin');
            
            row2.appendChild(postcodeField);
            row2.appendChild(cityField);
            addressManualFields.appendChild(row2);
        }
    }
    
    function createManualField(id, labelText, placeholder) {
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'address-manual-field';
        
        const label = document.createElement('label');
        label.textContent = labelText;
        label.setAttribute('for', id);
        
        const input = document.createElement('input');
        input.type = 'text';
        input.id = id;
        input.placeholder = placeholder;
        
        fieldDiv.appendChild(label);
        fieldDiv.appendChild(input);
        
        manualAddressFields[id] = input;
        
        return fieldDiv;
    }
    
    function switchAddressMode(mode) {
        addressMode = mode;
        
        if (mode === 'auto') {
            addressToggleBtn.classList.add('active');
            if (addressModeLabel) {
                addressModeLabel.textContent = 'Auto Auswahl';
            }
            addressAutoContainer.style.display = 'block';
            addressManualContainer.style.display = 'none';
            
            if (addModalMapInstance) {
                addModalMapInstance.off('click');
                addModalMapInstance.on('click', function(e) {
                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;
                    
                    selectedLat = lat;
                    selectedLng = lng;
                    
                    if (selectedMarker) {
                        addModalMapInstance.removeLayer(selectedMarker);
                    }
                    
                    selectedMarker = L.marker([lat, lng]).addTo(addModalMapInstance);
                    reverseGeocode(lat, lng);
                });
            }
        } else {
            addressToggleBtn.classList.remove('active');
            if (addressModeLabel) {
                addressModeLabel.textContent = 'Manuelle Auswahl';
            }
            addressAutoContainer.style.display = 'none';
            addressManualContainer.style.display = 'block';
            initManualFields();
            
            if (addModalMapInstance) {
                addModalMapInstance.off('click');
            }
        }
    }
    
    if (addressToggleBtn) {
        addressToggleBtn.addEventListener('click', function() {
            if (addressMode === 'auto') {
                switchAddressMode('manual');
            } else {
                switchAddressMode('auto');
            }
        });
    }
    
    addBtn.addEventListener('click', function() {
        openModal();
    });
    
    addModalClose.addEventListener('click', function() {
        closeModal();
    });
    
    if (formCancelBtn) {
        formCancelBtn.addEventListener('click', function() {
            closeModal();
        });
    }
    
    let mouseDownOnAddModalOverlay = false;
    
    addModalOverlay.addEventListener('mousedown', function(e) {
        if (e.target === addModalOverlay) {
            mouseDownOnAddModalOverlay = true;
        } else {
            mouseDownOnAddModalOverlay = false;
        }
    });
    
    addModalOverlay.addEventListener('mouseup', function(e) {
        if (mouseDownOnAddModalOverlay && e.target === addModalOverlay) {
            closeModal();
        }
        mouseDownOnAddModalOverlay = false;
    });
    
    function openModal() {
        addModalOverlay.classList.add('active');
        
        setTimeout(function() {
            if (typeof L !== 'undefined' && addModalMap && !addModalMapInstance) {
                initMap();
            }
        }, 100);
    }
    
    function closeModal() {
        addModalOverlay.classList.remove('active');
        resetForm();
    }
    
    function resetForm() {
        const titleInput = document.getElementById('angebotTitle');
        const descriptionInput = document.getElementById('angebotDescription');
        const categoryInput = document.getElementById('angebotCategory');
        const startDateTimeInput = document.getElementById('angebotStartDateTime');
        const endDateTimeInput = document.getElementById('angebotEndDateTime');
        const addressInput = document.getElementById('angebotAddress');
        const requiredPersonsInput = document.getElementById('angebotRequiredPersons');
        
        if (titleInput) titleInput.value = '';
        if (descriptionInput) descriptionInput.value = '';
        if (categoryInput) categoryInput.value = '';
        if (startDateTimeInput) startDateTimeInput.value = '';
        if (endDateTimeInput) endDateTimeInput.value = '';
        if (addressInput) addressInput.value = '';
        if (requiredPersonsInput) requiredPersonsInput.value = '1';
        
        uploadedFiles = [];
        if (filePreviewContainer) filePreviewContainer.innerHTML = '';
        selectedLat = null;
        selectedLng = null;
        addressMode = 'auto';
        
        if (addressToggleBtn) {
            addressToggleBtn.classList.add('active');
        }
        
        if (addressAutoContainer) {
            addressAutoContainer.style.display = 'block';
        }
        if (addressManualContainer) {
            addressManualContainer.style.display = 'none';
        }
        
        for (let fieldId in manualAddressFields) {
            if (manualAddressFields[fieldId]) {
                manualAddressFields[fieldId].value = '';
            }
        }
        
        if (addModalMapInstance) {
            addModalMapInstance.remove();
            addModalMapInstance = null;
        }
        if (selectedMarker) {
            selectedMarker = null;
        }
    }
    
    function initMap() {
        const mapElement = document.getElementById('addModalMap');
        if (!mapElement) {
            return;
        }
        addModalMapInstance = L.map('addModalMap').setView([51.1657, 10.4515], 6);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(addModalMapInstance);
        
        if (addressMode === 'auto') {
            addModalMapInstance.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                selectedLat = lat;
                selectedLng = lng;
                
                if (selectedMarker) {
                    addModalMapInstance.removeLayer(selectedMarker);
                }
                
                selectedMarker = L.marker([lat, lng]).addTo(addModalMapInstance);
                reverseGeocode(lat, lng);
            });
        }
        
        initManualFields();
    }
    
    function reverseGeocode(lat, lng) {
        const addressInput = document.getElementById('angebotAddress');
        addressInput.value = 'Lade Adresse...';
        
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(response => response.json())
            .then(data => {
                if (data.address) {
                    const addressParts = [];
                    if (data.address.road) addressParts.push(data.address.road);
                    if (data.address.house_number) addressParts.push(data.address.house_number);
                    if (data.address.postcode) addressParts.push(data.address.postcode);
                    if (data.address.city || data.address.town || data.address.village) {
                        addressParts.push(data.address.city || data.address.town || data.address.village);
                    }
                    
                    addressInput.value = addressParts.join(', ') || `${lat}, ${lng}`;
                } else {
                    addressInput.value = `${lat}, ${lng}`;
                }
            })
            .catch(error => {
                console.error('Geocoding error:', error);
                addressInput.value = `${lat}, ${lng}`;
            });
    }
    
    const fileUploadArea = document.getElementById('fileUploadArea');
    
    function handleFiles(files) {
        for (let i = 0; i < files.length; i++) {
            if (uploadedFiles.length >= 5) {
                alert('Maximal 5 Dateien erlaubt');
                break;
            }
            
            const file = files[i];
            if (file.type.startsWith('image/')) {
                uploadedFiles.push(file);
                addFilePreview(file);
            }
        }
    }
    
    if (angebotFileInput) {
        angebotFileInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            handleFiles(files);
            e.target.value = '';
        });
    }
    
    if (fileUploadArea) {
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileUploadArea.classList.add('drag-over');
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileUploadArea.classList.remove('drag-over');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileUploadArea.classList.remove('drag-over');
            
            const files = Array.from(e.dataTransfer.files);
            handleFiles(files);
        });
    }
    
        function addFilePreview(file) {
            const reader = new FileReader();

            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'file-preview-item';
                previewItem.dataset.fileName = file.name;

                const removeBtn = document.createElement('button');
                removeBtn.className = 'file-preview-remove';
                removeBtn.innerHTML = '×';
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    removeFile(file.name);
                    previewItem.remove();
                });

                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = file.name;

                previewItem.appendChild(removeBtn);
                previewItem.appendChild(img);
                filePreviewContainer.appendChild(previewItem);
            };

            reader.readAsDataURL(file);
        }
    
    function removeFile(fileName) {
        uploadedFiles = uploadedFiles.filter(file => file.name !== fileName);
    }
    
    // Helper: Konvertiert datetime-local zu separaten date/time
    function splitDateTime(dateTimeString) {
        if (!dateTimeString) return { date: '', time: '' };
        const [date, time] = dateTimeString.split('T');
        return { date: date || '', time: time || '' };
    }
    
    // Event Handler für datetime-local Inputs
    const startDateTimeInput = document.getElementById('angebotStartDateTime');
    const endDateTimeInput = document.getElementById('angebotEndDateTime');
    
    let previousValues = {
        start: '',
        end: ''
    };
    
    function setupDateTimeInput(input, nextInput, key) {
        if (!input) return;
        
        let checkTimeout = null;
        
        // Begrenze Jahr auf 4 Ziffern und erkenne vollständige Eingabe
        input.addEventListener('input', function(e) {
            let value = e.target.value;
            const previousValue = previousValues[key];
            
            // Stelle sicher, dass das Jahr nur 4 Ziffern hat
            if (value && value.length > 0) {
                // Prüfe verschiedene Formate für das Jahr
                // Format 1: YYYY-MM-DDTHH:MM (vollständig)
                let match = value.match(/^(\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
                if (match && match[1].length > 4) {
                    const year = match[1].substring(0, 4);
                    value = year + '-' + match[2] + '-' + match[3] + 'T' + match[4] + ':' + match[5];
                    e.target.value = value;
                }
                // Format 2: YYYY-MM-DDTHH: (noch keine Minuten)
                else {
                    match = value.match(/^(\d{4,})-(\d{2})-(\d{2})T(\d{2}):?$/);
                    if (match && match[1].length > 4) {
                        const year = match[1].substring(0, 4);
                        value = year + '-' + match[2] + '-' + match[3] + 'T' + match[4] + ':';
                        e.target.value = value;
                    }
                    // Format 3: Beginn der Eingabe - nur Jahr
                    else if (value.length >= 4 && value.indexOf('-') === -1) {
                        // Prüfe ob mehr als 4 Ziffern am Anfang stehen
                        const yearMatch = value.match(/^(\d{4,})/);
                        if (yearMatch && yearMatch[1].length > 4) {
                            value = yearMatch[1].substring(0, 4) + value.substring(yearMatch[1].length);
                            e.target.value = value;
                        }
                    }
                }
            }
            
            // Aktualisiere den gespeicherten Wert
            previousValues[key] = value;
            
            // Prüfe ob das Format vollständig ist (YYYY-MM-DDTHH:MM)
            const fullFormatMatch = value.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
            const previousFullMatch = previousValue ? previousValue.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/) : null;
            
            // Wenn das Format gerade vollständig wurde UND es ist nicht das letzte Feld
            if (fullFormatMatch && !previousFullMatch && nextInput) {
                // Warte kurz, dann wechsle zum nächsten Feld
                clearTimeout(checkTimeout);
                checkTimeout = setTimeout(function() {
                    nextInput.focus();
                    // Versuche, den Cursor an den Anfang zu setzen
                    try {
                        if (nextInput.setSelectionRange) {
                            nextInput.setSelectionRange(0, 0);
                        }
                    } catch (e) {
                        // Fallback für Browser, die setSelectionRange nicht unterstützen
                    }
                }, 150);
            }
        });
        
        // Auch auf 'change' Event hören (für Browser, die das Format anders handhaben)
        input.addEventListener('change', function(e) {
            let value = e.target.value;
            if (value && value.length > 0) {
                // Extrahiere und korrigiere das Jahr
                const match = value.match(/^(\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
                if (match && match[1].length > 4) {
                    // Kürze das Jahr auf 4 Ziffern
                    const year = match[1].substring(0, 4);
                    const corrected = year + '-' + match[2] + '-' + match[3] + 'T' + match[4] + ':' + match[5];
                    e.target.value = corrected;
                    previousValues[key] = corrected;
                }
                
                // Prüfe ob vollständig und wechsle zum nächsten Feld
                const fullFormatMatch = value.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
                if (fullFormatMatch && nextInput) {
                    setTimeout(function() {
                        nextInput.focus();
                        try {
                            if (nextInput.setSelectionRange) {
                                nextInput.setSelectionRange(0, 0);
                            }
                        } catch (e) {
                            // Fallback
                        }
                    }, 100);
                }
            }
        });
        
        // Begrenze das Jahr beim Blur (zur Sicherheit)
        input.addEventListener('blur', function(e) {
            let value = e.target.value;
            if (value && value.length > 0) {
                // Extrahiere und korrigiere das Jahr
                const match = value.match(/^(\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
                if (match && match[1].length > 4) {
                    // Kürze das Jahr auf 4 Ziffern
                    const year = match[1].substring(0, 4);
                    const corrected = year + '-' + match[2] + '-' + match[3] + 'T' + match[4] + ':' + match[5];
                    e.target.value = corrected;
                    previousValues[key] = corrected;
                }
            }
        });
    }
    
    // Setup für beide Inputs
    if (startDateTimeInput && endDateTimeInput) {
        setupDateTimeInput(startDateTimeInput, endDateTimeInput, 'start');
        setupDateTimeInput(endDateTimeInput, null, 'end'); // Letztes Feld hat kein nächstes
    }
    
    // Funktion zur Normalisierung und Validierung von datetime-local Werten
    function normalizeDateTime(dateTimeString) {
        if (!dateTimeString) return null;
        
        // Prüfe ob das Format vollständig ist (YYYY-MM-DDTHH:MM)
        const match = dateTimeString.match(/^(\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/);
        if (match) {
            // Stelle sicher, dass das Jahr nur 4 Ziffern hat
            const year = match[1].length > 4 ? match[1].substring(0, 4) : match[1];
            return year + '-' + match[2] + '-' + match[3] + 'T' + match[4] + ':' + match[5];
        }
        
        return dateTimeString;
    }
    
    if (formSubmitBtn) {
        formSubmitBtn.addEventListener('click', function() {
            const title = document.getElementById('angebotTitle').value.trim();
            const description = document.getElementById('angebotDescription').value.trim();
            const category = document.getElementById('angebotCategory').value;
            let startDateTime = document.getElementById('angebotStartDateTime').value;
            let endDateTime = document.getElementById('angebotEndDateTime').value;
            const requiredPersons = parseInt(document.getElementById('angebotRequiredPersons').value) || 1;
            
            // Normalisiere die Datumswerte (Jahr auf 4 Ziffern begrenzen)
            startDateTime = normalizeDateTime(startDateTime);
            endDateTime = normalizeDateTime(endDateTime);
            
            // Aktualisiere die Input-Felder mit normalisierten Werten
            if (startDateTime) {
                document.getElementById('angebotStartDateTime').value = startDateTime;
            }
            if (endDateTime) {
                document.getElementById('angebotEndDateTime').value = endDateTime;
            }
            
            // Validierung der Pflichtfelder
            if (!title || title.length === 0) {
                alert('Bitte geben Sie einen Titel ein');
                document.getElementById('angebotTitle').focus();
                return;
            }
            
            if (title.length < 3) {
                alert('Der Titel muss mindestens 3 Zeichen lang sein');
                document.getElementById('angebotTitle').focus();
                return;
            }
            
            if (!description || description.length === 0) {
                alert('Bitte geben Sie eine Beschreibung ein');
                document.getElementById('angebotDescription').focus();
                return;
            }
            
            if (description.length < 10) {
                alert('Die Beschreibung muss mindestens 10 Zeichen lang sein');
                document.getElementById('angebotDescription').focus();
                return;
            }
            
            if (!category || category === '') {
                alert('Bitte wähle eine Kategorie');
                document.getElementById('angebotCategory').focus();
                return;
            }
            
            if (!startDateTime || startDateTime.length === 0) {
                alert('Bitte geben Sie einen Start (Datum & Uhrzeit) ein');
                document.getElementById('angebotStartDateTime').focus();
                return;
            }
            
            if (!endDateTime || endDateTime.length === 0) {
                alert('Bitte geben Sie ein Ende (Datum & Uhrzeit) ein');
                document.getElementById('angebotEndDateTime').focus();
                return;
            }
            
            // Validiere das Format
            const startFormatMatch = startDateTime.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
            const endFormatMatch = endDateTime.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
            
            if (!startFormatMatch) {
                alert('Das Start (Datum & Uhrzeit) hat ein ungültiges Format. Bitte verwenden Sie das Format TT.MM.JJJJ HH:MM');
                return;
            }
            
            if (!endFormatMatch) {
                alert('Das Ende (Datum & Uhrzeit) hat ein ungültiges Format. Bitte verwenden Sie das Format TT.MM.JJJJ HH:MM');
                return;
            }
            
            // Prüfe ob Startdatum/Zeit in der Vergangenheit liegt
            const startDT = new Date(startDateTime);
            const now = new Date();
            if (isNaN(startDT.getTime())) {
                alert('Das Start (Datum & Uhrzeit) ist ungültig');
                return;
            }
            if (startDT < now) {
                alert('Das Start (Datum & Uhrzeit) darf nicht in der Vergangenheit liegen');
                return;
            }
            
            // Prüfe ob Enddatum/Zeit nach Startdatum/Zeit liegt
            const endDT = new Date(endDateTime);
            if (isNaN(endDT.getTime())) {
                alert('Das Ende (Datum & Uhrzeit) ist ungültig');
                return;
            }
            if (endDT <= startDT) {
                alert('Das Ende muss nach dem Start liegen');
                return;
            }
            
            // Wenn 0 eingegeben wurde, auf 1 setzen
            if (requiredPersons < 1) {
                document.getElementById('angebotRequiredPersons').value = 1;
                requiredPersons = 1;
            }
            
            if (requiredPersons > 100) {
                alert('Die Anzahl der benötigten Personen darf höchstens 100 sein');
                document.getElementById('angebotRequiredPersons').focus();
                return;
            }
            
            // Konvertiere datetime-local zu separaten date/time
            const startSplit = splitDateTime(startDateTime);
            const endSplit = splitDateTime(endDateTime);
            const startDate = startSplit.date;
            const startTime = startSplit.time;
            const endDate = endSplit.date;
            const endTime = endSplit.time;
            
            let address = '';
            let lat = null;
            let lng = null;
            
            // Adressvalidierung
            if (addressMode === 'auto') {
                address = document.getElementById('angebotAddress').value.trim();
                lat = selectedLat;
                lng = selectedLng;
                
                if (!address || address === '' || address === 'Klicken Sie auf die Karte') {
                    alert('Bitte wählen Sie einen Standort auf der Karte aus');
                    if (typeof L !== 'undefined' && addModalMapInstance) {
                        addModalMapInstance.invalidateSize();
                    }
                    return;
                }
                
                if (!lat || !lng || isNaN(lat) || isNaN(lng)) {
                    alert('Bitte wählen Sie einen gültigen Standort auf der Karte aus');
                    if (typeof L !== 'undefined' && addModalMapInstance) {
                        addModalMapInstance.invalidateSize();
                    }
                    return;
                }
            } else {
                const street = manualAddressFields['manualStreet'] ? manualAddressFields['manualStreet'].value.trim() : '';
                const houseNumber = manualAddressFields['manualHouseNumber'] ? manualAddressFields['manualHouseNumber'].value.trim() : '';
                const postcode = manualAddressFields['manualPostcode'] ? manualAddressFields['manualPostcode'].value.trim() : '';
                const city = manualAddressFields['manualCity'] ? manualAddressFields['manualCity'].value.trim() : '';
                
                if (!street || street.length === 0) {
                    alert('Bitte geben Sie eine Straße ein');
                    if (manualAddressFields['manualStreet']) {
                        manualAddressFields['manualStreet'].focus();
                    }
                    return;
                }
                
                if (!postcode || postcode.length === 0) {
                    alert('Bitte geben Sie eine Postleitzahl ein');
                    if (manualAddressFields['manualPostcode']) {
                        manualAddressFields['manualPostcode'].focus();
                    }
                    return;
                }
                
                if (!/^\d{5}$/.test(postcode)) {
                    alert('Die Postleitzahl muss aus 5 Ziffern bestehen');
                    if (manualAddressFields['manualPostcode']) {
                        manualAddressFields['manualPostcode'].focus();
                    }
                    return;
                }
                
                if (!city || city.length === 0) {
                    alert('Bitte geben Sie eine Stadt ein');
                    if (manualAddressFields['manualCity']) {
                        manualAddressFields['manualCity'].focus();
                    }
                    return;
                }
                
                address = [street, houseNumber, postcode, city].filter(Boolean).join(', ');
                
                geocodeAddress(address, title, description, category, startDate, startTime, endTime, endDate, requiredPersons);
                return;
            }
            
            submitForm(title, description, address, lat, lng, category, startDate, startTime, endTime, endDate, requiredPersons);
        });
    }
    
    function geocodeAddress(address, title, description, category, startDate, startTime, endTime, endDate, requiredPersons) {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    
                    submitForm(title, description, address, lat, lng, category, startDate, startTime, endTime, endDate, requiredPersons);
                } else {
                    alert('Adresse konnte nicht gefunden werden. Bitte überprüfen Sie die Eingabe.');
                }
            })
            .catch(error => {
                console.error('Geocoding error:', error);
                alert('Fehler beim Geocoding der Adresse');
            });
    }
    
    function submitForm(title, description, address, lat, lng, category, startDate, startTime, endTime, endDate, requiredPersons) {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('description', description);
        formData.append('category', category);
        formData.append('startDate', startDate);
        formData.append('startTime', startTime);
        formData.append('endTime', endTime);
        formData.append('endDate', endDate);
        formData.append('required_persons', requiredPersons);
        formData.append('address', address);
        formData.append('lat', lat);
        formData.append('lng', lng);
        
        for (let i = 0; i < uploadedFiles.length; i++) {
            formData.append('files[]', uploadedFiles[i]);
        }
        
        const basePath = typeof getBasePath === 'function' ? getBasePath() : '';
        fetch(basePath + 'api/add-angebot.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal();
                // Lade Angebote neu - prüfe ob Funktion verfügbar ist
                // Die loadAngebote Funktion ist im angebote-karte.js definiert
                // Versuche sie über das globale Window-Objekt oder direkt aufzurufen
                setTimeout(function() {
                    // Prüfe verschiedene Möglichkeiten, die Funktion zu finden
                    if (typeof window.loadAngebote === 'function') {
                        window.loadAngebote();
                    } else {
                        // Fallback: Seite neu laden, um die neuen Angebote zu sehen
                        location.reload();
                    }
                }, 100);
            } else {
                alert('Fehler: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
                alert('Fehler beim Senden der Hilfsanfrage');
        });
    }
});

