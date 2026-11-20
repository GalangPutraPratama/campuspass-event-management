<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['admin', 'panitia']);

$userId  = (int) ($_SESSION['user']['id'] ?? 0);
$role    = $_SESSION['user']['role'] ?? null;
$isAdmin = ($role === 'admin');

const PESERTA_PER_PAGE = 20;
const STATUS_BAYAR_VALID = ['Pending', 'Approved', 'Rejected'];

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

function admin_filter_qs(array $filters, int $page): string
{
    $q = $filters;
    $q['page'] = $page > 1 ? $page : null;
    $q = array_filter($q, fn($v) => $v !== null && $v !== '' && $v !== 0);

    return http_build_query($q);
}

function admin_page_url(array $filters, int $page): string
{
    $qs = admin_filter_qs($filters, $page);

    return 'peserta.php' . ($qs !== '' ? '?' . $qs : '');
}

function admin_aksi_form(array $row, string $status, string $returnQs): void
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
        <input type="hidden" name="kembali" value="peserta">
        <input type="hidden" name="qs" value="<?= e($returnQs) ?>">
        <button type="submit" class="btn-neo-sm <?= e($class) ?>"<?= $disabled ?>>
            <i class="bi <?= e($icon) ?>"></i> <?= e($label) ?>
        </button>
    </form>
    <?php
}

// ---------- Baca & validasi filter dari GET ----------
$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$eventId = $eventId ? (int) $eventId : 0;

$statusFilter = $_GET['status_bayar'] ?? '';
if (!is_string($statusFilter) || !in_array($statusFilter, STATUS_BAYAR_VALID, true)) {
    $statusFilter = '';
}

$keyword = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$keyword = mb_substr($keyword, 0, 100);

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page ? (int) $page : 1;

$filters = [
    'event_id'     => $eventId ?: null,
    'status_bayar' => $statusFilter,
    'q'            => $keyword,
];

// ---------- Susun WHERE (panitia dibatasi created_by di query) ----------
$where  = [];
$types  = '';
$params = [];

if (!$isAdmin) {
    $where[]  = 'e.created_by = ?';
    $types   .= 'i';
    $params[] = $userId;
}
if ($eventId > 0) {
    $where[]  = 'p.event_id = ?';
    $types   .= 'i';
    $params[] = $eventId;
}
if ($statusFilter !== '') {
    $where[]  = 'p.status_bayar = ?';
    $types   .= 's';
    $params[] = $statusFilter;
}
if ($keyword !== '') {
    $like     = '%' . addcslashes($keyword, '\\%_') . '%';
    $where[]  = '(p.nama_lengkap LIKE ? OR p.nim LIKE ? OR p.email LIKE ?)';
    $types   .= 'sss';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$events     = [];
$rows       = [];
$total      = 0;
$totalPages = 1;

try {
    // Daftar event untuk dropdown filter (sesuai role).
    $evWhere  = $isAdmin ? '' : 'WHERE e.created_by = ?';
    $evTypes  = $isAdmin ? '' : 'i';
    $evParams = $isAdmin ? [] : [$userId];
    $events = admin_query(
        $conn,
        "SELECT e.id, e.judul_event FROM events e $evWhere ORDER BY e.created_at DESC, e.id DESC",
        $evTypes,
        $evParams
    );

    $r = admin_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM pendaftaran p
         INNER JOIN events e ON e.id = p.event_id
         $whereSql",
        $types,
        $params
    );
    $total      = (int) ($r[0]['total'] ?? 0);
    $totalPages = max(1, (int) ceil($total / PESERTA_PER_PAGE));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * PESERTA_PER_PAGE;

    $rows = admin_query(
        $conn,
        "SELECT p.id, p.nama_lengkap, p.nim, p.email, p.no_whatsapp, p.fakultas,
                p.bukti_pembayaran, p.status_bayar, p.status_kehadiran,
                e.judul_event
         FROM pendaftaran p
         INNER JOIN events e ON e.id = p.event_id
         $whereSql
         ORDER BY p.waktu_pendaftaran DESC, p.id DESC
         LIMIT ? OFFSET ?",
        $types . 'ii',
        array_merge($params, [PESERTA_PER_PAGE, $offset])
    );
} catch (Throwable $ex) {
    error_log('peserta.php: ' . $ex->getMessage());
    flash('error', 'Data peserta gagal dimuat. Silakan coba lagi.');
}

$returnQs  = admin_filter_qs($filters, $page);
$firstRow  = $total > 0 ? (($page - 1) * PESERTA_PER_PAGE) + 1 : 0;
$lastRow   = $total > 0 ? $firstRow + count($rows) - 1 : 0;

$pageTitle = 'Daftar Peserta';
$baseUrl   = '/CAMPUSPASS/';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>
<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<!--
    Style khusus halaman admin (sama dengan dashboard.php).
    Jika includes/header.php punya slot CSS tambahan, blok ini bisa dipindah ke sana.
-->
<style>
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

    <!-- HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2">
        <div>
            <span class="badge-neo badge-approved mb-1">
                <i class="bi bi-shield-lock-fill me-1"></i> <?= $isAdmin ? 'Admin Panel' : 'Panel Panitia' ?>
            </span>
            <h1 class="fw-bold mb-1" style="font-size: 2rem;">👥 Daftar Peserta</h1>
            <p class="text-muted mb-0">Cari peserta, cek bukti pembayaran, lalu setujui atau tolak pendaftaran.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-neo">
                <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER & PENCARIAN -->
    <form method="get" action="peserta.php" class="card-neo card-filter mb-4">
        <h2 class="h5 fw-bold mb-3">
            <i class="bi bi-funnel-fill me-2"></i>Filter &amp; Pencarian Peserta
        </h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label-neo" for="filterEvent">Event</label>
                <select id="filterEvent" name="event_id" class="form-select-neo">
                    <option value="">Semua event</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= (int) $ev['id'] ?>" <?= $eventId === (int) $ev['id'] ? 'selected' : '' ?>>
                            <?= e($ev['judul_event']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label-neo" for="filterStatus">Status pembayaran</label>
                <select id="filterStatus" name="status_bayar" class="form-select-neo">
                    <option value="">Semua status</option>
                    <?php foreach (STATUS_BAYAR_VALID as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label-neo" for="searchKeyword">Cari peserta</label>
                <input type="search" id="searchKeyword" name="q" class="form-control-neo"
                       value="<?= e($keyword) ?>" placeholder="Nama, NIM, atau email"
                       maxlength="100" autocomplete="off">
            </div>
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">
            <button type="submit" class="btn-neo">
                <i class="bi bi-search"></i> Terapkan
            </button>
            <a href="peserta.php" class="btn-neo">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
        </div>
    </form>

    <!-- TABEL PESERTA -->
    <div class="mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
            <h2 class="h3 fw-bold mb-0">
                <i class="bi bi-table me-2"></i>Data Pendaftar
            </h2>
            <span class="badge-neo badge-approved mb-0" aria-live="polite">
                Menampilkan <?= e((string) $firstRow) ?>-<?= e((string) $lastRow) ?> dari <?= e((string) $total) ?> data pendaftar
            </span>
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
                        <th>Event</th>
                        <th class="text-center">Bukti</th>
                        <th class="text-center">Status Bayar</th>
                        <th class="text-center">Kehadiran</th>
                        <th class="text-center" style="width: 190px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="11" class="text-center fw-bold py-4">Tidak ada peserta yang cocok. Ubah filter atau kata kunci pencarian.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $i => $row): ?>
                        <?php
                        [$badgeClass, $badgeIcon] = admin_badge_bayar((string) $row['status_bayar']);
                        $hadir = ($row['status_kehadiran'] === 'Hadir');
                        ?>
                        <tr>
                            <td class="text-center fw-bold"><?= e((string) ($firstRow + $i)) ?></td>
                            <td class="fw-bold"><?= e($row['nama_lengkap']) ?></td>
                            <td><span class="font-monospace"><?= e($row['nim']) ?></span></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['no_whatsapp']) ?></td>
                            <td><?= e($row['fakultas']) ?></td>
                            <td><?= e($row['judul_event']) ?></td>
                            <td class="text-center"><?= admin_bukti_link($row['bukti_pembayaran']) ?></td>
                            <td class="text-center">
                                <span class="badge-neo <?= e($badgeClass) ?>">
                                    <i class="bi <?= e($badgeIcon) ?>"></i> <?= e($row['status_bayar']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge-neo <?= $hadir ? 'badge-approved' : 'badge-pending' ?>">
                                    <i class="bi <?= $hadir ? 'bi-person-check-fill' : 'bi-clock' ?>"></i> <?= e($row['status_kehadiran']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <?php admin_aksi_form($row, 'Approved', $returnQs); ?>
                                    <?php admin_aksi_form($row, 'Rejected', $returnQs); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Navigasi halaman">
                <?php if ($page > 1): ?>
                    <a href="<?= e(admin_page_url($filters, $page - 1)) ?>" class="btn-neo">
                        <i class="bi bi-chevron-left"></i> Sebelumnya
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>

                <span class="fw-bold">Halaman <?= e((string) $page) ?> dari <?= e((string) $totalPages) ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= e(admin_page_url($filters, $page + 1)) ?>" class="btn-neo">
                        Berikutnya <i class="bi bi-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
