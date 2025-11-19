
<?php
require_once 'config.php';

// Set user as offline before destroying session
if (isset($_SESSION['user_id'])) {
    setUserOnlineStatus($_SESSION['user_id'], 'offline');
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>
