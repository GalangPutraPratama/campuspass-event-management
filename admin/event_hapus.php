<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin', 'panitia']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
verify_csrf();
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('ID event tidak valid.'); }
$stmt = $conn->prepare('SELECT poster_file FROM events WHERE id = ?');
$stmt->bind_param('i', $id); $stmt->execute(); $event = $stmt->get_result()->fetch_assoc();
if (!$event) { flash('error', 'Event tidak ditemukan.'); redirect('dashboard.php'); }
$stmt = $conn->prepare('DELETE FROM events WHERE id = ?'); $stmt->bind_param('i', $id);
if ($stmt->execute()) { if (!empty($event['poster_file'])) { $path = __DIR__ . '/../uploads/poster/' . basename($event['poster_file']); if (is_file($path)) unlink($path); } flash('success', 'Event berhasil dihapus.'); }
else { flash('error', 'Event gagal dihapus.'); }
redirect('dashboard.php');
