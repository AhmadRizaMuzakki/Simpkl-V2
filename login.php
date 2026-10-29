<?php

require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $error = 'Email atau password salah.';
    } elseif (strtoupper((string) $user['status']) !== 'AKTIF') {
        $error = 'Akun tidak aktif. Hubungi administrator.';
    } else {
        session_regenerate_id(true);
        $peserta = get_peserta_by_email($user['email']);
        $_SESSION['user'] = [
            'id_user' => $user['id_user'],
            'email' => $user['email'],
            'nama' => $user['nama'],
            'role' => $user['role'],
            'id_peserta' => $peserta['id_peserta'] ?? null,
        ];
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e($config['app_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/styles.css')) ?>" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-8">
            <div class="card o-hidden border-0 shadow-lg my-5">
                <div class="card-body p-0">
                    <div class="p-5">
                        <div class="text-center">
                            <h1 class="h4 text-gray-900 mb-4">Login</h1>
                        </div>
                                <?php if ($msg = flash('error')): ?>
                                    <div class="alert alert-danger"><?= e($msg) ?></div>
                                <?php endif; ?>
                                <?php if ($msg = flash('success')): ?>
                                    <div class="alert alert-success"><?= e($msg) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($_GET['out'])): ?>
                                    <div class="alert alert-success">Anda sudah keluar.</div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?= e($error) ?></div>
                                <?php endif; ?>
                                <form class="user" method="post" action="<?= e(base_url('login.php')) ?>">
                                    <?= csrf_field() ?>
                                    <div class="mb-3">
                                        <input class="form-control form-control-user" id="inputEmail" name="email" type="email" placeholder="Email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                                    </div>
                                    <div class="mb-3">
                                        <input class="form-control form-control-user" id="inputPassword" name="password" type="password" placeholder="Password" required>
                                    </div>
                                    <button class="btn btn-primary btn-user w-100" type="submit">Login</button>
                                </form>
                                <hr>
                                <div class="small text-gray-600 text-start">
                                    <div class="font-weight-bold mb-1">Akun demo</div>
                                    Admin: alhafidz.almagribi409@gmail.com / admin123<br>
                                    Staff: pembimbing@unida.ac.id / pembimbing123<br>
                                    Anggota: peserta1@unida.ac.id / peserta123
                                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
