<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Katalog Event Kampus';
$baseUrl = '/CAMPUSPASS/';

$events = [];

$sql = "
    SELECT
        e.id,
        e.judul_event,
        e.kategori,
        e.tanggal_kegiatan,
        e.lokasi,
        e.kuota_maksimal,
        e.poster_file,
        COUNT(
            CASE
                WHEN p.status_bayar = 'Approved' THEN p.id
            END
        ) AS kuota_terisi
    FROM events e
    LEFT JOIN pendaftaran p ON p.event_id = e.id
    WHERE e.tanggal_kegiatan >= NOW()
    GROUP BY
        e.id,
        e.judul_event,
        e.kategori,
        e.tanggal_kegiatan,
        e.lokasi,
        e.kuota_maksimal,
        e.poster_file
    ORDER BY e.tanggal_kegiatan ASC
    LIMIT 6
";

$result = $conn->query($sql);

if ($result) {
    while ($event = $result->fetch_assoc()) {
        $events[] = $event;
    }
}

$totalEvents = 0;
$totalParticipants = 0;
$totalOrganizers = 0;

$statsResult = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM events) AS total_events,
        (SELECT COUNT(*) FROM pendaftaran) AS total_participants,
        (
            SELECT COUNT(DISTINCT created_by)
            FROM events
            WHERE created_by IS NOT NULL
        ) AS total_organizers"
);

if ($statsResult) {
    $stats = $statsResult->fetch_assoc();

    $totalEvents = (int) $stats['total_events'];
    $totalParticipants = (int) $stats['total_participants'];
    $totalOrganizers = (int) $stats['total_organizers'];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<header class="hero-section">
    <div class="container">
        <h1>
            Temukan Event Kampus<br>
            <span>Terbaik &amp; Seru!</span>
        </h1>

        <p class="hero-subtitle">
            Platform pendaftaran dan e-ticketing event kampus yang terintegrasi.
            Cari, daftar, dan kelola tiket event dalam satu tempat.
        </p>

        <div class="search-box">
            <i class="bi bi-search"></i>
            <input
                type="text"
                placeholder="Cari event, seminar, workshop..."
            >
        </div>
    </div>
</header>

<section class="container">
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num">
                <?= e((string) $totalEvents) ?>
            </div>
            <div class="stat-label">
                Event Tersedia
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-num">
                <?= e((string) $totalParticipants) ?>
            </div>
            <div class="stat-label">
                Peserta Terdaftar
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-num">
                <?= e((string) $totalOrganizers) ?>
            </div>
            <div class="stat-label">
                Panitia Aktif
            </div>
        </div>
    </div>
</section>

<main class="container my-4">
    <div class="section-heading">
        <div>
            <span>📅 Event Mendatang</span>
        </div>

        <a
            href="#event-catalog"
            class="btn-neo btn-neo-outline btn-neo-sm"
        >
            Lihat Semua
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <div id="event-catalog" class="row g-4">
        <?php if (empty($events)): ?>

            <div class="col-12">
                <div class="alert alert-warning">
                    Belum ada event mendatang.
                </div>
            </div>

        <?php else: ?>

            <?php foreach ($events as $event): ?>
                <?php
                $capacity = (int) $event['kuota_maksimal'];
                $registered = (int) $event['kuota_terisi'];

                $percentage = $capacity > 0
                    ? min(100, (int) round(($registered / $capacity) * 100))
                    : 0;

                if (!empty($event['poster_file'])) {
                    $poster = 'uploads/poster/' . rawurlencode(
                        $event['poster_file']
                    );
                } else {
                    $poster = 'https://placehold.co/600x320/'
                        . 'BFDBFE/1a1a1a?text=Event+Kampus';
                }

                $eventDate = format_date_id(
                    $event['tanggal_kegiatan']
                );
                ?>

                <div class="col-lg-4 col-md-6">
                    <div class="event-card">
                        <img
                            src="<?= e($poster) ?>"
                            alt="<?= e($event['judul_event']) ?>"
                        >

                        <div class="card-body">
                            <span
                                class="badge-neo
                                <?= e(category_class($event['kategori'])) ?>"
                            >
                                <?= e($event['kategori']) ?>
                            </span>

                            <h2 class="card-title">
                                <?= e($event['judul_event']) ?>
                            </h2>

                            <div class="card-meta">
                                <i class="bi bi-calendar3"></i>
                                <?= e($eventDate) ?>
                            </div>

                            <div class="card-meta">
                                <i class="bi bi-geo-alt"></i>
                                <?= e($event['lokasi']) ?>
                            </div>

                            <div class="card-footer-actions">
                                <div class="quota-text">
                                    <span>Kuota Terisi</span>

                                    <span>
                                        <?= e((string) $registered) ?>
                                        /
                                        <?= e((string) $capacity) ?>
                                    </span>
                                </div>

                                <div class="quota-bar">
                                    <div
                                        class="quota-bar-fill"
                                        style="width: <?= $percentage ?>%;"
                                    ></div>
                                </div>

                                <a
                                    href="detail_event.php?id=<?= (int) $event['id'] ?>"
                                    class="btn-neo btn-neo-sm w-100 mt-3"
                                >
                                    Lihat Detail
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>