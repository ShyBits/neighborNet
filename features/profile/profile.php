<?php
if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    $redirectPath = 'index.php';
    if (isset($_SERVER['PHP_SELF'])) {
        $scriptPath = $_SERVER['PHP_SELF'];
        $scriptFile = basename($scriptPath);
        $rootFiles = ['index.php', 'qa.php', 'angebote-karte.php', 'flohmarkt.php', 'profile.php', 'finanzen.php'];
        
        if (!in_array($scriptFile, $rootFiles)) {
            $scriptDir = dirname($scriptPath);
            $dirPath = trim($scriptDir, '/');
            if (!empty($dirPath)) {
                $depth = substr_count($dirPath, '/') + 1;
                $redirectPath = str_repeat('../', $depth) . 'index.php';
            }
        }
    }
    header('Location: ' . $redirectPath);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Benutzer';
$userAvatar = $_SESSION['user_avatar'] ?? 'assets/images/profile-placeholder.svg';
?>

<div class="profile-container">
    <div class="profile-banner">
        <div class="profile-banner-content">
            <div class="profile-avatar-wrapper">
                <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Profilbild" class="profile-avatar-img">
            </div>
        </div>
    </div>
    
    <div class="profile-content">
        <div class="profile-stats">
            <div class="profile-stat-item">
                <div class="profile-stat-value" id="laufendeAnfragen">0</div>
                <div class="profile-stat-label">Laufende Anfragen</div>
            </div>
            <div class="profile-stat-item">
                <div class="profile-stat-value" id="amHelfen">0</div>
                <div class="profile-stat-label">Am helfen</div>
            </div>
            <div class="profile-stat-item">
                <div class="profile-stat-value" id="geholfen">0</div>
                <div class="profile-stat-label">Geholfen</div>
            </div>
        </div>
        
        <div class="profile-badges">
            <h3 class="profile-section-title">Badges</h3>
            <div class="badges-grid" id="badgesGrid">
            </div>
        </div>
        
        <div class="profile-comments">
            <h3 class="profile-section-title">Kommentare</h3>
            <div class="comments-list" id="commentsList">
            </div>
        </div>
    </div>
</div>

<script src="<?php echo isset($basePath) ? $basePath : ''; ?>features/profile/profile.js"></script>




