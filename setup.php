<?php

$config = require __DIR__ . '/config/config.php';
date_default_timezone_set($config['timezone']);
require __DIR__ . '/includes/functions.php';

$db = $config['db'];
$error = null;
$ready = false;
$seeded = false;

try {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db['name'])) {
        throw new RuntimeException('Nama database tidak valid.');
    }

    $server = new PDO(
        sprintf('mysql:host=%s;charset=%s', $db['host'], $db['charset']),
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']),
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $tabelKit = $pdo->query("SHOW TABLES LIKE 'kit'")->fetch();
    $tabelSet = $pdo->query("SHOW TABLES LIKE 'set_alat'")->fetch();
    if ($tabelKit && !$tabelSet && $pdo->query("SHOW COLUMNS FROM kit LIKE 'jenjang'")->fetch()) {
        $pdo->exec('RENAME TABLE kit TO set_alat');
    }

    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(__DIR__ . '/database/schema.sql'));
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    $punyaFoto = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'foto'")->fetch();
    if (!$punyaFoto) {
        $pdo->exec('ALTER TABLE absensi ADD COLUMN foto VARCHAR(255) DEFAULT NULL AFTER keterangan');
    }

    $punyaDisetujui = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'disetujui'")->fetch();
    if (!$punyaDisetujui) {
        $pdo->exec('ALTER TABLE absensi ADD COLUMN disetujui TINYINT(1) NOT NULL DEFAULT 0 AFTER foto');
    }

    $punyaAgenda = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'agenda'")->fetch();
    if (!$punyaAgenda) {
        $pdo->exec('ALTER TABLE absensi ADD COLUMN agenda TEXT DEFAULT NULL AFTER keterangan');
        $pdo->exec(
            "UPDATE absensi a
             LEFT JOIN (
                SELECT tp.id_peserta, t.tanggal,
                       GROUP_CONCAT(CONCAT(t.materi, ' · ', COALESCE(s.nama_sekolah, t.nama_sekolah)) ORDER BY t.materi SEPARATOR ', ') AS agenda
                FROM training_peserta tp
                JOIN training t ON t.id_training = tp.id_training
                LEFT JOIN sekolah s ON s.id_sekolah = t.id_sekolah
                GROUP BY tp.id_peserta, t.tanggal
             ) x ON x.id_peserta = a.id_peserta AND x.tanggal = a.tanggal
             SET a.agenda = x.agenda
             WHERE x.agenda IS NOT NULL"
        );
    }
    $tipeAgenda = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'agenda'")->fetch();
    if ($tipeAgenda && stripos((string) $tipeAgenda['Type'], 'varchar') !== false) {
        $pdo->exec('ALTER TABLE absensi MODIFY agenda TEXT DEFAULT NULL');
    }

    $punyaPertemuan = $pdo->query("SHOW COLUMNS FROM training LIKE 'pertemuan'")->fetch();
    if (!$punyaPertemuan) {
        $pdo->exec('ALTER TABLE training ADD COLUMN pertemuan INT NOT NULL DEFAULT 1 AFTER jumlah_siswa');
    }

    $pdo->exec('DROP TABLE IF EXISTS dokumentasi');
    $pdo->exec('DROP TABLE IF EXISTS penilaian_sikap');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS silabus (
            id_silabus VARCHAR(20) NOT NULL,
            materi VARCHAR(255) NOT NULL,
            jenjang VARCHAR(20) NOT NULL,
            semester VARCHAR(10) NOT NULL,
            tahun SMALLINT NOT NULL,
            PRIMARY KEY (id_silabus),
            KEY idx_silabus_level (jenjang, tahun)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $kolomSilabus = static function (string $nama) use ($pdo): bool {
        $stmt = $pdo->query('SHOW COLUMNS FROM silabus LIKE ' . $pdo->quote($nama));

        return (bool) $stmt->fetch();
    };
    if (!$kolomSilabus('semester')) {
        $pdo->exec("ALTER TABLE silabus ADD COLUMN semester VARCHAR(10) NOT NULL DEFAULT 'Ganjil' AFTER materi");
    }
    if (!$kolomSilabus('tahun')) {
        $pdo->exec('ALTER TABLE silabus ADD COLUMN tahun SMALLINT NOT NULL DEFAULT ' . (int) date('Y') . ' AFTER semester');
    }
    $indeksLama = $pdo->query("SHOW INDEX FROM silabus WHERE Key_name = 'idx_silabus_jenjang'")->fetch();
    if ($indeksLama) {
        $pdo->exec('ALTER TABLE silabus DROP INDEX idx_silabus_jenjang');
    }
    if ($kolomSilabus('pertemuan')) {
        $pdo->exec('ALTER TABLE silabus DROP COLUMN pertemuan');
    }
    if ($kolomSilabus('isi')) {
        $pdo->exec('ALTER TABLE silabus DROP COLUMN isi');
    }
    $indeksBaru = $pdo->query("SHOW INDEX FROM silabus WHERE Key_name = 'idx_silabus_level'")->fetch();
    if (!$indeksBaru) {
        $pdo->exec('ALTER TABLE silabus ADD KEY idx_silabus_level (jenjang, tahun)');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS set_alat (
            id_kit VARCHAR(20) NOT NULL,
            nama VARCHAR(150) NOT NULL,
            jenjang VARCHAR(20) NOT NULL,
            komponen TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'Dikembalikan',
            PRIMARY KEY (id_kit),
            UNIQUE KEY uq_kit_nama (nama)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $punyaStatusKit = $pdo->query("SHOW COLUMNS FROM set_alat LIKE 'status'")->fetch();
    if (!$punyaStatusKit) {
        $pdo->exec("ALTER TABLE set_alat ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Dikembalikan'");
    }
    if ($pdo->query("SHOW COLUMNS FROM set_alat LIKE 'jumlah'")->fetch()) {
        $pdo->exec('ALTER TABLE set_alat DROP COLUMN jumlah');
    }
    if ($pdo->query("SHOW COLUMNS FROM set_alat LIKE 'deskripsi'")->fetch()) {
        $pdo->exec('ALTER TABLE set_alat DROP COLUMN deskripsi');
    }
    if (!$pdo->query("SHOW COLUMNS FROM set_alat LIKE 'komponen'")->fetch()) {
        $pdo->exec('ALTER TABLE set_alat ADD COLUMN komponen TEXT NULL AFTER jenjang');
    }
    if (!$pdo->query("SHOW COLUMNS FROM kit LIKE 'jumlah'")->fetch()) {
        $pdo->exec('ALTER TABLE kit ADD COLUMN jumlah INT UNSIGNED NOT NULL DEFAULT 0 AFTER nama');
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS set_addon (
            id_set VARCHAR(20) NOT NULL,
            id_kit VARCHAR(20) NOT NULL,
            jumlah INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id_set, id_kit),
            KEY idx_set_addon_kit (id_kit)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    if (!$pdo->query("SHOW COLUMNS FROM set_addon LIKE 'jumlah'")->fetch()) {
        $pdo->exec('ALTER TABLE set_addon ADD COLUMN jumlah INT UNSIGNED NOT NULL DEFAULT 1');
    }

    pastikan_data_sekolah($pdo);

    $uploadDir = $config['upload_dir'];
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Folder unggahan tidak dapat dibuat.');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notulen (
            id_notulen VARCHAR(20) NOT NULL,
            judul VARCHAR(255) NOT NULL,
            cabang VARCHAR(150) DEFAULT NULL,
            pekan VARCHAR(150) DEFAULT NULL,
            tanggal_mulai DATE NOT NULL,
            tanggal_selesai DATE NOT NULL,
            id_user VARCHAR(20) NOT NULL,
            nama_notulen VARCHAR(150) NOT NULL,
            PRIMARY KEY (id_notulen),
            KEY idx_notulen_periode (tanggal_mulai),
            KEY idx_notulen_user (id_user)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notulen_baris (
            id_baris VARCHAR(20) NOT NULL,
            id_notulen VARCHAR(20) NOT NULL,
            hari VARCHAR(10) NOT NULL,
            jenis VARCHAR(20) NOT NULL,
            nama VARCHAR(150) NOT NULL,
            isi TEXT NOT NULL,
            urutan SMALLINT NOT NULL DEFAULT 0,
            PRIMARY KEY (id_baris),
            KEY idx_notulen_baris (id_notulen, hari, urutan)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $tipeRole = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if ($tipeRole && !str_contains((string) $tipeRole['Type'], 'STAFF')) {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('ADMIN', 'PEMBIMBING', 'PESERTA', 'PEMBINA', 'ANGGOTA', 'STAFF') NOT NULL");
        $pdo->exec("UPDATE users SET role = 'STAFF' WHERE role = 'PEMBIMBING'");
        $pdo->exec("UPDATE users SET role = 'ANGGOTA' WHERE role = 'PESERTA'");
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('ADMIN', 'PEMBINA', 'STAFF', 'ANGGOTA') NOT NULL");
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        seed_demo($pdo);
        $seeded = true;
    }
    $ready = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function seed_demo(PDO $pdo): void
{
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $twoDays = date('Y-m-d', strtotime('-2 days'));
    $mulai = date('Y-m-d', strtotime('-30 days'));
    $selesai = date('Y-m-d', strtotime('+60 days'));

    $users = [
        ['USR-ADMIN', 'alhafidz.almagribi409@gmail.com', 'Aulia Alhafidz', 'admin123', 'ADMIN'],
        ['USR-BIMB1', 'pembimbing@unida.ac.id', 'Pembimbing PKL', 'pembimbing123', 'STAFF'],
        ['USR-PES01', 'peserta1@unida.ac.id', 'Andi Pratama', 'peserta123', 'ANGGOTA'],
        ['USR-PES02', 'peserta2@unida.ac.id', 'Siti Rahma', 'peserta123', 'ANGGOTA'],
        ['USR-PES03', 'peserta3@unida.ac.id', 'Budi Santoso', 'peserta123', 'ANGGOTA'],
    ];
    $userStmt = $pdo->prepare(
        'INSERT INTO users (id_user, email, nama, password, role, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($users as $user) {
        $userStmt->execute([$user[0], $user[1], $user[2], password_hash($user[3], PASSWORD_DEFAULT), $user[4], 'Aktif']);
    }

    $peserta = [
        ['PST-0001', 'peserta1@unida.ac.id', 'Andi Pratama', 'Universitas Djuanda', 'Ilmu Komputer', '081234567801'],
        ['PST-0002', 'peserta2@unida.ac.id', 'Siti Rahma', 'Universitas Djuanda', 'Ilmu Komputer', '081234567802'],
        ['PST-0003', 'peserta3@unida.ac.id', 'Budi Santoso', 'Universitas Djuanda', 'Sistem Informasi', '081234567803'],
    ];
    $pesertaStmt = $pdo->prepare(
        'INSERT INTO peserta (id_peserta, email, nama_lengkap, asal_sekolah, jurusan, nomor_telepon, tanggal_mulai, tanggal_selesai, status_peserta)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($peserta as $row) {
        $pesertaStmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $mulai, $selesai, 'Aktif']);
    }

    $absenStmt = $pdo->prepare(
        'INSERT INTO absensi (id_absen, tanggal, id_peserta, jam_masuk, jam_pulang, status_kehadiran, keterangan)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $absensi = [
        ['ABS-000001', $today, 'PST-0001', '07:40:00', null, 'Hadir', 'Absen Masuk'],
        ['ABS-000002', $today, 'PST-0002', '08:25:00', null, 'Hadir', 'Absen Masuk'],
        ['ABS-000003', $yesterday, 'PST-0001', '07:35:00', '16:05:00', 'Hadir', 'Absen Masuk & Pulang'],
        ['ABS-000004', $yesterday, 'PST-0002', '07:50:00', '16:10:00', 'Hadir', 'Absen Masuk & Pulang'],
        ['ABS-000005', $yesterday, 'PST-0003', '07:55:00', '16:00:00', 'Hadir', 'Absen Masuk & Pulang'],
        ['ABS-000006', $twoDays, 'PST-0001', '07:42:00', '16:02:00', 'Hadir', 'Absen Masuk & Pulang'],
        ['ABS-000007', $twoDays, 'PST-0003', '08:15:00', '16:20:00', 'Hadir', 'Absen Masuk & Pulang'],
    ];
    foreach ($absensi as $row) {
        $absenStmt->execute($row);
    }

    $pdo->prepare('INSERT INTO sekolah (id_sekolah, nama_sekolah, alamat) VALUES (?, ?, ?)')
        ->execute(['SKL-0001', 'SDN 1 Ciawi', 'Ciawi, Bogor']);
    $pdo->prepare('INSERT INTO sekolah (id_sekolah, nama_sekolah, alamat) VALUES (?, ?, ?)')
        ->execute(['SKL-0002', 'SMPN 3 Bogor', 'Bogor']);

    $pdo->prepare(
        'INSERT INTO training (id_training, tanggal, id_sekolah, nama_sekolah, jenjang, materi, jumlah_siswa, pertemuan, catatan)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute(['TR-0001', $today, 'SKL-0001', 'SDN 1 Ciawi', 'SD', 'Robotika Dasar', 28, 1, 'Pengenalan sensor dan rangkaian sederhana.']);
    $pdo->prepare(
        'INSERT INTO training (id_training, tanggal, id_sekolah, nama_sekolah, jenjang, materi, jumlah_siswa, pertemuan, catatan)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute(['TR-0002', $yesterday, 'SKL-0002', 'SMPN 3 Bogor', 'SMP', 'Line Follower', 32, 1, 'Latihan lintasan garis.']);

    $timStmt = $pdo->prepare('INSERT INTO training_peserta (id, id_training, id_peserta, peran) VALUES (?, ?, ?, ?)');
    $timStmt->execute(['TP-0001', 'TR-0001', 'PST-0001', 'Trainer']);
    $timStmt->execute(['TP-0002', 'TR-0001', 'PST-0002', 'Asisten']);
    $timStmt->execute(['TP-0003', 'TR-0002', 'PST-0001', 'Trainer']);
    $timStmt->execute(['TP-0004', 'TR-0002', 'PST-0003', 'Dokumentator']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars(base_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card o-hidden border-0 shadow-lg my-5">
                <div class="card-body p-5">
                    <div class="text-center">
                        <h1 class="h4 text-gray-900 mb-4">Setup SIMPKL</h1>
                    </div>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger">
                                        Database belum siap.<br>
                                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <p class="mb-0">Pastikan MySQL Laragon sedang berjalan, lalu muat ulang halaman ini. Kredensial database ada di <code>config/config.php</code>.</p>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <?= $seeded ? 'Database berhasil dibuat dan diisi data demo.' : 'Database sudah terpasang.' ?>
                                    </div>
                                    <p>Akun demo:</p>
                                    <ul>
                                        <li>Admin: <code>alhafidz.almagribi409@gmail.com</code> / <code>admin123</code></li>
                                        <li>Pembimbing: <code>pembimbing@unida.ac.id</code> / <code>pembimbing123</code></li>
                                        <li>Peserta: <code>peserta1@unida.ac.id</code> / <code>peserta123</code></li>
                                    </ul>
                                    <p class="text-muted small mb-0">Peserta lain memakai email peserta2 dan peserta3 dengan password yang sama.</p>
                                <?php endif; ?>
                    <?php if ($ready): ?>
                    <div class="text-center mt-4">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('login.php'), ENT_QUOTES, 'UTF-8') ?>">Menuju Login</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
