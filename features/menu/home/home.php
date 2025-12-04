<?php
// basePath sollte bereits von header.php gesetzt sein
// Falls nicht gesetzt, verwende leeren String als Fallback
if (!isset($basePath)) {
    $basePath = '';
}
?>

<div class="home-banner">
    <div class="banner-overlay"></div>
    <img src="<?php echo $basePath; ?>assets/images/white_logo.png" alt="NeighborNet Logo" class="banner-logo-background">
    <div class="banner-content">
        <div class="banner-left">
            <div class="banner-badge">Willkommen</div>
            <h1 class="banner-title">
                <span class="banner-title-line">Ihre Nachbarschaft</span>
                <span class="banner-title-brand">gemeinsam stärker</span>
            </h1>
            <p class="banner-description">
                Verbinden Sie sich mit Ihrer Nachbarschaft, finden Sie Hilfe oder bieten Sie Ihre an. 
                Teil unserer wachsenden Gemeinschaft für lokale Unterstützung und direkten Austausch.
            </p>
            <div class="banner-stats">
                <div class="banner-stat-card">
                    <div class="banner-stat-number" id="bannerUserCount">0</div>
                    <div class="banner-stat-label">einmalige Nutzer</div>
                    <button class="banner-cta-btn" id="bannerCtaBtn">Jetzt mitmachen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="home-presentation">
    <section class="presentation-slide slide-features">
        <div class="slide-content">
            <h2 class="slide-title">Was wir bieten</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </div>
                    <h3 class="feature-title">Lokale Angebote</h3>
                    <p class="feature-text">Entdecken Sie Angebote in Ihrer Nachbarschaft</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="feature-title">Direkter Chat</h3>
                    <p class="feature-text">Kommunizieren Sie direkt mit Ihren Nachbarn</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h3 class="feature-title">Gemeinschaft</h3>
                    <p class="feature-text">Stärken Sie Ihre Nachbarschaft</p>
                </div>
            </div>
        </div>
    </section>

    <section class="presentation-slide slide-statistics">
        <div class="statistics-circle-bg"></div>
        <div class="slide-content">
            <h2 class="slide-title">Unsere Zahlen</h2>
            <div class="statistics-grid">
                <div class="stat-card">
                    <div class="stat-value" id="statUsers">0</div>
                    <div class="stat-label">Aktive Nutzer</div>
                    <div class="stat-growth" id="statUserGrowth">
                        <span class="growth-icon">↑</span>
                        <span class="growth-value">+0%</span>
                        <span class="growth-text">im letzten Monat</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="statOffers">0</div>
                    <div class="stat-label">Verfügbare Angebote</div>
                    <div class="stat-growth" id="statOfferGrowth">
                        <span class="growth-icon">↑</span>
                        <span class="growth-value">+0%</span>
                        <span class="growth-text">im letzten Monat</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="statMessages">0</div>
                    <div class="stat-label">Gesendete Nachrichten</div>
                    <div class="stat-growth">
                        <span class="growth-text">Kommunikation in der Gemeinschaft</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="presentation-slide slide-how">
        <div class="slide-content">
            <h2 class="slide-title">So funktioniert es</h2>
            <div class="steps-container">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Registrieren</h3>
                    <p class="step-text">Erstellen Sie kostenlos ein Konto</p>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <h3 class="step-title">Angebote durchsuchen</h3>
                    <p class="step-text">Finden Sie Hilfe oder bieten Sie Ihre an</p>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Kontakt aufnehmen</h3>
                    <p class="step-text">Chatten Sie direkt mit anderen Nutzern</p>
                </div>
                <div class="step-item">
                    <div class="step-number">4</div>
                    <h3 class="step-title">Gemeinsam helfen</h3>
                    <p class="step-text">Unterstützen Sie sich gegenseitig</p>
                </div>
            </div>
        </div>
    </section>

    <section class="presentation-slide slide-cta">
        <div class="slide-content">
            <h2 class="slide-title">Bereit loszulegen?</h2>
            <p class="slide-subtitle">Werden Sie Teil der NeighborNet Gemeinschaft</p>
            <div class="cta-buttons">
                <a href="<?php echo $basePath; ?>qa.php" class="cta-btn cta-secondary">Mehr erfahren</a>
                <button class="cta-btn cta-primary" id="homeRegisterBtn">Jetzt registrieren</button>
            </div>
        </div>
    </section>
</div>


