<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';
requireLogin();
logActivity('logout', 'Logged out.');
session_unset();
session_destroy();
header('Location: login.php');
exit;
