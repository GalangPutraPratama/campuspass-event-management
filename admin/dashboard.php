<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['admin', 'panitia']);

$userId  = (int) ($_SESSION['user']['id'] ?? 0);
$role    = $_SESSION['user']['role'] ?? null;
$isAdmin = ($role === 'admin');

const DASHBOARD_RECENT_LIMIT = 8;

/**
 * Menjalankan prepared statement dan mengembalikan semua baris (assoc).
 */
function admin_query(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Query gagal disiapkan.');
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function admin_badge_bayar(string $status): array
{
    switch ($status) {
        case 'Approved':
            return ['badge-approved', 'bi-check-circle-fill'];
        case 'Rejected':
            return ['badge-rejected', 'bi-x-circle-fill'];
        default:
            return ['badge-pending', 'bi-hourglass-split'];
    }
}

function admin_bukti_link(?string $file): string
{
    $file = (string) $file;
    // Nama file hanya berasal dari database; tolak jika berisi path.
    if ($file === '' || $file !== basename($file)) {
        return '<span class="text-muted">-</span>';
    }

    return '<a href="../uploads/pembayaran/' . e(rawurlencode($file)) . '" target="_blank" rel="noopener" class="fw-bold">Lihat bukti</a>';
}

function admin_aksi_form(array $row, string $status, string $kembali): void
{
    $isApprove = ($status === 'Approved');
    $class     = $isApprove ? 'btn-neo-success' : 'btn-neo-danger';
    $icon      = $isApprove ? 'bi-check-lg' : 'bi-x-lg';
    $label     = $isApprove ? 'Approve' : 'Reject';
    $confirm   = $isApprove ? 'Setujui pembayaran peserta ini?' : 'Tolak pembayaran peserta ini?';
    $disabled  = ($row['status_bayar'] === $status) ? ' disabled' : '';
    ?>
    <form method="post" action="verifikasi.php" class="d-inline" onsubmit="return confirm('<?= e($confirm) ?>');">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="kembali" value="<?= e($kembali) ?>">
        <button type="submit" class="btn-neo-sm <?= e($class) ?>"<?= $disabled ?>>
            <i class="bi <?= e($icon) ?>"></i> <?= e($label) ?>
        </button>
    </form>
    <?php
}

// Panitia hanya boleh melihat event miliknya (filter di query, bukan di tampilan).
$scopeWhere  = $isAdmin ? '' : 'WHERE e.created_by = ?';
$scopeTypes  = $isAdmin ? '' : 'i';
$scopeParams = $isAdmin ? [] : [$userId];

$stats  = ['event' => 0, 'peserta' => 0, 'approved' => 0, 'hadir' => 0];
$events = [];
$recent = [];

try {
    $r = admin_query($conn, "SELECT COUNT(*) AS total FROM events e $scopeWhere", $scopeTypes, $scopeParams);
    $stats['event'] = (int) ($r[0]['total'] ?? 0);

    $r = admin_query(
        $conn,
        "SELECT COUNT(*) AS total_peserta,
                COALESCE(SUM(p.status_bayar = 'Approved'), 0) AS total_approved,
                COALESCE(SUM(p.status_kehadiran = 'Hadir'), 0) AS total_hadir
         FROM pendaftaran p
         INNER JOIN events e ON e.id = p.event_id
         $scopeWhere",
        $scopeTypes,
        $scopeParams
    );
    $stats['peserta']  = (int) ($r[0]['total_peserta'] ?? 0);
    $stats['approved'] = (int) ($r[0]['total_approved'] ?? 0);
    $stats['hadir']    = (int) ($r[0]['total_hadir'] ?? 0);

    $events = admin_query(
        $conn,
        "SELECT e.id, e.judul_event FROM events e $scopeWhere ORDER BY e.created_at DESC, e.id DESC",
        $scopeTypes,
        $scopeParams
    );

    $recent = admin_query(
        $conn,
        "SELECT p.id, p.nama_lengkap, p.nim, p.email, p.no_whatsapp, p.fakultas,
                p.bukti_pembayaran, p.status_bayar, p.status_kehadiran
         FROM pendaftaran p
         INNER JOIN events e ON e.id = p.event_id
         $scopeWhere
         ORDER BY p.waktu_pendaftaran DESC, p.id DESC
         LIMIT " . DASHBOARD_RECENT_LIMIT,
        $scopeTypes,
        $scopeParams
    );
} catch (Throwable $ex) {
    error_log('dashboard.php: ' . $ex->getMessage());
    flash('error', 'Data dashboard gagal dimuat. Silakan coba lagi.');
}

$pageTitle = 'Dashboard Panitia';
$baseUrl   = '/CAMPUSPASS/';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<!--
    Style khusus halaman dashboard (dipindah dari dashboard.html).
    Jika includes/header.php punya slot CSS tambahan, blok ini bisa dipindah ke sana.
-->
<style>
    .row .stat-card {
        flex: none;
        min-width: 0;
        max-width: none;
        padding: 20px;
        height: 100%;
    }
    .row .stat-card.stat-card-green  { background: var(--green); }
    .row .stat-card.stat-card-blue   { background: var(--blue); }
    .row .stat-card.stat-card-yellow { background: var(--yellow); }
    .row .stat-card.stat-card-purple { background: var(--purple); }
    .row .stat-card .stat-num   { font-size: 2.5rem; }
    .row .stat-card .stat-label { font-size: 0.95rem; font-weight: 700; color: var(--text); margin-top: 6px; }

    .card-neo.card-filter {
        display: block;
        height: auto;
        overflow: visible;
        padding: 24px;
    }
    .card-neo.card-filter:hover {
        transform: none;
        box-shadow: var(--shadow);
    }

    .badge-approved { background: var(--green); color: var(--text); }
    .badge-rejected { background: var(--pink-light); color: #991b1b; }
    .table-neo .badge-neo { margin-bottom: 0; white-space: nowrap; }

    .table-neo-wrapper .table-neo {
        border: none;
        border-radius: 0;
        box-shadow: none;
    }
    .table-neo-wrapper .table-neo thead th {
        background: var(--yellow);
        font-size: 0.85rem;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .table-neo-wrapper .table-neo tbody td { white-space: nowrap; }

    .table-neo .btn-neo-sm {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 0.8rem;
        font-weight: 700;
        border: 2px solid var(--border);
        border-radius: 6px;
        box-shadow: 2px 2px 0px var(--border);
        cursor: pointer;
    }
    .table-neo .btn-neo-sm:hover:not(:disabled) {
        transform: translate(1px, 1px);
        box-shadow: 1px 1px 0px var(--border);
    }
    .table-neo .btn-neo-sm:disabled { opacity: 0.45; cursor: not-allowed; }
    .table-neo .btn-neo-danger { background: var(--pink-light); color: #991b1b; }
    .table-neo .btn-neo-danger:hover:not(:disabled) { background: #fbcfe8; color: #991b1b; }
    .table-neo .btn-neo-sm:focus-visible { outline: 3px solid var(--text); outline-offset: 2px; }
</style>

<main class="container py-4">

    <!-- HEADER DASHBOARD + TOMBOL TAMBAH EVENT -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2">
        <div>
            <span class="badge-neo badge-approved mb-1">
                <i class="bi bi-shield-lock-fill me-1"></i> <?= $isAdmin ? 'Admin Panel' : 'Panel Panitia' ?>
            </span>
            <h1 class="fw-bold mb-1" style="font-size: 2rem;">⚡ Dashboard Panitia</h1>
            <p class="text-muted mb-0">Pantau statistik kehadiran, verifikasi status pendaftar, dan kelola event kampus.</p>
        </div>
        <div>
            <a href="event_tambah.php" class="btn-neo">
                <i class="bi bi-plus-circle-fill"></i> Tambah Event
            </a>
        </div>
    </div>

    <!-- 4 KARTU STATISTIK -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-green">
                <i class="bi bi-calendar-check-fill fs-2 text-success"></i>
                <div class="stat-num"><?= e((string) $stats['event']) ?></div>
                <div class="stat-label">Total Event</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-blue">
                <i class="bi bi-people-fill fs-2 text-primary"></i>
                <div class="stat-num"><?= e((string) $stats['peserta']) ?></div>
                <div class="stat-label">Total Pendaftar</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-yellow">
                <i class="bi bi-cash-coin fs-2 text-warning"></i>
                <div class="stat-num"><?= e((string) $stats['approved']) ?></div>
                <div class="stat-label">Total Approved</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-purple">
                <i class="bi bi-person-check-fill fs-2" style="color: #6b21a8;"></i>
                <div class="stat-num"><?= e((string) $stats['hadir']) ?></div>
                <div class="stat-label">Sudah Hadir</div>
            </div>
        </div>
    </div>

    <!-- FILTER & PENCARIAN (dikirim ke halaman daftar peserta lengkap) -->
    <form method="get" action="peserta.php" class="card-neo card-filter mb-4">
        <h2 class="h5 fw-bold mb-3">
            <i class="bi bi-funnel-fill me-2"></i>Filter &amp; Pencarian Peserta
        </h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label-neo" for="filterEvent">Event</label>
                <select id="filterEvent" name="event_id" class="form-select-neo">
                    <option value="" selected>Semua event</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= (int) $ev['id'] ?>"><?= e($ev['judul_event']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label-neo" for="filterStatus">Status pembayaran</label>
                <select id="filterStatus" name="status_bayar" class="form-select-neo">
                    <option value="" selected>Semua status</option>
                    <option value="Approved">Approved</option>
                    <option value="Pending">Pending</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label-neo" for="searchKeyword">Cari peserta</label>
                <input type="search" id="searchKeyword" name="q" class="form-control-neo"
                       placeholder="Nama, NIM, atau email" maxlength="100" autocomplete="off">
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn-neo">
                <i class="bi bi-search"></i> Cari di Daftar Peserta
            </button>
        </div>
    </form>

    <!-- TABEL PENDAFTAR TERBARU -->
    <div class="mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
            <h2 class="h3 fw-bold mb-0">
                <i class="bi bi-table me-2"></i>Daftar Peserta Masuk
            </h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge-neo badge-approved mb-0" aria-live="polite">
                    Menampilkan <?= e((string) count($recent)) ?> data terbaru dari <?= e((string) $stats['peserta']) ?> data pendaftar
                </span>
                <a href="peserta.php" class="btn-neo">
                    <i class="bi bi-list-ul"></i> Lihat Semua Peserta
                </a>
            </div>
        </div>

        <div class="table-neo-wrapper">
            <table class="table-neo" id="tabelPendaftar">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 50px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>NIM</th>
                        <th>Email</th>
                        <th>WhatsApp</th>
                        <th>Fakultas</th>
                        <th class="text-center">Bukti</th>
                        <th class="text-center">Status Bayar</th>
                        <th class="text-center" style="width: 190px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recent): ?>
                        <tr>
                            <td colspan="9" class="text-center fw-bold py-4">Belum ada pendaftar.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($recent as $i => $row): ?>
                        <?php [$badgeClass, $badgeIcon] = admin_badge_bayar((string) $row['status_bayar']); ?>
                        <tr>
                            <td class="text-center fw-bold"><?= e((string) ($i + 1)) ?></td>
                            <td class="fw-bold"><?= e($row['nama_lengkap']) ?></td>
                            <td><span class="font-monospace"><?= e($row['nim']) ?></span></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['no_whatsapp']) ?></td>
                            <td><?= e($row['fakultas']) ?></td>
                            <td class="text-center"><?= admin_bukti_link($row['bukti_pembayaran']) ?></td>
                            <td class="text-center">
                                <span class="badge-neo <?= e($badgeClass) ?>">
                                    <i class="bi <?= e($badgeIcon) ?>"></i> <?= e($row['status_bayar']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <?php admin_aksi_form($row, 'Approved', 'dashboard'); ?>
                                    <?php admin_aksi_form($row, 'Rejected', 'dashboard'); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
