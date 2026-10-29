<?php

require __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$staff = is_staff($user);
$formError = null;
$old = [
    'id_training' => '',
    'id_trainer' => [],
    'tanggal' => date('Y-m-d'),
    'id_sekolah' => '',
    'jenjang' => 'SD',
    'pertemuan' => '1',
    'materi' => '',
    'id_silabus' => '',
];
$openForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? 'simpan');
    $actor = $staff ? null : (string) ($user['id_peserta'] ?? '');
    if ($aksi === 'hapus') {
        try {
            flash('success', hapus_training((string) ($_POST['id_training'] ?? ''), $actor));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('training.php');
    }

    $old = array_merge($old, [
        'id_training' => trim((string) ($_POST['id_training'] ?? '')),
        'tanggal' => trim((string) ($_POST['tanggal'] ?? '')),
        'id_sekolah' => trim((string) ($_POST['id_sekolah'] ?? '')),
        'jenjang' => (string) ($_POST['jenjang'] ?? 'SD'),
        'pertemuan' => (string) ($_POST['pertemuan'] ?? ''),
        'materi' => trim((string) ($_POST['materi'] ?? '')),
        'id_silabus' => trim((string) ($_POST['id_silabus'] ?? '')),
    ]);
    $old['id_trainer'] = id_trainer_input($_POST);
    try {
        if ($old['id_training'] !== '') {
            $pesan = update_training($old['id_training'], $_POST, $actor);
        } else {
            $pesan = simpan_training($_POST, $actor);
        }
        flash('success', $pesan);
        redirect('training.php');
    } catch (PDOException $e) {
        $formError = 'Gagal menyimpan data ke database.';
        $openForm = true;
    } catch (RuntimeException $e) {
        $formError = $e->getMessage();
        $openForm = true;
    }
} elseif (!empty($_GET['edit'])) {
    $idEdit = (string) $_GET['edit'];
    if (!user_can_view_training($idEdit)) {
        flash('error', 'Training tidak ditemukan.');
        redirect('training.php');
    }
    $stmt = db()->prepare('SELECT * FROM training WHERE id_training = ? LIMIT 1');
    $stmt->execute([$idEdit]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Training tidak ditemukan.');
        redirect('training.php');
    }
    $trainerStmt = db()->prepare(
        "SELECT id_peserta FROM training_peserta WHERE id_training = ? AND peran = 'Trainer' ORDER BY id_peserta"
    );
    $trainerStmt->execute([$idEdit]);
    $old = [
        'id_training' => $row['id_training'],
        'id_trainer' => $trainerStmt->fetchAll(PDO::FETCH_COLUMN),
        'tanggal' => $row['tanggal'],
        'id_sekolah' => (string) ($row['id_sekolah'] ?? ''),
        'jenjang' => $row['jenjang'],
        'pertemuan' => (string) ($row['pertemuan'] ?? '1'),
        'materi' => $row['materi'],
        'id_silabus' => '',
    ];
    $openForm = true;
} elseif ($staff && isset($_GET['baru'])) {
    $openForm = true;
}

$detail = null;
$tim = [];
if (!empty($_GET['id'])) {
    $idTraining = (string) $_GET['id'];
    if (!user_can_view_training($idTraining)) {
        flash('error', 'Training tidak ditemukan.');
        redirect('training.php');
    }
    $stmt = db()->prepare(
        'SELECT t.*, COALESCE(s.nama_sekolah, t.nama_sekolah) AS nama_sekolah_aktif
         FROM training t
         LEFT JOIN sekolah s ON s.id_sekolah = t.id_sekolah
         WHERE t.id_training = ?
         LIMIT 1'
    );
    $stmt->execute([$idTraining]);
    $detail = $stmt->fetch() ?: null;
    if ($detail && $detail['nama_sekolah_aktif'] !== null && $detail['nama_sekolah_aktif'] !== '') {
        $detail['nama_sekolah'] = $detail['nama_sekolah_aktif'];
    }
    if (!$detail) {
        flash('error', 'Training tidak ditemukan.');
        redirect('training.php');
    }
    $timStmt = db()->prepare(
        "SELECT p.nama_lengkap
         FROM training_peserta tp
         JOIN peserta p ON p.id_peserta = tp.id_peserta
         WHERE tp.id_training = ? AND tp.peran = 'Trainer'
         ORDER BY p.nama_lengkap"
    );
    $timStmt->execute([$idTraining]);
    $tim = $timStmt->fetchAll();
}

if ($staff) {
    $summary = db()->query(
        'SELECT COUNT(*) AS total, COUNT(DISTINCT nama_sekolah) AS sekolah, COALESCE(SUM(jumlah_siswa), 0) AS siswa FROM training'
    )->fetch();
}
$rows = daftar_training($staff ? null : (string) ($user['id_peserta'] ?? ''));

$pageTitle = $detail ? 'Detail Training' : 'Training';
$activeMenu = 'training';
$editing = $old['id_training'] !== '';
if (!$staff && !$editing) {
    $openForm = true;
}
if (!$staff && !$editing && !$formError && $old['id_trainer'] === []) {
    $milik = (string) ($user['id_peserta'] ?? '');
    if ($milik !== '') {
        $old['id_trainer'] = [$milik];
    }
}
$daftarSekolah = $openForm ? daftar_sekolah() : [];
$daftarSilabus = $openForm ? daftar_silabus() : [];
$pesertaPilihan = $openForm ? daftar_peserta_pilihan($old['id_trainer']) : [];
require __DIR__ . '/includes/header.php';
?>
<?php if ($detail): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-school me-1"></i>Detail Training</span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('training.php')) ?>">Kembali</a>
    </div>
    <div class="card-body">
        <?php
        $namaTrainer = $tim ? implode(', ', array_column($tim, 'nama_lengkap')) : '-';
        $barisLaporan = [
            'Hari/Tanggal: ' . fmt_hari_tanggal_lengkap($detail['tanggal']),
            'Sekolah: ' . ($detail['nama_sekolah'] ?: '-'),
            'Level Materi: ' . ($detail['jenjang'] ?: '-'),
            'Materi: ' . ($detail['materi'] ?: '-'),
            'Nama Trainer: ' . $namaTrainer,
            'Pertemuan: ' . (int) ($detail['pertemuan'] ?? 1),
        ];
        $teksLaporan = "📎 Laporan Kegiatan Ekskul Robotik\n" . implode("\n", array_map(static function (string $baris): string {
            return '🔴 ' . $baris;
        }, $barisLaporan));
        ?>
        <div class="laporan-wa">
            <div class="laporan-wa-judul">📎 Laporan Kegiatan Ekskul Robotik</div>
            <ul class="laporan-wa-list">
                <?php foreach ($barisLaporan as $baris): ?>
                    <li><?= e($baris) ?></li>
                <?php endforeach; ?>
            </ul>
            <textarea id="teksLaporan" class="laporan-wa-sumber" readonly><?= e($teksLaporan) ?></textarea>
            <div class="mt-3">
                <button class="btn btn-primary" type="button" data-copy-target="teksLaporan">
                    <i class="fas fa-copy me-1"></i><span>Salin</span>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($openForm): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plus me-1"></i><?= $editing ? 'Ubah Training' : 'Input Training' ?></span>
        <?php if ($staff || $editing): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('training.php')) ?>">Batal</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('training.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <?php if ($editing): ?>
                <input type="hidden" name="id_training" value="<?= e($old['id_training']) ?>">
            <?php endif; ?>
            <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="tanggalTraining">Hari/Tanggal</label>
                <input class="form-control" id="tanggalTraining" name="tanggal" type="date" value="<?= e($old['tanggal']) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="pertemuan">Pertemuan</label>
                <input class="form-control" id="pertemuan" name="pertemuan" type="number" min="1" value="<?= e($old['pertemuan']) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="sekolah">Sekolah</label>
                <?php if (!$daftarSekolah): ?>
                    <div class="alert alert-warning mb-0">
                        Belum ada data sekolah.
                        <?php if ($staff): ?>
                            <a href="<?= e(base_url('sekolah.php?baru=1')) ?>">Tambah di Data Sekolah</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <select class="form-select" id="sekolah" name="id_sekolah" required>
                        <option value="">-- Pilih Sekolah --</option>
                        <?php foreach ($daftarSekolah as $sekolah): ?>
                            <option value="<?= e($sekolah['id_sekolah']) ?>" <?= $old['id_sekolah'] === $sekolah['id_sekolah'] ? 'selected' : '' ?>><?= e($sekolah['nama_sekolah']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="jenjang">Level Materi</label>
                <select class="form-select" id="jenjang" name="jenjang">
                    <?php foreach (['TK', 'SD', 'SMP', 'SMA', 'SMK'] as $jenjang): ?>
                        <option value="<?= e($jenjang) ?>" <?= $old['jenjang'] === $jenjang ? 'selected' : '' ?>><?= e($jenjang) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label" for="materi">Materi</label>
                <?php if (!$daftarSilabus): ?>
                    <div class="alert alert-warning mb-0">
                        Belum ada silabus.
                        <?php if ($staff): ?>
                            <a href="<?= e(base_url('silabus.php?baru=1')) ?>">Tambah di Silabus</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php
                    $materiTerpilih = (string) ($old['id_silabus'] ?? '');
                    if ($materiTerpilih === '' && $old['materi'] !== '') {
                        foreach ($daftarSilabus as $silabus) {
                            if ($silabus['materi'] === $old['materi']) {
                                $materiTerpilih = $silabus['id_silabus'];
                                break;
                            }
                        }
                    }
                    ?>
                    <select class="form-select" id="materi" name="id_silabus" required>
                        <option value="">-- Pilih Materi --</option>
                        <?php foreach ($daftarSilabus as $silabus): ?>
                            <option value="<?= e($silabus['id_silabus']) ?>" <?= $materiTerpilih === $silabus['id_silabus'] ? 'selected' : '' ?>><?= e($silabus['materi'] . ' · ' . $silabus['jenjang'] . ' · ' . $silabus['semester'] . ' ' . $silabus['tahun']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label" for="namaTrainer">Nama Trainer</label>
                <?php if (!$pesertaPilihan): ?>
                    <div class="alert alert-warning mb-0">Belum ada peserta aktif.</div>
                <?php else: ?>
                    <div class="tag-select" data-max="10" data-name="id_trainer[]">
                        <button type="button" class="tag-select-control" id="namaTrainer" aria-expanded="false" aria-haspopup="listbox">
                            <span class="tag-select-values"></span>
                            <span class="tag-select-placeholder">Pilih trainer</span>
                            <i class="fas fa-chevron-down tag-select-caret" aria-hidden="true"></i>
                        </button>
                        <div class="tag-select-menu" hidden role="listbox">
                            <input class="form-control form-control-sm tag-select-search" type="search" placeholder="Cari peserta..." aria-label="Cari peserta">
                            <?php foreach ($pesertaPilihan as $peserta): ?>
                                <button type="button" class="tag-select-option" role="option" data-value="<?= e($peserta['id_peserta']) ?>" <?= in_array($peserta['id_peserta'], $old['id_trainer'], true) ? 'data-selected="1"' : '' ?>><?= e($peserta['nama_lengkap']) ?></button>
                            <?php endforeach; ?>
                            <div class="tag-select-empty" hidden>Semua peserta sudah dipilih.</div>
                        </div>
                    </div>
                    <div class="form-text">Maksimal 10 orang.</div>
                <?php endif; ?>
            </div>
            </div>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Simpan Training' ?></button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($staff && !$openForm && !$detail): ?>
<div class="row">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col me-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Training</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $summary['total'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col me-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sekolah</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $summary['sekolah'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-school fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col me-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Siswa</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $summary['siswa'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-1"></i><?= $staff ? 'Monitoring Training' : 'Training Saya' ?></span>
        <?php if ($staff): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(base_url('training.php?baru=1')) ?>">Tambah Training</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="flat-table">
                <thead>
                    <tr>
                        <th>Hari/Tanggal</th>
                        <th>Sekolah</th>
                        <th>Level Materi</th>
                        <th>Materi</th>
                        <th>Nama Trainer</th>
                        <th>Pertemuan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted">Belum ada training.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(fmt_hari_tanggal($row['tanggal'])) ?></td>
                        <td><?= e($row['nama_sekolah']) ?></td>
                        <td><?= e($row['jenjang']) ?></td>
                        <td><?= e($row['materi']) ?></td>
                        <td><?= e($row['nama_trainer'] ?: '-') ?></td>
                        <td><?= (int) ($row['pertemuan'] ?? 1) ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-primary" href="<?= e(base_url('training.php?id=' . urlencode($row['id_training']))) ?>">Detail</a>
                            <a class="btn btn-sm btn-warning" href="<?= e(base_url('training.php?edit=' . urlencode($row['id_training']))) ?>">Ubah</a>
                            <form class="d-inline" method="post" action="<?= e(base_url('training.php')) ?>" onsubmit="return confirm('Hapus training ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_training" value="<?= e($row['id_training']) ?>">
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
