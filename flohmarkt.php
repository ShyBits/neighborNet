<?php
$pageTitle = 'Flohmarkt';
include 'includes/header.php';
?>
    <div class="top-container">
        <div class="top-container-left">
            <?php include 'features/navigation/navigation.php'; ?>
            <?php
            $isLoggedIn = isset($_SESSION['user_id']) && !isset($_SESSION['is_guest']);
            if ($isLoggedIn):
            ?>
            <button class="chat-toggle-btn" id="chatToggleBtn" aria-label="Chat öffnen">
                <svg class="chat-icon-outline" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <svg class="chat-icon-filled" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </button>
            <?php endif; ?>
        </div>
        <?php include 'features/navigation/user-actions.php'; ?>
    </div>

    <?php include 'features/auth/auth-modal.php'; ?>

    <div class="flohmarkt-container">
        <div class="construction-design">
            <div class="construction-stripes">
                <div class="stripe stripe-1"></div>
                <div class="stripe stripe-2"></div>
                <div class="stripe stripe-3"></div>
                <div class="stripe stripe-4"></div>
                <div class="stripe stripe-5"></div>
                <div class="stripe stripe-6"></div>
                <div class="stripe stripe-7"></div>
                <div class="stripe stripe-8"></div>
            </div>
            <div class="coming-soon-content">
                <h1 class="coming-soon-text">Coming Soon...</h1>
                <p class="coming-soon-subtitle">Der Flohmarkt ist in Vorbereitung</p>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

