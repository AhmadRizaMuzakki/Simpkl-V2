<?php

require __DIR__ . '/includes/init.php';
require_role(role_kontrol());

$user = current_user();
$staff = is_staff($user);
$kosong = [
    'id_kit' => '',
    'nama' => '',
    'jumlah' => '',
];
$form = $kosong;
$formError = null;
$openForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$staff) {
        flash('error', 'Anda tidak memiliki akses.');
        redirect('kit.php');
    }
    $aksi = (string) ($_POST['aksi'] ?? '');
    try {
        if ($aksi === 'hapus') {
            flash('success', hapus_kit((string) ($_POST['id_kit'] ?? '')));
        } elseif ($aksi === 'ubah') {
            flash('success', update_kit($_POST));
        } else {
            flash('success', simpan_kit($_POST));
        }
        redirect('kit.php');
    } catch (PDOException $e) {
        $formError = 'Gagal menyimpan data ke database.';
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = $aksi !== 'hapus';
        if ($aksi === 'hapus') {
            flash('error', $formError);
            redirect('kit.php');
        }
    } catch (RuntimeException $e) {
        if ($aksi === 'hapus') {
            flash('error', $e->getMessage());
            redirect('kit.php');
        }
        $formError = $e->getMessage();
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = true;
    }
} elseif ($staff && !empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT id_kit, nama, jumlah FROM kit WHERE id_kit = ? LIMIT 1');
    $stmt->execute([(string) $_GET['edit']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Kit tidak ditemukan.');
        redirect('kit.php');
    }
    $form = array_merge($kosong, $row);
    $openForm = true;
} elseif ($staff && isset($_GET['baru'])) {
    $openForm = true;
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id_kit, nama, jumlah FROM kit';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE nama LIKE ?';
    $params[] = like_query($q);
}
$sql .= ' ORDER BY nama';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Kit';
$activeMenu = 'kit';
$editing = $form['id_kit'] !== '';
require __DIR__ . '/includes/header.php';
?>
<?php if ($openForm): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-box me-1"></i><?= $editing ? 'Ubah Kit' : 'Tambah Kit' ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('kit.php')) ?>">Batal</a>
    </div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('kit.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editing ? 'ubah' : 'tambah' ?>">
            <input type="hidden" name="id_kit" value="<?= e($form['id_kit']) ?>">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="namaKit">Nama Kit</label>
                    <input class="form-control" id="namaKit" name="nama" value="<?= e($form['nama']) ?>" maxlength="150" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="jumlahKit">Jumlah</label>
                    <input class="form-control" id="jumlahKit" name="jumlah" type="number" min="0" max="9999" step="1" value="<?= e((string) $form['jumlah']) ?>" required>
                </div>
            </div>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Tambah Kit' ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <form class="row g-2 align-items-center flex-grow-1" method="get" action="<?= e(base_url('kit.php')) ?>">
        <div class="col-md-6">
            <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Cari kit...">
        </div>
        <div class="col-md-auto">
            <button class="btn btn-primary" type="submit">Cari</button>
            <?php if ($q !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(base_url('kit.php')) ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($staff): ?>
        <a class="btn btn-primary" href="<?= e(base_url('kit.php?baru=1')) ?>">Tambah Kit</a>
    <?php endif; ?>
</div>

<?php if (!$rows): ?>
    <p class="text-muted">Belum ada kit.</p>
<?php else: ?>
    <div class="row">
        <?php foreach ($rows as $row): ?>
            <div class="col-lg-4 col-12 mb-4">
                <div class="card kit-card kit-junior h-100">
                    <div class="card-body">
                        <?php $sisa = (int) $row['jumlah']; ?>
                        <div class="kit-card-head">
                            <span class="kit-card-icon"><i class="fas fa-box"></i></span>
                            <div>
                                <div class="kit-card-kategori">Kit</div>
                                <div class="kit-card-nama"><?= e($row['nama']) ?></div>
                            </div>
                            <?php if ($sisa > 0): ?>
                                <span class="badge bg-primary">Sisa <?= $sisa ?></span>
                            <?php else: ?>
                                <span class="badge bg-danger">Habis</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($staff): ?>
                        <div class="card-footer">
                            <div class="kit-aksi ms-auto">
                                <a class="btn btn-sm btn-warning" href="<?= e(base_url('kit.php?edit=' . urlencode($row['id_kit']))) ?>">Ubah</a>
                                <form method="post" action="<?= e(base_url('kit.php')) ?>" onsubmit="return confirm('Hapus kit ini?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id_kit" value="<?= e($row['id_kit']) ?>">
                                    <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
