<!-- Finanzen Button (Global) -->
<button class="finanzen-open-btn" id="finanzenOpenBtn">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
    </svg>
    Unsere Finanzen
</button>

<!-- Finanzen Modal -->
<div class="finanzen-modal-overlay" id="finanzenModalOverlay">
    <div class="finanzen-modal-container" id="finanzenModalContainer">
        <button class="finanzen-modal-close" id="finanzenModalClose">×</button>
        
        <!-- A4 Papier Blätter -->
        <div class="finanzen-papers-container" id="finanzenPapersContainer">
            <!-- Papier 1: Personalausgaben -->
            <div class="finanzen-paper a4-paper" data-paper-index="0">
                <div class="finanzen-paper-content">
                    <h2>Personalausgaben 2024</h2>
                    <div class="finanzen-content-section">
                        <h3>Monatliche Gehälter</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Geschäftsführung (1 Person):</span>
                                <span class="finanzen-value">€4.200,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Entwickler Vollzeit (2 Personen):</span>
                                <span class="finanzen-value">€7.600,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Entwickler Teilzeit (1 Person):</span>
                                <span class="finanzen-value">€2.400,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">UX/UI Designer (1 Person):</span>
                                <span class="finanzen-value">€3.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Community Manager (1 Person):</span>
                                <span class="finanzen-value">€2.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Marketing Manager (1 Person):</span>
                                <span class="finanzen-value">€3.200,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Buchhaltung & Verwaltung:</span>
                                <span class="finanzen-value">€1.200,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Sozialabgaben (ca. 20%):</span>
                                <span class="finanzen-value">€4.520,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt monatlich:</span>
                                <span class="finanzen-value">€29.720,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt jährlich:</span>
                                <span class="finanzen-value">€356.640,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Papier 2: IT & Technische Infrastruktur -->
            <div class="finanzen-paper a4-paper" data-paper-index="1">
                <div class="finanzen-paper-content">
                    <h2>IT & Infrastruktur 2024</h2>
                    <div class="finanzen-content-section">
                        <h3>Monatliche Ausgaben</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Cloud-Hosting (AWS/Server):</span>
                                <span class="finanzen-value">€420,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Domain & SSL-Zertifikate:</span>
                                <span class="finanzen-value">€45,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">CDN & Traffic-Kosten:</span>
                                <span class="finanzen-value">€180,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Backup & Datenbank:</span>
                                <span class="finanzen-value">€120,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Software-Lizenzen:</span>
                                <span class="finanzen-value">€350,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Monitoring & Sicherheit:</span>
                                <span class="finanzen-value">€150,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Entwicklungstools:</span>
                                <span class="finanzen-value">€280,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt monatlich:</span>
                                <span class="finanzen-value">€1.545,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt jährlich:</span>
                                <span class="finanzen-value">€18.540,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Papier 3: Marketing & Werbung -->
            <div class="finanzen-paper a4-paper" data-paper-index="2">
                <div class="finanzen-paper-content">
                    <h2>Marketing & Werbung 2024</h2>
                    <div class="finanzen-content-section">
                        <h3>Monatliche Ausgaben</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Google Ads:</span>
                                <span class="finanzen-value">€2.500,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Social Media Marketing:</span>
                                <span class="finanzen-value">€1.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Content Marketing:</span>
                                <span class="finanzen-value">€1.200,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Print-Werbung & Flyer:</span>
                                <span class="finanzen-value">€800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">SEO & Analytics Tools:</span>
                                <span class="finanzen-value">€450,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Events & Messen:</span>
                                <span class="finanzen-value">€1.500,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt monatlich:</span>
                                <span class="finanzen-value">€8.250,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt jährlich:</span>
                                <span class="finanzen-value">€99.000,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Papier 4: Einnahmen & Finanzierung -->
            <div class="finanzen-paper a4-paper" data-paper-index="3">
                <div class="finanzen-paper-content">
                    <h2>Einnahmen & Finanzierung 2024</h2>
                    <div class="finanzen-content-section">
                        <h3>Monatliche Einnahmen</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Premium-Mitgliedschaften:</span>
                                <span class="finanzen-value positive">€8.500,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Basis-Mitgliedsbeiträge:</span>
                                <span class="finanzen-value positive">€4.200,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Spenden & Förderungen:</span>
                                <span class="finanzen-value positive">€6.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Öffentliche Fördermittel:</span>
                                <span class="finanzen-value positive">€12.000,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Kooperationspartner:</span>
                                <span class="finanzen-value positive">€3.500,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Werbung auf der Plattform:</span>
                                <span class="finanzen-value positive">€2.400,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt monatlich:</span>
                                <span class="finanzen-value positive">€37.400,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt jährlich:</span>
                                <span class="finanzen-value positive">€448.800,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Papier 5: Sonstige Betriebsausgaben -->
            <div class="finanzen-paper a4-paper" data-paper-index="4">
                <div class="finanzen-paper-content">
                    <h2>Betriebsausgaben 2024</h2>
                    <div class="finanzen-content-section">
                        <h3>Monatliche Ausgaben</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Büromiete & Nebenkosten:</span>
                                <span class="finanzen-value">€2.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Büroausstattung & Möbel:</span>
                                <span class="finanzen-value">€450,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Rechtsberatung & Steuerberater:</span>
                                <span class="finanzen-value">€1.200,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Versicherungen (Haftpflicht, etc.):</span>
                                <span class="finanzen-value">€580,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Telefon & Internet:</span>
                                <span class="finanzen-value">€280,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Reisekosten:</span>
                                <span class="finanzen-value">€850,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Fortbildung & Schulungen:</span>
                                <span class="finanzen-value">€620,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt monatlich:</span>
                                <span class="finanzen-value">€6.780,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamt jährlich:</span>
                                <span class="finanzen-value">€81.360,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Papier 6: Jahresbilanz 2024 -->
            <div class="finanzen-paper a4-paper" data-paper-index="5">
                <div class="finanzen-paper-content">
                    <h2>Jahresbilanz 2024</h2>
                    <div class="finanzen-content-section">
                        <h3>Einnahmen & Ausgaben</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Gesamteinnahmen:</span>
                                <span class="finanzen-value positive">€448.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Personalausgaben:</span>
                                <span class="finanzen-value negative">€356.640,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">IT & Infrastruktur:</span>
                                <span class="finanzen-value negative">€18.540,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Marketing & Werbung:</span>
                                <span class="finanzen-value negative">€99.000,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Betriebsausgaben:</span>
                                <span class="finanzen-value negative">€81.360,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Gesamtausgaben:</span>
                                <span class="finanzen-value negative">€555.540,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Jahresüberschuss/-fehlbetrag:</span>
                                <span class="finanzen-value negative">-€106.740,00</span>
                            </div>
                        </div>
                    </div>
                    <div class="finanzen-content-section">
                        <h3>Finanzierung des Fehlbetrags</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Anfangskapital 2024:</span>
                                <span class="finanzen-value">€180.000,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Investitionszuschüsse:</span>
                                <span class="finanzen-value positive">€85.000,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Kreditaufnahme:</span>
                                <span class="finanzen-value">€35.000,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Kapitalstand Ende 2024:</span>
                                <span class="finanzen-value">€193.260,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Papier 7: Gehaltsstruktur Detail -->
            <div class="finanzen-paper a4-paper" data-paper-index="6">
                <div class="finanzen-paper-content">
                    <h2>Gehaltsstruktur Detail</h2>
                    <div class="finanzen-content-section">
                        <h3>Vollzeit-Mitarbeiter (40h/Woche)</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Geschäftsführer:</span>
                                <span class="finanzen-value">€4.200,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Senior Full-Stack Entwickler (2x):</span>
                                <span class="finanzen-value">€3.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">UX/UI Designer:</span>
                                <span class="finanzen-value">€3.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Community Manager:</span>
                                <span class="finanzen-value">€2.800,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Marketing Manager:</span>
                                <span class="finanzen-value">€3.200,00</span>
                            </div>
                        </div>
                        <h3>Teilzeit-Mitarbeiter</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Junior Entwickler (20h/Woche):</span>
                                <span class="finanzen-value">€2.400,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Buchhaltung (10h/Woche):</span>
                                <span class="finanzen-value">€1.200,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Papier 8: Kostendeckung & Prognose -->
            <div class="finanzen-paper a4-paper" data-paper-index="7">
                <div class="finanzen-paper-content">
                    <h2>Kostendeckung & Prognose</h2>
                    <div class="finanzen-content-section">
                        <h3>Monatliche Kostendeckung</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Monatliche Einnahmen:</span>
                                <span class="finanzen-value positive">€37.400,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Monatliche Ausgaben:</span>
                                <span class="finanzen-value negative">€46.295,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Monatliches Defizit:</span>
                                <span class="finanzen-value negative">-€8.895,00</span>
                            </div>
                        </div>
                        <h3>Prognose 2025</h3>
                        <div class="finanzen-table">
                            <div class="finanzen-row">
                                <span class="finanzen-label">Erwartete Einnahmen:</span>
                                <span class="finanzen-value positive">€52.000,00</span>
                            </div>
                            <div class="finanzen-row">
                                <span class="finanzen-label">Erwartete Ausgaben:</span>
                                <span class="finanzen-value negative">€48.500,00</span>
                            </div>
                            <div class="finanzen-row finanzen-total">
                                <span class="finanzen-label">Erwarteter Überschuss:</span>
                                <span class="finanzen-value positive">€3.500,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


