<?php
require_once '../database/config.php';

$logout_actor = database_activity_actor_from_session();
if ((int) ($logout_actor['id'] ?? 0) > 0) {
    database_log_activity($conn, $logout_actor, 'Logout', 'Successful logout.', 'Success');
}

unset($_SESSION['shoestagram_pending_registration']);
session_destroy();
$redirect = trim((string) ($_GET['redirect'] ?? 'login.php'));

if ($redirect === '' || strpos($redirect, '://') !== false || strpos($redirect, "\n") !== false || strpos($redirect, "\r") !== false) {
    $redirect = 'login.php';
}

header('Location: ' . $redirect);
exit();
?>
