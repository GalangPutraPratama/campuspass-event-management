<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'E-Tiket Peserta';
$baseUrl = '/CAMPUSPASS/';
$ticket = null;
$error = '';

$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ticketCode = strtoupper(trim((string) ($_GET['kode'] ?? '')));

if ($ticketId === false || $ticketId === null) {
    $ticketId = null;
}

if ($ticketId === null && $ticketCode === '') {
    $error = 'Masukkan kode tiket untuk melihat e-tiket.';
} elseif ($ticketCode !== '' && !preg_match('/^[A-Z0-9-]{3,50}$/', $ticketCode)) {
    $error = 'Format kode tiket tidak valid.';
} else {
    if ($ticketId !== null) {
        $stmt = $conn->prepare(
            "SELECT p.id, p.nama_lengkap, p.nim, p.kode_tiket, p.status_bayar,
                    p.status_kehadiran, e.judul_event, e.tanggal_kegiatan, e.lokasi
             FROM pendaftaran p
             INNER JOIN events e ON e.id = p.event_id
             WHERE p.id = ? AND p.status_bayar = 'Approved'
             LIMIT 1"
        );
        $stmt->bind_param('i', $ticketId);
    } else {
        $stmt = $conn->prepare(
            "SELECT p.id, p.nama_lengkap, p.nim, p.kode_tiket, p.status_bayar,
                    p.status_kehadiran, e.judul_event, e.tanggal_kegiatan, e.lokasi
             FROM pendaftaran p
             INNER JOIN events e ON e.id = p.event_id
             WHERE p.kode_tiket = ? AND p.status_bayar = 'Approved'
             LIMIT 1"
        );
        $stmt->bind_param('s', $ticketCode);
    }

    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if ($ticket === null) {
        $error = 'E-tiket tidak ditemukan atau pembayaran belum disetujui.';
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main class="container my-5">
    <div class="section-heading">
        <h1 class="mb-0">E-Tiket Peserta</h1>
    </div>

    <?php if ($error !== ''): ?>
        <div class="card-neo bg-neo-pink-light p-4 mb-4" role="alert">
            <h2 class="h4 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Tiket tidak tersedia</h2>
            <p class="mb-0"><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($ticket === null): ?>
        <div class="card-neo p-4" style="max-width: 680px;">
            <form method="get" class="row g-3">
                <div class="col-12">
                    <label for="kode" class="form-label-neo">Kode tiket</label>
                    <input type="text" name="kode" id="kode" class="form-control-neo text-uppercase" placeholder="Contoh: CP-ABC123" value="<?= e($ticketCode) ?>" required>
                </div>
                <div class="col-12"><button type="submit" class="btn-neo"><i class="bi bi-ticket-perforated-fill me-1"></i>Lihat E-Tiket</button></div>
            </form>
        </div>
    <?php else: ?>
        <div class="card-neo bg-neo-blue p-2" style="max-width: 820px;">
            <div class="card-body bg-white rounded-2 border-neo p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-4">
                    <div>
                        <span class="badge-neo badge-success">PEMBAYARAN APPROVED</span>
                        <h2 class="fw-bold mt-3 mb-1"><?= e($ticket['judul_event']) ?></h2>
                    </div>
                    <i class="bi bi-ticket-perforated-fill fs-1"></i>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6"><small class="text-muted d-block">Nama peserta</small><strong><?= e($ticket['nama_lengkap']) ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">NIM</small><strong><?= e($ticket['nim']) ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Tanggal kegiatan</small><strong><?= e(format_date_id($ticket['tanggal_kegiatan'])) ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Lokasi</small><strong><?= e($ticket['lokasi']) ?></strong></div>
                </div>

                <div class="border-neo rounded-2 bg-neo-yellow p-3 text-center">
                    <small class="text-uppercase fw-bold d-block">Kode tiket</small>
                    <strong class="font-monospace fs-2"><?= e($ticket['kode_tiket']) ?></strong>
                </div>
                <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Tunjukkan kode ini kepada panitia saat check-in.</p>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
