<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';
if (is_post()) {
    verify_csrf();
    $username = post('username');
    $password = (string) ($_POST['password'] ?? '');

    // Pembatasan percobaan login sederhana per sesi
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'time' => time()];
    if (time() - $attempts['time'] > 300) {
        $attempts = ['count' => 0, 'time' => time()];
    }
    if ($attempts['count'] >= 5) {
        $error = 'Terlalu banyak percobaan login. Coba lagi dalam 5 menit.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            unset($_SESSION['login_attempts']);
            login_user($user);
            flash('success', 'Selamat datang, ' . $user['name'] . '!');
            redirect('dashboard.php');
        }
        $attempts['count']++;
        $_SESSION['login_attempts'] = $attempts;
        $error = 'Username atau password salah.';
    }
}

$title = 'Masuk';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1 class="auth-title">📚 <?= e($config['app_name']) ?></h1>
    <p class="muted auth-title">Silakan masuk untuk melanjutkan</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <div class="form-actions"><button class="btn block" type="submit">Masuk</button></div>
    </form>
    <?php if ($config['allow_register']): ?>
        <p class="muted small auth-title">Siswa baru? <a href="register.php">Daftar di sini</a></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
