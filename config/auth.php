<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: /campuspass/login.php');
        exit;
    }
}

function require_role(array $roles): void
{
    require_login();

    $currentRole = $_SESSION['user']['role'] ?? null;

    if (!in_array($currentRole, $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}
