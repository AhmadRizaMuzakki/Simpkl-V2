<?php

require __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/fpdf/fpdf.php';

class LaporanPdf extends FPDF
{
    public string $judulLaporan = '';
    public int $jumlahData = 0;
    public string $waktuCetak = '';

    public function Header(): void
    {
        $lebar = $this->GetPageWidth();
        $this->SetFillColor(78, 115, 223);
        $this->Rect(0, 0, $lebar, 28, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(12, 6);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, 'SIMPKL Robotics', 0, 1);
        $this->SetX(12);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell($lebar - 24, 8, pdf_teks($this->judulLaporan), 0, 1);
        $this->SetX(12);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(219, 228, 255);
        $ringkas = $this->jumlahData . ' data';
        if ($this->waktuCetak !== '') {
            $ringkas .= '   |   Dicetak ' . $this->waktuCetak;
        }
        $this->Cell($lebar - 24, 5, pdf_teks($ringkas), 0, 1);
        $this->SetY(34);
        $this->SetTextColor(31, 41, 55);
    }

    public function Footer(): void
    {
        $lebar = $this->GetPageWidth() - 24;
        $this->SetDrawColor(226, 232, 240);
        $this->Line(12, $this->GetPageHeight() - 14, $this->GetPageWidth() - 12, $this->GetPageHeight() - 14);
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(107, 114, 128);
        $this->SetX(12);
        $this->Cell($lebar / 2, 8, 'SIMPKL Robotics', 0, 0, 'L');
        $this->Cell($lebar / 2, 8, 'Halaman ' . $this->PageNo(), 0, 0, 'R');
    }
}

require_role(role_kontrol());

$user = current_user();
$staff = is_staff($user);
$level = ['TK', 'SD', 'SMP', 'SMA', 'SMK'];
$labelSet = [
    'Junior' => 'Kit Junior',
    'Exciting' => 'Kit Exciting',
    'Sensing' => 'Sensing',
];

$opsiPeserta = db()->query('SELECT id_peserta, nama_lengkap FROM peserta ORDER BY nama_lengkap')->fetchAll();
$opsiSekolah = db()->query('SELECT id_sekolah, nama_sekolah FROM sekolah ORDER BY nama_sekolah')->fetchAll();
$opsiSilabus = daftar_silabus();

if (($_GET['export'] ?? '') === '1') {
    $jenis = (string) ($_GET['jenis'] ?? '');
    $idPeserta = $staff ? '' : (string) ($user['id_peserta'] ?? '');
    $bagian = laporan_export($jenis, $staff, $idPeserta, $level, $labelSet, $opsiSekolah, $opsiPeserta, $opsiSilabus);
    if ($bagian === null) {
        flash('error', 'Laporan tidak ditemukan.');
        redirect('laporan.php');
    }
    if ($jenis === 'notulen') {
        kirim_pdf_notulen($bagian['dokumen']);
    }
    kirim_pdf_laporan($jenis, $bagian);
}

$pageTitle = 'Laporan';
$activeMenu = 'laporan';
require __DIR__ . '/includes/header.php';
?>
<div class="laporan-daftar">
    <?php
    kartu_laporan('Laporan Absensi', 'fa-calendar-check', 'absensi', '#0f766e', function () use ($staff): void {
        pilih_rentang();
        if ($staff) {
            pilih_cari('Nama peserta', 'q', 'Cari berdasarkan nama peserta...');
        }
    });
    kartu_laporan('Laporan Training', 'fa-chalkboard-teacher', 'training', '#6d28d9', function () use ($opsiPeserta, $opsiSekolah, $opsiSilabus, $level): void {
        pilih_rentang();
        pilih_daftar('Sekolah', 'id_sekolah', 'Semua sekolah', $opsiSekolah, 'id_sekolah', 'nama_sekolah');
        pilih_sederhana('Level Materi', 'jenjang', array_merge(['' => 'Semua level'], array_combine($level, $level)));
        $materi = ['' => 'Semua materi'];
        foreach ($opsiSilabus as $silabus) {
            $materi[(string) $silabus['id_silabus']] = $silabus['materi'] . ' · ' . $silabus['jenjang'] . ' · ' . $silabus['semester'] . ' ' . $silabus['tahun'];
        }
        pilih_sederhana('Materi', 'id_silabus', $materi);
        pilih_daftar('Nama Trainer', 'id_trainer', 'Semua trainer', $opsiPeserta, 'id_peserta', 'nama_lengkap');
    });
    if ($staff) {
        kartu_laporan('Laporan Peserta', 'fa-user-graduate', 'peserta', '#4e73df', function (): void {
            pilih_cari('Nama peserta', 'q', 'Cari peserta...');
            pilih_sederhana('Status', 'status', [
                '' => 'Semua',
                'Aktif' => 'Aktif',
                'Nonaktif' => 'Nonaktif',
            ]);
        });
    }
    kartu_laporan('Laporan Set', 'fa-toolbox', 'set', '#0891b2', function () use ($labelSet): void {
        pilih_cari('Nama set', 'q', 'Cari set...');
        pilih_sederhana('Kategori', 'jenjang', array_merge(['' => 'Semua kategori'], $labelSet));
        pilih_sederhana('Status', 'status', [
            '' => 'Semua status',
            'Dipakai' => 'Dipakai',
            'Dikembalikan' => 'Dikembalikan',
        ]);
    });
    if ($staff) {
        kartu_laporan('Laporan Sekolah', 'fa-school', 'sekolah', '#0f9f6e', function (): void {
            pilih_cari('Nama sekolah', 'q', 'Cari sekolah...');
        });
    }
    kartu_laporan('Laporan Kit', 'fa-box', 'kit', '#e11d48', function (): void {
        pilih_cari('Nama kit', 'q', 'Cari kit...');
    });
    kartu_laporan('Laporan Notulen', 'fa-clipboard', 'notulen', '#b45309', function (): void {
        pilih_rentang();
        pilih_cari('Cari notulen', 'q', 'Cari judul, cabang, pekan, atau notulen...');
    });
    ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
<?php

function id_ada(array $baris, string $kolom, string $id): bool
{
    foreach ($baris as $row) {
        if ((string) $row[$kolom] === $id) {
            return true;
        }
    }

    return false;
}

function kartu_laporan(string $judul, string $ikon, string $jenis, string $warna, callable $filter): void
{
    ?>
    <div class="card laporan-kartu" style="--laporan-aksen: <?= e($warna) ?>">
        <div class="card-body">
            <div class="laporan-kepala">
                <span class="laporan-ikon"><i class="fas <?= e($ikon) ?>"></i></span>
                <div>
                    <h2 class="laporan-judul"><?= e($judul) ?></h2>
                    <div class="laporan-filter-judul"><i class="fas fa-filter"></i> Export dengan Filter</div>
                </div>
            </div>
            <form method="get" action="<?= e(base_url('laporan.php')) ?>">
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                <?php $filter(); ?>
                <button class="laporan-export" type="submit">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </form>
        </div>
    </div>
    <?php
}

function pilih_rentang(): void
{
    echo '<div class="laporan-duo">';
    echo '<div class="laporan-field"><label class="form-label">Tanggal awal</label>';
    echo '<input class="form-control" type="date" name="dari"></div>';
    echo '<div class="laporan-field"><label class="form-label">Sampai tanggal</label>';
    echo '<input class="form-control" type="date" name="sampai"></div>';
    echo '</div>';
}

function pilih_cari(string $label, string $name, string $placeholder): void
{
    echo '<div class="laporan-field"><label class="form-label">' . e($label) . '</label>';
    echo '<input class="form-control" type="text" name="' . e($name) . '" maxlength="100" placeholder="' . e($placeholder) . '">';
    echo '</div>';
}

function pilih_daftar(string $label, string $name, string $semua, array $baris, string $nilai, string $teks): void
{
    echo '<div class="laporan-field"><label class="form-label">' . e($label) . '</label>';
    echo '<select class="form-select select-polos" name="' . e($name) . '">';
    echo '<option value="">' . e($semua) . '</option>';
    foreach ($baris as $row) {
        echo '<option value="' . e($row[$nilai]) . '">' . e($row[$teks]) . '</option>';
    }
    echo '</select></div>';
}

function pilih_sederhana(string $label, string $name, array $opsi): void
{
    echo '<div class="laporan-field"><label class="form-label">' . e($label) . '</label>';
    echo '<select class="form-select select-polos" name="' . e($name) . '">';
    foreach ($opsi as $nilai => $teks) {
        echo '<option value="' . e((string) $nilai) . '">' . e($teks) . '</option>';
    }
    echo '</select></div>';
}

function laporan_export(
    string $jenis,
    bool $staff,
    string $idPeserta,
    array $level,
    array $labelSet,
    array $opsiSekolah,
    array $opsiPeserta,
    array $opsiSilabus
): ?array {
    $staffSaja = ['peserta', 'sekolah'];
    if (in_array($jenis, $staffSaja, true) && !$staff) {
        return null;
    }

    return match ($jenis) {
        'absensi' => export_absensi($idPeserta, $staff),
        'training' => export_training($idPeserta, $level, $opsiSekolah, $opsiPeserta, $opsiSilabus),
        'peserta' => export_peserta(),
        'sekolah' => export_sekolah(),
        'kit' => export_kit(),
        'set' => export_set($labelSet),
        'notulen' => export_notulen(),
        default => null,
    };
}

function tanggal_filter(string $nilai): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) === 1 ? $nilai : '';
}

function syarat_rentang(string $kolom, array &$params): string
{
    $dari = tanggal_filter((string) ($_GET['dari'] ?? ''));
    $sampai = tanggal_filter((string) ($_GET['sampai'] ?? ''));
    if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    $sql = '';
    if ($dari !== '') {
        $sql .= " AND {$kolom} >= ?";
        $params[] = $dari;
    }
    if ($sampai !== '') {
        $sql .= " AND {$kolom} <= ?";
        $params[] = $sampai;
    }

    return $sql;
}

function kata_cari(): string
{
    return trim((string) ($_GET['q'] ?? ''));
}

function export_absensi(string $idPeserta, bool $staff): array
{
    $params = [];
    $sql = 'SELECT a.tanggal, a.jam_masuk, a.jam_pulang, a.keterangan, a.agenda, a.disetujui, p.nama_lengkap
        FROM absensi a
        JOIN peserta p ON p.id_peserta = a.id_peserta
        WHERE 1 = 1' . syarat_rentang('a.tanggal', $params);
    if ($idPeserta !== '') {
        $sql .= ' AND a.id_peserta = ?';
        $params[] = $idPeserta;
    }
    $q = kata_cari();
    if ($staff && $q !== '') {
        $sql .= ' AND p.nama_lengkap LIKE ?';
        $params[] = like_query($q);
    }
    $sql .= ' ORDER BY a.tanggal DESC, p.nama_lengkap';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $baris = [];
    foreach ($stmt->fetchAll() as $row) {
        if (empty($row['jam_masuk'])) {
            $status = '-';
        } elseif (is_terlambat($row['jam_masuk'])) {
            $status = 'Terlambat';
        } else {
            $status = 'Tepat Waktu';
        }
        $baris[] = [
            fmt_date($row['tanggal']),
            $row['nama_lengkap'],
            fmt_time($row['jam_masuk']),
            fmt_time($row['jam_pulang']),
            $status,
            !empty($row['disetujui']) ? 'Disetujui' : 'Belum',
            teks_laporan($row['agenda']),
            teks_laporan($row['keterangan']),
        ];
    }

    return [
        'judul' => 'Laporan Absensi',
        'kolom' => ['Tanggal', 'Nama', 'Masuk', 'Pulang', 'Status', 'Disetujui', 'Agenda', 'Keterangan'],
        'baris' => $baris,
    ];
}

function export_training(string $idPeserta, array $level, array $opsiSekolah, array $opsiPeserta, array $opsiSilabus): array
{
    $idSekolah = trim((string) ($_GET['id_sekolah'] ?? ''));
    $jenjang = trim((string) ($_GET['jenjang'] ?? ''));
    $idSilabus = trim((string) ($_GET['id_silabus'] ?? ''));
    $idTrainer = trim((string) ($_GET['id_trainer'] ?? ''));
    if (!id_ada($opsiSekolah, 'id_sekolah', $idSekolah)) {
        $idSekolah = '';
    }
    if (!in_array($jenjang, $level, true)) {
        $jenjang = '';
    }
    if (!id_ada($opsiPeserta, 'id_peserta', $idTrainer)) {
        $idTrainer = '';
    }
    $materi = '';
    foreach ($opsiSilabus as $silabus) {
        if ((string) $silabus['id_silabus'] === $idSilabus) {
            $materi = (string) $silabus['materi'];
            break;
        }
    }
    $trainer = "(SELECT GROUP_CONCAT(p.nama_lengkap ORDER BY p.nama_lengkap SEPARATOR ', ')
        FROM training_peserta tp
        JOIN peserta p ON p.id_peserta = tp.id_peserta
        WHERE tp.id_training = t.id_training AND tp.peran = 'Trainer')";
    $params = [];
    $sql = "SELECT t.tanggal, COALESCE(s.nama_sekolah, t.nama_sekolah) AS nama_sekolah,
            t.jenjang, t.materi, t.pertemuan, {$trainer} AS nama_trainer
        FROM training t
        LEFT JOIN sekolah s ON s.id_sekolah = t.id_sekolah
        WHERE 1 = 1" . syarat_rentang('t.tanggal', $params);
    if ($idSekolah !== '') {
        $sql .= ' AND t.id_sekolah = ?';
        $params[] = $idSekolah;
    }
    if ($jenjang !== '') {
        $sql .= ' AND t.jenjang = ?';
        $params[] = $jenjang;
    }
    if ($materi !== '') {
        $sql .= ' AND t.materi = ?';
        $params[] = $materi;
    }
    if ($idTrainer !== '') {
        $sql .= " AND EXISTS (
            SELECT 1 FROM training_peserta trainer
            WHERE trainer.id_training = t.id_training AND trainer.peran = 'Trainer' AND trainer.id_peserta = ?
        )";
        $params[] = $idTrainer;
    }
    if ($idPeserta !== '') {
        $sql .= ' AND EXISTS (
            SELECT 1 FROM training_peserta milik
            WHERE milik.id_training = t.id_training AND milik.id_peserta = ?
        )';
        $params[] = $idPeserta;
    }
    $sql .= ' ORDER BY t.tanggal DESC, nama_sekolah';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $baris = [];
    foreach ($stmt->fetchAll() as $row) {
        $baris[] = [
            fmt_hari_tanggal($row['tanggal']),
            teks_laporan($row['nama_sekolah']),
            $row['jenjang'],
            $row['materi'],
            teks_laporan($row['nama_trainer']),
            (string) (int) $row['pertemuan'],
        ];
    }

    return [
        'judul' => 'Laporan Training',
        'kolom' => ['Hari/Tanggal', 'Sekolah', 'Level Materi', 'Materi', 'Nama Trainer', 'Pertemuan'],
        'baris' => $baris,
    ];
}

function export_peserta(): array
{
    $status = (string) ($_GET['status'] ?? '');
    if (!in_array($status, ['', 'Aktif', 'Nonaktif'], true)) {
        $status = '';
    }
    $params = [];
    $sql = "SELECT p.nama_lengkap, p.email, p.asal_sekolah, p.jurusan, p.nomor_telepon, p.tanggal_mulai, p.tanggal_selesai, p.status_peserta, COALESCE(u.role, 'ANGGOTA') AS role
        FROM peserta p
        LEFT JOIN users u ON u.email = p.email
        WHERE 1 = 1";
    $q = kata_cari();
    if ($q !== '') {
        $sql .= ' AND p.nama_lengkap LIKE ?';
        $params[] = like_query($q);
    }
    if ($status !== '') {
        $sql .= ' AND p.status_peserta = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY p.nama_lengkap';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $baris = [];
    foreach ($stmt->fetchAll() as $row) {
        $baris[] = [
            $row['nama_lengkap'],
            label_role((string) $row['role']),
            $row['email'],
            teks_laporan($row['asal_sekolah']),
            teks_laporan($row['jurusan']),
            teks_laporan($row['nomor_telepon']),
            fmt_date($row['tanggal_mulai']),
            fmt_date($row['tanggal_selesai']),
            $row['status_peserta'],
        ];
    }

    return [
        'judul' => 'Laporan Peserta',
        'kolom' => ['Nama', 'Role', 'Email', 'Asal Sekolah', 'Jurusan', 'Telepon', 'Mulai', 'Selesai', 'Status'],
        'baris' => $baris,
    ];
}

function export_sekolah(): array
{
    $params = [];
    $sql = 'SELECT nama_sekolah, alamat FROM sekolah WHERE 1 = 1';
    $q = kata_cari();
    if ($q !== '') {
        $sql .= ' AND nama_sekolah LIKE ?';
        $params[] = like_query($q);
    }
    $sql .= ' ORDER BY nama_sekolah';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $baris = [];
    foreach ($stmt->fetchAll() as $row) {
        $baris[] = [$row['nama_sekolah'], teks_laporan($row['alamat'])];
    }

    return [
        'judul' => 'Laporan Sekolah',
        'kolom' => ['Nama Sekolah', 'Alamat'],
        'baris' => $baris,
    ];
}

function export_kit(): array
{
    $params = [];
    $sql = 'SELECT nama, jumlah FROM kit WHERE 1 = 1';
    $q = kata_cari();
    if ($q !== '') {
        $sql .= ' AND nama LIKE ?';
        $params[] = like_query($q);
    }
    $sql .= ' ORDER BY nama';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $baris = [];
    foreach ($stmt->fetchAll() as $row) {
        $baris[] = [$row['nama'], (string) (int) $row['jumlah']];
    }

    return [
        'judul' => 'Laporan Kit',
        'kolom' => ['Nama Kit', 'Jumlah'],
        'baris' => $baris,
    ];
}

function export_set(array $labelSet): array
{
    $jenjang = (string) ($_GET['jenjang'] ?? '');
    $status = (string) ($_GET['status'] ?? '');
    if (!isset($labelSet[$jenjang])) {
        $jenjang = '';
    }
    if (!in_array($status, ['', 'Dipakai', 'Dikembalikan'], true)) {
        $status = '';
    }
    $params = [];
    $sql = "SELECT id_kit, nama, jenjang, status FROM set_alat WHERE 1 = 1";
    $q = kata_cari();
    if ($q !== '') {
        $sql .= ' AND nama LIKE ?';
        $params[] = like_query($q);
    }
    if ($jenjang !== '') {
        $sql .= ' AND jenjang = ?';
        $params[] = $jenjang;
    }
    if ($status !== '') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    $sql .= " ORDER BY FIELD(jenjang, 'Junior', 'Sensing', 'Exciting'), nama";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $set = $stmt->fetchAll();
    $addonSet = [];
    foreach (db()->query('SELECT a.id_set, a.jumlah, k.nama FROM set_addon a JOIN kit k ON k.id_kit = a.id_kit ORDER BY k.nama') as $item) {
        $addonSet[(string) $item['id_set']][] = $item['nama'] . ' (' . (int) $item['jumlah'] . ')';
    }
    $baris = [];
    foreach ($set as $row) {
        $addon = $addonSet[$row['id_kit']] ?? [];
        $baris[] = [
            $row['nama'],
            $labelSet[$row['jenjang']] ?? $row['jenjang'],
            $row['status'],
            $addon ? implode(', ', $addon) : '-',
        ];
    }

    return [
        'judul' => 'Laporan Set',
        'kolom' => ['Nama Set', 'Kategori', 'Status', 'Add-on'],
        'baris' => $baris,
    ];
}

function export_notulen(): array
{
    $params = [];
    $sql = 'SELECT * FROM notulen WHERE 1 = 1';
    $q = kata_cari();
    if ($q !== '') {
        $sql .= ' AND (judul LIKE ? OR cabang LIKE ? OR pekan LIKE ? OR nama_notulen LIKE ?)';
        $cari = like_query($q);
        array_push($params, $cari, $cari, $cari, $cari);
    }
    $dari = tanggal_filter((string) ($_GET['dari'] ?? ''));
    $sampai = tanggal_filter((string) ($_GET['sampai'] ?? ''));
    if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    if ($dari !== '') {
        $sql .= ' AND tanggal_selesai >= ?';
        $params[] = $dari;
    }
    if ($sampai !== '') {
        $sql .= ' AND tanggal_mulai <= ?';
        $params[] = $sampai;
    }
    $sql .= ' ORDER BY tanggal_mulai DESC, judul';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $dokumen = [];
    $barisStmt = db()->prepare('SELECT hari, jenis, nama, isi FROM notulen_baris WHERE id_notulen = ? ORDER BY urutan, hari');
    foreach ($stmt->fetchAll() as $row) {
        $barisStmt->execute([$row['id_notulen']]);
        $row['baris'] = $barisStmt->fetchAll();
        $dokumen[] = $row;
    }

    return ['dokumen' => $dokumen];
}

function kirim_pdf_notulen(array $daftar): void
{
    $pdf = new LaporanPdf('P', 'mm', 'A4');
    $pdf->judulLaporan = 'Laporan Notulen';
    $pdf->jumlahData = count($daftar);
    $pdf->waktuCetak = date('d/m/Y H:i');
    $pdf->SetMargins(12, 34, 12);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();
    $lebar = $pdf->GetPageWidth() - 24;
    if ($daftar === []) {
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell($lebar, 6, pdf_teks('Tidak ada data untuk filter ini.'));
    } else {
        foreach ($daftar as $indeks => $row) {
            if ($indeks > 0) {
                $pdf->AddPage();
            }
            tulis_notulen_pdf($pdf, $row, $lebar);
        }
    }
    $pdf->Output('D', 'laporan-notulen.pdf');
    exit;
}

function tulis_notulen_pdf(LaporanPdf $pdf, array $row, float $lebar): void
{
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->MultiCell($lebar, 7, pdf_teks(mb_strtoupper((string) $row['judul'])), 0, 'C');
    $periode = [];
    if (!empty($row['pekan'])) {
        $periode[] = (string) $row['pekan'];
    }
    $periode[] = fmt_date($row['tanggal_mulai']) . ' - ' . fmt_date($row['tanggal_selesai']);
    if (!empty($row['cabang'])) {
        $periode[] = (string) $row['cabang'];
    }
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->MultiCell($lebar, 6, pdf_teks(mb_strtoupper(implode(', ', $periode))), 0, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($lebar, 6, pdf_teks('Notulen: ' . $row['nama_notulen']), 0, 1);
    foreach (hari_notulen() as $hari) {
        $kegiatan = [];
        $catatan = [];
        foreach ($row['baris'] as $item) {
            if (($item['hari'] ?? '') !== $hari) {
                continue;
            }
            if (($item['jenis'] ?? '') === 'catatan') {
                $catatan[] = $item;
            } else {
                $kegiatan[] = $item;
            }
        }
        if ($kegiatan === [] && $catatan === []) {
            continue;
        }
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($lebar, 7, pdf_teks(mb_strtoupper($hari)), 0, 1);
        foreach ($kegiatan as $item) {
            tulis_poin_notulen_pdf($pdf, (string) $item['nama'] . ':', (string) $item['isi'], $lebar);
        }
        if ($catatan !== []) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell($lebar, 6, pdf_teks('Catatan:'), 0, 1);
            foreach ($catatan as $item) {
                tulis_poin_notulen_pdf($pdf, (string) $item['nama'] . ':', (string) $item['isi'], $lebar);
            }
        }
    }
}

function tulis_poin_notulen_pdf(LaporanPdf $pdf, string $nama, string $isi, float $lebar): void
{
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($lebar, 6, pdf_teks($nama), 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $poin = [];
    foreach (preg_split('/\R/u', $isi) ?: [] as $baris) {
        $baris = trim($baris, " \t\n\r\0\x0B-");
        if ($baris !== '') {
            $poin[] = $baris;
        }
    }
    if ($poin === []) {
        $poin[] = $isi;
    }
    foreach ($poin as $baris) {
        $pdf->MultiCell($lebar, 5.5, pdf_teks('- ' . $baris));
    }
}

function teks_laporan(?string $nilai): string
{
    $nilai = trim((string) $nilai);

    return $nilai === '' ? '-' : $nilai;
}

function kirim_pdf_laporan(string $jenis, array $tabel): void
{
    $kolom = $tabel['kolom'];
    $baris = $tabel['baris'];
    $orientasi = count($kolom) > 4 ? 'L' : 'P';
    $pdf = new LaporanPdf($orientasi, 'mm', 'A4');
    $pdf->judulLaporan = (string) $tabel['judul'];
    $pdf->jumlahData = count($baris);
    $pdf->waktuCetak = date('d/m/Y H:i');
    $pdf->SetMargins(12, 34, 12);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    $lebarHalaman = $pdf->GetPageWidth() - 24;
    $ukuran = lebar_kolom_pdf($kolom, $lebarHalaman);
    $rata = array_map(static fn (string $nama): string => gaya_kolom_pdf($nama)['align'], $kolom);
    $gambarKepala = static function () use ($pdf, $kolom, $ukuran): void {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(37, 99, 235);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(37, 99, 235);
        $teks = array_map(static fn (string $nama): string => pdf_teks($nama), $kolom);
        gambar_baris_pdf($pdf, $ukuran, $teks, array_fill(0, count($kolom), 'C'), 9, true);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetFont('Arial', '', 8);
    };
    $gambarKepala();

    if (!$baris) {
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->Cell($lebarHalaman, 12, pdf_teks('Tidak ada data untuk filter ini.'), 1, 1, 'C', true);
    }

    foreach ($baris as $indeks => $isi) {
        $sel = [];
        foreach ($kolom as $i => $nama) {
            $sel[] = pdf_teks((string) ($isi[$i] ?? '-'));
        }
        $tinggi = max(8, (4.2 * pdf_jumlah_baris($pdf, $ukuran, $sel)) + 2.6);
        if ($pdf->GetY() + $tinggi > $pdf->GetPageHeight() - 18) {
            $pdf->AddPage();
            $gambarKepala();
        }
        if ($indeks % 2 === 0) {
            $pdf->SetFillColor(255, 255, 255);
        } else {
            $pdf->SetFillColor(241, 245, 249);
        }
        gambar_baris_pdf($pdf, $ukuran, $sel, $rata, $tinggi, true);
    }

    $pdf->Output('D', 'laporan-' . preg_replace('/[^a-z0-9_-]/', '', $jenis) . '.pdf');
    exit;
}

function gaya_kolom_pdf(string $nama): array
{
    $tengah = ['Tanggal', 'Hari/Tanggal', 'Masuk', 'Pulang', 'Pertemuan', 'Jumlah', 'Status', 'Disetujui', 'Level', 'Level Materi', 'Semester', 'Tahun', 'Kategori', 'Mulai', 'Selesai', 'Telepon', 'Role'];
    $profil = [
        'Pertemuan' => [16, 18],
        'Jumlah' => [18, 22],
        'Tahun' => [16, 20],
        'Masuk' => [22, 26],
        'Pulang' => [22, 26],
        'Telepon' => [28, 32],
        'Mulai' => [24, 28],
        'Selesai' => [24, 28],
        'Semester' => [24, 28],
        'Level' => [22, 28],
        'Role' => [24, 30],
        'Status' => [28, 34],
        'Disetujui' => [26, 32],
        'Kategori' => [28, 36],
        'Level Materi' => [28, 36],
        'Tanggal' => [26, 32],
        'Hari/Tanggal' => [38, 44],
        'Agenda' => [48, 70],
        'Keterangan' => [36, 52],
        'Add-on' => [46, 68],
        'Materi' => [40, 58],
        'Alamat' => [60, 90],
        'Email' => [42, 58],
        'Nama Trainer' => [36, 48],
        'Sekolah' => [40, 54],
        'Nama Sekolah' => [48, 70],
        'Nama' => [38, 52],
        'Nama Kit' => [48, 70],
        'Nama Set' => [40, 56],
        'Asal Sekolah' => [40, 54],
        'Jurusan' => [32, 44],
    ];
    $ukuran = $profil[$nama] ?? [30, 42];
    $lentur = ['Agenda', 'Keterangan', 'Add-on', 'Materi', 'Alamat', 'Email', 'Nama', 'Nama Kit', 'Nama Set', 'Nama Trainer', 'Sekolah', 'Nama Sekolah', 'Asal Sekolah', 'Jurusan'];

    return [
        'min' => $ukuran[0],
        'ideal' => $ukuran[1],
        'align' => in_array($nama, $tengah, true) ? 'C' : 'L',
        'lentur' => in_array($nama, $lentur, true),
    ];
}

function lebar_kolom_pdf(array $kolom, float $total): array
{
    $gaya = array_map(static fn (string $nama): array => gaya_kolom_pdf($nama), $kolom);
    $ideal = array_sum(array_column($gaya, 'ideal'));
    $lebar = [];
    if ($ideal <= $total) {
        $sisa = $total - $ideal;
        $lentur = 0;
        foreach ($gaya as $item) {
            if ($item['lentur']) {
                $lentur++;
            }
        }
        $tambah = $lentur > 0 ? $sisa / $lentur : ($sisa / max(1, count($gaya)));
        foreach ($gaya as $i => $item) {
            $lebar[$i] = $item['ideal'] + (($lentur === 0 || $item['lentur']) ? $tambah : 0);
        }
    } else {
        $skala = $total / $ideal;
        foreach ($gaya as $i => $item) {
            $lebar[$i] = max($item['min'], $item['ideal'] * $skala);
        }
    }
    $jumlah = array_sum($lebar);
    if ($jumlah > 0 && abs($jumlah - $total) > 0.05) {
        $skala = $total / $jumlah;
        foreach ($lebar as $i => $nilai) {
            $lebar[$i] = $nilai * $skala;
        }
    }
    $terpakai = 0.0;
    $akhir = array_key_last($lebar);
    foreach ($lebar as $i => $nilai) {
        if ($i === $akhir) {
            $lebar[$i] = round($total - $terpakai, 2);
        } else {
            $lebar[$i] = round($nilai, 2);
            $terpakai += $lebar[$i];
        }
    }

    return $lebar;
}

function pdf_jumlah_baris(FPDF $pdf, array $ukuran, array $sel): int
{
    $pdf->SetFont('Arial', '', 8);
    $maks = 1;
    foreach ($sel as $i => $teks) {
        $maks = max($maks, pdf_baris_teks($pdf, $ukuran[$i] - 3, $teks));
    }

    return $maks;
}

function pdf_baris_teks(FPDF $pdf, float $lebar, string $teks): int
{
    $jumlah = 0;
    foreach (explode("\n", str_replace("\r", '', $teks)) as $paragraf) {
        $kata = $paragraf === '' ? [''] : explode(' ', $paragraf);
        $saat = '';
        $baris = 1;
        foreach ($kata as $bagian) {
            $coba = $saat === '' ? $bagian : $saat . ' ' . $bagian;
            if ($saat !== '' && $pdf->GetStringWidth($coba) > $lebar) {
                $baris++;
                $saat = $bagian;
            } else {
                $saat = $coba;
            }
        }
        $jumlah += $baris;
    }

    return max(1, $jumlah);
}

function gambar_baris_pdf(FPDF $pdf, array $ukuran, array $sel, array $rata, float $tinggi, bool $isi): void
{
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $awal = $x;
    foreach ($sel as $i => $teks) {
        $pdf->Rect($x, $y, $ukuran[$i], $tinggi, $isi ? 'DF' : 'D');
        $dalam = $ukuran[$i] - 3;
        $tinggiTeks = 4.2 * pdf_baris_teks($pdf, $dalam, $teks);
        $pdf->SetXY($x + 1.5, $y + max(1.3, ($tinggi - $tinggiTeks) / 2));
        $pdf->MultiCell($dalam, 4.2, $teks, 0, $rata[$i] ?? 'L');
        $x += $ukuran[$i];
    }
    $pdf->SetXY($awal, $y + $tinggi);
}

function pdf_teks(string $teks): string
{
    $teks = str_replace(["\r\n", "\r"], "\n", $teks);
    $hasil = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $teks);
    if ($hasil === false || $hasil === '') {
        $hasil = iconv('UTF-8', 'ISO-8859-1//IGNORE', $teks);
    }

    return $hasil === false ? $teks : $hasil;
}
