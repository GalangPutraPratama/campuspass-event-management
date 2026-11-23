<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['admin', 'panitia']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash('error', 'ID event tidak valid.');
    redirect('dashboard.php');
}

$userId  = (int) ($_SESSION['user']['id'] ?? 0);
$isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');

// Cek keberadaan event dan otorisasi kepemilikan
$stmt = $conn->prepare('SELECT * FROM events WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    flash('error', 'Event tidak ditemukan.');
    redirect('dashboard.php');
}

if (!$isAdmin && (int) $event['created_by'] !== $userId) {
    flash('error', 'Anda tidak memiliki hak untuk mengedit event ini.');
    redirect('dashboard.php');
}

// Cek jumlah pendaftar yang sudah di-Approve
$stmt = $conn->prepare("SELECT COUNT(*) AS total_approved FROM pendaftaran WHERE event_id = ? AND status_bayar = 'Approved'");
$stmt->bind_param('i', $id);
$stmt->execute();
$totalApproved = (int) ($stmt->get_result()->fetch_assoc()['total_approved'] ?? 0);
$stmt->close();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $judul       = trim($_POST['judul_event'] ?? '');
    $kategori    = trim($_POST['kategori'] ?? '');
    $deskripsi   = trim($_POST['deskripsi'] ?? '');
    $tanggal     = trim($_POST['tanggal_kegiatan'] ?? '');
    $lokasi      = trim($_POST['lokasi'] ?? '');
    $narahubung  = trim($_POST['narahubung'] ?? '');
    $kuota       = filter_var($_POST['kuota_maksimal'] ?? null, FILTER_VALIDATE_INT);
    $biaya       = filter_var($_POST['biaya_pendaftaran'] ?? null, FILTER_VALIDATE_FLOAT);

    if ($judul === '' || $kategori === '' || $deskripsi === '' || $tanggal === '' || $lokasi === '' || $narahubung === '') {
        $errors[] = 'Semua field wajib diisi.';
    }
    if ($kuota === false || $kuota < 0) {
        $errors[] = 'Kuota harus berupa angka tidak negatif.';
    } elseif ($kuota < $totalApproved) {
        $errors[] = "Kuota baru ({$kuota}) tidak boleh lebih kecil dari jumlah peserta yang sudah disetujui ({$totalApproved}).";
    }
    if ($biaya === false || $biaya < 0) {
        $errors[] = 'Biaya pendaftaran tidak valid.';
    }

    $poster = $event['poster_file'];
    if (!empty($_FILES['poster']['name'])) {
        $file    = $_FILES['poster'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);

        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !isset($allowed[$mime])) {
            $errors[] = 'Poster harus berupa gambar JPG, PNG, atau WEBP maksimal 5 MB.';
        } else {
            $newPoster = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/poster/' . $newPoster)) {
                if ($poster && file_exists(__DIR__ . '/../uploads/poster/' . $poster)) {
                    @unlink(__DIR__ . '/../uploads/poster/' . $poster);
                }
                $poster = $newPoster;
            } else {
                $errors[] = 'Gagal mengunggah file poster baru.';
            }
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare('UPDATE events SET judul_event = ?, kategori = ?, deskripsi = ?, tanggal_kegiatan = ?, lokasi = ?, kuota_maksimal = ?, biaya_pendaftaran = ?, narahubung = ?, poster_file = ? WHERE id = ?');
        $stmt->bind_param('sssssidssi', $judul, $kategori, $deskripsi, $tanggal, $lokasi, $kuota, $biaya, $narahubung, $poster, $id);

        if ($stmt->execute()) {
            $stmt->close();
            flash('success', 'Event berhasil diperbarui.');
            redirect('dashboard.php');
        } else {
            $errors[] = 'Gagal menyimpan perubahan event: ' . $conn->error;
            $stmt->close();
        }
    }
}

$pageTitle = 'Edit Event';
$baseUrl   = '/CAMPUSPASS/';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/flash.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">✏️ Edit Event</h1>
            <p class="text-muted mb-0">Ubah informasi event, tanggal kegiatan, kuota, atau poster.</p>
        </div>
        <a href="dashboard.php" class="btn-neo">
            <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Dashboard
        </a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card-neo p-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-bold">Judul Event</label>
                <input class="form-control form-control-neo" name="judul_event" value="<?= e($_POST['judul_event'] ?? $event['judul_event']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Kategori</label>
                <select class="form-select form-select-neo" name="kategori" required>
                    <?php
                    $kategoriList = ['Seminar', 'Workshop', 'Lomba', 'Webinar'];
                    $selectedKat  = $_POST['kategori'] ?? $event['kategori'];
                    foreach ($kategoriList as $kat):
                    ?>
                        <option value="<?= e($kat) ?>" <?= $selectedKat === $kat ? 'selected' : '' ?>><?= e($kat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Deskripsi Event</label>
                <textarea class="form-control form-control-neo" name="deskripsi" rows="5" required><?= e($_POST['deskripsi'] ?? $event['deskripsi']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Tanggal &amp; Waktu Kegiatan</label>
                <?php
                $tglVal = $_POST['tanggal_kegiatan'] ?? date('Y-m-d\TH:i', strtotime($event['tanggal_kegiatan']));
                ?>
                <input type="datetime-local" class="form-control form-control-neo" name="tanggal_kegiatan" value="<?= e($tglVal) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Lokasi / Tempat</label>
                <input class="form-control form-control-neo" name="lokasi" value="<?= e($_POST['lokasi'] ?? $event['lokasi']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Kuota Maksimal</label>
                <input type="number" min="<?= $totalApproved ?>" class="form-control form-control-neo" name="kuota_maksimal" value="<?= e((string) ($_POST['kuota_maksimal'] ?? $event['kuota_maksimal'])) ?>" required>
                <small class="text-muted">Minimal <?= $totalApproved ?> (jumlah peserta sudah approved).</small>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Biaya Pendaftaran (Rp)</label>
                <input type="number" min="0" step="0.01" class="form-control form-control-neo" name="biaya_pendaftaran" value="<?= e((string) ($_POST['biaya_pendaftaran'] ?? $event['biaya_pendaftaran'])) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Narahubung / Kontak</label>
                <input class="form-control form-control-neo" name="narahubung" value="<?= e($_POST['narahubung'] ?? $event['narahubung']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Poster Event (Biarkan kosong jika tidak ingin mengganti)</label>
                <?php if ($event['poster_file']): ?>
                    <div class="mb-2">
                        <small class="text-muted d-block">Poster saat ini:</small>
                        <img src="../uploads/poster/<?= e($event['poster_file']) ?>" alt="Poster Saat Ini" style="max-height: 120px; border: 2px solid var(--border); border-radius: 6px;">
                    </div>
                <?php endif; ?>
                <input type="file" class="form-control form-control-neo" name="poster" accept="image/jpeg,image/png,image/webp">
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button class="btn-neo" type="submit">
                <i class="bi bi-save-fill"></i> Simpan Perubahan
            </button>
            <a class="btn-neo btn-neo-outline" href="dashboard.php">Batal</a>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
