<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'panitia']);

$pageTitle = 'Check-in Peserta';
$baseUrl = '/CAMPUSPASS/';
$message = '';
$messageType = 'info';
$submittedCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submittedCode = strtoupper(trim((string) ($_POST['kode_tiket'] ?? '')));

    if ($submittedCode === '' || !preg_match('/^[A-Z0-9-]{3,50}$/', $submittedCode)) {
        $message = 'Kode tiket wajib diisi dengan format yang valid.';
        $messageType = 'danger';
    } else {
        $transactionStarted = false;
        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            $stmt = $conn->prepare(
                "SELECT p.id, p.nama_lengkap, p.nim, p.kode_tiket, p.status_bayar,
                        p.status_kehadiran, e.judul_event, e.tanggal_kegiatan, e.lokasi
                 FROM pendaftaran p
                 INNER JOIN events e ON e.id = p.event_id
                 WHERE p.kode_tiket = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->bind_param('s', $submittedCode);
            $stmt->execute();
            $registration = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();

            if ($registration === null) {
                throw new RuntimeException('Kode tiket tidak ditemukan.');
            }
            if ($registration['status_bayar'] !== 'Approved') {
                throw new RuntimeException('Pembayaran peserta belum disetujui.');
            }
            if ($registration['status_kehadiran'] === 'Hadir') {
                throw new RuntimeException('Tiket ini sudah pernah digunakan untuk check-in.');
            }

            $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
            $insert = $conn->prepare(
                'INSERT INTO presensi (pendaftaran_id, petugas_id) VALUES (?, ?)'
            );
            $registrationId = (int) $registration['id'];
            $insert->bind_param('ii', $registrationId, $userId);
            if (!$insert->execute()) {
                $insert->close();
                throw new RuntimeException('Check-in gagal disimpan.');
            }
            $insert->close();

            $update = $conn->prepare(
                "UPDATE pendaftaran SET status_kehadiran = 'Hadir' WHERE id = ? AND status_kehadiran = 'Belum Hadir'"
            );
            $update->bind_param('i', $registrationId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                $update->close();
                throw new RuntimeException('Status kehadiran tidak dapat diperbarui.');
            }
            $update->close();

            $conn->commit();
            $message = 'Check-in berhasil untuk ' . $registration['nama_lengkap'] . '.';
            $messageType = 'success';
            $submittedCode = '';
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            $message = $exception->getMessage();
            $messageType = 'danger';
        }
    }
}

$history = [];
$historyStmt = $conn->prepare(
    'SELECT p.kode_tiket, p.nama_lengkap, p.nim, e.judul_event, pr.waktu_checkin
     FROM presensi pr
     INNER JOIN pendaftaran p ON p.id = pr.pendaftaran_id
     INNER JOIN events e ON e.id = p.event_id
     ORDER BY pr.waktu_checkin DESC
     LIMIT 20'
);
$historyStmt->execute();
$result = $historyStmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}
$historyStmt->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main class="container py-4">
    <div class="text-center mb-4">
        <span class="badge-neo badge-success">Meja Registrasi &amp; Validasi</span>
        <h1 class="fw-bold display-6 mb-2">Check-in Peserta Event</h1>
        <p class="text-muted">Validasi kode tiket peserta yang sudah memiliki pembayaran Approved.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= e($messageType) ?>" role="alert"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="card-neo bg-neo-yellow">
                <div class="card-body">
                    <form method="post" class="d-flex flex-column flex-sm-row gap-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="text" name="kode_tiket" class="form-control-neo text-uppercase fw-bold" placeholder="Contoh: CP-ABC123" value="<?= e($submittedCode) ?>" maxlength="50" required>
                        <button type="submit" class="btn-neo flex-shrink-0"><i class="bi bi-qr-code-scan me-1"></i>Validasi Tiket</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Check-in Terbaru</h2>
        <span class="badge-neo badge-seminar"><?= count($history) ?> data terakhir</span>
    </div>

    <div class="table-responsive">
        <table class="table-neo">
            <thead><tr><th>No</th><th>Kode Tiket</th><th>Nama Peserta</th><th>NIM</th><th>Event</th><th>Waktu</th></tr></thead>
            <tbody>
            <?php if (!$history): ?>
                <tr><td colspan="6" class="text-center">Belum ada riwayat check-in.</td></tr>
            <?php else: ?>
                <?php foreach ($history as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td class="font-monospace fw-bold"><?= e($row['kode_tiket']) ?></td>
                        <td class="fw-bold"><?= e($row['nama_lengkap']) ?></td>
                        <td><?= e($row['nim']) ?></td>
                        <td><?= e($row['judul_event']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($row['waktu_checkin']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
