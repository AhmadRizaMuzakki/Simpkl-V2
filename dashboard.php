<?php

require __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

if (is_staff($user)) {
    $data = dashboard_admin();
    $useCharts = true;
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $chartLabels = json_encode($data['chart_labels'], $jsonFlags);
    $chartValues = json_encode($data['chart_values'], $jsonFlags);
    $hadir = (int) $data['hadir_hari_ini'];
    $belum = (int) $data['belum_absen'];
    $extraScripts = '<script>
    new Chart(document.getElementById("chartHadir"), {
        type: "bar",
        data: {
            labels: ' . $chartLabels . ',
            datasets: [{
                label: "Kehadiran",
                backgroundColor: "#4e73df",
                borderColor: "#4e73df",
                hoverBackgroundColor: "#2e59d9",
                hoverBorderColor: "#2e59d9",
                data: ' . $chartValues . '
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: { legend: { display: false } }
        }
    });
    new Chart(document.getElementById("chartStatus"), {
        type: "doughnut",
        data: {
            labels: ["Hadir", "Belum Absen"],
            datasets: [{
                backgroundColor: ["#1cc88a", "#e74a3b"],
                data: [' . $hadir . ', ' . $belum . ']
            }]
        },
        options: { maintainAspectRatio: false }
    });
    </script>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row g-0 align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Peserta Aktif</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $data['peserta_aktif'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a class="stretched-link" href="<?= e(base_url('peserta.php')) ?>"></a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row g-0 align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hadir Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $data['hadir_hari_ini'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a class="stretched-link" href="<?= e(base_url('absensi.php?tanggal=' . date('Y-m-d'))) ?>"></a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row g-0 align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Belum Absen</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $data['belum_absen'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a class="stretched-link" href="#monitoring"></a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row g-0 align-items-center">
                        <div class="col me-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Training Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $data['training_hari_ini'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <a class="stretched-link" href="<?= e(base_url('training.php')) ?>"></a>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Kehadiran 7 Hari Terakhir</h6>
                </div>
                <div class="card-body"><div class="chart-area"><canvas id="chartHadir"></canvas></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Status Hari Ini</h6>
                </div>
                <div class="card-body"><div class="chart-pie"><canvas id="chartStatus"></canvas></div></div>
            </div>
        </div>
    </div>
    <div class="card shadow mb-4" id="monitoring">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Monitoring Hari Ini</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="flat-table">
                    <thead>
                        <tr><th>Nama</th><th>Status</th><th>Jam Masuk</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$data['monitoring']): ?>
                        <tr><td colspan="3" class="text-center text-muted">Belum ada peserta aktif.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['monitoring'] as $item): ?>
                        <tr>
                            <td><?= e($item['nama']) ?></td>
                            <td>
                                <?php if ($item['status'] === 'Hadir'): ?>
                                    <span class="badge bg-success">Hadir</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Belum Absen</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($item['jam_masuk']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Training Hari Ini</h6></div>
        <div class="card-body">
            <?php if (!$data['training_today']): ?>
                <p class="text-muted mb-0">Belum ada training hari ini.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="flat-table">
                    <thead>
                        <tr><th>Sekolah</th><th>Jenjang</th><th>Materi</th><th>Siswa</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data['training_today'] as $item): ?>
                        <tr>
                            <td><?= e($item['nama_sekolah']) ?></td>
                            <td><?= e($item['jenjang']) ?></td>
                            <td><?= e($item['materi']) ?></td>
                            <td><?= (int) $item['jumlah_siswa'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    return;
}

$pesertaId = $user['id_peserta'] ?? '';
$info = $pesertaId !== '' ? dashboard_peserta($pesertaId) : null;
require __DIR__ . '/includes/header.php';

if (!$info || !$info['peserta']): ?>
    <div class="alert alert-warning">Data peserta untuk akun ini belum terdaftar. Hubungi administrator.</div>
<?php
    require __DIR__ . '/includes/footer.php';
    return;
endif;

$absen = $info['absen_hari_ini'];
$statusHariIni = 'Belum absen';
if ($absen && !empty($absen['jam_masuk']) && !empty($absen['jam_pulang'])) {
    $statusHariIni = 'Masuk & pulang';
} elseif ($absen && !empty($absen['jam_masuk'])) {
    $statusHariIni = 'Sudah absen masuk';
}
?>
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col me-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Status PKL</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= e($info['peserta']['status_peserta']) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-id-badge fa-2x text-gray-300"></i></div>
                </div>
                <div class="small text-gray-500 mt-2"><?= e($info['peserta']['asal_sekolah'] ?: '-') ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col me-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hadir Bulan Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $info['hadir_bulan_ini'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                </div>
                <a class="stretched-link" href="<?= e(base_url('absensi.php')) ?>"></a>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row g-0 align-items-center">
                    <div class="col me-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Training</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= (int) $info['peserta']['total_training'] ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i></div>
                </div>
                <a class="stretched-link" href="<?= e(base_url('training.php')) ?>"></a>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Hari Ini</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= e($statusHariIni) ?></div>
                <div class="small text-gray-500 mt-2">Masuk <?= e(fmt_time($absen['jam_masuk'] ?? null)) ?> · Pulang <?= e(fmt_time($absen['jam_pulang'] ?? null)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-calendar-check me-1"></i>Absensi Harian</div>
            <div class="card-body">
                <p class="mb-3">Masuk <?= e(fmt_time($absen['jam_masuk'] ?? null)) ?> · Pulang <?= e(fmt_time($absen['jam_pulang'] ?? null)) ?></p>
                <a class="btn btn-primary" href="<?= e(base_url('absensi.php')) ?>">Isi Absensi</a>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-chalkboard-teacher me-1"></i>Training Terakhir</div>
            <div class="card-body">
                <?php if (!$info['training']): ?>
                    <p class="text-muted mb-3">Belum ada laporan training.</p>
                <?php else: ?>
                <div class="table-responsive mb-3">
                    <table class="flat-table">
                        <thead><tr><th>Tanggal</th><th>Sekolah</th><th>Materi</th><th>Peran</th></tr></thead>
                        <tbody>
                        <?php foreach ($info['training'] as $row): ?>
                            <tr>
                                <td><?= e(fmt_date($row['tanggal'])) ?></td>
                                <td><?= e($row['nama_sekolah']) ?></td>
                                <td><?= e($row['materi']) ?></td>
                                <td><?= e($row['peran']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <a class="btn btn-primary" href="<?= e(base_url('training.php')) ?>">Input Training</a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
