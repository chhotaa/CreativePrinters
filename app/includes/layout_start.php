<?php
require_once __DIR__ . '/flash.php';
[$flashMessage, $flashError] = consumeFlashMessages();
$message = (isset($message) && $message !== '') ? $message : $flashMessage;
$error = (isset($error) && $error !== '') ? $error : $flashError;

$currentFile = basename($_SERVER['SCRIPT_NAME']);

// Nav is permission-driven: a module link only appears if the viewer's
// role has at least View on that module. Users/Roles stay Super-Admin-only.
$navItems = ['index.php' => 'Dashboard'];
if (hasPermission('stock', 'view')) $navItems['stock.php'] = 'Stock';
if (hasPermission('purchase_orders', 'view')) $navItems['purchase_orders.php'] = 'Purchase Orders';
if (hasPermission('deliveries', 'view')) $navItems['deliveries.php'] = 'Delivery Schedule';
if (hasPermission('restock_orders', 'view')) $navItems['restock_orders.php'] = 'Restock Orders';
if (hasPermission('job_cards', 'view')) $navItems['job_cards.php'] = 'Job Cards';
if (hasPermission('activity_log', 'view')) $navItems['activity_log.php'] = 'Activity Log';
if (currentUser()['role_name'] === 'Super Admin') {
    $navItems['users.php'] = 'Users';
    $navItems['roles.php'] = 'Roles & Permissions';
}
$navItems['change_password.php'] = 'Change Password';
$heading = $pageHeading ?? ($pageTitle ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Creative Printers') ?> - Creative Printers</title>
    <?php include __DIR__ . '/tailwind_head.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800">
    <div class="flex min-h-screen">
        <aside class="w-60 shrink-0 bg-brand-dark text-white flex flex-col">
            <div class="px-5 py-5 border-b border-white/10">
                <span class="font-bold text-lg">Creative Printers</span>
                <a href="logout.php" class="mt-3 flex items-center gap-1 text-xs font-semibold text-white/70 hover:text-white transition-colors">&larr; Log Out</a>
            </div>
            <nav class="flex-1 py-3">
                <?php foreach ($navItems as $navFile => $navLabel): ?>
                    <a href="<?= htmlspecialchars($navFile) ?>" class="block px-5 py-2.5 text-sm <?= $currentFile === $navFile ? 'font-semibold bg-brand-green text-white' : 'font-medium text-white/80 hover:bg-white/10 hover:text-white transition-colors' ?>"><?= htmlspecialchars($navLabel) ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="flex-1 p-6">
            <?php if ($heading !== ''): ?>
                <h2 class="text-2xl font-bold text-brand-dark mb-6 text-center"><?= htmlspecialchars($heading) ?></h2>
            <?php endif; ?>
            <?php if (!empty($message)): ?><div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
