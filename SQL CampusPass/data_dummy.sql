USE db_campuspass;

START TRANSACTION;

-- =========================
-- 1. DATA USERS
-- Password dummy: ganti lewat password_hash() PHP sebelum login dipakai
-- =========================
INSERT INTO users
    (id, nama_lengkap, email, password, role)
VALUES
    (1, 'Galang Putra Pratama', 'galang@campuspass.test', 'dummy_password_galang', 'admin'),
    (2, 'Kenzi Alkhalid Syahputra', 'kenzi@campuspass.test', 'dummy_password_kenzi', 'panitia'),
    (3, 'Delia Ayu Nandhita', 'delia@campuspass.test', 'dummy_password_delia', 'panitia')
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role = VALUES(role);

-- =========================
-- 2. DATA EVENTS
-- =========================
INSERT INTO events
    (
        id,
        judul_event,
        kategori,
        deskripsi,
        tanggal_kegiatan,
        lokasi,
        kuota_maksimal,
        biaya_pendaftaran,
        narahubung,
        poster_file,
        created_by
    )
VALUES
    (
        1,
        'Seminar Nasional AI dan Machine Learning 2026',
        'Seminar',
        'Seminar nasional membahas perkembangan Artificial Intelligence dan Machine Learning di dunia pendidikan dan industri.',
        '2026-11-25 09:00:00',
        'Aula Utama Kampus',
        50,
        25000,
        'Galang - 081234567890',
        NULL,
        1
    ),
    (
        2,
        'Workshop UI/UX Design untuk Pemula',
        'Workshop',
        'Workshop dasar perancangan UI/UX menggunakan prinsip desain modern dan berorientasi pada pengguna.',
        '2026-11-28 08:30:00',
        'Lab Komputer FIK',
        40,
        15000,
        'Kenzi - 081234567891',
        NULL,
        2
    ),
    (
        3,
        'Lomba Hackathon Nasional 2026',
        'Lomba',
        'Kompetisi pengembangan solusi digital untuk menyelesaikan masalah di lingkungan kampus.',
        '2026-12-05 08:00:00',
        'Gedung Rektorat',
        50,
        0,
        'Delia - 081234567892',
        NULL,
        3
    )
ON DUPLICATE KEY UPDATE
    judul_event = VALUES(judul_event),
    deskripsi = VALUES(deskripsi),
    tanggal_kegiatan = VALUES(tanggal_kegiatan),
    lokasi = VALUES(lokasi),
    kuota_maksimal = VALUES(kuota_maksimal),
    biaya_pendaftaran = VALUES(biaya_pendaftaran);

-- =========================
-- 3. DATA PENDAFTARAN
-- =========================
INSERT INTO pendaftaran
    (
        id,
        event_id,
        nama_lengkap,
        nim,
        email,
        no_whatsapp,
        fakultas,
        jurusan,
        bukti_pembayaran,
        ktm_file,
        kode_tiket,
        status_bayar,
        status_kehadiran
    )
VALUES
    (
        1,
        1,
        'Andi Pratama',
        '2510501001',
        'andi@student.test',
        '081234560001',
        'FIK',
        'Sistem Informasi',
        'bukti_andi.jpg',
        'ktm_andi.jpg',
        'CP26-AI001',
        'Approved',
        'Hadir'
    ),
    (
        2,
        1,
        'Budi Santoso',
        '2510501002',
        'budi@student.test',
        '081234560002',
        'FIK',
        'Informatika',
        'bukti_budi.jpg',
        'ktm_budi.jpg',
        'CP26-AI002',
        'Approved',
        'Belum Hadir'
    ),
    (
        3,
        1,
        'Citra Lestari',
        '2510501003',
        'citra@student.test',
        '081234560003',
        'FEB',
        'Manajemen',
        'bukti_citra.jpg',
        'ktm_citra.jpg',
        'CP26-AI003',
        'Pending',
        'Belum Hadir'
    ),
    (
        4,
        2,
        'Dimas Ramadhan',
        '2510501004',
        'dimas@student.test',
        '081234560004',
        'FIK',
        'Sistem Informasi',
        'bukti_dimas.jpg',
        'ktm_dimas.jpg',
        'CP26-UX001',
        'Approved',
        'Hadir'
    ),
    (
        5,
        2,
        'Eka Wulandari',
        '2510501005',
        'eka@student.test',
        '081234560005',
        'FISIP',
        'Ilmu Komunikasi',
        'bukti_eka.jpg',
        'ktm_eka.jpg',
        'CP26-UX002',
        'Rejected',
        'Belum Hadir'
    ),
    (
        6,
        3,
        'Fajar Nugroho',
        '2510501006',
        'fajar@student.test',
        '081234560006',
        'FT',
        'Teknik Industri',
        NULL,
        'ktm_fajar.jpg',
        'CP26-HK001',
        'Approved',
        'Belum Hadir'
    ),
    (
        7,
        3,
        'Gita Permata',
        '2510501007',
        'gita@student.test',
        '081234560007',
        'FIK',
        'Informatika',
        NULL,
        'ktm_gita.jpg',
        'CP26-HK002',
        'Pending',
        'Belum Hadir'
    ),
    (
        8,
        3,
        'Hendra Saputra',
        '2510501008',
        'hendra@student.test',
        '081234560008',
        'FH',
        'Ilmu Hukum',
        NULL,
        'ktm_hendra.jpg',
        'CP26-HK003',
        'Approved',
        'Hadir'
    )
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    status_bayar = VALUES(status_bayar),
    status_kehadiran = VALUES(status_kehadiran);

-- =========================
-- 4. DATA PRESENSI
-- =========================
INSERT INTO presensi
    (id, pendaftaran_id, waktu_checkin, petugas_id)
VALUES
    (1, 1, '2026-11-25 08:42:00', 2),
    (2, 4, '2026-11-28 08:15:00', 2),
    (3, 8, '2026-12-05 08:30:00', 3)
ON DUPLICATE KEY UPDATE
    waktu_checkin = VALUES(waktu_checkin),
    petugas_id = VALUES(petugas_id);

COMMIT;
