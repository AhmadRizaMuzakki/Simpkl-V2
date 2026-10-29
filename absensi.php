<?php

require __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$staff = is_staff($user);

$formAbsen = [
    'id_absen' => '',
    'id_peserta' => '',
    'tanggal' => date('Y-m-d'),
    'jam_masuk' => '',
    'jam_pulang' => '',
    'agenda' => '',
    'keterangan' => '',
];
$formError = null;
$openModal = false;
$formDiri = [
    'tanggal' => date('Y-m-d'),
    'jam_masuk' => '',
    'jam_pulang' => '',
    'agenda' => '',
    'keterangan' => '',
    'foto' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($staff) {
        $aksi = (string) ($_POST['aksi'] ?? '');
        try {
            if ($aksi === 'hapus') {
                flash('success', hapus_absensi((string) ($_POST['id_absen'] ?? '')));
            } elseif ($aksi === 'setujui') {
                flash('success', setujui_absensi((string) ($_POST['id_absen'] ?? '')));
            } elseif ($aksi === 'ubah') {
                flash('success', update_absensi_manual($_POST, $_FILES['foto'] ?? []));
            } else {
                flash('success', simpan_absensi_manual($_POST, $_FILES['foto'] ?? []));
            }
        } catch (PDOException $e) {
            flash('error', 'Gagal menyimpan absensi.');
        } catch (RuntimeException $e) {
            if ($aksi === 'hapus' || $aksi === 'setujui') {
                flash('error', $e->getMessage());
            } else {
                $formError = $e->getMessage();
                $formAbsen = array_merge($formAbsen, [
                    'id_absen' => trim((string) ($_POST['id_absen'] ?? '')),
                    'id_peserta' => trim((string) ($_POST['id_peserta'] ?? '')),
                    'tanggal' => trim((string) ($_POST['tanggal'] ?? '')),
                    'jam_masuk' => trim((string) ($_POST['jam_masuk'] ?? '')),
                    'jam_pulang' => trim((string) ($_POST['jam_pulang'] ?? '')),
                    'agenda' => trim((string) ($_POST['agenda'] ?? '')),
                    'keterangan' => trim((string) ($_POST['keterangan'] ?? '')),
                ]);
                $openModal = true;
            }
        }
        if (!$openModal) {
            redirect('absensi.php');
        }
    } else {
        try {
            flash('success', simpan_absensi_diri($user['id_peserta'] ?? '', $_POST, $_FILES['foto'] ?? []));
            redirect('absensi.php');
        } catch (PDOException $e) {
            $formError = 'Gagal menyimpan absensi.';
        } catch (RuntimeException $e) {
            $formError = $e->getMessage();
        }
        $formDiri = array_merge($formDiri, [
            'tanggal' => trim((string) ($_POST['tanggal'] ?? '')),
            'jam_masuk' => trim((string) ($_POST['jam_masuk'] ?? '')),
            'jam_pulang' => trim((string) ($_POST['jam_pulang'] ?? '')),
            'agenda' => trim((string) ($_POST['agenda'] ?? '')),
            'keterangan' => trim((string) ($_POST['keterangan'] ?? '')),
        ]);
    }
}

if ($staff && !$openModal && !empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM absensi WHERE id_absen = ? LIMIT 1');
    $stmt->execute([(string) $_GET['edit']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Data absensi tidak ditemukan.');
        redirect('absensi.php');
    }
    $formAbsen = [
        'id_absen' => $row['id_absen'],
        'id_peserta' => $row['id_peserta'],
        'tanggal' => $row['tanggal'],
        'jam_masuk' => substr((string) $row['jam_masuk'], 0, 5),
        'jam_pulang' => $row['jam_pulang'] ? substr((string) $row['jam_pulang'], 0, 5) : '',
        'agenda' => (string) ($row['agenda'] ?? ''),
        'keterangan' => (string) ($row['keterangan'] ?? ''),
        'foto' => (string) ($row['foto'] ?? ''),
    ];
    $openModal = true;
} elseif ($staff && isset($_GET['baru'])) {
    $openModal = true;
}

$q = trim((string) ($_GET['q'] ?? ''));
$dari = (string) ($_GET['dari'] ?? '');
$sampai = (string) ($_GET['sampai'] ?? '');
if ($dari !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
    $dari = '';
}
if ($sampai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
    $sampai = '';
}
if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

$sql = 'SELECT a.*, p.nama_lengkap
        FROM absensi a
        JOIN peserta p ON p.id_peserta = a.id_peserta
        WHERE 1 = 1';
$params = [];
if (!$staff) {
    $sql .= ' AND a.id_peserta = ?';
    $params[] = $user['id_peserta'] ?? '';
} elseif ($q !== '') {
    $sql .= ' AND p.nama_lengkap LIKE ?';
    $params[] = like_query($q);
}
if ($dari !== '') {
    $sql .= ' AND a.tanggal >= ?';
    $params[] = $dari;
}
if ($sampai !== '') {
    $sql .= ' AND a.tanggal <= ?';
    $params[] = $sampai;
}
$sql .= ' ORDER BY a.tanggal DESC, a.jam_masuk DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$absenHariIni = null;
if (!$staff && !empty($user['id_peserta'])) {
    $todayStmt = db()->prepare('SELECT * FROM absensi WHERE id_peserta = ? AND tanggal = ? LIMIT 1');
    $todayStmt->execute([$user['id_peserta'], date('Y-m-d')]);
    $absenHariIni = $todayStmt->fetch() ?: null;
    if ($formError === null && $absenHariIni) {
        $formDiri = [
            'tanggal' => (string) $absenHariIni['tanggal'],
            'jam_masuk' => substr((string) $absenHariIni['jam_masuk'], 0, 5),
            'jam_pulang' => $absenHariIni['jam_pulang'] ? substr((string) $absenHariIni['jam_pulang'], 0, 5) : '',
            'agenda' => (string) ($absenHariIni['agenda'] ?? ''),
            'keterangan' => (string) ($absenHariIni['keterangan'] ?? ''),
            'foto' => (string) ($absenHariIni['foto'] ?? ''),
        ];
    }
}

$opsiPeserta = [];
if ($staff) {
    $opsiPeserta = db()->query('SELECT id_peserta, nama_lengkap FROM peserta ORDER BY nama_lengkap')->fetchAll();
}

$pageTitle = 'Absensi';
$activeMenu = 'absensi';
$editingAbsen = $formAbsen['id_absen'] !== '';
if ($openModal) {
    $extraScripts = '<script>bootstrap.Modal.getOrCreateInstance(document.getElementById("modalAbsensi")).show();</script>';
}
require __DIR__ . '/includes/header.php';
?>
<?php if (!$staff): ?>
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-calendar-check me-1"></i>Tambah Absensi</div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('absensi.php')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="absen">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="mb-4">
                        <label class="form-label" for="absenPeserta">Peserta</label>
                        <input class="form-control" id="absenPeserta" value="<?= e($user['nama'] ?? '') ?>" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="absenTanggal">Tanggal</label>
                        <input class="form-control" id="absenTanggal" name="tanggal" type="date" value="<?= e($formDiri['tanggal']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-sm-6 mb-4">
                            <label class="form-label" for="absenMasuk">Jam Masuk</label>
                            <input class="form-control" id="absenMasuk" name="jam_masuk" type="time" value="<?= e($formDiri['jam_masuk']) ?>" required>
                        </div>
                        <div class="col-sm-6 mb-4">
                            <label class="form-label" for="absenPulang">Jam Pulang</label>
                            <input class="form-control" id="absenPulang" name="jam_pulang" type="time" value="<?= e($formDiri['jam_pulang']) ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="absenAgenda">Deskripsi</label>
                        <textarea class="form-control" id="absenAgenda" name="agenda" rows="4" maxlength="2000" placeholder="Tulis deskripsi agenda"><?= e($formDiri['agenda']) ?></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="absenKet">Keterangan</label>
                        <input class="form-control" id="absenKet" name="keterangan" value="<?= e($formDiri['keterangan']) ?>" placeholder="Catatan kehadiran">
                    </div>
                </div>
                <div class="col-lg-5">
                    <label class="form-label">Foto</label>
                    <?php $fotoLama = (string) ($formDiri['foto'] ?? ''); ?>
                    <div class="kamera-box">
                        <video class="kamera-video" autoplay playsinline muted hidden></video>
                        <canvas class="kamera-canvas d-none"></canvas>
                        <img class="kamera-hasil" alt="Foto absensi" <?= $fotoLama === '' ? 'hidden' : '' ?> src="<?= $fotoLama !== '' ? e(base_url($config['upload_url'] . '/' . $fotoLama)) : '' ?>">
                        <div class="kamera-kosong" <?= $fotoLama !== '' ? 'hidden' : '' ?>>
                            <i class="fas fa-camera"></i>
                            <span>Ambil foto dari kamera</span>
                        </div>
                        <div class="kamera-aksi">
                            <button class="btn btn-primary btn-sm" type="button" data-kamera="buka" <?= $fotoLama !== '' ? 'hidden' : '' ?>>Buka kamera</button>
                            <button class="btn btn-primary btn-sm" type="button" data-kamera="jepret" hidden>Ambil foto</button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-kamera="ulang" <?= $fotoLama === '' ? 'hidden' : '' ?>>Ambil ulang</button>
                        </div>
                        <p class="kamera-error text-danger small mb-0" hidden></p>
                        <input id="absenFoto" name="foto" type="file" accept="image/jpeg" hidden>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Simpan Absensi</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-1"></i>Data Absensi</span>
        <?php if ($staff): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(base_url('absensi.php?baru=1')) ?>">Tambah Absensi</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3 align-items-end" method="get" action="<?= e(base_url('absensi.php')) ?>">
            <?php if ($staff): ?>
            <div class="col-md-4">
                <label class="form-label mb-1" for="filterNama">Nama peserta</label>
                <input class="form-control" id="filterNama" type="text" name="q" value="<?= e($q) ?>" placeholder="Cari berdasarkan nama peserta...">
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label mb-1" for="filterDari">Tanggal awal</label>
                <input class="form-control" id="filterDari" type="date" name="dari" value="<?= e($dari) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" for="filterSampai">Sampai tanggal</label>
                <input class="form-control" id="filterSampai" type="date" name="sampai" value="<?= e($sampai) ?>">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(base_url('absensi.php')) ?>">Reset</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="flat-table">
                <thead>
                    <tr><th>Tanggal</th><th>Nama</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Agenda</th><th>Disetujui</th><th>Foto</th><?php if ($staff): ?><th>Aksi</th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?= $staff ? 9 : 8 ?>" class="text-center text-muted">Belum ada data absensi.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(fmt_date($row['tanggal'])) ?></td>
                        <td><?= e($row['nama_lengkap']) ?></td>
                        <td><?= e(fmt_time($row['jam_masuk'])) ?></td>
                        <td><?= e(fmt_time($row['jam_pulang'])) ?></td>
                        <td>
                            <?php if (empty($row['jam_masuk'])): ?>
                                <span class="text-muted">-</span>
                            <?php elseif (is_terlambat($row['jam_masuk'])): ?>
                                <span class="badge bg-warning text-dark">Terlambat</span>
                            <?php else: ?>
                                <span class="badge bg-success">Tepat Waktu</span>
                            <?php endif; ?>
                        </td>
                        <td class="agenda-teks"><?= e($row['agenda'] ?: '-') ?></td>
                        <td class="text-nowrap">
                            <?php if (!empty($row['disetujui'])): ?>
                                <span class="badge bg-success">Disetujui</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum</span>
                                <?php if ($staff): ?>
                                    <form class="d-inline" method="post" action="<?= e(base_url('absensi.php')) ?>" onsubmit="return confirm('Setujui absensi ini? Foto akan dihapus.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="setujui">
                                        <input type="hidden" name="id_absen" value="<?= e($row['id_absen']) ?>">
                                        <button class="btn btn-sm btn-success ms-1" type="submit">Setujui</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($row['disetujui']) && !empty($row['foto'])): ?>
                                <?php $srcFoto = base_url($config['upload_url'] . '/' . $row['foto']); ?>
                                <a href="#" class="absen-thumb" data-preview="<?= e($srcFoto) ?>" data-caption="<?= e($row['nama_lengkap'] . ' · ' . fmt_date($row['tanggal'])) ?>">
                                    <img src="<?= e($srcFoto) ?>" alt="Foto absensi <?= e($row['nama_lengkap']) ?>">
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($staff): ?>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-warning" href="<?= e(base_url('absensi.php?edit=' . urlencode($row['id_absen']))) ?>">Ubah</a>
                            <form class="d-inline" method="post" action="<?= e(base_url('absensi.php')) ?>" onsubmit="return confirm('Hapus absensi ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_absen" value="<?= e($row['id_absen']) ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($staff): ?>
<div class="modal fade" id="modalAbsensi" tabindex="-1" aria-labelledby="judulAbsensi" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(base_url('absensi.php')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="<?= $editingAbsen ? 'ubah' : 'tambah' ?>">
                <?php if ($editingAbsen): ?>
                    <input type="hidden" name="id_absen" value="<?= e($formAbsen['id_absen']) ?>">
                    <input type="hidden" name="id_peserta" value="<?= e($formAbsen['id_peserta']) ?>">
                <?php endif; ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="judulAbsensi"><?= $editingAbsen ? 'Ubah Absensi' : 'Tambah Absensi' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <?php if ($formError): ?>
                        <div class="alert alert-danger"><?= e($formError) ?></div>
                    <?php endif; ?>
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-7">
                            <div class="mb-4">
                                <label class="form-label" for="absenPeserta">Peserta</label>
                                <select class="form-select" id="absenPeserta" <?= $editingAbsen ? 'disabled' : 'name="id_peserta" required' ?>>
                                    <option value="">-- Pilih Peserta --</option>
                                    <?php foreach ($opsiPeserta as $peserta): ?>
                                        <option value="<?= e($peserta['id_peserta']) ?>" <?= $formAbsen['id_peserta'] === $peserta['id_peserta'] ? 'selected' : '' ?>><?= e($peserta['nama_lengkap']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="absenTanggal">Tanggal</label>
                                <input class="form-control" id="absenTanggal" name="tanggal" type="date" value="<?= e($formAbsen['tanggal']) ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-sm-6 mb-4">
                                    <label class="form-label" for="absenMasuk">Jam Masuk</label>
                                    <input class="form-control" id="absenMasuk" name="jam_masuk" type="time" value="<?= e($formAbsen['jam_masuk']) ?>" required>
                                </div>
                                <div class="col-sm-6 mb-4">
                                    <label class="form-label" for="absenPulang">Jam Pulang</label>
                                    <input class="form-control" id="absenPulang" name="jam_pulang" type="time" value="<?= e($formAbsen['jam_pulang']) ?>">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="absenAgenda">Deskripsi</label>
                                <textarea class="form-control" id="absenAgenda" name="agenda" rows="4" maxlength="2000" placeholder="Tulis deskripsi agenda"><?= e($formAbsen['agenda']) ?></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="absenKet">Keterangan</label>
                                <input class="form-control" id="absenKet" name="keterangan" value="<?= e($formAbsen['keterangan']) ?>" placeholder="Catatan kehadiran">
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label">Foto</label>
                            <?php $fotoLama = (string) ($formAbsen['foto'] ?? ''); ?>
                            <div class="kamera-box">
                                <video class="kamera-video" autoplay playsinline muted hidden></video>
                                <canvas class="kamera-canvas d-none"></canvas>
                                <img class="kamera-hasil" alt="Foto absensi" <?= $fotoLama === '' ? 'hidden' : '' ?> src="<?= $fotoLama !== '' ? e(base_url($config['upload_url'] . '/' . $fotoLama)) : '' ?>">
                                <div class="kamera-kosong" <?= $fotoLama !== '' ? 'hidden' : '' ?>>
                                    <i class="fas fa-camera"></i>
                                    <span>Ambil foto dari kamera</span>
                                </div>
                                <div class="kamera-aksi">
                                    <button class="btn btn-primary btn-sm" type="button" data-kamera="buka" <?= $fotoLama !== '' ? 'hidden' : '' ?>>Buka kamera</button>
                                    <button class="btn btn-primary btn-sm" type="button" data-kamera="jepret" hidden>Ambil foto</button>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" data-kamera="ulang" <?= $fotoLama === '' ? 'hidden' : '' ?>>Ambil ulang</button>
                                </div>
                                <p class="kamera-error text-danger small mb-0" hidden></p>
                                <input id="absenFoto" name="foto" type="file" accept="image/jpeg" hidden>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" type="submit"><?= $editingAbsen ? 'Simpan Perubahan' : 'Tambah Absensi' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>