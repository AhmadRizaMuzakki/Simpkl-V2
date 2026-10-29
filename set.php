<?php

require __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$staff = is_staff($user);
$level = daftar_level_materi();
$kosong = [
    'id_kit' => '',
    'nama' => '',
    'jenjang' => $level[0],
];
$form = $kosong;
$formError = null;
$openForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? '');
    if (!$staff && $aksi !== 'status') {
        flash('error', 'Anda tidak memiliki akses.');
        redirect('set.php');
    }
    try {
        if ($aksi === 'status') {
            flash('success', ubah_status_set((string) ($_POST['id_kit'] ?? ''), (string) ($_POST['status'] ?? '')));
        } elseif ($aksi === 'addon') {
            flash('success', tambah_addon_set((string) ($_POST['id_kit'] ?? ''), (string) ($_POST['id_addon'] ?? ''), $_POST['jumlah'] ?? ''));
        } elseif ($aksi === 'lepas') {
            flash('success', lepas_addon_set((string) ($_POST['id_kit'] ?? ''), (string) ($_POST['id_addon'] ?? '')));
        } elseif ($aksi === 'hapus') {
            flash('success', hapus_set((string) ($_POST['id_kit'] ?? '')));
        } elseif ($aksi === 'ubah') {
            flash('success', update_set($_POST));
        } else {
            flash('success', simpan_set($_POST));
        }
        redirect('set.php');
    } catch (PDOException $e) {
        $formError = 'Gagal menyimpan data ke database.';
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $aksiKartu = in_array($aksi, ['hapus', 'status', 'addon', 'lepas'], true);
        $openForm = !$aksiKartu;
        if ($aksiKartu) {
            flash('error', $formError);
            redirect('set.php');
        }
    } catch (RuntimeException $e) {
        if (in_array($aksi, ['hapus', 'status', 'addon', 'lepas'], true)) {
            flash('error', $e->getMessage());
            redirect('set.php');
        }
        $formError = $e->getMessage();
        $form = array_merge($kosong, array_intersect_key($_POST, $kosong));
        $openForm = true;
    }
} elseif ($staff && !empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT id_kit, nama, jenjang FROM set_alat WHERE id_kit = ? LIMIT 1');
    $stmt->execute([(string) $_GET['edit']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Set tidak ditemukan.');
        redirect('set.php');
    }
    $form = array_merge($kosong, $row);
    $openForm = true;
} elseif ($staff && isset($_GET['baru'])) {
    $openForm = true;
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id_kit, nama, jenjang, komponen, status FROM set_alat';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE nama LIKE ?';
    $params[] = like_query($q);
}
$sql .= " ORDER BY FIELD(jenjang, 'Junior', 'Sensing', 'Exciting'), nama";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$daftarKit = db()->query('SELECT id_kit, nama, jumlah FROM kit ORDER BY nama')->fetchAll();
$addon = [];
foreach (db()->query('SELECT a.id_set, a.id_kit, a.jumlah, k.nama FROM set_addon a JOIN kit k ON k.id_kit = a.id_kit ORDER BY k.nama') as $item) {
    $addon[(string) $item['id_set']][] = $item;
}

$kategori = [
    'Junior' => 'Kit Junior',
    'Exciting' => 'Kit Exciting',
    'Sensing' => 'Sensing',
];
$warna = [
    'Junior' => 'primary',
    'Exciting' => 'warning',
    'Sensing' => 'success',
];
$grup = [];
foreach ($kategori as $kode => $judul) {
    $grup[$kode] = [];
}
foreach ($rows as $row) {
    $kode = (string) $row['jenjang'];
    if (!isset($grup[$kode])) {
        $grup[$kode] = [];
    }
    $grup[$kode][] = $row;
}

$pageTitle = 'Set';
$activeMenu = 'set';
$editing = $form['id_kit'] !== '';
require __DIR__ . '/includes/header.php';
?>
<?php if ($openForm): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-toolbox me-1"></i><?= $editing ? 'Ubah Set' : 'Tambah Set' ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('set.php')) ?>">Batal</a>
    </div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('set.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editing ? 'ubah' : 'tambah' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id_kit" value="<?= e($form['id_kit']) ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="namaKit">Nama Set</label>
                    <input class="form-control" id="namaKit" name="nama" value="<?= e($form['nama']) ?>" maxlength="150" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="jenjangKit">Kategori</label>
                    <select class="form-select" id="jenjangKit" name="jenjang" required>
                        <?php foreach ($kategori as $kode => $judul): ?>
                            <option value="<?= e($kode) ?>" <?= $form['jenjang'] === $kode ? 'selected' : '' ?>><?= e($judul) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Tambah Set' ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <form class="row g-2 align-items-center flex-grow-1" method="get" action="<?= e(base_url('set.php')) ?>">
        <div class="col-md-6">
            <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Cari set...">
        </div>
        <div class="col-md-auto">
            <button class="btn btn-primary" type="submit">Cari</button>
            <?php if ($q !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(base_url('set.php')) ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($staff): ?>
        <a class="btn btn-primary" href="<?= e(base_url('set.php?baru=1')) ?>">Tambah Set</a>
    <?php endif; ?>
</div>

<?php foreach ($kategori as $kode => $judul): ?>
    <?php $aksen = $warna[$kode] ?? 'primary'; ?>
    <?php $kelas = 'kit-' . strtolower($kode); ?>
    <section class="kit-grup">
        <div class="kit-grup-judul"><span class="text-<?= e($aksen) ?>"><?= e($judul) ?></span></div>
        <?php if (!$grup[$kode]): ?>
            <p class="text-muted small mb-4">Belum ada set di kategori ini.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($grup[$kode] as $row): ?>
                    <?php $dipakai = ($row['status'] ?? 'Dikembalikan') === 'Dipakai'; ?>
                    <div class="col-lg-4 col-12 mb-4">
                        <div class="card kit-card <?= e($kelas) ?> shadow h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="kit-card-head">
                                    <span class="kit-card-icon"><i class="fas fa-toolbox"></i></span>
                                    <div class="flex-grow-1">
                                        <div class="kit-card-kategori text-<?= e($aksen) ?>"><?= e($judul) ?></div>
                                        <div class="kit-card-nama"><?= e($row['nama']) ?></div>
                                    </div>
                                    <?php if ($dipakai): ?>
                                        <span class="badge bg-warning text-dark">Dipakai</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Dikembalikan</span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $addonSet = $addon[$row['id_kit']] ?? [];
                                $sudahAddon = array_column($addonSet, 'id_kit');
                                $bisaDiambil = array_values(array_filter(
                                    $daftarKit,
                                    static fn (array $kit): bool => !in_array($kit['id_kit'], $sudahAddon, true)
                                ));
                                ?>
                                <div class="kit-komponen-judul">Add-on</div>
                                <?php if ($staff && $bisaDiambil): ?>
                                    <form class="kit-addon-form" method="post" action="<?= e(base_url('set.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="addon">
                                        <input type="hidden" name="id_kit" value="<?= e($row['id_kit']) ?>">
                                        <select class="form-select form-select-sm select-polos" name="id_addon" required>
                                            <option value="">Pilih kit</option>
                                            <?php foreach ($bisaDiambil as $kit): ?>
                                                <option value="<?= e($kit['id_kit']) ?>"><?= e($kit['nama']) ?> (sisa <?= (int) $kit['jumlah'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="kit-addon-jumlah">
                                            <label for="pinjam-<?= e($row['id_kit']) ?>">Jumlah</label>
                                            <input class="form-control form-control-sm" id="pinjam-<?= e($row['id_kit']) ?>" name="jumlah" type="number" min="1" max="9999" step="1" value="1" required>
                                            <button class="btn btn-sm btn-primary" type="submit">Add-on</button>
                                        </div>
                                    </form>
                                <?php elseif ($staff && !$daftarKit): ?>
                                    <p class="kit-komponen-kosong">Belum ada kit untuk diambil.</p>
                                <?php endif; ?>
                                <?php if (!$addonSet && !$staff): ?>
                                    <p class="kit-komponen-kosong">Belum ada add-on.</p>
                                <?php elseif ($addonSet): ?>
                                    <ul class="kit-addon">
                                        <?php foreach ($addonSet as $komponen): ?>
                                            <li>
                                                <span class="kit-addon-plus"><i class="fas fa-plus"></i></span>
                                                <span><?= e($komponen['nama']) ?></span>
                                                <span class="kit-addon-pinjam"><?= (int) $komponen['jumlah'] ?> dipinjam</span>
                                                <?php if ($staff): ?>
                                                    <form method="post" action="<?= e(base_url('set.php')) ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="aksi" value="lepas">
                                                        <input type="hidden" name="id_kit" value="<?= e($row['id_kit']) ?>">
                                                        <input type="hidden" name="id_addon" value="<?= e($komponen['id_kit']) ?>">
                                                        <button class="kit-addon-lepas" type="submit" title="Lepas add-on"><i class="fas fa-times"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="kit-status">
                                    <form method="post" action="<?= e(base_url('set.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="status">
                                        <input type="hidden" name="id_kit" value="<?= e($row['id_kit']) ?>">
                                        <input type="hidden" name="status" value="Dipakai">
                                        <button class="btn btn-sm btn-primary" type="submit" <?= $dipakai ? 'disabled' : '' ?>>Pakai</button>
                                    </form>
                                    <form method="post" action="<?= e(base_url('set.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="status">
                                        <input type="hidden" name="id_kit" value="<?= e($row['id_kit']) ?>">
                                        <input type="hidden" name="status" value="Dikembalikan">
                                        <button class="btn btn-sm btn-success" type="submit" <?= $dipakai ? '' : 'disabled' ?>>Kembalikan</button>
                                    </form>
                                </div>
                                <?php if ($staff): ?>
                                    <div class="kit-aksi">
                                        <a class="btn btn-sm btn-warning" href="<?= e(base_url('set.php?edit=' . urlencode($row['id_kit']))) ?>">Ubah</a>
                                        <form method="post" action="<?= e(base_url('set.php')) ?>" onsubmit="return confirm('Hapus set ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id_kit" value="<?= e($row['id_kit']) ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
