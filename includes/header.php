<?php
$user = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
$staff = is_staff($user);

function nav_item(string $menu, string $active): string
{
    return $menu === $active ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/styles.css')) ?>?v=16" rel="stylesheet">
</head>
<body id="page-top">
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= e(base_url('dashboard.php')) ?>">
            <div class="sidebar-brand-icon">
                <i class="fas fa-robot"></i>
            </div>
            <div class="sidebar-brand-text mx-3">SIMPKL</div>
        </a>
        <hr class="sidebar-divider my-0">
        <li class="nav-item<?= nav_item('dashboard', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('dashboard.php')) ?>">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <hr class="sidebar-divider">
        <?php if ($staff): ?>
        <div class="sidebar-heading">Monitoring</div>
        <li class="nav-item<?= nav_item('peserta', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('peserta.php')) ?>">
                <i class="fas fa-fw fa-user-graduate"></i>
                <span>Peserta</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('sekolah', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('sekolah.php')) ?>">
                <i class="fas fa-fw fa-school"></i>
                <span>Data Sekolah</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('silabus', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('silabus.php')) ?>">
                <i class="fas fa-fw fa-book-open"></i>
                <span>Silabus</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('set', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('set.php')) ?>">
                <i class="fas fa-fw fa-toolbox"></i>
                <span>Set</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('kit', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('kit.php')) ?>">
                <i class="fas fa-fw fa-box"></i>
                <span>Kit</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('training', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('training.php')) ?>">
                <i class="fas fa-fw fa-chalkboard-teacher"></i>
                <span>Training</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('absensi', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('absensi.php')) ?>">
                <i class="fas fa-fw fa-calendar-check"></i>
                <span>Absensi</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('notulen', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('notulen.php')) ?>">
                <i class="fas fa-fw fa-clipboard"></i>
                <span>Notulen</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('laporan', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('laporan.php')) ?>">
                <i class="fas fa-fw fa-file-alt"></i>
                <span>Laporan</span>
            </a>
        </li>
        <?php else: ?>
        <div class="sidebar-heading">Kegiatan</div>
        <li class="nav-item<?= nav_item('absensi', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('absensi.php')) ?>">
                <i class="fas fa-fw fa-calendar-check"></i>
                <span>Absensi</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('training', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('training.php')) ?>">
                <i class="fas fa-fw fa-chalkboard-teacher"></i>
                <span>Training</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('set', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('set.php')) ?>">
                <i class="fas fa-fw fa-toolbox"></i>
                <span>Set</span>
            </a>
        </li>
        <li class="nav-item<?= nav_item('notulen', $activeMenu) ?>">
            <a class="nav-link" href="<?= e(base_url('notulen.php')) ?>">
                <i class="fas fa-fw fa-clipboard"></i>
                <span>Notulen</span>
            </a>
        </li>
        <?php endif; ?>
        <hr class="sidebar-divider d-none d-md-block">
        <div class="sidebar-card d-none d-md-flex">
            <div class="sidebar-card-title">Masuk sebagai</div>
            <p><?= e($user['nama'] ?? '') ?></p>
        </div>
        <div class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle" type="button" aria-label="Ciutkan menu"></button>
        </div>
    </ul>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button class="btn btn-link d-md-none rounded-circle me-2" id="sidebarToggleTop" type="button" aria-label="Buka menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="d-md-none topbar-title text-truncate">SIMPKL</div>
                <?php if ($staff): ?>
                <form class="me-auto ms-md-3 my-2 my-md-0 navbar-search" action="<?= e(base_url('peserta.php')) ?>" method="get">
                    <div class="input-group">
                        <input class="form-control bg-light border-0 small" type="text" name="q" placeholder="Cari peserta..." value="<?= e($_GET['q'] ?? '') ?>" aria-label="Cari peserta">
                        <button class="btn btn-primary" type="submit" aria-label="Cari"><i class="fas fa-search fa-sm"></i></button>
                    </div>
                </form>
                <?php endif; ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= e($user['nama'] ?? '') ?></span>
                            <i class="fas fa-user-circle fa-lg"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                            <li><span class="dropdown-item-text">
                                <strong class="text-gray-800"><?= e($user['nama'] ?? '') ?></strong><br>
                                <span class="small text-gray-500"><?= e($user['email'] ?? '') ?></span><br>
                                <span class="badge bg-secondary"><?= e(label_role((string) ($user['role'] ?? ''))) ?></span>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= e(base_url('logout.php')) ?>"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800"><?= e($pageTitle) ?></h1>
                </div>
                <?php if ($msg = flash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>
                <?php if ($msg = flash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>
