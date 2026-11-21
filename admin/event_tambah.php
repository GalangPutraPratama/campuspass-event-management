<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin', 'panitia']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $judul = trim($_POST['judul_event'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $tanggal = trim($_POST['tanggal_kegiatan'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $kuota = filter_var($_POST['kuota_maksimal'] ?? null, FILTER_VALIDATE_INT);
    $biaya = filter_var($_POST['biaya_pendaftaran'] ?? null, FILTER_VALIDATE_FLOAT);
    $narahubung = trim($_POST['narahubung'] ?? '');
    if ($judul === '' || $kategori === '' || $deskripsi === '' || $tanggal === '' || $lokasi === '' || $narahubung === '') $errors[] = 'Semua field wajib diisi.';
    if ($kuota === false || $kuota < 0) $errors[] = 'Kuota harus berupa angka tidak negatif.';
    if ($biaya === false || $biaya < 0) $errors[] = 'Biaya harus berupa angka tidak negatif.';
    $poster = null;
    if (!empty($_FILES['poster']['name'])) {
        $file = $_FILES['poster'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !isset($allowed[$mime])) $errors[] = 'Poster harus JPG, PNG, atau WEBP dan maksimal 5 MB.';
        else $poster = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    }
    if (!$errors) {
        $stmt = $conn->prepare('INSERT INTO events (judul_event,kategori,deskripsi,tanggal_kegiatan,lokasi,kuota_maksimal,biaya_pendaftaran,narahubung,poster_file,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $userId = (int) $_SESSION['user']['id'];
        $stmt->bind_param('sssssidssi', $judul, $kategori, $deskripsi, $tanggal, $lokasi, $kuota, $biaya, $narahubung, $poster, $userId);
        if ($stmt->execute()) {
            if ($poster) move_uploaded_file($_FILES['poster']['tmp_name'], __DIR__ . '/../uploads/poster/' . $poster);
            flash('success', 'Event berhasil ditambahkan.'); redirect('dashboard.php');
        }
        $errors[] = 'Event gagal disimpan.';
    }
}
$pageTitle = 'Tambah Event'; $baseUrl = '/CAMPUSPASS/';
require_once __DIR__ . '/../includes/header.php'; require_once __DIR__ . '/../includes/navbar.php'; require_once __DIR__ . '/../includes/flash.php';
?>
<main class="container py-4"><h1 class="fw-bold mb-4">Tambah Event</h1><?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card-neo p-4"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="row g-3"><div class="col-md-8"><label class="form-label">Judul Event</label><input class="form-control form-control-neo" name="judul_event" value="<?= old('judul_event') ?>" required></div><div class="col-md-4"><label class="form-label">Kategori</label><input class="form-control form-control-neo" name="kategori" value="<?= old('kategori') ?>" required></div><div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control form-control-neo" name="deskripsi" rows="5" required><?= old('deskripsi') ?></textarea></div><div class="col-md-6"><label class="form-label">Tanggal Kegiatan</label><input type="datetime-local" class="form-control form-control-neo" name="tanggal_kegiatan" value="<?= old('tanggal_kegiatan') ?>" required></div><div class="col-md-6"><label class="form-label">Lokasi</label><input class="form-control form-control-neo" name="lokasi" value="<?= old('lokasi') ?>" required></div><div class="col-md-4"><label class="form-label">Kuota</label><input type="number" min="0" class="form-control form-control-neo" name="kuota_maksimal" value="<?= old('kuota_maksimal') ?>" required></div><div class="col-md-4"><label class="form-label">Biaya</label><input type="number" min="0" step="0.01" class="form-control form-control-neo" name="biaya_pendaftaran" value="<?= old('biaya_pendaftaran','0') ?>" required></div><div class="col-md-4"><label class="form-label">Narahubung</label><input class="form-control form-control-neo" name="narahubung" value="<?= old('narahubung') ?>" required></div><div class="col-12"><label class="form-label">Poster (JPG, PNG, WEBP; maksimal 5 MB)</label><input type="file" class="form-control form-control-neo" name="poster" accept="image/jpeg,image/png,image/webp"></div></div><div class="mt-4"><button class="btn-neo" type="submit">Simpan Event</button> <a class="btn-neo btn-neo-outline" href="dashboard.php">Batal</a></div></form></main><?php require_once __DIR__ . '/../includes/footer.php'; ?>