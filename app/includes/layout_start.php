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
if (hasPermission('customers', 'view')) $navItems['customers.php'] = 'Customers';
if (hasPermission('suppliers', 'view')) $navItems['suppliers.php'] = 'Suppliers';
if (hasPermission('reports', 'view')) $navItems['reports.php'] = 'Reports';
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
    <div class="md:flex md:min-h-screen">
        <!-- Mobile hamburger toggle + backdrop. Hidden on md+ where the
             sidebar is a normal flex child. -->
        <button id="navToggle" type="button" aria-label="Open navigation" class="md:hidden fixed top-3 left-3 z-40 inline-flex items-center justify-center w-10 h-10 rounded-md bg-brand-dark text-white shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 4a1 1 0 100 2h12a1 1 0 100-2H4z" clip-rule="evenodd"/></svg>
        </button>
        <div id="navBackdrop" class="hidden md:hidden fixed inset-0 bg-black/40 z-30"></div>

        <aside id="sideNav" class="w-60 shrink-0 bg-brand-dark text-white flex flex-col fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform md:relative md:translate-x-0">
            <div class="px-5 py-5 border-b border-white/10">
                <span class="font-bold text-lg">Creative Printers</span>
                <div class="mt-2 text-xs text-white/60">
                    Logged in as <span class="font-semibold text-white"><?= htmlspecialchars(currentUser()['username'] ?? '') ?></span>
                    <span class="text-white/40">(<?= htmlspecialchars(currentUser()['role_name'] ?? '') ?>)</span>
                </div>
                <a href="logout.php" class="mt-3 flex items-center gap-1 text-xs font-semibold text-white/70 hover:text-white transition-colors">&larr; Log Out</a>
            </div>
            <nav class="flex-1 py-3 overflow-y-auto">
                <?php foreach ($navItems as $navFile => $navLabel): ?>
                    <a href="<?= htmlspecialchars($navFile) ?>" class="block px-5 py-2.5 text-sm <?= $currentFile === $navFile ? 'font-semibold bg-brand-green text-white' : 'font-medium text-white/80 hover:bg-white/10 hover:text-white transition-colors' ?>"><?= htmlspecialchars($navLabel) ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="flex-1 p-4 pt-16 md:p-6 md:pt-6">
            <?php if ($heading !== ''): ?>
                <h2 class="text-2xl font-bold text-brand-dark mb-6 text-center"><?= htmlspecialchars($heading) ?></h2>
            <?php endif; ?>
            <?php if (!empty($message)): ?><div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
