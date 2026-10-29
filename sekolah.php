<?php

require __DIR__ . '/includes/init.php';
require_role(role_kontrol());

$kosong = [
    'id_sekolah' => '',
    'nama_sekolah' => '',
    'alamat' => '',
];
$form = $kosong;
$formError = null;
$openForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? '');
    try {
        if ($aksi === 'hapus') {
            flash('success', hapus_sekolah((string) ($_POST['id_sekolah'] ?? '')));
        } elseif ($aksi === 'ubah') {
            flash('success', update_sekolah($_POST));
        } else {
            flash('success', simpan_sekolah($_POST));
        }
        redirect('sekolah.php');
    } catch (PDOException $e) {
        $formError = 'Gagal menyimpan data ke database.';
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = $aksi !== 'hapus';
        if ($aksi === 'hapus') {
            flash('error', $formError);
            redirect('sekolah.php');
        }
    } catch (RuntimeException $e) {
        if ($aksi === 'hapus') {
            flash('error', $e->getMessage());
            redirect('sekolah.php');
        }
        $formError = $e->getMessage();
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = true;
    }
} elseif (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT id_sekolah, nama_sekolah, alamat FROM sekolah WHERE id_sekolah = ? LIMIT 1');
    $stmt->execute([(string) $_GET['edit']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Sekolah tidak ditemukan.');
        redirect('sekolah.php');
    }
    $form = array_merge($kosong, $row);
    $form['alamat'] = (string) ($row['alamat'] ?? '');
    $openForm = true;
} elseif (isset($_GET['baru'])) {
    $openForm = true;
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id_sekolah, nama_sekolah, alamat FROM sekolah';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE nama_sekolah LIKE ?';
    $params[] = like_query($q);
}
$sql .= ' ORDER BY nama_sekolah';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Data Sekolah';
$activeMenu = 'sekolah';
$editing = $form['id_sekolah'] !== '';
require __DIR__ . '/includes/header.php';
?>
<?php if ($openForm): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-school me-1"></i><?= $editing ? 'Ubah Sekolah' : 'Tambah Sekolah' ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('sekolah.php')) ?>">Batal</a>
    </div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('sekolah.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editing ? 'ubah' : 'tambah' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id_sekolah" value="<?= e($form['id_sekolah']) ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="namaSekolah">Nama Sekolah</label>
                    <input class="form-control" id="namaSekolah" name="nama_sekolah" value="<?= e($form['nama_sekolah']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="alamatSekolah">Alamat</label>
                    <input class="form-control" id="alamatSekolah" name="alamat" value="<?= e($form['alamat']) ?>">
                </div>
            </div>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Tambah Sekolah' ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-1"></i>Data Sekolah</span>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('sekolah.php?baru=1')) ?>">Tambah Sekolah</a>
    </div>
    <div class="card-body">
        <form class="row g-2 align-items-center mb-3" method="get" action="<?= e(base_url('sekolah.php')) ?>">
            <div class="col-md-6">
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Cari sekolah...">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary" type="submit">Cari</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="flat-table">
                <thead>
                    <tr><th>Nama Sekolah</th><th>Alamat</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="3" class="text-center text-muted">Belum ada data sekolah.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['nama_sekolah']) ?></td>
                        <td><?= e($row['alamat'] ?: '-') ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-warning" href="<?= e(base_url('sekolah.php?edit=' . urlencode($row['id_sekolah']))) ?>">Ubah</a>
                            <form class="d-inline" method="post" action="<?= e(base_url('sekolah.php')) ?>" onsubmit="return confirm('Hapus sekolah ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_sekolah" value="<?= e($row['id_sekolah']) ?>">
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
