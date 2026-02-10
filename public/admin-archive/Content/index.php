<?php
require_once dirname(__DIR__, 2) . '/_guard.php';
$root = dirname(__DIR__, 2);
require_once $root.'/lib/bootstrap.php';

use NGN\Lib\Env;
use NGN\Lib\Config;

if (!class_exists('NGN\\Lib\\Env')) { exit('Bootstrap failed'); }
Env::load($root);
$cfg = new Config();

$pageTitle = 'Content Management';
$currentPage = 'content';
include dirname(__DIR__) . '/_header.php';
include dirname(__DIR__) . '/_topbar.php';
?>

<section class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Content Management</h1>
        <p class="text-gray-600 dark:text-gray-400">Manage all platform content and user-generated materials</p>
    </div>

    <!-- Content Categories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        <!-- Posts -->
        <a href="/admin/Content/posts.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Posts</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Blog & news content</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Create and manage platform posts</p>
        </a>

        <!-- Videos -->
        <a href="/admin/Content/videos.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Videos</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Video content</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage video uploads and metadata</p>
        </a>

        <!-- Artists -->
        <a href="/admin/Content/artists.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Artists</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Artist profiles</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Review and manage artist accounts</p>
        </a>

        <!-- Labels -->
        <a href="/admin/Content/labels.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Labels</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Record labels</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage label organizations</p>
        </a>

        <!-- Venues -->
        <a href="/admin/Content/venues.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-orange-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m-1 4h1M9 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Venues</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Performance venues</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage venue listings</p>
        </a>

        <!-- Stations -->
        <a href="/admin/Content/stations.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Stations</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Radio stations</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage station content</p>
        </a>

        <!-- Pages -->
        <a href="/admin/Content/pages.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Pages</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Static pages</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage informational pages</p>
        </a>

        <!-- Ads -->
        <a href="/admin/Content/ads.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-pink-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.961 1.961 0 01-2.404.434A1.961 1.961 0 018 18.239V5.882m13 0A8.004 8.004 0 005.882 3c-1.68 0-3.257.529-4.573 1.409m15.591 0A8.002 8.002 0 005.091 3H15m0 0h3c.563 0 1.07.206 1.466.547m-9 0a8.001 8.001 0 01-2.467-.547"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Ads</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Advertisements</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage ad campaigns</p>
        </a>

        <!-- Claims -->
        <a href="/admin/Content/claims.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-yellow-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Claims</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Artist claims</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Review profile claims</p>
        </a>

        <!-- Donations -->
        <a href="/admin/Content/donations.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-rose-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Donations</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Fan donations</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Monitor donation activity</p>
        </a>

        <!-- Orders -->
        <a href="/admin/Content/orders.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Orders</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Customer orders</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage customer orders</p>
        </a>

        <!-- Products -->
        <a href="/admin/Content/products.php" class="rounded-lg border border-gray-200 dark:border-white/10 p-6 bg-white/70 dark:bg-white/5 hover:bg-white/80 dark:hover:bg-white/10 transition cursor-pointer">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-teal-500/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold">Products</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Merchandise items</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Manage product catalog</p>
        </a>
    </div>
</section>

<?php include dirname(__DIR__) . '/_footer.php'; ?>
