<?php
// Prüfe ob Karten-Einwilligung vorhanden ist
$hasConsent = isset($_COOKIE['map_consent']) && $_COOKIE['map_consent'] === 'accepted';
?>
<div class="add-modal-overlay" id="addModalOverlay">
    <div class="add-modal" id="addModal">
        <div class="add-modal-header">
            <h3>Hilfsanfrage hinzufügen</h3>
            <button class="add-modal-close" id="addModalClose">×</button>
        </div>
        
        <div class="add-modal-content">
            <div class="add-modal-form">
                <div class="form-group form-group-row">
                    <div class="form-group-third">
                        <label for="angebotTitle">Titel <span class="required-asterisk">*</span></label>
                        <input type="text" id="angebotTitle" placeholder="z.B. Gartenarbeit" required>
                    </div>
                    <div class="form-group-third">
                        <label for="angebotCategory">Kategorie <span class="required-asterisk">*</span></label>
                        <select id="angebotCategory" required>
                            <option value="">Wähle eine Kategorie</option>
                            <option value="gartenarbeit">Gartenarbeit</option>
                            <option value="haushalt">Haushalt</option>
                            <option value="umzug">Umzug</option>
                            <option value="reparatur">Reparatur</option>
                            <option value="betreuung">Betreuung</option>
                            <option value="einkauf">Einkauf</option>
                            <option value="sonstiges">Sonstiges</option>
                        </select>
                    </div>
                    <div class="form-group-third form-group-narrow">
                        <label for="angebotRequiredPersons">Benötigte Personen</label>
                        <input type="number" id="angebotRequiredPersons" min="1" max="100" value="1" placeholder="z.B. 2">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="angebotDescription">Beschreibung <span class="required-asterisk">*</span></label>
                    <textarea id="angebotDescription" rows="4" placeholder="Beschreiben Sie Ihre Hilfsanfrage..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Zeitraum (Datum & Uhrzeit) <span class="required-asterisk">*</span></label>
                    <div class="datetime-range-container">
                        <div class="datetime-range-item">
                            <span class="datetime-range-label">Von</span>
                            <input type="datetime-local" id="angebotStartDateTime" class="datetime-input" required>
                        </div>
                        <div class="datetime-range-separator">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </div>
                        <div class="datetime-range-item">
                            <span class="datetime-range-label">Bis</span>
                            <input type="datetime-local" id="angebotEndDateTime" class="datetime-input" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Adresse <span class="required-asterisk">*</span></label>
                    <div class="address-mode-selector">
                        <span class="address-mode-label" id="addressModeLabel">Auto Auswahl</span>
                        <button type="button" class="address-toggle-btn active" id="addressToggleBtn">
                            <span class="address-toggle-slider"></span>
                        </button>
                    </div>
                    
                    <div class="address-auto-container" id="addressAutoContainer">
                        <input type="text" id="angebotAddress" placeholder="Klicken Sie auf die Karte" readonly required>
                    </div>
                    
                    <div class="address-manual-container" id="addressManualContainer" style="display: none;">
                        <div class="address-manual-fields" id="addressManualFields">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Dateien (max. 5)</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="angebotFileInput" multiple accept="image/*">
                        <div class="file-upload-content">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <p class="file-upload-text">Dateien hierher ziehen oder klicken zum Auswählen</p>
                            <p class="file-upload-hint">Maximal 5 Bilder</p>
                        </div>
                        <div class="file-preview-container" id="filePreviewContainer"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button class="form-cancel-btn" id="formCancelBtn">Abbrechen</button>
                    <button class="form-submit-btn" id="formSubmitBtn">Hinzufügen</button>
                </div>
            </div>
            
            <div class="add-modal-map">
                <?php if ($hasConsent): ?>
                    <div id="addModalMap" class="add-modal-map-container"></div>
                <?php else: ?>
                    <div class="add-modal-map-consent">
                        <div class="map-consent-message">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <h3>Karten-Einwilligung erforderlich</h3>
                            <p>Um die Karte zur Standortauswahl zu nutzen, benötigen wir Ihre Einwilligung zur Anzeige von Kartenmaterial.</p>
                            <button type="button" class="map-consent-accept-btn" id="addModalConsentBtn">Einwilligen</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

