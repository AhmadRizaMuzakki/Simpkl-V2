<?php

require __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$staff = is_staff($user);
$idUser = (string) ($user['id_user'] ?? '');
$kosong = [
    'id_notulen' => '',
    'judul' => 'Resume Evaluasi Kegiatan Operasional',
    'cabang' => '',
    'pekan' => '',
    'tanggal_mulai' => '',
    'tanggal_selesai' => '',
    'nama_notulen' => (string) ($user['nama'] ?? ''),
    'baris' => baris_notulen_kosong(),
];
$form = $kosong;
$formError = null;
$openForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = (string) ($_POST['aksi'] ?? '');
    try {
        if ($aksi === 'hapus') {
            flash('success', hapus_notulen((string) ($_POST['id_notulen'] ?? ''), $staff, $idUser));
        } elseif ($aksi === 'ubah') {
            flash('success', update_notulen($_POST, $staff, $idUser));
        } else {
            flash('success', simpan_notulen($_POST, $idUser, (string) ($user['nama'] ?? '')));
        }
        redirect('notulen.php');
    } catch (PDOException $e) {
        $formError = 'Gagal menyimpan notulen.';
        $form = form_notulen_ulang($kosong, $_POST);
        $openForm = $aksi !== 'hapus';
        if ($aksi === 'hapus') {
            flash('error', $formError);
            redirect('notulen.php');
        }
    } catch (RuntimeException $e) {
        if ($aksi === 'hapus') {
            flash('error', $e->getMessage());
            redirect('notulen.php');
        }
        $formError = $e->getMessage();
        $form = form_notulen_ulang($kosong, $_POST);
        $openForm = true;
    }
}

$detail = null;
if (!$openForm && !empty($_GET['id'])) {
    $detail = ambil_notulen((string) $_GET['id']);
    if (!$detail || (!$staff && (string) $detail['id_user'] !== $idUser)) {
        flash('error', 'Notulen tidak ditemukan.');
        redirect('notulen.php');
    }
} elseif (!$openForm && !empty($_GET['edit'])) {
    $row = ambil_notulen((string) $_GET['edit']);
    if (!$row || (!$staff && (string) $row['id_user'] !== $idUser)) {
        flash('error', 'Notulen tidak ditemukan.');
        redirect('notulen.php');
    }
    $form = array_merge($kosong, $row);
    $openForm = true;
} elseif (!$openForm && isset($_GET['baru'])) {
    $openForm = true;
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id_notulen, judul, cabang, pekan, tanggal_mulai, tanggal_selesai, nama_notulen FROM notulen WHERE 1 = 1';
$params = [];
if (!$staff) {
    $sql .= ' AND id_user = ?';
    $params[] = $idUser;
}
if ($q !== '') {
    $sql .= ' AND (judul LIKE ? OR cabang LIKE ? OR pekan LIKE ? OR nama_notulen LIKE ?)';
    $cari = like_query($q);
    array_push($params, $cari, $cari, $cari, $cari);
}
$sql .= ' ORDER BY tanggal_mulai DESC, judul';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Notulen';
$activeMenu = 'notulen';
$editing = $form['id_notulen'] !== '';
$extraScripts = <<<'JS'
<script>
document.querySelectorAll("[data-tambah-baris]").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
        var daftar = tombol.closest("[data-kelompok]").querySelector("[data-daftar-baris]");
        var baru = daftar.querySelector("[data-baris]").cloneNode(true);
        baru.querySelectorAll("input, textarea").forEach(function (el) { el.value = ""; });
        daftar.appendChild(baru);
    });
});
document.addEventListener("click", function (event) {
    var hapus = event.target.closest("[data-hapus-baris]");
    if (!hapus) {
        return;
    }
    var baris = hapus.closest("[data-baris]");
    var daftar = baris.parentElement;
    if (daftar.querySelectorAll("[data-baris]").length === 1) {
        baris.querySelectorAll("input, textarea").forEach(function (el) { el.value = ""; });
        return;
    }
    baris.remove();
});
</script>
JS;
require __DIR__ . '/includes/header.php';
?>
<?php if ($detail): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clipboard me-1"></i>Notulen</span>
        <span class="d-flex gap-2">
            <a class="btn btn-sm btn-warning" href="<?= e(base_url('notulen.php?edit=' . urlencode($detail['id_notulen']))) ?>">Ubah</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('notulen.php')) ?>">Kembali</a>
        </span>
    </div>
    <div class="card-body notulen-dokumen">
        <h2 class="notulen-judul"><?= e($detail['judul']) ?></h2>
        <p class="notulen-periode"><?= e(periode_notulen($detail)) ?></p>
        <p class="notulen-penulis">Notulen: <?= e($detail['nama_notulen']) ?></p>
        <?php foreach (hari_notulen() as $hari): ?>
            <?php
            $kegiatan = saring_baris_notulen($detail['baris'], $hari, 'kegiatan');
            $catatan = saring_baris_notulen($detail['baris'], $hari, 'catatan');
            if (!$kegiatan && !$catatan) {
                continue;
            }
            ?>
            <h3 class="notulen-hari"><?= e(strtoupper($hari)) ?></h3>
            <?php foreach ($kegiatan as $item): ?>
                <div class="notulen-blok">
                    <div class="notulen-nama"><?= e($item['nama']) ?>:</div>
                    <ul>
                        <?php foreach (poin_notulen($item['isi']) as $poin): ?>
                            <li><?= e($poin) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
            <?php if ($catatan): ?>
                <div class="notulen-blok">
                    <div class="notulen-nama">Catatan:</div>
                    <?php foreach ($catatan as $item): ?>
                        <div class="notulen-nama"><?= e($item['nama']) ?>:</div>
                        <ul>
                            <?php foreach (poin_notulen($item['isi']) as $poin): ?>
                                <li><?= e($poin) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($openForm): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clipboard me-1"></i><?= $editing ? 'Ubah Notulen' : 'Tambah Notulen' ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('notulen.php')) ?>">Batal</a>
    </div>
    <div class="card-body">
        <?php if ($formError): ?>
            <div class="alert alert-danger"><?= e($formError) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('notulen.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= $editing ? 'ubah' : 'tambah' ?>">
            <input type="hidden" name="id_notulen" value="<?= e($form['id_notulen']) ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="judulNotulen">Judul</label>
                    <input class="form-control" id="judulNotulen" name="judul" value="<?= e($form['judul']) ?>" maxlength="255" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="penulisNotulen">Notulen</label>
                    <input class="form-control" id="penulisNotulen" value="<?= e($form['nama_notulen']) ?>" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="cabangNotulen">Cabang</label>
                    <input class="form-control" id="cabangNotulen" name="cabang" value="<?= e((string) $form['cabang']) ?>" maxlength="150" placeholder="Cabang Bogor">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="pekanNotulen">Pekan</label>
                    <input class="form-control" id="pekanNotulen" name="pekan" value="<?= e((string) $form['pekan']) ?>" maxlength="150" placeholder="Pekan ketiga September">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label" for="mulaiNotulen">Tanggal mulai</label>
                    <input class="form-control" id="mulaiNotulen" name="tanggal_mulai" type="date" value="<?= e((string) $form['tanggal_mulai']) ?>" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label" for="selesaiNotulen">Tanggal selesai</label>
                    <input class="form-control" id="selesaiNotulen" name="tanggal_selesai" type="date" value="<?= e((string) $form['tanggal_selesai']) ?>" required>
                </div>
            </div>
            <?php foreach (hari_notulen() as $hari): ?>
                <div class="card notulen-hari-form">
                    <div class="card-header"><?= e($hari) ?></div>
                    <div class="card-body">
                        <?php foreach (['kegiatan' => 'Kegiatan', 'catatan' => 'Catatan'] as $jenis => $label): ?>
                            <div data-kelompok>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold"><?= e($label) ?></span>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-tambah-baris>Tambah</button>
                                </div>
                                <div data-daftar-baris>
                                    <?php foreach (kelompok_baris_notulen($form['baris'], $hari, $jenis) as $item): ?>
                                        <div class="row g-2 mb-2" data-baris>
                                            <div class="col-md-4">
                                                <input class="form-control" name="<?= e($jenis) ?>[<?= e($hari) ?>][nama][]" value="<?= e($item['nama']) ?>" maxlength="150" placeholder="<?= $jenis === 'catatan' ? 'Nama sekolah' : 'Nama' ?>">
                                            </div>
                                            <div class="col-md-7">
                                                <textarea class="form-control" name="<?= e($jenis) ?>[<?= e($hari) ?>][isi][]" rows="2" maxlength="4000" placeholder="Satu poin tiap baris"><?= e($item['isi']) ?></textarea>
                                            </div>
                                            <div class="col-md-1">
                                                <button class="btn btn-outline-danger w-100" type="button" data-hapus-baris aria-label="Hapus baris">&times;</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <button class="btn btn-primary mt-3" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Simpan Notulen' ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-1"></i><?= $staff ? 'Semua Notulen' : 'Notulen Saya' ?></span>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('notulen.php?baru=1')) ?>">Tambah Notulen</a>
    </div>
    <div class="card-body">
        <form class="row g-2 align-items-center mb-3" method="get" action="<?= e(base_url('notulen.php')) ?>">
            <div class="col-md-6">
                <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul, cabang, pekan, atau notulen...">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary" type="submit">Cari</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="flat-table">
                <thead>
                    <tr><th>Periode</th><th>Judul</th><th>Cabang</th><th>Notulen</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="5" class="text-center text-muted">Belum ada notulen.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(fmt_date($row['tanggal_mulai'])) ?> – <?= e(fmt_date($row['tanggal_selesai'])) ?></td>
                        <td>
                            <?= e($row['judul']) ?>
                            <?php if ($row['pekan']): ?>
                                <div class="small text-muted"><?= e($row['pekan']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['cabang'] ?: '-') ?></td>
                        <td><?= e($row['nama_notulen']) ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-primary" href="<?= e(base_url('notulen.php?id=' . urlencode($row['id_notulen']))) ?>">Lihat</a>
                            <a class="btn btn-sm btn-warning" href="<?= e(base_url('notulen.php?edit=' . urlencode($row['id_notulen']))) ?>">Ubah</a>
                            <form class="d-inline" method="post" action="<?= e(base_url('notulen.php')) ?>" onsubmit="return confirm('Hapus notulen ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_notulen" value="<?= e($row['id_notulen']) ?>">
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
<?php

function form_notulen_ulang(array $kosong, array $input): array
{
    $form = $kosong;
    $form['id_notulen'] = trim((string) ($input['id_notulen'] ?? ''));
    $form['judul'] = trim((string) ($input['judul'] ?? $kosong['judul']));
    $form['cabang'] = trim((string) ($input['cabang'] ?? ''));
    $form['pekan'] = trim((string) ($input['pekan'] ?? ''));
    $form['tanggal_mulai'] = trim((string) ($input['tanggal_mulai'] ?? ''));
    $form['tanggal_selesai'] = trim((string) ($input['tanggal_selesai'] ?? ''));
    $baris = [];
    foreach (['kegiatan', 'catatan'] as $jenis) {
        $grup = $input[$jenis] ?? [];
        if (!is_array($grup)) {
            continue;
        }
        foreach (hari_notulen() as $hari) {
            $blok = $grup[$hari] ?? null;
            if (!is_array($blok)) {
                continue;
            }
            $namaList = is_array($blok['nama'] ?? null) ? $blok['nama'] : [];
            $isiList = is_array($blok['isi'] ?? null) ? $blok['isi'] : [];
            $jumlah = max(count($namaList), count($isiList));
            for ($i = 0; $i < $jumlah; $i++) {
                $baris[] = [
                    'hari' => $hari,
                    'jenis' => $jenis,
                    'nama' => trim((string) ($namaList[$i] ?? '')),
                    'isi' => trim((string) ($isiList[$i] ?? '')),
                ];
            }
        }
    }
    $form['baris'] = $baris === [] ? baris_notulen_kosong() : $baris;

    return $form;
}

function kelompok_baris_notulen(array $baris, string $hari, string $jenis): array
{
    $hasil = [];
    foreach ($baris as $item) {
        if (($item['hari'] ?? '') === $hari && ($item['jenis'] ?? '') === $jenis) {
            $hasil[] = $item;
        }
    }
    if ($hasil === []) {
        $hasil[] = ['nama' => '', 'isi' => ''];
    }

    return $hasil;
}

function saring_baris_notulen(array $baris, string $hari, string $jenis): array
{
    return array_values(array_filter(
        $baris,
        static fn (array $item): bool => ($item['hari'] ?? '') === $hari && ($item['jenis'] ?? '') === $jenis && trim((string) ($item['nama'] ?? '')) !== ''
    ));
}

function poin_notulen(string $isi): array
{
    $poin = [];
    foreach (preg_split('/\R/u', $isi) ?: [] as $baris) {
        $baris = trim($baris, " \t\n\r\0\x0B•-");
        if ($baris !== '') {
            $poin[] = $baris;
        }
    }

    return $poin === [] ? [$isi] : $poin;
}

function periode_notulen(array $row): string
{
    $bagian = [];
    if (!empty($row['pekan'])) {
        $bagian[] = (string) $row['pekan'];
    }
    $bagian[] = fmt_date($row['tanggal_mulai']) . ' – ' . fmt_date($row['tanggal_selesai']);
    if (!empty($row['cabang'])) {
        $bagian[] = (string) $row['cabang'];
    }

    return implode(', ', $bagian);
}
