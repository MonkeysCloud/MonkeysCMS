<?php
$title = 'Register - MonkeysCMS';
$heading = 'Create an Account';
$subheading = 'Start your journey with MonkeysCMS.';
include ML_BASE_PATH . '/app/Views/layouts/header.ml.php';
?>

<form action="/register" method="POST" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
    
    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="space-y-5">
        <div class="input-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" class="input-field" placeholder="John Doe" required autofocus>
        </div>

        <div class="input-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="input-field" placeholder="you@example.com" required>
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="input-field" placeholder="at least 8 characters" required>
        </div>
        
        <div class="input-group">
            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="input-field" placeholder="Same as password" required>
        </div>
    </div>

    <div class="pt-2">
         <button type="submit" class="w-full btn-primary shadow-lg shadow-orange-500/20 text-sm flex justify-center py-2.5">
            Create Account
         </button>
    </div>

    <div class="text-center text-sm text-gray-500">
        Already have an account? <a href="/login" class="link">Sign in</a>
    </div>
</form>

<?php include ML_BASE_PATH . '/app/Views/layouts/footer.ml.php'; ?>
