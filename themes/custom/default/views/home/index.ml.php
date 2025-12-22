<?php
$title = 'MonkeysCMS - Home (Theme Active)';
$heading = 'MonkeysCMS (Theme Active)';
$subheading = 'Welcome to your new CMS installation.';
echo $this->render('layouts/header', get_defined_vars());
?>

<div class="text-center space-y-4">
    <p class="text-gray-600 mb-4">Everything is configured and ready to go.</p>
    
    <div class="flex justify-center flex-col sm:flex-row gap-4">
        <a href="/login" class="btn-primary inline-flex items-center justify-center">
            Go to Dashboard
        </a>
    </div>
</div>

<?php include ML_BASE_PATH . '/app/Views/layouts/footer.ml.php'; ?>
