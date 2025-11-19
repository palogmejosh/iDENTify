
<?php
require_once 'config.php';

// Redirect to dashboard if already logged in, otherwise to login
if (isAuthenticated()) {
    redirectToDashboard();
} else {
    header('Location: login.php');
    exit;
}
?>
