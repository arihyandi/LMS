<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    verify_csrf();
    $_SESSION = [];
    session_destroy();
}
redirect('login.php');
