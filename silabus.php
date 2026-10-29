<?php

require __DIR__ . '/includes/init.php';
require_role(role_kontrol());

$jenjangOk = daftar_level_materi();
$semesterOk = ['Ganjil', 'Genap'];
$kosong = [
    'id_silabus' => '',
    'materi' => '',
    'jenjang' => 'Junior',
    'semester' => 'Ganjil',
    'tahun' => date('Y'),
];
$form = $kosong;
$formError = null;
$openForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? '');
    try {
        if ($aksi === 'hapus') {
            flash('success', hapus_silabus((string) ($_POST['id_silabus'] ?? '')));
        } elseif ($aksi === 'ubah') {
            flash('success', update_silabus($_POST));
        } else {
            flash('success', simpan_silabus($_POST));
        }
        redirect('silabus.php');
    } catch (PDOException $e) {
        $formError = 'Gagal menyimpan data ke database.';
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = $aksi !== 'hapus';
        if ($aksi === 'hapus') {
            flash('error', $formError);
            redirect('silabus.php');
        }
    } catch (RuntimeException $e) {
        if ($aksi === 'hapus') {
            flash('error', $e->getMessage());
            redirect('silabus.php');
        }
        $formError = $e->getMessage();
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = true;
    }
} elseif (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT id_silabus, materi, jenjang, semester, tahun FROM silabus WHERE id_silabus = ? LIMIT 1');
    $stmt->execute([(string) $_GET['edit']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Silabus tidak ditemukan.');
        redirect('silabus.php');
    }
    $form = array_merge($kosong, $row);
    $form['tahun'] = (string) $row['tahun'];
    $openForm = true;
} elseif (isset($_GET['baru'])) {
    $openForm = true;
}

$q = trim((string) ($_GET['q'] ?? ''));
$jenjang = (string) ($_GET['jenjang'] ?? '');
if (!in_array($jenjang, $jenjangOk, true)) {
    $jenjang = '';
}
$sql = 'SELECT id_silabus, materi, jenjang, semester, tahun FROM silabus WHERE 1 = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND materi LIKE ?';
    $params[] = like_query($q);
}
if ($jenjang !== '') {
    $sql .= ' AND jenjang = ?';
    $params[] = $jenjang;
}
$sql .= ' ORDER BY tahun DESC, FIELD(jenjang, \'Junior\', \'Sensing\', \'Exciting\'), semester, materi';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Silabus';
$activeMenu = 'silabus';
$editing = $form['id_silabus'] !== '';
require __DIR__ . '/includes/header.php';
?>
<?php if ($openForm): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-book-open me-1"></i><?= $editing ? 'Ubah Silabus' : 'Tambah Silabus' ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('silabus.php')) ?>">Batal</a>
    </div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('silabus.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editing ? 'ubah' : 'tambah' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id_silabus" value="<?= e($form['id_silabus']) ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="silabusMateri">Materi</label>
                    <input class="form-control" id="silabusMateri" name="materi" value="<?= e($form['materi']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="silabusJenjang">Level Materi</label>
                    <select class="form-select" id="silabusJenjang" name="jenjang" required>
                        <?php foreach ($jenjangOk as $opsi): ?>
                            <option value="<?= e($opsi) ?>" <?= $form['jenjang'] === $opsi ? 'selected' : '' ?>><?= e($opsi) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="silabusSemester">Semester</label>
                    <select class="form-select" id="silabusSemester" name="semester" required>
                        <?php foreach ($semesterOk as $opsi): ?>
                            <option value="<?= e($opsi) ?>" <?= $form['semester'] === $opsi ? 'selected' : '' ?>><?= e($opsi) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="silabusTahun">Tahun</label>
                    <input class="form-control" id="silabusTahun" name="tahun" type="number" min="2000" max="2100" value="<?= e((string) $form['tahun']) ?>" required>
                </div>
            </div>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Tambah Silabus' ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-1"></i>Silabus</span>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('silabus.php?baru=1')) ?>">Tambah Silabus</a>
    </div>
    <div class="card-body">
        <form class="row g-2 align-items-center mb-3" method="get" action="<?= e(base_url('silabus.php')) ?>">
            <div class="col-md-4">
                <select class="form-select" name="jenjang">
                    <option value="">Semua level</option>
                    <?php foreach ($jenjangOk as $opsi): ?>
                        <option value="<?= e($opsi) ?>" <?= $jenjang === $opsi ? 'selected' : '' ?>><?= e($opsi) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Cari materi...">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary" type="submit">Cari</button>
                <a class="btn btn-outline-secondary" href="<?= e(base_url('silabus.php')) ?>">Reset</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="flat-table">
                <thead>
                    <tr><th>Materi</th><th>Level Materi</th><th>Semester</th><th>Tahun</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="5" class="text-center text-muted">Belum ada silabus.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['materi']) ?></td>
                        <td><?= e($row['jenjang']) ?></td>
                        <td><?= e($row['semester']) ?></td>
                        <td><?= (int) $row['tahun'] ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-warning" href="<?= e(base_url('silabus.php?edit=' . urlencode($row['id_silabus']))) ?>">Ubah</a>
                            <form class="d-inline" method="post" action="<?= e(base_url('silabus.php')) ?>" onsubmit="return confirm('Hapus silabus ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_silabus" value="<?= e($row['id_silabus']) ?>">
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
