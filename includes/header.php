<?php
$__user = current_user();
$__page = basename($_SERVER['SCRIPT_NAME']);
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' — ' . $config['app_name'] : $config['app_name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<?php if ($__user): ?>
<header class="topbar">
    <a class="brand" href="dashboard.php">📚 <?= e($config['app_name']) ?></a>
    <button class="nav-toggle" type="button" data-toggle="nav" aria-label="Menu">☰</button>
    <nav class="nav" id="nav">
        <a href="dashboard.php" class="<?= $__page === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="courses.php" class="<?= $__page === 'courses.php' ? 'active' : '' ?>">Kelas</a>
        <?php if ($__user['role'] === 'admin'): ?>
            <a href="admin_users.php" class="<?= $__page === 'admin_users.php' ? 'active' : '' ?>">Pengguna</a>
        <?php endif; ?>
        <a href="profile.php" class="<?= $__page === 'profile.php' ? 'active' : '' ?>">
            <?= e($__user['name']) ?> <span class="badge"><?= e(role_label($__user['role'])) ?></span>
        </a>
        <form method="post" action="logout.php" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="link">Keluar</button>
        </form>
    </nav>
</header>
<?php endif; ?>
<main class="container<?= $__user ? '' : ' narrow' ?>">
<?php foreach (take_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
