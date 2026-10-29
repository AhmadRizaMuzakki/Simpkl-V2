<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
    }

    $path = ltrim($path, '/');
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if ($known === '' || !hash_equals($known, $token)) {
        flash('error', 'Sesi formulir tidak valid. Silakan ulangi.');
        $target = 'dashboard.php';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $ref = parse_url($_SERVER['HTTP_REFERER']);
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $file = basename($ref['path'] ?? '');
            if (($ref['host'] ?? '') === $host && preg_match('/^[a-z0-9_-]+\.php$/', $file)) {
                $target = $file;
            }
        }
        redirect($target);
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }

    if (in_array(current_user()['role'], ['ANGGOTA', 'PEMBINA', 'STAFF'], true) && empty($_SESSION['user']['id_peserta'])) {
        $peserta = get_peserta_by_email(current_user()['email']);
        $_SESSION['user']['id_peserta'] = $peserta['id_peserta'] ?? null;
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        flash('error', 'Anda tidak memiliki akses ke halaman ini.');
        redirect('dashboard.php');
    }
}

function role_kontrol(): array
{
    return ['ADMIN', 'PEMBINA', 'STAFF'];
}

function is_staff(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user && in_array($user['role'], role_kontrol(), true);
}

function daftar_role_orang(): array
{
    return [
        'PEMBINA' => 'Pembina',
        'STAFF' => 'Staff',
        'ANGGOTA' => 'Anggota',
    ];
}

function label_role(string $role): string
{
    return [
        'ADMIN' => 'Admin',
        'PEMBINA' => 'Pembina',
        'STAFF' => 'Staff',
        'ANGGOTA' => 'Anggota',
    ][$role] ?? $role;
}

function generate_id(string $prefix): string
{
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function fmt_date(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function fmt_hari_tanggal(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '-';
    }
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    return $hari[(int) date('w', $ts)] . ', ' . date('d/m/Y', $ts);
}

function fmt_hari_tanggal_lengkap(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '-';
    }
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    return $hari[(int) date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function fmt_time(?string $time): string
{
    if ($time === null || $time === '') {
        return '-';
    }
    return substr($time, 0, 8);
}

function is_terlambat(?string $jam): bool
{
    return $jam !== null && $jam !== '' && $jam > '08:00:00';
}

function like_query(string $keyword): string
{
    return '%' . addcslashes($keyword, '%_\\') . '%';
}

function get_peserta_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM peserta WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_peserta(string $id): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, COALESCE(u.role, \'ANGGOTA\') AS role,
            (SELECT COUNT(*) FROM absensi a WHERE a.id_peserta = p.id_peserta) AS total_hadir,
            (SELECT COUNT(*) FROM training_peserta t WHERE t.id_peserta = p.id_peserta) AS total_training,
            (SELECT MAX(tanggal) FROM absensi a WHERE a.id_peserta = p.id_peserta) AS terakhir_absen
         FROM peserta p
         LEFT JOIN users u ON u.email = p.email
         WHERE p.id_peserta = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function simpan_absen(string $idPeserta, string $tipe): string
{
    if ($idPeserta === '') {
        throw new RuntimeException('Data peserta tidak ditemukan.');
    }

    $today = date('Y-m-d');
    $waktu = date('H:i:s');
    $stmt = db()->prepare('SELECT * FROM absensi WHERE id_peserta = ? AND tanggal = ? LIMIT 1');
    $stmt->execute([$idPeserta, $today]);
    $row = $stmt->fetch();

    if ($tipe === 'Masuk') {
        if ($row && !empty($row['jam_masuk'])) {
            throw new RuntimeException('Anda sudah melakukan absen masuk hari ini.');
        }

        $insert = db()->prepare(
            'INSERT INTO absensi (id_absen, tanggal, id_peserta, jam_masuk, jam_pulang, status_kehadiran, keterangan)
             VALUES (?, ?, ?, ?, NULL, ?, ?)'
        );
        $insert->execute([
            generate_id('ABS'),
            $today,
            $idPeserta,
            $waktu,
            'Hadir',
            'Absen Masuk',
        ]);

        return 'Absen masuk berhasil pada ' . $waktu;
    }

    if ($tipe === 'Pulang') {
        if (!$row || empty($row['jam_masuk'])) {
            throw new RuntimeException('Belum melakukan absen masuk.');
        }
        if (!empty($row['jam_pulang'])) {
            throw new RuntimeException('Anda sudah melakukan absen pulang.');
        }

        $update = db()->prepare(
            'UPDATE absensi SET jam_pulang = ?, keterangan = ? WHERE id_absen = ?'
        );
        $update->execute([$waktu, 'Absen Masuk & Pulang', $row['id_absen']]);

        return 'Absen pulang berhasil pada ' . $waktu;
    }

    throw new RuntimeException('Tipe absensi tidak valid.');
}

function store_upload(array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Dokumentasi foto wajib diunggah.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Ukuran foto terlalu besar.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah dokumentasi.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimal 5 MB.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];
    if (!isset($mimeMap[$ext])) {
        throw new RuntimeException('Format foto harus JPG, PNG, WEBP, atau GIF.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== $mimeMap[$ext]) {
        throw new RuntimeException('File bukan gambar yang valid.');
    }

    global $config;
    $dir = $config['upload_dir'];
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder unggahan tidak dapat dibuat.');
    }

    $storedExt = $ext === 'jpeg' ? 'jpg' : $ext;
    $name = bin2hex(random_bytes(16)) . '.' . $storedExt;
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Tidak dapat menyimpan file dokumentasi.');
    }

    return $name;
}

function pastikan_data_sekolah(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sekolah (
            id_sekolah VARCHAR(20) NOT NULL,
            nama_sekolah VARCHAR(200) NOT NULL,
            alamat VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id_sekolah),
            UNIQUE KEY uq_sekolah_nama (nama_sekolah)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $kolom = $pdo->query("SHOW COLUMNS FROM training LIKE 'id_sekolah'")->fetch();
    if (!$kolom) {
        $pdo->exec('ALTER TABLE training ADD COLUMN id_sekolah VARCHAR(20) NULL AFTER tanggal');
    }

    $cari = $pdo->prepare('SELECT id_sekolah FROM sekolah WHERE nama_sekolah = ? LIMIT 1');
    $buat = $pdo->prepare('INSERT INTO sekolah (id_sekolah, nama_sekolah) VALUES (?, ?)');
    $sambung = $pdo->prepare(
        "UPDATE training SET id_sekolah = ? WHERE nama_sekolah = ? AND (id_sekolah IS NULL OR id_sekolah = '')"
    );
    $namaStmt = $pdo->query(
        "SELECT DISTINCT nama_sekolah FROM training WHERE nama_sekolah <> '' AND (id_sekolah IS NULL OR id_sekolah = '')"
    );
    foreach ($namaStmt->fetchAll(PDO::FETCH_COLUMN) as $nama) {
        $cari->execute([$nama]);
        $id = $cari->fetchColumn();
        if ($id === false) {
            $id = 'SKL-' . strtoupper(bin2hex(random_bytes(4)));
            $buat->execute([$id, $nama]);
        }
        $sambung->execute([$id, $nama]);
    }

    $fk = $pdo->prepare(
        'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $fk->execute(['training', 'fk_training_sekolah']);
    if (!$fk->fetch()) {
        $pdo->exec('ALTER TABLE training ADD CONSTRAINT fk_training_sekolah FOREIGN KEY (id_sekolah) REFERENCES sekolah (id_sekolah)');
    }
}

function daftar_sekolah(): array
{
    return db()->query('SELECT id_sekolah, nama_sekolah FROM sekolah ORDER BY nama_sekolah')->fetchAll();
}

function sekolah_terpilih(array $input): array
{
    $id = trim((string) ($input['id_sekolah'] ?? ''));
    if ($id === '') {
        throw new RuntimeException('Nama sekolah wajib dipilih.');
    }
    $stmt = db()->prepare('SELECT id_sekolah, nama_sekolah FROM sekolah WHERE id_sekolah = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Sekolah tidak ditemukan. Pilih dari data sekolah.');
    }

    return $row;
}

function data_sekolah(array $input): array
{
    $nama = trim((string) ($input['nama_sekolah'] ?? ''));
    $alamat = trim((string) ($input['alamat'] ?? ''));
    if ($nama === '') {
        throw new RuntimeException('Nama sekolah wajib diisi.');
    }
    if (mb_strlen($nama) > 200 || mb_strlen($alamat) > 255) {
        throw new RuntimeException('Nama sekolah atau alamat terlalu panjang.');
    }

    return [
        'nama_sekolah' => $nama,
        'alamat' => $alamat !== '' ? $alamat : null,
    ];
}

function simpan_sekolah(array $input): string
{
    $data = data_sekolah($input);
    $ada = db()->prepare('SELECT 1 FROM sekolah WHERE nama_sekolah = ? LIMIT 1');
    $ada->execute([$data['nama_sekolah']]);
    if ($ada->fetchColumn()) {
        throw new RuntimeException('Nama sekolah sudah terdaftar.');
    }
    db()->prepare('INSERT INTO sekolah (id_sekolah, nama_sekolah, alamat) VALUES (?, ?, ?)')
        ->execute([generate_id('SKL'), $data['nama_sekolah'], $data['alamat']]);

    return 'Data sekolah berhasil disimpan.';
}

function update_sekolah(array $input): string
{
    $id = trim((string) ($input['id_sekolah'] ?? ''));
    if ($id === '') {
        throw new RuntimeException('Sekolah tidak ditemukan.');
    }
    $data = data_sekolah($input);
    $ada = db()->prepare('SELECT 1 FROM sekolah WHERE nama_sekolah = ? AND id_sekolah <> ? LIMIT 1');
    $ada->execute([$data['nama_sekolah'], $id]);
    if ($ada->fetchColumn()) {
        throw new RuntimeException('Nama sekolah sudah terdaftar.');
    }
    $cek = db()->prepare('SELECT 1 FROM sekolah WHERE id_sekolah = ? LIMIT 1');
    $cek->execute([$id]);
    if (!$cek->fetchColumn()) {
        throw new RuntimeException('Sekolah tidak ditemukan.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE sekolah SET nama_sekolah = ?, alamat = ? WHERE id_sekolah = ?')
            ->execute([$data['nama_sekolah'], $data['alamat'], $id]);
        $pdo->prepare('UPDATE training SET nama_sekolah = ? WHERE id_sekolah = ?')
            ->execute([$data['nama_sekolah'], $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Data sekolah berhasil diperbarui.';
}

function hapus_sekolah(string $id): string
{
    $id = trim($id);
    $cek = db()->prepare('SELECT 1 FROM sekolah WHERE id_sekolah = ? LIMIT 1');
    $cek->execute([$id]);
    if (!$cek->fetchColumn()) {
        throw new RuntimeException('Sekolah tidak ditemukan.');
    }
    $pakai = db()->prepare('SELECT COUNT(*) FROM training WHERE id_sekolah = ?');
    $pakai->execute([$id]);
    if ((int) $pakai->fetchColumn() > 0) {
        throw new RuntimeException('Sekolah masih dipakai di training. Hapus training terkait terlebih dahulu.');
    }
    db()->prepare('DELETE FROM sekolah WHERE id_sekolah = ?')->execute([$id]);

    return 'Data sekolah berhasil dihapus.';
}

function data_set(array $input): array
{
    $nama = trim((string) ($input['nama'] ?? ''));
    $jenjang = trim((string) ($input['jenjang'] ?? ''));

    if ($nama === '') {
        throw new RuntimeException('Nama set wajib diisi.');
    }
    if (mb_strlen($nama) > 150) {
        throw new RuntimeException('Nama set terlalu panjang.');
    }
    if (!in_array($jenjang, daftar_level_materi(), true)) {
        throw new RuntimeException('Kategori set tidak valid.');
    }

    return [
        'nama' => $nama,
        'jenjang' => $jenjang,
    ];
}

function simpan_set(array $input): string
{
    $data = data_set($input);
    $ada = db()->prepare('SELECT 1 FROM set_alat WHERE nama = ? LIMIT 1');
    $ada->execute([$data['nama']]);
    if ($ada->fetchColumn()) {
        throw new RuntimeException('Nama set sudah terdaftar.');
    }
    db()->prepare('INSERT INTO set_alat (id_kit, nama, jenjang, status) VALUES (?, ?, ?, ?)')
        ->execute([generate_id('SET'), $data['nama'], $data['jenjang'], 'Dikembalikan']);

    return 'Set berhasil disimpan.';
}

function update_set(array $input): string
{
    $id = trim((string) ($input['id_kit'] ?? ''));
    if ($id === '') {
        throw new RuntimeException('Set tidak ditemukan.');
    }
    $data = data_set($input);
    $ada = db()->prepare('SELECT 1 FROM set_alat WHERE nama = ? AND id_kit <> ? LIMIT 1');
    $ada->execute([$data['nama'], $id]);
    if ($ada->fetchColumn()) {
        throw new RuntimeException('Nama set sudah terdaftar.');
    }
    $cek = db()->prepare('SELECT 1 FROM set_alat WHERE id_kit = ? LIMIT 1');
    $cek->execute([$id]);
    if (!$cek->fetchColumn()) {
        throw new RuntimeException('Set tidak ditemukan.');
    }
    db()->prepare('UPDATE set_alat SET nama = ?, jenjang = ? WHERE id_kit = ?')
        ->execute([$data['nama'], $data['jenjang'], $id]);

    return 'Set berhasil diperbarui.';
}

function hapus_set(string $id): string
{
    $id = trim($id);
    $db = db();
    $db->beginTransaction();
    try {
        $status = kunci_status_set($id);
        if ($status === 'Dipakai') {
            tambah_stok_kit(kunci_kit_pinjam(daftar_pinjam_set($id)));
        }
        $db->prepare('DELETE FROM set_addon WHERE id_set = ?')->execute([$id]);
        $stmt = $db->prepare('DELETE FROM set_alat WHERE id_kit = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Set tidak ditemukan.');
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return 'Set berhasil dihapus.';
}

function ubah_status_set(string $id, string $status): string
{
    $id = trim($id);
    if (!in_array($status, ['Dipakai', 'Dikembalikan'], true)) {
        throw new RuntimeException('Status set tidak valid.');
    }
    $db = db();
    $db->beginTransaction();
    try {
        $sekarang = kunci_status_set($id);
        if ($sekarang === $status) {
            $db->commit();

            return $status === 'Dipakai' ? 'Set sudah dipakai.' : 'Set sudah dikembalikan.';
        }
        $kit = kunci_kit_pinjam(daftar_pinjam_set($id));
        if ($status === 'Dipakai') {
            kurangi_stok_kit($kit);
        } else {
            tambah_stok_kit($kit);
            $db->prepare('DELETE FROM set_addon WHERE id_set = ?')->execute([$id]);
        }
        $db->prepare('UPDATE set_alat SET status = ? WHERE id_kit = ?')->execute([$status, $id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return $status === 'Dipakai' ? 'Set dipakai. Stok kit berkurang.' : 'Set dikembalikan. Stok kit kembali.';
}

function tambah_addon_set(string $idSet, string $idKit, mixed $jumlahPinjam): string
{
    $idSet = trim($idSet);
    $idKit = trim($idKit);
    $jumlah = filter_var($jumlahPinjam, FILTER_VALIDATE_INT);
    if ($idKit === '') {
        throw new RuntimeException('Pilih kit yang akan diambil.');
    }
    if ($jumlah === false || $jumlah < 1 || $jumlah > 9999) {
        throw new RuntimeException('Jumlah pinjam harus angka 1 sampai 9999.');
    }
    $db = db();
    $db->beginTransaction();
    try {
        $status = kunci_status_set($idSet);
        $sudah = $db->prepare('SELECT 1 FROM set_addon WHERE id_set = ? AND id_kit = ? LIMIT 1');
        $sudah->execute([$idSet, $idKit]);
        if ($sudah->fetchColumn()) {
            throw new RuntimeException('Kit ini sudah menjadi add-on.');
        }
        if ($status === 'Dipakai') {
            kurangi_stok_kit(kunci_kit_pinjam([$idKit => $jumlah]));
        } else {
            $pinjam = daftar_pinjam_set($idSet);
            $pinjam[$idKit] = $jumlah;
            kurangi_stok_kit(kunci_kit_pinjam($pinjam));
            $db->prepare('UPDATE set_alat SET status = ? WHERE id_kit = ?')->execute(['Dipakai', $idSet]);
        }
        $db->prepare('INSERT INTO set_addon (id_set, id_kit, jumlah) VALUES (?, ?, ?)')->execute([$idSet, $idKit, $jumlah]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return 'Kit diambil sebagai add-on.';
}

function lepas_addon_set(string $idSet, string $idKit): string
{
    $idSet = trim($idSet);
    $idKit = trim($idKit);
    $db = db();
    $db->beginTransaction();
    try {
        $status = kunci_status_set($idSet);
        $ada = $db->prepare('SELECT jumlah FROM set_addon WHERE id_set = ? AND id_kit = ? LIMIT 1');
        $ada->execute([$idSet, $idKit]);
        $pinjam = $ada->fetchColumn();
        if ($pinjam === false) {
            throw new RuntimeException('Add-on tidak ditemukan.');
        }
        if ($status === 'Dipakai') {
            tambah_stok_kit(kunci_kit_pinjam([$idKit => (int) $pinjam]));
        }
        $db->prepare('DELETE FROM set_addon WHERE id_set = ? AND id_kit = ?')->execute([$idSet, $idKit]);
        if ($status === 'Dipakai' && daftar_pinjam_set($idSet) === []) {
            $db->prepare('UPDATE set_alat SET status = ? WHERE id_kit = ?')->execute(['Dikembalikan', $idSet]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return $status === 'Dipakai' ? 'Add-on dilepas. Stok kit kembali.' : 'Add-on dilepas.';
}

function kunci_status_set(string $id): string
{
    $stmt = db()->prepare('SELECT status FROM set_alat WHERE id_kit = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();
    if ($status === false) {
        throw new RuntimeException('Set tidak ditemukan.');
    }

    return (string) $status;
}

function daftar_pinjam_set(string $idSet): array
{
    $stmt = db()->prepare('SELECT id_kit, jumlah FROM set_addon WHERE id_set = ?');
    $stmt->execute([$idSet]);
    $pinjam = [];
    foreach ($stmt->fetchAll() as $row) {
        $pinjam[(string) $row['id_kit']] = (int) $row['jumlah'];
    }

    return $pinjam;
}

function kunci_kit_pinjam(array $pinjam): array
{
    if ($pinjam === []) {
        return [];
    }
    $rows = [];
    foreach (kunci_kit(array_keys($pinjam)) as $row) {
        $row['pinjam'] = (int) ($pinjam[(string) $row['id_kit']] ?? 0);
        $rows[] = $row;
    }

    return $rows;
}

function kunci_kit(array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids, static fn (string $id): bool => $id !== '')));
    if ($ids === []) {
        return [];
    }
    $tanda = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id_kit, nama, jumlah FROM kit WHERE id_kit IN ($tanda) ORDER BY nama FOR UPDATE");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    if (count($rows) !== count($ids)) {
        throw new RuntimeException('Kit tidak ditemukan.');
    }

    return $rows;
}

function kurangi_stok_kit(array $rows): void
{
    $stmt = db()->prepare('UPDATE kit SET jumlah = jumlah - ? WHERE id_kit = ? AND jumlah >= ?');
    foreach ($rows as $row) {
        $pinjam = (int) ($row['pinjam'] ?? 0);
        if ($pinjam < 1) {
            throw new RuntimeException('Jumlah pinjam tidak valid.');
        }
        $stmt->execute([$pinjam, $row['id_kit'], $pinjam]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Stok ' . $row['nama'] . ' tidak cukup.');
        }
    }
}

function tambah_stok_kit(array $rows): void
{
    $stmt = db()->prepare('UPDATE kit SET jumlah = jumlah + ? WHERE id_kit = ?');
    foreach ($rows as $row) {
        $pinjam = (int) ($row['pinjam'] ?? 0);
        if ($pinjam < 1) {
            throw new RuntimeException('Jumlah pinjam tidak valid.');
        }
        $stmt->execute([$pinjam, $row['id_kit']]);
    }
}

function data_kit(array $input): array
{
    $nama = trim((string) ($input['nama'] ?? ''));
    $jumlah = filter_var($input['jumlah'] ?? '', FILTER_VALIDATE_INT);
    if ($nama === '') {
        throw new RuntimeException('Nama kit wajib diisi.');
    }
    if (mb_strlen($nama) > 150) {
        throw new RuntimeException('Nama kit terlalu panjang.');
    }
    if ($jumlah === false || $jumlah < 0 || $jumlah > 9999) {
        throw new RuntimeException('Jumlah kit harus angka 0 sampai 9999.');
    }

    return [
        'nama' => $nama,
        'jumlah' => $jumlah,
    ];
}

function simpan_kit(array $input): string
{
    $data = data_kit($input);
    $ada = db()->prepare('SELECT 1 FROM kit WHERE nama = ? LIMIT 1');
    $ada->execute([$data['nama']]);
    if ($ada->fetchColumn()) {
        throw new RuntimeException('Nama kit sudah terdaftar.');
    }
    db()->prepare('INSERT INTO kit (id_kit, nama, jumlah) VALUES (?, ?, ?)')
        ->execute([generate_id('KIT'), $data['nama'], $data['jumlah']]);

    return 'Kit berhasil disimpan.';
}

function update_kit(array $input): string
{
    $id = trim((string) ($input['id_kit'] ?? ''));
    if ($id === '') {
        throw new RuntimeException('Kit tidak ditemukan.');
    }
    $data = data_kit($input);
    $ada = db()->prepare('SELECT 1 FROM kit WHERE nama = ? AND id_kit <> ? LIMIT 1');
    $ada->execute([$data['nama'], $id]);
    if ($ada->fetchColumn()) {
        throw new RuntimeException('Nama kit sudah terdaftar.');
    }
    $cek = db()->prepare('SELECT 1 FROM kit WHERE id_kit = ? LIMIT 1');
    $cek->execute([$id]);
    if (!$cek->fetchColumn()) {
        throw new RuntimeException('Kit tidak ditemukan.');
    }
    db()->prepare('UPDATE kit SET nama = ?, jumlah = ? WHERE id_kit = ?')
        ->execute([$data['nama'], $data['jumlah'], $id]);

    return 'Kit berhasil diperbarui.';
}

function hapus_kit(string $id): string
{
    $id = trim($id);
    $pakai = db()->prepare('SELECT 1 FROM set_addon WHERE id_kit = ? LIMIT 1');
    $pakai->execute([$id]);
    if ($pakai->fetchColumn()) {
        throw new RuntimeException('Lepas kit ini dari set sebelum dihapus.');
    }
    $stmt = db()->prepare('DELETE FROM kit WHERE id_kit = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Kit tidak ditemukan.');
    }

    return 'Kit berhasil dihapus.';
}

function daftar_level_materi(): array
{
    return ['Junior', 'Sensing', 'Exciting'];
}

function daftar_silabus(): array
{
    return db()->query(
        "SELECT id_silabus, materi, jenjang, semester, tahun
         FROM silabus
         ORDER BY tahun DESC, FIELD(jenjang, 'Junior', 'Sensing', 'Exciting'), semester, materi"
    )->fetchAll();
}

function materi_silabus(string $id): string
{
    $id = trim($id);
    if ($id === '') {
        throw new RuntimeException('Materi wajib dipilih.');
    }
    $stmt = db()->prepare('SELECT materi FROM silabus WHERE id_silabus = ? LIMIT 1');
    $stmt->execute([$id]);
    $materi = $stmt->fetchColumn();
    if ($materi === false || $materi === '') {
        throw new RuntimeException('Materi tidak ditemukan di silabus.');
    }

    return (string) $materi;
}

function data_silabus(array $input): array
{
    $jenjang = trim((string) ($input['jenjang'] ?? ''));
    $materi = trim((string) ($input['materi'] ?? ''));
    $semester = trim((string) ($input['semester'] ?? ''));
    $tahun = $input['tahun'] ?? '';
    $jenjangOk = daftar_level_materi();

    if (!in_array($jenjang, $jenjangOk, true)) {
        throw new RuntimeException('Level materi tidak valid.');
    }
    if ($materi === '') {
        throw new RuntimeException('Materi wajib diisi.');
    }
    if (mb_strlen($materi) > 255) {
        throw new RuntimeException('Materi terlalu panjang.');
    }
    if (!in_array($semester, ['Ganjil', 'Genap'], true)) {
        throw new RuntimeException('Semester tidak valid.');
    }
    if (!is_numeric($tahun) || (int) $tahun < 2000 || (int) $tahun > 2100) {
        throw new RuntimeException('Tahun tidak valid.');
    }

    return [
        'materi' => $materi,
        'jenjang' => $jenjang,
        'semester' => $semester,
        'tahun' => (int) $tahun,
    ];
}

function simpan_silabus(array $input): string
{
    $data = data_silabus($input);
    db()->prepare('INSERT INTO silabus (id_silabus, materi, jenjang, semester, tahun) VALUES (?, ?, ?, ?, ?)')
        ->execute([generate_id('SLB'), $data['materi'], $data['jenjang'], $data['semester'], $data['tahun']]);

    return 'Silabus berhasil disimpan.';
}

function update_silabus(array $input): string
{
    $id = trim((string) ($input['id_silabus'] ?? ''));
    $data = data_silabus($input);
    $cek = db()->prepare('SELECT 1 FROM silabus WHERE id_silabus = ? LIMIT 1');
    $cek->execute([$id]);
    if (!$cek->fetchColumn()) {
        throw new RuntimeException('Silabus tidak ditemukan.');
    }
    db()->prepare('UPDATE silabus SET materi = ?, jenjang = ?, semester = ?, tahun = ? WHERE id_silabus = ?')
        ->execute([$data['materi'], $data['jenjang'], $data['semester'], $data['tahun'], $id]);

    return 'Silabus berhasil diperbarui.';
}

function hapus_silabus(string $id): string
{
    $id = trim($id);
    $stmt = db()->prepare('DELETE FROM silabus WHERE id_silabus = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Silabus tidak ditemukan.');
    }

    return 'Silabus berhasil dihapus.';
}

function id_trainer_input(array $input): array
{
    $mentah = $input['id_trainer'] ?? [];
    if (!is_array($mentah)) {
        $mentah = [$mentah];
    }
    $ids = [];
    foreach ($mentah as $id) {
        $id = trim((string) $id);
        if ($id !== '' && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

function trainer_terpilih(array $input): array
{
    $ids = id_trainer_input($input);
    $jumlah = count($ids);
    if ($jumlah < 1) {
        throw new RuntimeException('Nama trainer wajib dipilih.');
    }
    if ($jumlah > 10) {
        throw new RuntimeException('Nama trainer maksimal 10 orang.');
    }

    $tanda = implode(',', array_fill(0, $jumlah, '?'));
    $stmt = db()->prepare("SELECT id_peserta FROM peserta WHERE id_peserta IN ($tanda)");
    $stmt->execute($ids);
    if (count($stmt->fetchAll(PDO::FETCH_COLUMN)) !== $jumlah) {
        throw new RuntimeException('Nama trainer tidak valid.');
    }

    return $ids;
}

function daftar_peserta_pilihan(array $terpilih = []): array
{
    $terpilih = array_values(array_filter($terpilih, static fn ($id) => $id !== ''));
    if (!$terpilih) {
        return db()->query("SELECT id_peserta, nama_lengkap FROM peserta WHERE status_peserta = 'Aktif' ORDER BY nama_lengkap")->fetchAll();
    }

    $tanda = implode(',', array_fill(0, count($terpilih), '?'));
    $stmt = db()->prepare(
        "SELECT id_peserta, nama_lengkap FROM peserta
         WHERE status_peserta = 'Aktif' OR id_peserta IN ($tanda)
         ORDER BY nama_lengkap"
    );
    $stmt->execute($terpilih);

    return $stmt->fetchAll();
}

function simpan_trainer_training(PDO $pdo, string $idTraining, array $ids): void
{
    $pdo->prepare('DELETE FROM training_peserta WHERE id_training = ?')->execute([$idTraining]);
    if (!$ids) {
        return;
    }

    $bagian = [];
    $params = [];
    foreach ($ids as $id) {
        $bagian[] = '(?, ?, ?, ?)';
        $params[] = generate_id('TP');
        $params[] = $idTraining;
        $params[] = $id;
        $params[] = 'Trainer';
    }
    $pdo->prepare(
        'INSERT INTO training_peserta (id, id_training, id_peserta, peran) VALUES ' . implode(', ', $bagian)
    )->execute($params);
}

function pastikan_trainer_pemilik(array $trainer, ?string $actorPesertaId): void
{
    if ($actorPesertaId !== null && !in_array($actorPesertaId, $trainer, true)) {
        throw new RuntimeException('Anda harus termasuk dalam nama trainer.');
    }
}

function simpan_training(array $input, ?string $actorPesertaId): string
{
    $data = data_training($input);
    pastikan_trainer_pemilik($data['trainer'], $actorPesertaId);
    $trainingId = generate_id('TR');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO training (id_training, tanggal, id_sekolah, nama_sekolah, jenjang, materi, jumlah_siswa, pertemuan, catatan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $trainingId,
            $data['tanggal'],
            $data['id_sekolah'],
            $data['sekolah'],
            $data['jenjang'],
            $data['materi'],
            0,
            $data['pertemuan'],
            '',
        ]);
        simpan_trainer_training($pdo, $trainingId, $data['trainer']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Training berhasil disimpan.';
}

function tanggal_input(mixed $value, bool $wajib): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        if ($wajib) {
            return date('Y-m-d');
        }
        return null;
    }
    $parsed = DateTime::createFromFormat('Y-m-d', $value);
    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
        throw new RuntimeException('Tanggal tidak valid.');
    }

    return $value;
}

function waktu_input(mixed $value, bool $wajib, string $label): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        if ($wajib) {
            throw new RuntimeException($label . ' wajib diisi.');
        }
        return null;
    }
    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    $parsed = DateTime::createFromFormat('H:i:s', $value);
    if (!$parsed || $parsed->format('H:i:s') !== $value) {
        throw new RuntimeException($label . ' tidak valid.');
    }

    return $value;
}

function unlink_upload(string $fileName): void
{
    if ($fileName === '' || strpbrk($fileName, '/\\') !== false) {
        return;
    }
    global $config;
    $path = $config['upload_dir'] . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($path)) {
        unlink($path);
    }
}

function email_sudah_dipakai(string $email, ?string $kecualiUser, ?string $kecualiPeserta): bool
{
    $user = db()->prepare('SELECT id_user FROM users WHERE email = ? AND id_user <> ? LIMIT 1');
    $user->execute([$email, $kecualiUser ?? '']);
    if ($user->fetch()) {
        return true;
    }
    $peserta = db()->prepare('SELECT id_peserta FROM peserta WHERE email = ? AND id_peserta <> ? LIMIT 1');
    $peserta->execute([$email, $kecualiPeserta ?? '']);

    return (bool) $peserta->fetch();
}

function data_peserta(array $input, bool $buat): array
{
    $nama = trim((string) ($input['nama_lengkap'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $asal = trim((string) ($input['asal_sekolah'] ?? ''));
    $jurusan = trim((string) ($input['jurusan'] ?? ''));
    $telepon = trim((string) ($input['nomor_telepon'] ?? ''));
    $status = (string) ($input['status_peserta'] ?? 'Aktif');
    $role = (string) ($input['role'] ?? 'ANGGOTA');
    $mulai = tanggal_input($input['tanggal_mulai'] ?? '', false);
    $selesai = tanggal_input($input['tanggal_selesai'] ?? '', false);

    if ($nama === '' || mb_strlen($nama) > 150) {
        throw new RuntimeException('Nama lengkap wajib diisi dan maksimal 150 karakter.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        throw new RuntimeException('Email tidak valid.');
    }
    if (!in_array($status, ['Aktif', 'Nonaktif'], true)) {
        throw new RuntimeException('Status peserta tidak valid.');
    }
    if (!isset(daftar_role_orang()[$role])) {
        throw new RuntimeException('Role tidak valid.');
    }
    if (mb_strlen($asal) > 150 || mb_strlen($jurusan) > 150 || mb_strlen($telepon) > 30) {
        throw new RuntimeException('Asal sekolah, jurusan, atau nomor telepon terlalu panjang.');
    }
    if ($mulai !== null && $selesai !== null && $selesai < $mulai) {
        throw new RuntimeException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
    }
    if ($buat && strlen($password) < 6) {
        throw new RuntimeException('Password minimal 6 karakter.');
    }
    if (!$buat && $password !== '' && strlen($password) < 6) {
        throw new RuntimeException('Password baru minimal 6 karakter.');
    }

    return [
        'nama_lengkap' => $nama,
        'email' => $email,
        'password' => $password,
        'asal_sekolah' => $asal !== '' ? $asal : null,
        'jurusan' => $jurusan !== '' ? $jurusan : null,
        'nomor_telepon' => $telepon !== '' ? $telepon : null,
        'tanggal_mulai' => $mulai,
        'tanggal_selesai' => $selesai,
        'status_peserta' => $status,
        'role' => $role,
    ];
}

function simpan_peserta(array $input): string
{
    $data = data_peserta($input, true);
    if (email_sudah_dipakai($data['email'], null, null)) {
        throw new RuntimeException('Email sudah digunakan akun lain.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO peserta (id_peserta, email, nama_lengkap, asal_sekolah, jurusan, nomor_telepon, tanggal_mulai, tanggal_selesai, status_peserta)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            generate_id('PST'),
            $data['email'],
            $data['nama_lengkap'],
            $data['asal_sekolah'],
            $data['jurusan'],
            $data['nomor_telepon'],
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $data['status_peserta'],
        ]);
        $pdo->prepare(
            'INSERT INTO users (id_user, email, nama, password, role, status) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            generate_id('USR'),
            $data['email'],
            $data['nama_lengkap'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
            $data['status_peserta'],
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return label_role($data['role']) . ' berhasil ditambahkan. Akun login memakai email tersebut.';
}

function update_peserta(array $input): string
{
    $id = trim((string) ($input['id_peserta'] ?? ''));
    $lama = db()->prepare('SELECT * FROM peserta WHERE id_peserta = ? LIMIT 1');
    $lama->execute([$id]);
    $row = $lama->fetch();
    if (!$row) {
        throw new RuntimeException('Peserta tidak ditemukan.');
    }

    $data = data_peserta($input, false);
    $akun = db()->prepare("SELECT id_user FROM users WHERE email = ? AND role IN ('PEMBINA', 'STAFF', 'ANGGOTA') LIMIT 1");
    $akun->execute([$row['email']]);
    $idUser = $akun->fetchColumn() ?: null;
    if (!$idUser && $data['password'] === '') {
        throw new RuntimeException('Password wajib diisi karena akun login peserta belum ada.');
    }
    if (email_sudah_dipakai($data['email'], $idUser, $id)) {
        throw new RuntimeException('Email sudah digunakan akun lain.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE peserta
             SET email = ?, nama_lengkap = ?, asal_sekolah = ?, jurusan = ?, nomor_telepon = ?, tanggal_mulai = ?, tanggal_selesai = ?, status_peserta = ?
             WHERE id_peserta = ?'
        )->execute([
            $data['email'],
            $data['nama_lengkap'],
            $data['asal_sekolah'],
            $data['jurusan'],
            $data['nomor_telepon'],
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $data['status_peserta'],
            $id,
        ]);

        if ($idUser) {
            if ($data['password'] !== '') {
                $pdo->prepare('UPDATE users SET email = ?, nama = ?, status = ?, role = ?, password = ? WHERE id_user = ?')
                    ->execute([$data['email'], $data['nama_lengkap'], $data['status_peserta'], $data['role'], password_hash($data['password'], PASSWORD_DEFAULT), $idUser]);
            } else {
                $pdo->prepare('UPDATE users SET email = ?, nama = ?, status = ?, role = ? WHERE id_user = ?')
                    ->execute([$data['email'], $data['nama_lengkap'], $data['status_peserta'], $data['role'], $idUser]);
            }
        } else {
            $pdo->prepare(
                'INSERT INTO users (id_user, email, nama, password, role, status) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                generate_id('USR'),
                $data['email'],
                $data['nama_lengkap'],
                password_hash($data['password'] !== '' ? $data['password'] : bin2hex(random_bytes(4)), PASSWORD_DEFAULT),
                $data['role'],
                $data['status_peserta'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Data ' . label_role($data['role']) . ' berhasil diperbarui.';
}

function hapus_peserta(string $id): string
{
    $stmt = db()->prepare('SELECT * FROM peserta WHERE id_peserta = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Peserta tidak ditemukan.');
    }

    $cek = db()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM absensi WHERE id_peserta = ?) +
            (SELECT COUNT(*) FROM training_peserta WHERE id_peserta = ?)'
    );
    $cek->execute([$id, $id]);
    if ((int) $cek->fetchColumn() > 0) {
        throw new RuntimeException('Peserta masih punya absensi atau training. Ubah status menjadi Nonaktif.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM peserta WHERE id_peserta = ?')->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE email = ? AND role IN ('PEMBINA', 'STAFF', 'ANGGOTA')")->execute([$row['email']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Peserta berhasil dihapus.';
}

function pastikan_akses_training(string $idTraining, ?string $actorPesertaId): void
{
    $ada = db()->prepare('SELECT id_training FROM training WHERE id_training = ? LIMIT 1');
    $ada->execute([$idTraining]);
    if (!$ada->fetch()) {
        throw new RuntimeException('Training tidak ditemukan.');
    }
    if ($actorPesertaId === null) {
        return;
    }
    $milik = db()->prepare('SELECT 1 FROM training_peserta WHERE id_training = ? AND id_peserta = ? LIMIT 1');
    $milik->execute([$idTraining, $actorPesertaId]);
    if (!$milik->fetchColumn()) {
        throw new RuntimeException('Anda tidak dapat mengubah training ini.');
    }
}

function data_training(array $input): array
{
    $sekolah = sekolah_terpilih($input);
    $trainer = trainer_terpilih($input);
    $jenjang = trim((string) ($input['jenjang'] ?? ''));
    $materi = materi_silabus((string) ($input['id_silabus'] ?? ''));
    $pertemuan = $input['pertemuan'] ?? '';
    $jenjangOk = ['TK', 'SD', 'SMP', 'SMA', 'SMK'];

    if (!in_array($jenjang, $jenjangOk, true)) {
        throw new RuntimeException('Level materi tidak valid.');
    }
    if (!is_numeric($pertemuan) || (int) $pertemuan < 1) {
        throw new RuntimeException('Pertemuan tidak valid.');
    }

    return [
        'id_sekolah' => $sekolah['id_sekolah'],
        'sekolah' => $sekolah['nama_sekolah'],
        'jenjang' => $jenjang,
        'materi' => $materi,
        'trainer' => $trainer,
        'pertemuan' => (int) $pertemuan,
        'tanggal' => tanggal_input($input['tanggal'] ?? '', true),
    ];
}

function daftar_training(?string $idPeserta = null): array
{
    $trainer = "(SELECT GROUP_CONCAT(p.nama_lengkap ORDER BY p.nama_lengkap SEPARATOR ', ')
         FROM training_peserta tp
         JOIN peserta p ON p.id_peserta = tp.id_peserta
         WHERE tp.id_training = t.id_training AND tp.peran = 'Trainer')";

    if ($idPeserta !== null) {
        $stmt = db()->prepare(
            "SELECT t.id_training, t.tanggal, COALESCE(s.nama_sekolah, t.nama_sekolah) AS nama_sekolah,
                    t.jenjang, t.materi, t.pertemuan, mine.peran, {$trainer} AS nama_trainer
             FROM training t
             LEFT JOIN sekolah s ON s.id_sekolah = t.id_sekolah
             JOIN training_peserta mine ON mine.id_training = t.id_training AND mine.id_peserta = ?
             ORDER BY t.tanggal DESC, nama_sekolah"
        );
        $stmt->execute([$idPeserta]);

        return $stmt->fetchAll();
    }

    return db()->query(
        "SELECT t.id_training, t.tanggal, COALESCE(s.nama_sekolah, t.nama_sekolah) AS nama_sekolah,
                t.jenjang, t.materi, t.pertemuan, {$trainer} AS nama_trainer
         FROM training t
         LEFT JOIN sekolah s ON s.id_sekolah = t.id_sekolah
         ORDER BY t.tanggal DESC, nama_sekolah"
    )->fetchAll();
}

function update_training(string $idTraining, array $input, ?string $actorPesertaId): string
{
    pastikan_akses_training($idTraining, $actorPesertaId);
    $data = data_training($input);
    pastikan_trainer_pemilik($data['trainer'], $actorPesertaId);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE training SET tanggal = ?, id_sekolah = ?, nama_sekolah = ?, jenjang = ?, materi = ?, pertemuan = ? WHERE id_training = ?'
        )->execute([
            $data['tanggal'],
            $data['id_sekolah'],
            $data['sekolah'],
            $data['jenjang'],
            $data['materi'],
            $data['pertemuan'],
            $idTraining,
        ]);
        simpan_trainer_training($pdo, $idTraining, $data['trainer']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Training berhasil diperbarui.';
}

function hapus_training(string $idTraining, ?string $actorPesertaId): string
{
    pastikan_akses_training($idTraining, $actorPesertaId);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM training_peserta WHERE id_training = ?')->execute([$idTraining]);
        $pdo->prepare('DELETE FROM training WHERE id_training = ?')->execute([$idTraining]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Training berhasil dihapus.';
}

function simpan_absensi_diri(string $idPeserta, array $input, array $file = []): string
{
    if ($idPeserta === '') {
        throw new RuntimeException('Data peserta tidak ditemukan.');
    }
    $input['id_peserta'] = $idPeserta;
    if (trim((string) ($input['keterangan'] ?? '')) === '') {
        $input['keterangan'] = 'Absen peserta';
    }
    $tanggal = tanggal_input($input['tanggal'] ?? '', true);
    $ada = db()->prepare('SELECT id_absen FROM absensi WHERE id_peserta = ? AND tanggal = ? LIMIT 1');
    $ada->execute([$idPeserta, $tanggal]);
    $id = $ada->fetchColumn();
    if ($id) {
        $input['id_absen'] = (string) $id;
        update_absensi_manual($input, $file);

        return 'Absensi berhasil disimpan.';
    }

    simpan_absensi_manual($input, $file);

    return 'Absensi berhasil disimpan.';
}

function simpan_absensi_manual(array $input, array $file = []): string
{
    $idPeserta = trim((string) ($input['id_peserta'] ?? ''));
    $tanggal = tanggal_input($input['tanggal'] ?? '', true);
    $masuk = waktu_input($input['jam_masuk'] ?? '', true, 'Jam masuk');
    $pulang = waktu_input($input['jam_pulang'] ?? '', false, 'Jam pulang');
    $keterangan = trim((string) ($input['keterangan'] ?? ''));
    $agenda = trim((string) ($input['agenda'] ?? ''));

    if ($pulang !== null && $pulang < $masuk) {
        throw new RuntimeException('Jam pulang tidak boleh lebih awal dari jam masuk.');
    }
    if (mb_strlen($keterangan) > 255) {
        throw new RuntimeException('Keterangan terlalu panjang.');
    }
    if (mb_strlen($agenda) > 2000) {
        throw new RuntimeException('Deskripsi agenda terlalu panjang.');
    }

    $cekPeserta = db()->prepare('SELECT id_peserta FROM peserta WHERE id_peserta = ? LIMIT 1');
    $cekPeserta->execute([$idPeserta]);
    if (!$cekPeserta->fetch()) {
        throw new RuntimeException('Peserta tidak ditemukan.');
    }
    $duplikat = db()->prepare('SELECT id_absen FROM absensi WHERE id_peserta = ? AND tanggal = ? LIMIT 1');
    $duplikat->execute([$idPeserta, $tanggal]);
    if ($duplikat->fetch()) {
        throw new RuntimeException('Absensi peserta pada tanggal itu sudah ada.');
    }

    $namaFile = ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? null : store_upload($file);

    try {
        db()->prepare(
            'INSERT INTO absensi (id_absen, tanggal, id_peserta, jam_masuk, jam_pulang, status_kehadiran, keterangan, agenda, foto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            generate_id('ABS'),
            $tanggal,
            $idPeserta,
            $masuk,
            $pulang,
            'Hadir',
            $keterangan !== '' ? $keterangan : 'Input pembimbing',
            $agenda !== '' ? $agenda : null,
            $namaFile,
        ]);
    } catch (Throwable $e) {
        if ($namaFile) {
            unlink_upload($namaFile);
        }
        throw $e;
    }

    return 'Absensi berhasil ditambahkan.';
}

function update_absensi_manual(array $input, array $file = []): string
{
    $id = trim((string) ($input['id_absen'] ?? ''));
    $tanggal = tanggal_input($input['tanggal'] ?? '', true);
    $masuk = waktu_input($input['jam_masuk'] ?? '', true, 'Jam masuk');
    $pulang = waktu_input($input['jam_pulang'] ?? '', false, 'Jam pulang');
    $keterangan = trim((string) ($input['keterangan'] ?? ''));
    $agenda = trim((string) ($input['agenda'] ?? ''));

    if ($pulang !== null && $pulang < $masuk) {
        throw new RuntimeException('Jam pulang tidak boleh lebih awal dari jam masuk.');
    }
    if (mb_strlen($keterangan) > 255) {
        throw new RuntimeException('Keterangan terlalu panjang.');
    }
    if (mb_strlen($agenda) > 2000) {
        throw new RuntimeException('Deskripsi agenda terlalu panjang.');
    }

    $lama = db()->prepare('SELECT * FROM absensi WHERE id_absen = ? LIMIT 1');
    $lama->execute([$id]);
    $row = $lama->fetch();
    if (!$row) {
        throw new RuntimeException('Data absensi tidak ditemukan.');
    }
    $duplikat = db()->prepare('SELECT id_absen FROM absensi WHERE id_peserta = ? AND tanggal = ? AND id_absen <> ? LIMIT 1');
    $duplikat->execute([$row['id_peserta'], $tanggal, $id]);
    if ($duplikat->fetch()) {
        throw new RuntimeException('Absensi peserta pada tanggal itu sudah ada.');
    }

    $namaFile = ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? null : store_upload($file);
    $disetujui = $namaFile ? 0 : (int) ($row['disetujui'] ?? 0);
    try {
        db()->prepare(
            'UPDATE absensi SET tanggal = ?, jam_masuk = ?, jam_pulang = ?, keterangan = ?, agenda = ?, foto = COALESCE(?, foto), disetujui = ? WHERE id_absen = ?'
        )->execute([$tanggal, $masuk, $pulang, $keterangan, $agenda !== '' ? $agenda : null, $namaFile, $disetujui, $id]);
    } catch (Throwable $e) {
        if ($namaFile) {
            unlink_upload($namaFile);
        }
        throw $e;
    }
    if ($namaFile && !empty($row['foto']) && $row['foto'] !== $namaFile) {
        unlink_upload((string) $row['foto']);
    }

    return 'Absensi berhasil diperbarui.';
}

function hapus_absensi(string $id): string
{
    $lama = db()->prepare('SELECT foto FROM absensi WHERE id_absen = ? LIMIT 1');
    $lama->execute([$id]);
    $foto = $lama->fetchColumn();
    if ($foto === false) {
        throw new RuntimeException('Data absensi tidak ditemukan.');
    }
    $stmt = db()->prepare('DELETE FROM absensi WHERE id_absen = ?');
    $stmt->execute([$id]);
    if ($foto) {
        unlink_upload((string) $foto);
    }

    return 'Absensi berhasil dihapus.';
}

function setujui_absensi(string $id): string
{
    $stmt = db()->prepare('SELECT foto, disetujui FROM absensi WHERE id_absen = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Data absensi tidak ditemukan.');
    }
    if ((int) $row['disetujui'] === 1) {
        return 'Absensi sudah disetujui.';
    }

    db()->prepare('UPDATE absensi SET disetujui = 1, foto = NULL WHERE id_absen = ?')->execute([$id]);
    if (!empty($row['foto'])) {
        unlink_upload((string) $row['foto']);
    }

    return 'Absensi disetujui. Foto dihapus.';
}

function dashboard_admin(): array
{
    $today = date('Y-m-d');
    $pdo = db();

    $pesertaAktif = (int) $pdo->query("SELECT COUNT(*) FROM peserta WHERE status_peserta = 'Aktif'")->fetchColumn();

    $hadirStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM absensi a
         JOIN peserta p ON p.id_peserta = a.id_peserta
         WHERE a.tanggal = ? AND p.status_peserta = 'Aktif' AND a.jam_masuk IS NOT NULL"
    );
    $hadirStmt->execute([$today]);
    $hadirHariIni = (int) $hadirStmt->fetchColumn();

    $trainingStmt = $pdo->prepare('SELECT COUNT(*) FROM training WHERE tanggal = ?');
    $trainingStmt->execute([$today]);
    $trainingHariIni = (int) $trainingStmt->fetchColumn();

    $monitoringStmt = $pdo->prepare(
        "SELECT p.nama_lengkap, a.jam_masuk
         FROM peserta p
         LEFT JOIN absensi a ON a.id_peserta = p.id_peserta AND a.tanggal = ?
         WHERE p.status_peserta = 'Aktif'
         ORDER BY p.nama_lengkap"
    );
    $monitoringStmt->execute([$today]);
    $monitoring = [];
    foreach ($monitoringStmt->fetchAll() as $row) {
        $hadir = !empty($row['jam_masuk']);
        $monitoring[] = [
            'nama' => $row['nama_lengkap'],
            'status' => $hadir ? 'Hadir' : 'Belum Absen',
            'jam_masuk' => $hadir ? fmt_time($row['jam_masuk']) : '-',
        ];
    }

    $todayTraining = $pdo->prepare(
        'SELECT nama_sekolah, jenjang, materi, jumlah_siswa FROM training WHERE tanggal = ? ORDER BY nama_sekolah'
    );
    $todayTraining->execute([$today]);

    $start = date('Y-m-d', strtotime('-6 days'));
    $chartStmt = $pdo->prepare('SELECT tanggal, COUNT(*) AS total FROM absensi WHERE tanggal BETWEEN ? AND ? GROUP BY tanggal');
    $chartStmt->execute([$start, $today]);
    $map = [];
    foreach ($chartStmt->fetchAll() as $row) {
        $map[$row['tanggal']] = (int) $row['total'];
    }

    $labels = [];
    $values = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $labels[] = date('d/m', strtotime($day));
        $values[] = $map[$day] ?? 0;
    }

    return [
        'peserta_aktif' => $pesertaAktif,
        'hadir_hari_ini' => $hadirHariIni,
        'belum_absen' => max(0, $pesertaAktif - $hadirHariIni),
        'training_hari_ini' => $trainingHariIni,
        'monitoring' => $monitoring,
        'training_today' => $todayTraining->fetchAll(),
        'chart_labels' => $labels,
        'chart_values' => $values,
    ];
}

function dashboard_peserta(string $idPeserta): array
{
    $peserta = get_peserta($idPeserta);
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $hadirStmt = db()->prepare(
        'SELECT COUNT(*) FROM absensi WHERE id_peserta = ? AND tanggal BETWEEN ? AND ? AND jam_masuk IS NOT NULL'
    );
    $hadirStmt->execute([$idPeserta, $monthStart, $today]);

    $absenStmt = db()->prepare('SELECT tanggal, jam_masuk, jam_pulang, agenda, keterangan, foto FROM absensi WHERE id_peserta = ? AND tanggal = ? LIMIT 1');
    $absenStmt->execute([$idPeserta, $today]);
    $absen = $absenStmt->fetch() ?: null;

    $trainingStmt = db()->prepare(
        'SELECT t.tanggal, t.nama_sekolah, t.materi, tp.peran
         FROM training_peserta tp
         JOIN training t ON t.id_training = tp.id_training
         WHERE tp.id_peserta = ?
         ORDER BY t.tanggal DESC
         LIMIT 5'
    );
    $trainingStmt->execute([$idPeserta]);

    return [
        'peserta' => $peserta,
        'hadir_bulan_ini' => (int) $hadirStmt->fetchColumn(),
        'absen_hari_ini' => $absen,
        'training' => $trainingStmt->fetchAll(),
    ];
}

function user_can_view_training(string $idTraining): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (is_staff($user)) {
        return true;
    }
    if (empty($user['id_peserta'])) {
        return false;
    }
    $stmt = db()->prepare('SELECT 1 FROM training_peserta WHERE id_training = ? AND id_peserta = ? LIMIT 1');
    $stmt->execute([$idTraining, $user['id_peserta']]);
    return (bool) $stmt->fetchColumn();
}

function hari_notulen(): array
{
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
}

function baris_notulen_kosong(): array
{
    $baris = [];
    foreach (hari_notulen() as $hari) {
        $baris[] = ['hari' => $hari, 'jenis' => 'kegiatan', 'nama' => '', 'isi' => ''];
        $baris[] = ['hari' => $hari, 'jenis' => 'catatan', 'nama' => '', 'isi' => ''];
    }

    return $baris;
}

function data_notulen(array $input): array
{
    $judul = trim((string) ($input['judul'] ?? ''));
    $cabang = trim((string) ($input['cabang'] ?? ''));
    $pekan = trim((string) ($input['pekan'] ?? ''));
    $mulai = tanggal_input($input['tanggal_mulai'] ?? '', true);
    $selesai = tanggal_input($input['tanggal_selesai'] ?? '', true);
    if ($judul === '' || mb_strlen($judul) > 255) {
        throw new RuntimeException('Judul wajib diisi dan maksimal 255 karakter.');
    }
    if (mb_strlen($cabang) > 150 || mb_strlen($pekan) > 150) {
        throw new RuntimeException('Cabang atau pekan terlalu panjang.');
    }
    if ($selesai < $mulai) {
        throw new RuntimeException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
    }

    $hariOk = hari_notulen();
    $baris = [];
    $urutan = 0;
    foreach (['kegiatan', 'catatan'] as $jenis) {
        $grup = $input[$jenis] ?? [];
        if (!is_array($grup)) {
            continue;
        }
        foreach ($hariOk as $hari) {
            $blok = $grup[$hari] ?? null;
            if (!is_array($blok)) {
                continue;
            }
            $namaList = is_array($blok['nama'] ?? null) ? $blok['nama'] : [];
            $isiList = is_array($blok['isi'] ?? null) ? $blok['isi'] : [];
            $jumlah = max(count($namaList), count($isiList));
            for ($i = 0; $i < $jumlah; $i++) {
                $nama = trim((string) ($namaList[$i] ?? ''));
                $isi = trim((string) ($isiList[$i] ?? ''));
                if ($nama === '' && $isi === '') {
                    continue;
                }
                if ($nama === '' || $isi === '') {
                    throw new RuntimeException('Nama dan isi pada ' . $hari . ' harus diisi bersama.');
                }
                if (mb_strlen($nama) > 150) {
                    throw new RuntimeException('Nama pada ' . $hari . ' maksimal 150 karakter.');
                }
                if (mb_strlen($isi) > 4000) {
                    throw new RuntimeException('Isi pada ' . $hari . ' terlalu panjang.');
                }
                $baris[] = [
                    'hari' => $hari,
                    'jenis' => $jenis,
                    'nama' => $nama,
                    'isi' => $isi,
                    'urutan' => $urutan,
                ];
                $urutan++;
            }
        }
    }
    if ($baris === []) {
        throw new RuntimeException('Isi minimal satu kegiatan atau catatan.');
    }

    return [
        'judul' => $judul,
        'cabang' => $cabang !== '' ? $cabang : null,
        'pekan' => $pekan !== '' ? $pekan : null,
        'tanggal_mulai' => $mulai,
        'tanggal_selesai' => $selesai,
        'baris' => $baris,
    ];
}

function simpan_baris_notulen(PDO $pdo, string $idNotulen, array $baris): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notulen_baris (id_baris, id_notulen, hari, jenis, nama, isi, urutan) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($baris as $item) {
        $stmt->execute([
            generate_id('NTB'),
            $idNotulen,
            $item['hari'],
            $item['jenis'],
            $item['nama'],
            $item['isi'],
            $item['urutan'],
        ]);
    }
}

function simpan_notulen(array $input, string $idUser, string $namaNotulen): string
{
    $data = data_notulen($input);
    $id = generate_id('NTL');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO notulen (id_notulen, judul, cabang, pekan, tanggal_mulai, tanggal_selesai, id_user, nama_notulen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id,
            $data['judul'],
            $data['cabang'],
            $data['pekan'],
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $idUser,
            $namaNotulen,
        ]);
        simpan_baris_notulen($pdo, $id, $data['baris']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Notulen berhasil disimpan.';
}

function update_notulen(array $input, bool $staff, string $idUser): string
{
    $id = trim((string) ($input['id_notulen'] ?? ''));
    $lama = db()->prepare('SELECT id_user FROM notulen WHERE id_notulen = ? LIMIT 1');
    $lama->execute([$id]);
    $pemilik = $lama->fetchColumn();
    if ($pemilik === false) {
        throw new RuntimeException('Notulen tidak ditemukan.');
    }
    if (!$staff && (string) $pemilik !== $idUser) {
        throw new RuntimeException('Anda tidak dapat mengubah notulen ini.');
    }
    $data = data_notulen($input);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE notulen SET judul = ?, cabang = ?, pekan = ?, tanggal_mulai = ?, tanggal_selesai = ? WHERE id_notulen = ?'
        )->execute([
            $data['judul'],
            $data['cabang'],
            $data['pekan'],
            $data['tanggal_mulai'],
            $data['tanggal_selesai'],
            $id,
        ]);
        $pdo->prepare('DELETE FROM notulen_baris WHERE id_notulen = ?')->execute([$id]);
        simpan_baris_notulen($pdo, $id, $data['baris']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Notulen berhasil diperbarui.';
}

function hapus_notulen(string $id, bool $staff, string $idUser): string
{
    $lama = db()->prepare('SELECT id_user FROM notulen WHERE id_notulen = ? LIMIT 1');
    $lama->execute([$id]);
    $pemilik = $lama->fetchColumn();
    if ($pemilik === false) {
        throw new RuntimeException('Notulen tidak ditemukan.');
    }
    if (!$staff && (string) $pemilik !== $idUser) {
        throw new RuntimeException('Anda tidak dapat menghapus notulen ini.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM notulen_baris WHERE id_notulen = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM notulen WHERE id_notulen = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return 'Notulen berhasil dihapus.';
}

function ambil_notulen(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM notulen WHERE id_notulen = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $baris = db()->prepare('SELECT hari, jenis, nama, isi, urutan FROM notulen_baris WHERE id_notulen = ? ORDER BY urutan, hari');
    $baris->execute([$id]);
    $row['baris'] = $baris->fetchAll();

    return $row;
}
