<?php
require __DIR__ . '/includes/bootstrap.php';
$admin = require_role('admin');
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'create') {
        $name = post('name');
        $username = strtolower(post('username'));
        $role = post('role');
        $password = (string) ($_POST['password'] ?? '');
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Nama wajib diisi (maks. 100 karakter).');
        } elseif (!preg_match('/^[a-z0-9._]{3,30}$/', $username)) {
            flash('error', 'Username 3–30 karakter, hanya huruf kecil, angka, titik, atau garis bawah.');
        } elseif (!in_array($role, ['admin', 'guru', 'siswa'], true)) {
            flash('error', 'Peran tidak valid.');
        } elseif (strlen($password) < 8) {
            flash('error', 'Password minimal 8 karakter.');
        } elseif ($stmt->fetchColumn()) {
            flash('error', 'Username sudah digunakan.');
        } else {
            $pdo->prepare('INSERT INTO users (name, username, email, password_hash, role, must_change_password, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)')
                ->execute([$name, $username, post('email') ?: null, password_hash($password, PASSWORD_DEFAULT), $role, now()]);
            flash('success', 'Pengguna ' . $name . ' dibuat. Ia wajib mengganti password saat login pertama.');
        }
    }

    if ($action === 'import') {
        // Format CSV: nama;username;password;peran (peran opsional, default siswa)
        $created = 0;
        $skipped = 0;
        $lines = preg_split('/\r\n|\r|\n/', (string) ($_POST['csv'] ?? ''));
        $check = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $insert = $pdo->prepare('INSERT INTO users (name, username, password_hash, role, must_change_password, created_at) VALUES (?, ?, ?, ?, 1, ?)');
        $pdo->beginTransaction();
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = array_map('trim', str_getcsv($line, str_contains($line, ';') ? ';' : ',', '"', '\\'));
            [$name, $username, $password, $role] = array_pad($cols, 4, '');
            $username = strtolower($username);
            $role = $role === '' ? 'siswa' : strtolower($role);
            $check->execute([$username]);
            if ($name === '' || !preg_match('/^[a-z0-9._]{3,30}$/', $username) || strlen($password) < 8
                || !in_array($role, ['guru', 'siswa'], true) || $check->fetchColumn()) {
                $skipped++;
                continue;
            }
            $insert->execute([mb_substr($name, 0, 100), $username, password_hash($password, PASSWORD_DEFAULT), $role, now()]);
            $created++;
        }
        $pdo->commit();
        flash($created ? 'success' : 'warning', "Impor selesai: $created akun dibuat, $skipped baris dilewati.");
    }

    if ($action === 'reset_password') {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) post('user_id')]);
            flash('success', 'Password direset. Pengguna wajib menggantinya saat login.');
        }
    }

    if ($action === 'change_role') {
        $role = post('role');
        $targetId = (int) post('user_id');
        if ($targetId === (int) $admin['id']) {
            flash('error', 'Anda tidak dapat mengubah peran akun Anda sendiri.');
        } elseif (in_array($role, ['admin', 'guru', 'siswa'], true)) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $targetId]);
            flash('success', 'Peran pengguna diperbarui.');
        }
    }

    if ($action === 'delete') {
        $targetId = (int) post('user_id');
        if ($targetId === (int) $admin['id']) {
            flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE teacher_id = ?');
            $stmt->execute([$targetId]);
            if ($stmt->fetchColumn() > 0) {
                flash('error', 'Pengguna ini masih menjadi guru pengampu kelas. Pindahkan atau hapus kelasnya terlebih dahulu.');
            } else {
                $files = $pdo->prepare('SELECT file_path FROM submissions WHERE user_id = ?');
                $files->execute([$targetId]);
                foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) {
                    delete_upload($f);
                }
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                flash('success', 'Pengguna dihapus.');
            }
        }
    }
    redirect('admin_users.php?' . http_build_query(['role' => $_GET['role'] ?? '', 'q' => $_GET['q'] ?? '']));
}

$filterRole = $_GET['role'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT * FROM users WHERE (name LIKE ? OR username LIKE ?)';
$params = ['%' . $search . '%', '%' . $search . '%'];
if (in_array($filterRole, ['admin', 'guru', 'siswa'], true)) {
    $sql .= ' AND role = ?';
    $params[] = $filterRole;
}
$stmt = $pdo->prepare($sql . ' ORDER BY role, name');
$stmt->execute($params);
$users = $stmt->fetchAll();

$title = 'Kelola Pengguna';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1>Kelola Pengguna</h1></div>
<div class="form-row">
    <div class="card">
        <h2>Tambah Pengguna</h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <label for="name">Nama Lengkap</label>
            <input type="text" id="name" name="name" required maxlength="100">
            <div class="form-row">
                <div><label for="username">Username</label><input type="text" id="username" name="username" required maxlength="30"></div>
                <div><label for="role">Peran</label>
                    <select id="role" name="role"><option value="siswa">Siswa</option><option value="guru">Guru</option><option value="admin">Admin</option></select></div>
            </div>
            <label for="email">Email (opsional)</label>
            <input type="email" id="email" name="email">
            <label for="password">Password Awal</label>
            <input type="text" id="password" name="password" required minlength="8" autocomplete="off">
            <div class="form-actions"><button class="btn" type="submit">Tambah</button></div>
        </form>
    </div>
    <div class="card">
        <h2>Impor Massal (CSV)</h2>
        <p class="muted small">Satu akun per baris dengan format: <code>nama;username;password;peran</code><br>
            Peran: <code>siswa</code> atau <code>guru</code> (kosong = siswa). Bisa disalin langsung dari Excel (pisahkan dengan titik koma).</p>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="import">
            <textarea name="csv" rows="8" placeholder="Budi Santoso;budi;rahasia123;siswa&#10;Siti Aminah;siti;rahasia123;siswa" required></textarea>
            <div class="form-actions"><button class="btn" type="submit">Impor</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Daftar Pengguna (<?= count($users) ?>)</h2>
        <form method="get" class="actions">
            <select name="role">
                <option value="">Semua peran</option>
                <?php foreach (['admin', 'guru', 'siswa'] as $r): ?>
                    <option value="<?= $r ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama / username">
            <button class="btn secondary" type="submit">Filter</button>
        </form>
    </div>
    <div class="table-wrap"><table>
        <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Terdaftar</th><th>Aksi</th></tr>
        <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $admin['id']; ?>
        <tr>
            <td><?= e($u['name']) ?><?= $self ? ' <span class="badge">Anda</span>' : '' ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?php if ($self): ?><?= e(role_label($u['role'])) ?><?php else: ?>
                <form method="post" class="actions"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_role"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <select name="role">
                        <?php foreach (['admin', 'guru', 'siswa'] as $r): ?>
                            <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn sm secondary" type="submit">Ubah</button></form>
            <?php endif; ?></td>
            <td class="small"><?= e(format_date($u['created_at'], false)) ?></td>
            <td><?php if (!$self): ?>
                <div class="actions">
                    <form method="post" class="actions"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <input type="text" name="password" placeholder="Password baru" minlength="8" required autocomplete="off" class="input-sm">
                        <button class="btn sm secondary" type="submit">Reset</button></form>
                    <form method="post" data-confirm="Hapus pengguna <?= e($u['name']) ?>?"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <button class="btn sm danger" type="submit">Hapus</button></form>
                </div>
            <?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
