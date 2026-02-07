<?php
/**
 * Master Layout
 * 
 * Provides global structure, assets, header, and footer.
 */

// Global Session Check for UI Elements
$isLoggedIn = isset($_SESSION['user_id']);
$tokenExpires = $_SESSION['token_expires'] ?? 0;
// Check if token is strictly valid in the future
$isSessionValid = $isLoggedIn && ($tokenExpires > time());
$canEdit = $isSessionValid; // Use this to control visibility of restricted UI elements
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'MonkeysCMS') ?></title>
    
    <!-- Global Meta -->
    @yield('meta')
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/app.css">
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="/css/custom.css">
    
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col pt-1">
    <div class="gradient-top"></div>

    <?php if ($canEdit): ?>
    <!-- Admin Bar -->
    <div class="fixed top-1 left-0 right-0 bg-slate-800 text-white text-xs py-2 px-4 flex justify-end gap-4 z-50">
        <?php if (isset($type_id, $item['id'])): ?>
        <a href="/admin/content/<?= $type_id ?>/<?= $item['id'] ?>/edit" class="text-slate-400 hover:text-white transition-colors">Edit</a>
        <?php endif; ?>
        <a href="/admin" class="text-slate-400 hover:text-white transition-colors">Admin</a>
    </div>
    <?php endif; ?>

    <!-- Site Header -->
    <header class="border-b border-gray-100 bg-white sticky top-0 z-40 backdrop-blur-md bg-white/90 <?= $canEdit ? 'mt-8' : '' ?>">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3 group">
                <img src="/image/monkeyscms-logo.png" alt="MonkeysCMS" class="h-8 w-auto group-hover:scale-105 transition-transform duration-200">
            </a>
            
            <nav class="hidden sm:flex items-center gap-6 text-sm font-medium text-slate-600">
                <a href="/" class="hover:text-amber-600 transition-colors">Home</a>
                <a href="/admin" class="hover:text-amber-600 transition-colors">Admin</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Injection -->
    @yield('content')

    <!-- Site Footer -->
    <footer class="border-t border-gray-100 bg-gray-50 py-12 mt-auto">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <div class="mb-4">
                <img src="/image/monkeyscms-logo.png" alt="MonkeysCMS" class="h-8 w-auto mx-auto grayscale opacity-50 hover:grayscale-0 hover:opacity-100 transition-all duration-300">
            </div>
            <p class="text-sm text-gray-500">
                &copy; <?= date('Y') ?> MonkeysCMS. All rights reserved.
            </p>
        </div>
    </footer>
    
    @stack('scripts')
</body>
</html>
