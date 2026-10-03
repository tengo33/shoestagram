<?php
$redirect = trim((string) ($_GET['redirect'] ?? '../staff/dashboard.php'));

if ($redirect === '' || strpos($redirect, '://') !== false || strpos($redirect, "\n") !== false || strpos($redirect, "\r") !== false) {
    $redirect = '../staff/dashboard.php';
}

header('Location: login.php?redirect=' . urlencode($redirect));
exit();
?>
