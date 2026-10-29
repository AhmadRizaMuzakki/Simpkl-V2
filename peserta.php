<?php

require __DIR__ . '/includes/init.php';
require_role(role_kontrol());

$kosong = [
    'id_peserta' => '',
    'nama_lengkap' => '',
    'email' => '',
    'password' => '',
    'asal_sekolah' => '',
    'jurusan' => '',
    'nomor_telepon' => '',
    'tanggal_mulai' => '',
    'tanggal_selesai' => '',
    'status_peserta' => 'Aktif',
    'role' => 'ANGGOTA',
];
$form = $kosong;
$formError = null;
$openForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? '');
    try {
        if ($aksi === 'hapus') {
            flash('success', hapus_peserta((string) ($_POST['id_peserta'] ?? '')));
        } elseif ($aksi === 'ubah') {
            flash('success', update_peserta($_POST));
        } else {
            flash('success', simpan_peserta($_POST));
        }
        redirect('peserta.php');
    } catch (PDOException $e) {
        $formError = 'Gagal menyimpan data ke database.';
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = $aksi !== 'hapus';
        if ($aksi === 'hapus') {
            flash('error', $formError);
            redirect('peserta.php');
        }
    } catch (RuntimeException $e) {
        if ($aksi === 'hapus') {
            flash('error', $e->getMessage());
            redirect('peserta.php');
        }
        $formError = $e->getMessage();
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = true;
    }
} elseif (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM peserta WHERE id_peserta = ? LIMIT 1');
    $stmt->execute([(string) $_GET['edit']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Peserta tidak ditemukan.');
        redirect('peserta.php');
    }
    $form = array_merge($kosong, $row);
    $form['password'] = '';
    $roleAkun = (string) ($row['role'] ?? '');
    if (!isset(daftar_role_orang()[$roleAkun])) {
        $akunRole = db()->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
        $akunRole->execute([(string) $row['email']]);
        $roleAkun = (string) ($akunRole->fetchColumn() ?: 'ANGGOTA');
    }
    $form['role'] = isset(daftar_role_orang()[$roleAkun]) ? $roleAkun : 'ANGGOTA';
    $openForm = true;
} elseif (isset($_GET['baru'])) {
    $openForm = true;
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'SEMUA');
if (!in_array($status, ['SEMUA', 'Aktif', 'Nonaktif'], true)) {
    $status = 'SEMUA';
}

$detail = null;
if (!empty($_GET['id'])) {
    $detail = get_peserta((string) $_GET['id']);
    if (!$detail) {
        flash('error', 'Peserta tidak ditemukan.');
        redirect('peserta.php');
    }
}

$sql = 'SELECT p.*, COALESCE(u.role, \'ANGGOTA\') AS role,
            (SELECT COUNT(*) FROM absensi a WHERE a.id_peserta = p.id_peserta) AS total_hadir
        FROM peserta p
        LEFT JOIN users u ON u.email = p.email
        WHERE 1 = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND p.nama_lengkap LIKE ?';
    $params[] = like_query($q);
}
if ($status !== 'SEMUA') {
    $sql .= ' AND p.status_peserta = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY p.nama_lengkap';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = $detail ? 'Detail Peserta' : 'Daftar Peserta';
$activeMenu = 'peserta';
require __DIR__ . '/includes/header.php';

function status_link(string $label, string $value, string $current, string $q, string $class): string
{
    $params = ['status' => $value];
    if ($q !== '') {
        $params['q'] = $q;
    }
    $active = $current === $value ? '' : 'outline-';
    $href = base_url('peserta.php?' . http_build_query($params));
    return '<a class="btn btn-sm btn-' . $active . $class . '" href="' . e($href) . '">' . e($label) . '</a>';
}

$editing = $form['id_peserta'] !== '';
?>
<?php if ($openForm): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-plus me-1"></i><?= $editing ? 'Ubah Peserta' : 'Tambah Peserta' ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('peserta.php')) ?>">Batal</a>
    </div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('peserta.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editing ? 'ubah' : 'tambah' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id_peserta" value="<?= e($form['id_peserta']) ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="namaPeserta">Nama Lengkap</label>
                    <input class="form-control" id="namaPeserta" name="nama_lengkap" value="<?= e($form['nama_lengkap']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="emailPeserta">Email</label>
                    <input class="form-control" id="emailPeserta" name="email" type="email" value="<?= e($form['email']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="passwordPeserta">Password <?= $editing ? '(kosongkan jika tidak diubah)' : '' ?></label>
                    <input class="form-control" id="passwordPeserta" name="password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required minlength="6"' ?>>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="rolePeserta">Role</label>
                    <select class="form-select select-polos" id="rolePeserta" name="role">
                        <?php foreach (daftar_role_orang() as $kode => $namaRole): ?>
                            <option value="<?= e($kode) ?>" <?= $form['role'] === $kode ? 'selected' : '' ?>><?= e($namaRole) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="statusPeserta">Status</label>
                    <select class="form-select" id="statusPeserta" name="status_peserta">
                        <option value="Aktif" <?= $form['status_peserta'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Nonaktif" <?= $form['status_peserta'] === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="asalPeserta">Asal Sekolah</label>
                    <input class="form-control" id="asalPeserta" name="asal_sekolah" value="<?= e($form['asal_sekolah']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="jurusanPeserta">Jurusan</label>
                    <input class="form-control" id="jurusanPeserta" name="jurusan" value="<?= e($form['jurusan']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="teleponPeserta">Nomor Telepon</label>
                    <input class="form-control" id="teleponPeserta" name="nomor_telepon" value="<?= e($form['nomor_telepon']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="mulaiPeserta">Tanggal Mulai</label>
                    <input class="form-control" id="mulaiPeserta" name="tanggal_mulai" type="date" value="<?= e($form['tanggal_mulai']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="selesaiPeserta">Tanggal Selesai</label>
                    <input class="form-control" id="selesaiPeserta" name="tanggal_selesai" type="date" value="<?= e($form['tanggal_selesai']) ?>">
                </div>
            </div>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Tambah Peserta' ?></button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($detail): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-id-card me-1"></i>Detail Peserta</span>
        <span class="d-flex gap-2">
            <a class="btn btn-sm btn-warning" href="<?= e(base_url('peserta.php?edit=' . urlencode($detail['id_peserta']))) ?>">Ubah</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('peserta.php')) ?>">Kembali</a>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="text-muted small">Nama Peserta</div>
                    <div class="fs-5 fw-bold"><?= e($detail['nama_lengkap']) ?></div>
                    <div class="text-muted"><?= e($detail['email']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Role</div>
                    <div class="fw-semibold"><?= e(label_role((string) ($detail['role'] ?? 'ANGGOTA'))) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold"><?= $detail['status_peserta'] === 'Aktif' ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>' ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Total Hadir</div>
                    <div class="fw-semibold"><?= (int) $detail['total_hadir'] ?> hari</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Total Training</div>
                    <div class="fw-semibold"><?= (int) $detail['total_training'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Terakhir Absen</div>
                    <div class="fw-semibold"><?= e(fmt_date($detail['terakhir_absen'])) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Asal</div>
                    <div class="fw-semibold"><?= e($detail['asal_sekolah'] ?: '-') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Jurusan</div>
                    <div class="fw-semibold"><?= e($detail['jurusan'] ?: '-') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Telepon</div>
                    <div class="fw-semibold"><?= e($detail['nomor_telepon'] ?: '-') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small">Periode</div>
                    <div class="fw-semibold"><?= e(fmt_date($detail['tanggal_mulai'])) ?> – <?= e(fmt_date($detail['tanggal_selesai'])) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-1"></i>Data Peserta</span>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('peserta.php?baru=1')) ?>">Tambah Peserta</a>
    </div>
    <div class="card-body">
        <form class="row g-2 align-items-center mb-3" method="get" action="<?= e(base_url('peserta.php')) ?>">
            <div class="col-md-6">
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Cari peserta...">
            </div>
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <div class="col-md-auto">
                <button class="btn btn-primary" type="submit">Cari</button>
            </div>
        </form>
        <div class="d-flex gap-2 mb-3">
            <?= status_link('Semua', 'SEMUA', $status, $q, 'primary') ?>
            <?= status_link('Aktif', 'Aktif', $status, $q, 'success') ?>
            <?= status_link('Nonaktif', 'Nonaktif', $status, $q, 'danger') ?>
        </div>
        <div class="table-responsive">
            <table class="flat-table">
                <thead>
                    <tr><th>Nama</th><th>Role</th><th>Asal</th><th>Status</th><th>Hadir</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted">Tidak ada peserta.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['nama_lengkap']) ?></td>
                        <td><?= e(label_role((string) ($row['role'] ?? 'ANGGOTA'))) ?></td>
                        <td><?= e($row['asal_sekolah'] ?: '-') ?></td>
                        <td>
                            <?php if ($row['status_peserta'] === 'Aktif'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $row['total_hadir'] ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-primary" href="<?= e(base_url('peserta.php?id=' . urlencode($row['id_peserta']))) ?>">Detail</a>
                            <a class="btn btn-sm btn-warning" href="<?= e(base_url('peserta.php?edit=' . urlencode($row['id_peserta']))) ?>">Ubah</a>
                            <form class="d-inline" method="post" action="<?= e(base_url('peserta.php')) ?>" onsubmit="return confirm('Hapus peserta ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_peserta" value="<?= e($row['id_peserta']) ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
