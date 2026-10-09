<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'profile') {
        $name = post('name');
        $email = post('email');
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Nama wajib diisi (maks. 100 karakter).');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Format email tidak valid.');
        } else {
            db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email ?: null, $user['id']]);
            flash('success', 'Profil diperbarui.');
        }
    } elseif ($action === 'password') {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();
        $old = (string) ($_POST['old_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if (!password_verify($old, $hash)) {
            flash('error', 'Password lama salah.');
        } elseif (strlen($new) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
        } elseif ($new !== $confirm) {
            flash('error', 'Konfirmasi password baru tidak sama.');
        } elseif ($new === $old) {
            flash('error', 'Password baru harus berbeda dari password lama.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Password berhasil diganti.');
        }
    }
    redirect('profile.php');
}

$title = 'Profil Saya';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>Profil Saya</h1></div>
<div class="form-row">
    <div class="card">
        <h2>Data Diri</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <label>Username</label>
            <input type="text" value="<?= e($user['username']) ?>" disabled>
            <label>Peran</label>
            <input type="text" value="<?= e(role_label($user['role'])) ?>" disabled>
            <label for="name">Nama Lengkap</label>
            <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" required maxlength="100">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($user['email']) ?>">
            <div class="form-actions"><button class="btn" type="submit">Simpan</button></div>
        </form>
    </div>
    <div class="card">
        <h2>Ganti Password</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label for="old_password">Password Lama</label>
            <input type="password" id="old_password" name="old_password" required autocomplete="current-password">
            <label for="new_password">Password Baru</label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
            <label for="new_password_confirm">Ulangi Password Baru</label>
            <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
            <div class="form-actions"><button class="btn" type="submit">Ganti Password</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
