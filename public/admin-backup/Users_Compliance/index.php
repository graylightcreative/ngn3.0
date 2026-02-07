<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();

$pageTitle = 'Users & Compliance';
$currentPage = 'users_compliance';
include dirname(__DIR__) . '/_header.php';
include dirname(__DIR__) . '/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Users & Compliance</h1>
        <p class="text-gray-600 dark:text-gray-400">Manage user accounts, legal compliance, and platform safety</p>
    </div>

    <!-- User & Compliance Categories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        <!-- Users -->
        <a href="/admin/Users_Compliance/users.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM15 20h7v-2a6 6 0 00-9-5.656V9a2 2 0 11-4 0v.5"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Users</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Account management</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">View and manage user accounts</p>
        </a>

        <!-- Contacts -->
        <a href="/admin/Users_Compliance/contacts.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Contacts</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Contact forms & inquiries</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage user inquiries and contacts</p>
        </a>

        <!-- Takedown Requests -->
        <a href="/admin/Users_Compliance/takedown_requests.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Takedown Requests</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">DMCA & removals</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Handle DMCA and removal requests</p>
        </a>

        <!-- Counter Notices -->
        <a href="/admin/Users_Compliance/counter_notice.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Counter Notices</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Appeal responses</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Review counter-notice submissions</p>
        </a>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-12">
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Active Users</div>
            <div class="text-3xl font-bold">—</div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Total platform users</p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Pending Requests</div>
            <div class="text-3xl font-bold">—</div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">DMCA & takedown requests</p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Support Tickets</div>
            <div class="text-3xl font-bold">—</div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Unresolved inquiries</p>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/_footer.php'; ?>
