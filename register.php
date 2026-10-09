<?php
require __DIR__ . '/includes/bootstrap.php';

if (!$config['allow_register']) {
    abort(403, 'Pendaftaran mandiri dinonaktifkan. Hubungi admin sekolah.');
}
if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$data = ['name' => '', 'username' => '', 'email' => ''];
if (is_post()) {
    verify_csrf();
    $data = ['name' => post('name'), 'username' => strtolower(post('username')), 'email' => post('email')];
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($data['name'] === '' || mb_strlen($data['name']) > 100) {
        $errors[] = 'Nama wajib diisi (maks. 100 karakter).';
    }
    if (!preg_match('/^[a-z0-9._]{3,30}$/', $data['username'])) {
        $errors[] = 'Username 3–30 karakter, hanya huruf kecil, angka, titik, atau garis bawah.';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }
    if (!$errors) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$data['username']]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Username sudah digunakan.';
        }
    }
    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (name, username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['name'], $data['username'], $data['email'] ?: null, password_hash($password, PASSWORD_DEFAULT), 'siswa', now()]);
        login_user(['id' => db()->lastInsertId()]);
        flash('success', 'Pendaftaran berhasil! Masukkan kode kelas dari guru untuk bergabung.');
        redirect('dashboard.php');
    }
}

$title = 'Daftar';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1 class="auth-title">Daftar Akun Siswa</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="name">Nama Lengkap</label>
        <input type="text" id="name" name="name" value="<?= e($data['name']) ?>" required maxlength="100">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($data['username']) ?>" required maxlength="30" autocomplete="username">
        <label for="email">Email (opsional)</label>
        <input type="email" id="email" name="email" value="<?= e($data['email']) ?>">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <label for="password_confirm">Ulangi Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
        <div class="form-actions"><button class="btn block" type="submit">Daftar</button></div>
    </form>
    <p class="muted small auth-title">Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
