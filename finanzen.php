<?php
$pageTitle = 'Unsere Finanzen';
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

    <?php include 'features/menu/finanzen/finanzen.php'; ?>


<?php include 'includes/footer.php'; ?>

