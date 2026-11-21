<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($eventId === false || $eventId === null || $eventId < 1) {
    http_response_code(404);
    exit('Event tidak ditemukan.');
}

$stmt = $conn->prepare(
    "SELECT e.id, e.judul_event, e.kategori, e.deskripsi, e.tanggal_kegiatan,
            e.lokasi, e.kuota_maksimal, e.biaya_pendaftaran, e.narahubung,
            e.poster_file, e.created_at, u.nama_lengkap AS pembuat_event
     FROM events e
     LEFT JOIN users u ON u.id = e.created_by
     WHERE e.id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if ($event === null) {
    http_response_code(404);
    exit('Event tidak ditemukan.');
}

$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS jumlah_pendaftar
     FROM pendaftaran
     WHERE event_id = ? AND status_bayar = 'Approved'"
);
$countStmt->bind_param('i', $eventId);
$countStmt->execute();
$registered = (int) ($countStmt->get_result()->fetch_assoc()['jumlah_pendaftar'] ?? 0);
$countStmt->close();

$capacity = (int) $event['kuota_maksimal'];
$remaining = max(0, $capacity - $registered);
$percentage = $capacity > 0 ? min(100, (int) round(($registered / $capacity) * 100)) : 100;
$eventTimestamp = strtotime((string) $event['tanggal_kegiatan']);
$isOpen = $eventTimestamp !== false && $eventTimestamp >= time() && $remaining > 0;

if (!empty($event['poster_file'])) {
    $poster = 'uploads/poster/' . rawurlencode((string) $event['poster_file']);
} else {
    $poster = 'https://placehold.co/1200x480/BFDBFE/1a1a1a?text=' . rawurlencode((string) $event['judul_event']);
}

$pageTitle = 'Detail Event: ' . (string) $event['judul_event'];
$baseUrl = '/CAMPUSPASS/';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<main class="container py-4">
    <div class="mb-4">
        <a href="<?= e($baseUrl) ?>index.php" class="btn-neo btn-neo-outline btn-neo-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Katalog Event
        </a>
    </div>

    <div class="card-neo h-auto p-2 mb-4">
        <img
            src="<?= e($poster) ?>"
            alt="Poster <?= e($event['judul_event']) ?>"
            class="img-fluid w-100 rounded-3 border-neo"
            style="object-fit: cover; max-height: 420px;"
        >
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-neo h-auto mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge-neo <?= e(category_class((string) $event['kategori'])) ?>">
                            <i class="bi bi-tag-fill me-1"></i><?= e($event['kategori']) ?>
                        </span>
                        <span class="badge-neo <?= $isOpen ? 'badge-workshop' : 'badge-lomba' ?>">
                            <i class="bi <?= $isOpen ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                            <?= $isOpen ? 'Pendaftaran Dibuka' : 'Pendaftaran Ditutup' ?>
                        </span>
                    </div>

                    <h1 class="fw-bold mb-3" style="font-size: 2rem;">
                        <?= e($event['judul_event']) ?>
                    </h1>

                    <p class="text-muted mb-4">
                        Dipublikasikan oleh
                        <strong><?= e($event['pembuat_event'] ?: 'Panitia CAMPUSPASS') ?></strong>
                        pada <?= e(format_date_id((string) $event['created_at'])) ?>
                    </p>

                    <div class="p-3 rounded-3 border-neo" style="background: var(--bg);">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <span class="fw-bold"><i class="bi bi-people-fill me-1"></i>Status Kuota Peserta</span>
                            <span class="fw-bold"><?= $registered ?> / <?= $capacity ?> Kursi Terisi (<?= $percentage ?>%)</span>
                        </div>
                        <div class="quota-bar">
                            <div class="quota-bar-fill" style="width: <?= $percentage ?>%;"></div>
                        </div>
                        <small class="text-muted fw-semibold">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?= $isOpen ? 'Tersisa ' . $remaining . ' slot lagi.' : 'Kuota atau waktu pendaftaran sudah berakhir.' ?>
                        </small>
                    </div>
                </div>
            </div>

            <div class="card-neo h-auto mb-4">
                <div class="card-body">
                    <h3 class="fw-bold mb-3 pb-2" style="border-bottom: 2.5px solid var(--border);">
                        <i class="bi bi-text-paragraph me-2"></i>Deskripsi Lengkap Event
                    </h3>
                    <div class="event-description">
                        <?= nl2br(e((string) $event['deskripsi'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-neo h-auto bg-neo-yellow mb-4">
                <div class="card-body">
                    <h4 class="fw-bold mb-3 pb-2" style="border-bottom: 2.5px solid var(--border);">
                        <i class="bi bi-calendar2-check-fill me-2"></i>Informasi Event
                    </h4>

                    <div class="mb-3">
                        <small class="text-uppercase fw-bold text-muted d-block">Tanggal Pelaksanaan</small>
                        <span class="fw-bold"><i class="bi bi-calendar-event me-2"></i><?= e(format_date_id((string) $event['tanggal_kegiatan'])) ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-uppercase fw-bold text-muted d-block">Waktu Acara</small>
                        <span class="fw-bold"><i class="bi bi-clock me-2"></i><?= $eventTimestamp !== false ? e(date('H:i', $eventTimestamp)) . ' WIB' : '-' ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-uppercase fw-bold text-muted d-block">Lokasi</small>
                        <span class="fw-bold"><i class="bi bi-geo-alt-fill me-2"></i><?= e($event['lokasi']) ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-uppercase fw-bold text-muted d-block">Biaya Tiket</small>
                        <span class="fs-5 fw-bold"><i class="bi bi-cash-stack me-2"></i><?= e(format_currency_id($event['biaya_pendaftaran'])) ?></span>
                    </div>
                    <hr style="border-top: 2px dashed var(--border); opacity: 1;">
                    <div class="mb-3">
                        <small class="text-uppercase fw-bold text-muted d-block">Narahubung</small>
                        <span class="fw-bold"><i class="bi bi-whatsapp me-2"></i><?= e($event['narahubung']) ?></span>
                    </div>
                </div>
            </div>

            <div class="card-neo h-auto mb-4 text-center">
                <div class="card-body">
                    <h5 class="fw-bold mb-2">Amankan Kursimu Sekarang!</h5>
                    <p class="text-muted small mb-3">E-Tiket diterbitkan setelah pembayaran diverifikasi panitia.</p>
                    <?php if ($isOpen): ?>
                        <a href="<?= e($baseUrl) ?>peserta/daftar.php?event_id=<?= $eventId ?>" class="btn-neo w-100 py-3 fs-5">
                            <i class="bi bi-ticket-perforated-fill"></i> Daftar Sekarang
                        </a>
                    <?php else: ?>
                        <span class="btn-neo btn-neo-secondary w-100 py-3 fs-5 disabled">Pendaftaran Ditutup</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
