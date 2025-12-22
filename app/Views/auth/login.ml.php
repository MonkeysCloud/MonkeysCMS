<?php
$title = 'Login - MonkeysCMS';
$heading = 'Welcome Back';
$subheading = 'Sign in to access your dashboard.';
include ML_BASE_PATH . '/app/Views/layouts/header.ml.php';
?>

<form action="/login" method="POST" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
    
    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="p-4 rounded-lg bg-green-50 text-green-700 border border-green-200 text-sm">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="space-y-5">
        <div class="input-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="input-field" placeholder="you@example.com" required autofocus>
        </div>

        <div class="input-group">
             <div class="flex justify-between items-center mb-1.5">
                <label for="password" class="!mb-0">Password</label>
                <a href="/password/forgot" class="text-sm link">Forgot password?</a>
            </div>
            <input type="password" id="password" name="password" class="input-field" placeholder="••••••••" required>
        </div>
        
        <div class="flex items-center">
            <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
            <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
        </div>
    </div>

    <div class="pt-2">
         <button type="submit" class="w-full btn-primary shadow-lg shadow-orange-500/20 text-sm flex justify-center py-2.5">
            Sign In
         </button>
    </div>

    <div class="text-center text-sm text-gray-500">
        Don't have an account? <a href="/register" class="link">Create one</a>
    </div>
</form>

<?php include ML_BASE_PATH . '/app/Views/layouts/footer.ml.php'; ?>
