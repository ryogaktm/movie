<?php
require __DIR__ . '/lib.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($_POST['csrf'] ?? null);
    $_SESSION = [];
    session_destroy();
}
header('Location: login.php');
