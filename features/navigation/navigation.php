<?php
$isLoggedIn = false;
$isGuest = false;
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_guest'])) {
        $isGuest = true;
    } else {
        $isLoggedIn = true;
    }
}
?>

<?php
// basePath sollte bereits von header.php gesetzt sein
// Falls nicht gesetzt, verwende leeren String als Fallback
if (!isset($basePath)) {
    $basePath = '';
}
?>
<nav class="navigation-bar">
    <div class="nav-logo">
        <a href="<?php echo $basePath; ?>index.php">
            <img src="<?php echo $basePath; ?>assets/images/logo.png" alt="NeighborNet Logo" class="logo-img">
        </a>
    </div>
    
    <div class="nav-right">
        <div class="nav-menu">
            <a href="<?php echo $basePath; ?>index.php" class="nav-link">Home</a>
            <a href="<?php echo $basePath; ?>qa.php" class="nav-link">Q&A</a>
            <a href="<?php echo $basePath; ?>angebote-karte.php" class="nav-link">Anfragen & Karte</a>
            <a href="<?php echo $basePath; ?>flohmarkt.php" class="nav-link">Flohmarkt</a>
        </div>
    </div>
</nav>


