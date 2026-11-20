<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = $baseUrl ?? '/CAMPUSPASS/';
$currentUser = $_SESSION['user'] ?? null;
?>

<nav class="navbar navbar-expand-lg navbar-neo sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= e($baseUrl) ?>index.php">
            <span>
                <img
                    src="<?= e($baseUrl) ?>img/icon.jpeg"
                    alt="CAMPUSPASS Logo"
                    class="navbar-logo"
                >
            </span>
            CAMPUSPASS
        </a>

        <button
            class="navbar-toggler navbar-toggler-neo"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarMain"
            aria-controls="navbarMain"
            aria-expanded="false"
            aria-label="Buka navigasi"
        >
            <i class="bi bi-list fs-3"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto me-3 gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e($baseUrl) ?>index.php">
                        Beranda
                    </a>
                </li>

                <?php if ($currentUser !== null): ?>
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= e($baseUrl) ?>admin/dashboard.php"
                        >
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= e($baseUrl) ?>panitia/checkin.php"
                        >
                            Check-in
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($currentUser !== null): ?>
                <span class="me-3">
                    <?= e($currentUser['nama_lengkap'] ?? $currentUser['email'] ?? '') ?>
                </span>

                <a
                    href="<?= e($baseUrl) ?>logout.php"
                    class="btn-nav-login"
                >
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            <?php else: ?>
                <a
                    href="<?= e($baseUrl) ?>login.php"
                    class="btn-nav-login"
                >
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>