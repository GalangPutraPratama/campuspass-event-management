<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Pendaftaran Berhasil';
$baseUrl = '/CAMPUSPASS/';
$ticketCode = trim((string) ($_GET['kode'] ?? ''));

if ($ticketCode === '' || !preg_match('/^CP-[A-F0-9]{10}$/', $ticketCode)) {
    http_response_code(404);
    $ticketCode = '';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main class="container my-5">
    <div class="alert alert-success">
        <h1 class="h3">Pendaftaran berhasil</h1>
        <p class="mb-2">Simpan kode tiket berikut untuk kebutuhan verifikasi:</p>
        <?php if ($ticketCode !== ''): ?>
            <strong class="fs-3"><?= e($ticketCode) ?></strong>
        <?php else: ?>
            <p class="mb-0">Kode tiket tidak ditemukan.</p>
        <?php endif; ?>
    </div>
    <a href="<?= e($baseUrl) ?>index.php" class="btn-neo">Kembali ke katalog</a>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
