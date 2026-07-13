<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require __DIR__ . '/../includes/job_card_logic.php';

$pageTitle = 'Job Cards';
include __DIR__ . '/../includes/layout_start.php';
include __DIR__ . '/../includes/job_card_content.php';
include __DIR__ . '/../includes/layout_end.php';
