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
if (hasPermission('deliveries', 'view')) $navItems['delivered_items.php'] = 'Delivered Items';
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

// Inline SVG icons for the sidebar (heroicons-style, 20x20, currentColor
// so they inherit the anchor's text color). Any nav item without a match
// here still renders — just without an icon.
$navIcons = [
    'index.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>',
    'stock.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.559-.5-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.559.499.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z"/></svg>',
    'purchase_orders.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>',
    'deliveries.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path d="M6.5 3c-1.051 0-2.093.04-3.125.117A1.49 1.49 0 002 4.607V10.5h9V4.606c0-.771-.59-1.43-1.375-1.489A41.568 41.568 0 006.5 3zM2 12v2.5A1.5 1.5 0 003.5 16h.041a3 3 0 015.918 0h.791a.75.75 0 00.75-.75V12H2z"/><path d="M12 6.75V16h.75a.75.75 0 00.75-.75V12h4V6.75A.75.75 0 0016.75 6h-4a.75.75 0 00-.75.75z"/><path d="M6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM14 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>',
    'delivered_items.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M6.32 2.577a49.255 49.255 0 017.36 0 1.5 1.5 0 011.36 1.494V16A.75.75 0 0114 16v-.75a.5.5 0 00-.5-.5H6.5a.5.5 0 00-.5.5V16a.75.75 0 01-1.04.694 1.5 1.5 0 01-.96-1.379V4.07a1.5 1.5 0 011.32-1.494zM12.28 8.72a.75.75 0 10-1.06-1.06L9 9.879 8.28 9.16a.75.75 0 10-1.06 1.061l1.25 1.25a.75.75 0 001.06 0l2.75-2.75z" clip-rule="evenodd"/></svg>',
    'restock_orders.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd"/></svg>',
    'job_cards.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M7.84 1.804A1 1 0 018.82 1h2.36a1 1 0 01.98.804l.331 1.652a6.993 6.993 0 011.929 1.115l1.598-.54a1 1 0 011.186.447l1.18 2.044a1 1 0 01-.205 1.251l-1.267 1.113a7.047 7.047 0 010 2.228l1.267 1.113a1 1 0 01.206 1.25l-1.18 2.045a1 1 0 01-1.187.447l-1.598-.54a6.993 6.993 0 01-1.929 1.115l-.33 1.652a1 1 0 01-.98.804H8.82a1 1 0 01-.98-.804l-.331-1.652a6.993 6.993 0 01-1.929-1.115l-1.598.54a1 1 0 01-1.186-.447l-1.18-2.044a1 1 0 01.205-1.251l1.267-1.114a7.05 7.05 0 010-2.227L1.821 7.773a1 1 0 01-.206-1.25l1.18-2.045a1 1 0 011.187-.447l1.598.54A6.993 6.993 0 017.51 3.456l.33-1.652zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>',
    'customers.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>',
    'suppliers.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M4 16.5v-13h-.25a.75.75 0 010-1.5h12.5a.75.75 0 010 1.5H16v13h.25a.75.75 0 010 1.5h-3.5a.75.75 0 01-.75-.75v-2.5a.75.75 0 00-.75-.75h-2.5a.75.75 0 00-.75.75v2.5a.75.75 0 01-.75.75h-3.5a.75.75 0 010-1.5H4zm3-11a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-1zm4.5-.5a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1zM7 8.5a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-1zm4.5-.5a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1zM7 11.5a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v1a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-1zm4.5-.5a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1z" clip-rule="evenodd"/></svg>',
    'reports.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path d="M15.5 2A1.5 1.5 0 0014 3.5v13a1.5 1.5 0 001.5 1.5h1a1.5 1.5 0 001.5-1.5v-13A1.5 1.5 0 0016.5 2h-1zM9.5 6A1.5 1.5 0 008 7.5v9A1.5 1.5 0 009.5 18h1a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0010.5 6h-1zM3.5 10A1.5 1.5 0 002 11.5v5A1.5 1.5 0 003.5 18h1A1.5 1.5 0 006 16.5v-5A1.5 1.5 0 004.5 10h-1z"/></svg>',
    'activity_log.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .2.08.4.22.53l3 3a.75.75 0 101.06-1.06l-2.78-2.78V5z" clip-rule="evenodd"/></svg>',
    'users.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z"/></svg>',
    'roles.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"/></svg>',
    'change_password.php' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 shrink-0"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Creative Printers') ?> - Creative Printers</title>
    <?php include __DIR__ . '/tailwind_head.php'; ?>
</head>
<body class="app-bg text-slate-800">
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
                    <a href="<?= htmlspecialchars($navFile) ?>" class="flex items-center gap-3 px-5 py-2.5 text-sm <?= $currentFile === $navFile ? 'font-semibold bg-brand-green text-white' : 'font-medium text-white/80 hover:bg-white/10 hover:text-white transition-colors' ?>">
                        <?= $navIcons[$navFile] ?? '<span class="w-5 h-5 shrink-0"></span>' ?>
                        <span><?= htmlspecialchars($navLabel) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="flex-1 p-4 pt-16 md:p-6 md:pt-6">
            <?php if ($heading !== ''): ?>
                <h2 class="text-2xl font-bold text-white mb-6 text-center drop-shadow-md"><?= htmlspecialchars($heading) ?></h2>
            <?php endif; ?>
            <?php if (!empty($message)): ?><div class="text-green-700 text-sm bg-green-50 border border-green-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded-md px-3 py-2 mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
