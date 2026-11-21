<?php
require_once __DIR__ . '/../config/database.php'; require_once __DIR__ . '/../config/auth.php'; require_once __DIR__ . '/../config/csrf.php'; require_once __DIR__ . '/../includes/functions.php'; require_role(['admin','panitia']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); if (!$id) { http_response_code(400); exit('ID event tidak valid.'); }
$stmt=$conn->prepare('SELECT * FROM events WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $event=$stmt->get_result()->fetch_assoc(); if (!$event) { http_response_code(404); exit('Event tidak ditemukan.'); }
$errors=[];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $judul = trim($_POST['judul_event'] ?? ''); $kategori = trim($_POST['kategori'] ?? ''); $deskripsi = trim($_POST['deskripsi'] ?? ''); $tanggal = trim($_POST['tanggal_kegiatan'] ?? ''); $lokasi = trim($_POST['lokasi'] ?? ''); $narahubung = trim($_POST['narahubung'] ?? '');
    $kuota = filter_var($_POST['kuota_maksimal'] ?? null, FILTER_VALIDATE_INT); $biaya = filter_var($_POST['biaya_pendaftaran'] ?? null, FILTER_VALIDATE_FLOAT);
    if (in_array('', [$judul,$kategori,$deskripsi,$tanggal,$lokasi,$narahubung], true)) $errors[] = 'Semua field wajib diisi.';
    if ($kuota === false || $kuota < 0) $errors[] = 'Kuota tidak valid.'; if ($biaya === false || $biaya < 0) $errors[] = 'Biaya tidak valid.';
    $poster = $event['poster_file']; $newPoster = null;
    if (!empty($_FILES['poster']['name'])) { $file=$_FILES['poster']; $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); if ($file['error']!==UPLOAD_ERR_OK || $file['size']>5242880 || !isset($allowed[$mime])) $errors[]='Poster tidak valid.'; else { $newPoster=bin2hex(random_bytes(16)).'.'.$allowed[$mime]; $poster=$newPoster; } }
    if (!$errors) { $stmt=$conn->prepare('UPDATE events SET judul_event=?,kategori=?,deskripsi=?,tanggal_kegiatan=?,lokasi=?,kuota_maksimal=?,biaya_pendaftaran=?,narahubung=?,poster_file=? WHERE id=?'); $stmt->bind_param('sssssidssi',$judul,$kategori,$deskripsi,$tanggal,$lokasi,$kuota,$biaya,$narahubung,$poster,$id); }
}
$pageTitle='Edit Event';$baseUrl='/CAMPUSPASS/';require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/navbar.php'; ?><main class="container py-4"><h1>Edit Event</h1><?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach; ?><p>Form edit sedang diproses.</p></main><?php require_once __DIR__.'/../includes/footer.php'; ?>