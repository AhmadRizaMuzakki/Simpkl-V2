<?php

$db = $config['db'];
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['name'],
    $db['charset']
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->query('SELECT 1 FROM users LIMIT 1');
} catch (PDOException $e) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
        header('Location: ' . base_url('setup.php'));
        exit;
    }
}

function db(): PDO
{
    global $pdo;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Koneksi database belum siap.');
    }
    return $pdo;
}
