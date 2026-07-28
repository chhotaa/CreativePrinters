<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';
requireLogin();
logActivity('logout', 'Logged out.');
endSession();
header('Location: login.php');
exit;
