<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email dan kata sandi wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, nama_lengkap, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            $user &&
            password_verify($password, $user['password']) &&
            in_array($user['role'], ['admin', 'panitia'], true)
        ) {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;

            header('Location: /campuspass/admin/dashboard.php');
            exit;
        }

        $error = 'Email atau kata sandi salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login: CAMPUSPASS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <nav class="navbar navbar-neo">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        <span><img src="img/icon.jpeg" alt="CAMPUSPASS Logo" class="navbar-logo"></span> CAMPUSPASS
      </a>
      <a href="index.php" class="btn-nav-login">
        <i class="bi bi-arrow-left"></i> Kembali ke Beranda
      </a>
    </div>
  </nav>

  <main class="login-wrapper">
    <div class="login-card-neo">
      <div class="login-card-header">
        <div class="mb-2">
          <span style="font-size: 2.25rem;"><img src="img/icon.jpeg" alt="CAMPUSPASS Logo" class="navbar-logo"></span>
        </div>
        <h2>Masuk ke CAMPUSPASS</h2>
        <p>Login sebagai panitia untuk mengelola event</p>
      </div>

      <div class="login-card-body">
        <?php if ($error !== ''): ?>
          <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
          >

          <div class="mb-3">
            <label class="form-label-neo" for="email">Alamat Email</label>
            <div class="input-group-neo">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input
                type="email"
                id="email"
                name="email"
                class="form-control-neo"
                placeholder="panitia@kampus.ac.id"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                required
              >
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label-neo" for="password">Kata Sandi</label>
            <div class="password-toggle-neo">
              <input
                type="password"
                id="password"
                name="password"
                class="form-control-neo"
                placeholder="Masukkan kata sandi"
                required
              >
              <button type="button" class="toggle-btn-neo" id="btnTogglePassword" aria-label="Tampilkan atau sembunyikan password">
                <i class="bi bi-eye" id="toggleIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-neo w-100">
            <i class="bi bi-box-arrow-in-right"></i> Masuk Sekarang
          </button>
        </form>

        <p class="login-footer-text">
          Belum punya akun panitia? <a href="#">Hubungi Admin Kampus</a>
        </p>
      </div>
    </div>
  </main>

  <footer class="footer-neo">
    <div class="container">
      <p class="mb-0">CAMPUSPASS &copy; 2026 - Kelompok 4 Praktikum Pemrograman Web</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const btnToggle = document.getElementById('btnTogglePassword');
    const inputPassword = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    if (btnToggle && inputPassword && toggleIcon) {
      btnToggle.addEventListener('click', () => {
        const isPassword = inputPassword.type === 'password';
        inputPassword.type = isPassword ? 'text' : 'password';
        toggleIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
      });
    }
  </script>
</body>
</html>
