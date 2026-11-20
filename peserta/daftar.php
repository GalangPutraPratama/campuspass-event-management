<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Pendaftaran Peserta';
$baseUrl = '/CAMPUSPASS/';
$errors = [];
$selectedEvent = null;
$formData = [
    'event_id' => filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT) ?: '',
    'nama_lengkap' => '',
    'nim' => '',
    'email' => '',
    'no_whatsapp' => '',
    'fakultas' => '',
    'jurusan' => '',
];

function upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas.',
        UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian.',
        UPLOAD_ERR_NO_FILE => 'File wajib diunggah.',
        default => 'File gagal diunggah.',
    };
}

function validate_upload(array $file, array $allowedMime, int $maxBytes, string $label): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $label . ': ' . upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return $label . ' maksimal ' . (int) ($maxBytes / 1048576) . ' MB.';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($allowedMime[$mime])) {
        return $label . ' harus berupa JPG, PNG' . (isset($allowedMime['application/pdf']) ? ' atau PDF' : '') . '.';
    }

    return null;
}

function move_validated_upload(array $file, string $directory, array $allowedMime): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extension = $allowedMime[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('File gagal disimpan.');
    }

    return $filename;
}

$eventList = [];
$eventListSql = <<<'SQL'
    SELECT e.id, e.judul_event, e.kategori, e.deskripsi, e.tanggal_kegiatan,
           e.lokasi, e.kuota_maksimal, e.biaya_pendaftaran,
           COUNT(p.id) AS jumlah_pendaftar
    FROM events e
    LEFT JOIN pendaftaran p ON p.event_id = e.id
    WHERE e.tanggal_kegiatan >= NOW()
    GROUP BY e.id, e.judul_event, e.kategori, e.deskripsi,
             e.tanggal_kegiatan, e.lokasi, e.kuota_maksimal, e.biaya_pendaftaran
    HAVING jumlah_pendaftar < e.kuota_maksimal
    ORDER BY e.tanggal_kegiatan ASC
SQL;

$result = $conn->query($eventListSql);
if ($result) {
    while ($event = $result->fetch_assoc()) {
        $eventList[] = $event;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($formData as $key => $value) {
        if ($key !== 'event_id') {
            $formData[$key] = trim((string) ($_POST[$key] ?? ''));
        }
    }
    $formData['event_id'] = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT) ?: '';

    if ($formData['event_id'] === '') {
        $errors[] = 'Event wajib dipilih.';
    }

    foreach (['nama_lengkap', 'nim', 'email', 'no_whatsapp', 'fakultas', 'jurusan'] as $field) {
        if ($formData[$field] === '') {
            $errors[] = 'Semua data peserta wajib diisi.';
            break;
        }
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if (strlen($formData['nim']) > 20 || strlen($formData['no_whatsapp']) > 20) {
        $errors[] = 'NIM dan WhatsApp maksimal 20 karakter.';
    }

    $paymentMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $ktmMime = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($_FILES['bukti_pembayaran'])) {
        $errors[] = 'Bukti pembayaran wajib diunggah.';
    } else {
        $uploadError = validate_upload($_FILES['bukti_pembayaran'], $paymentMime, 5 * 1048576, 'Bukti pembayaran');
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }
    if (!isset($_FILES['ktm'])) {
        $errors[] = 'KTM wajib diunggah.';
    } else {
        $uploadError = validate_upload($_FILES['ktm'], $ktmMime, 2 * 1048576, 'KTM');
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $paymentPath = __DIR__ . '/../uploads/pembayaran';
        $ktmPath = __DIR__ . '/../uploads/ktm';
        if (!is_dir($paymentPath) && !mkdir($paymentPath, 0750, true)) {
            $errors[] = 'Folder upload pembayaran tidak dapat dibuat.';
        }
        if (!is_dir($ktmPath) && !mkdir($ktmPath, 0750, true)) {
            $errors[] = 'Folder upload KTM tidak dapat dibuat.';
        }
    }

    if (!$errors) {
        $paymentFilename = null;
        $ktmFilename = null;
        $transactionStarted = false;

        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            $eventStmt = $conn->prepare(
                'SELECT id, judul_event, tanggal_kegiatan, lokasi, kuota_maksimal, biaya_pendaftaran
                 FROM events WHERE id = ? AND tanggal_kegiatan >= NOW() FOR UPDATE'
            );
            $eventStmt->bind_param('i', $formData['event_id']);
            $eventStmt->execute();
            $event = $eventStmt->get_result()->fetch_assoc();
            $eventStmt->close();

            if (!$event) {
                throw new RuntimeException('Event tidak tersedia atau sudah selesai.');
            }

            $countStmt = $conn->prepare('SELECT COUNT(*) AS jumlah FROM pendaftaran WHERE event_id = ?');
            $countStmt->bind_param('i', $formData['event_id']);
            $countStmt->execute();
            $registered = (int) $countStmt->get_result()->fetch_assoc()['jumlah'];
            $countStmt->close();

            if ($registered >= (int) $event['kuota_maksimal']) {
                throw new RuntimeException('Maaf, kuota event sudah penuh.');
            }

            $duplicateStmt = $conn->prepare('SELECT id FROM pendaftaran WHERE event_id = ? AND nim = ? LIMIT 1');
            $duplicateStmt->bind_param('is', $formData['event_id'], $formData['nim']);
            $duplicateStmt->execute();
            $duplicate = $duplicateStmt->get_result()->fetch_assoc();
            $duplicateStmt->close();

            if ($duplicate) {
                throw new RuntimeException('NIM tersebut sudah terdaftar pada event ini.');
            }

            $paymentFilename = move_validated_upload($_FILES['bukti_pembayaran'], $paymentPath, $paymentMime);
            $ktmFilename = move_validated_upload($_FILES['ktm'], $ktmPath, $ktmMime);

            $insertStmt = $conn->prepare(
                'INSERT INTO pendaftaran
                 (event_id, nama_lengkap, nim, email, no_whatsapp, fakultas, jurusan,
                  bukti_pembayaran, ktm_file, kode_tiket, status_bayar, status_kehadiran)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Pending\', \'Belum Hadir\')'
            );
            $ticketCode = '';
            $inserted = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $ticketCode = 'CP-' . strtoupper(bin2hex(random_bytes(5)));
                $insertStmt->bind_param(
                    'isssssssss',
                    $formData['event_id'], $formData['nama_lengkap'], $formData['nim'],
                    $formData['email'], $formData['no_whatsapp'], $formData['fakultas'],
                    $formData['jurusan'], $paymentFilename, $ktmFilename, $ticketCode
                );
                if ($insertStmt->execute()) {
                    $inserted = true;
                    break;
                }
                if ($conn->errno !== 1062) {
                    break;
                }
            }
            $insertStmt->close();

            if (!$inserted) {
                throw new RuntimeException('Pendaftaran tidak dapat disimpan.');
            }

            $conn->commit();
            redirect('sukses.php?kode=' . rawurlencode($ticketCode));
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            if ($paymentFilename !== null) {
                @unlink($paymentPath . DIRECTORY_SEPARATOR . $paymentFilename);
            }
            if ($ktmFilename !== null) {
                @unlink($ktmPath . DIRECTORY_SEPARATOR . $ktmFilename);
            }
            $errors[] = $exception->getMessage();
        }
    }
}

if ($formData['event_id'] !== '') {
    foreach ($eventList as $event) {
        if ((int) $event['id'] === (int) $formData['event_id']) {
            $selectedEvent = $event;
            break;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<main class="container my-4">
    <div class="section-heading"><h1 class="mb-0">Pendaftaran Peserta</h1></div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$eventList): ?>
        <div class="alert alert-warning">Belum ada event yang masih tersedia.</div>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="col-12">
                <label for="event_id" class="form-label">Pilih Event</label>
                <select name="event_id" id="event_id" class="form-select" required>
                    <option value="">-- Pilih event --</option>
                    <?php foreach ($eventList as $event): ?>
                        <option value="<?= (int) $event['id'] ?>" <?= (int) $formData['event_id'] === (int) $event['id'] ? 'selected' : '' ?>>
                            <?= e($event['judul_event']) ?> — sisa kuota <?= (int) $event['kuota_maksimal'] - (int) $event['jumlah_pendaftar'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selectedEvent): ?>
                <div class="col-12 alert alert-info">
                    <strong><?= e($selectedEvent['judul_event']) ?></strong><br>
                    <?= e(format_date_id($selectedEvent['tanggal_kegiatan'])) ?> · <?= e($selectedEvent['lokasi']) ?><br>
                    Sisa kuota: <?= (int) $selectedEvent['kuota_maksimal'] - (int) $selectedEvent['jumlah_pendaftar'] ?>
                </div>
            <?php endif; ?>
            <?php foreach ([
                'nama_lengkap' => 'Nama lengkap', 'nim' => 'NIM', 'email' => 'Email',
                'no_whatsapp' => 'WhatsApp', 'fakultas' => 'Fakultas', 'jurusan' => 'Jurusan'
            ] as $field => $label): ?>
                <div class="col-md-6">
                    <label for="<?= e($field) ?>" class="form-label"><?= e($label) ?></label>
                    <input type="<?= $field === 'email' ? 'email' : 'text' ?>" name="<?= e($field) ?>" id="<?= e($field) ?>" class="form-control" value="<?= e($formData[$field]) ?>" required>
                </div>
            <?php endforeach; ?>
            <div class="col-md-6">
                <label for="bukti_pembayaran" class="form-label">Bukti pembayaran (JPG/PNG/PDF, maks. 5 MB)</label>
                <input type="file" name="bukti_pembayaran" id="bukti_pembayaran" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
            </div>
            <div class="col-md-6">
                <label for="ktm" class="form-label">KTM (JPG/PNG, maks. 2 MB)</label>
                <input type="file" name="ktm" id="ktm" class="form-control" accept="image/jpeg,image/png" required>
            </div>
            <div class="col-12"><button type="submit" class="btn-neo">Daftar Sekarang</button></div>
        </form>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
