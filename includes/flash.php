<?php

$flash = get_flash();

if ($flash !== null):
    $allowedTypes = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'danger' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info',
    ];

    $flashClass = $allowedTypes[$flash['type'] ?? 'info'] ?? 'alert-info';
?>

<div class="container mt-3">
    <div class="alert <?= e($flashClass) ?>" role="alert">
        <?= e($flash['message'] ?? '') ?>
    </div>
</div>

<?php endif; ?>
