<?php

/**
 * Handler POST untuk verifikasi pembayaran (tanpa tampilan).
 * Hanya mengubah pendaftaran.status_bayar menjadi Approved / Rejected.
 * Kode tiket TIDAK dibuat di sini (bagian Kenzi).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['admin', 'panitia']);

// Request GET (atau method lain) tidak boleh mengubah data.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Metode tidak diizinkan.';
    exit;
}

$baseUrl = '/CAMPUSPASS/';

/**
 * Bangun ulang query string filter dari input, hanya kunci yang diizinkan,
 * supaya redirect kembali ke peserta.php tidak bisa disalahgunakan.
 */
function admin_clean_qs(string $raw): string
{
    parse_str($raw, $in);
    $out = [];

    if (isset($in['event_id']) && is_string($in['event_id']) && ctype_digit($in['event_id']) && (int) $in['event_id'] > 0) {
        $out['event_id'] = (int) $in['event_id'];
    }
    if (isset($in['status_bayar']) && is_string($in['status_bayar'])
        && in_array($in['status_bayar'], ['Pending', 'Approved', 'Rejected'], true)) {
        $out['status_bayar'] = $in['status_bayar'];
    }
    if (isset($in['q']) && is_string($in['q']) && trim($in['q']) !== '') {
        $out['q'] = mb_substr(trim($in['q']), 0, 100);
    }
    if (isset($in['page']) && is_string($in['page']) && ctype_digit($in['page']) && (int) $in['page'] > 1) {
        $out['page'] = (int) $in['page'];
    }

    return http_build_query($out);
}

// Tentukan tujuan redirect (hanya dua halaman yang diizinkan).
$backUrl = $baseUrl . 'admin/dashboard.php';
if (($_POST['kembali'] ?? '') === 'peserta') {
    $qs      = admin_clean_qs(is_string($_POST['qs'] ?? null) ? $_POST['qs'] : '');
    $backUrl = $baseUrl . 'admin/peserta.php' . ($qs !== '' ? '?' . $qs : '');
}

// CSRF wajib.
if (verify_csrf() === false) {
    flash('error', 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.');
    redirect($backUrl);
    exit;
}

// Validasi input.
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    flash('error', 'ID pendaftaran tidak valid.');
    redirect($backUrl);
    exit;
}

$status = $_POST['status'] ?? '';
if (!is_string($status) || !in_array($status, ['Approved', 'Rejected'], true)) {
    flash('error', 'Status tidak valid.');
    redirect($backUrl);
    exit;
}

$userId  = (int) ($_SESSION['user']['id'] ?? 0);
$isAdmin = (($_SESSION['user']['role'] ?? null) === 'admin');

try {
    // Ambil data pendaftaran beserta pemilik event-nya.
    $stmt = $conn->prepare(
        'SELECT p.id, p.status_bayar, e.created_by
         FROM pendaftaran p
         INNER JOIN events e ON e.id = p.event_id
         WHERE p.id = ?
         LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('Query gagal disiapkan.');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data   = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$data) {
        flash('error', 'Data pendaftaran tidak ditemukan.');
    } elseif (!$isAdmin && (int) $data['created_by'] !== $userId) {
        // Panitia hanya boleh memverifikasi peserta dari event miliknya.
        flash('error', 'Anda tidak berwenang memverifikasi pendaftaran ini.');
    } elseif ($data['status_bayar'] === $status) {
        flash('success', 'Status pendaftaran sudah ' . $status . ', tidak ada perubahan.');
    } else {
        $update = $conn->prepare('UPDATE pendaftaran SET status_bayar = ? WHERE id = ?');
        if ($update === false) {
            throw new RuntimeException('Query gagal disiapkan.');
        }
        $update->bind_param('si', $status, $id);
        $update->execute();
        $update->close();

        flash('success', $status === 'Approved'
            ? 'Pendaftaran berhasil disetujui (Approved).'
            : 'Pendaftaran berhasil ditolak (Rejected).');
    }
} catch (Throwable $ex) {
    error_log('verifikasi.php: ' . $ex->getMessage());
    flash('error', 'Terjadi kesalahan saat memproses verifikasi. Silakan coba lagi.');
}

redirect($backUrl);
exit;
