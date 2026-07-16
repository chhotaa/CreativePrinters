<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require __DIR__ . '/includes/change_password_logic.php';

$pageTitle = 'Change Password';
include __DIR__ . '/includes/layout_start.php';
?>
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 p-5 mb-5 max-w-md mx-auto">
        <form method="POST" class="space-y-3">
            <input type="password" name="current_password" placeholder="Current password" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="password" name="new_password" placeholder="New password (min. 6 characters)" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <input type="password" name="confirm_password" placeholder="Confirm new password" required class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-brand-green focus:border-brand-green">
            <button type="submit" name="change_password" value="1" class="w-full px-4 py-2 rounded-md bg-brand-green text-white text-sm font-semibold hover:bg-brand-greendark transition-colors cursor-pointer">Change Password</button>
        </form>
    </div>
<?php include __DIR__ . '/includes/layout_end.php'; ?>
