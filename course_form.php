<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'guru');
$pdo = db();

$id = get_int('id');
$course = $id ? course_or_abort($id, $user, true) : null;
$data = [
    'title' => $course['title'] ?? '',
    'description' => $course['description'] ?? '',
    'teacher_id' => (int) ($course['teacher_id'] ?? $user['id']),
];
$teachers = $user['role'] === 'admin'
    ? $pdo->query("SELECT id, name FROM users WHERE role IN ('guru','admin') ORDER BY name")->fetchAll()
    : [];
$error = '';

if (is_post()) {
    verify_csrf();
    $data['title'] = post('title');
    $data['description'] = post('description');
    if ($user['role'] === 'admin') {
        $data['teacher_id'] = (int) post('teacher_id');
        if (!in_array($data['teacher_id'], array_map('intval', array_column($teachers, 'id')), true)) {
            $error = 'Guru pengampu tidak valid.';
        }
    }
    if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
        $error = 'Nama kelas wajib diisi (maks. 150 karakter).';
    }
    if (!$error) {
        if ($course) {
            $pdo->prepare('UPDATE courses SET title = ?, description = ?, teacher_id = ? WHERE id = ?')
                ->execute([$data['title'], $data['description'], $data['teacher_id'], $course['id']]);
            $newId = (int) $course['id'];
            flash('success', 'Kelas diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO courses (title, description, code, teacher_id, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$data['title'], $data['description'], generate_course_code(), $data['teacher_id'], now()]);
            $newId = (int) $pdo->lastInsertId();
            flash('success', 'Kelas berhasil dibuat. Bagikan kode kelas kepada siswa.');
        }
        redirect('course.php?id=' . $newId);
    }
}

$title = $course ? 'Edit Kelas' : 'Buat Kelas';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1><?= e($title) ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="title">Nama Kelas / Mata Pelajaran</label>
        <input type="text" id="title" name="title" value="<?= e($data['title']) ?>" required maxlength="150" placeholder="mis. Informatika Kelas X-1">
        <label for="description">Deskripsi</label>
        <textarea id="description" name="description" placeholder="Tujuan pembelajaran, jadwal, aturan kelas…"><?= e($data['description']) ?></textarea>
        <?php if ($teachers): ?>
            <label for="teacher_id">Guru Pengampu</label>
            <select id="teacher_id" name="teacher_id">
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $data['teacher_id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn" type="submit">Simpan</button>
            <a class="btn secondary" href="<?= $course ? 'course.php?id=' . (int) $course['id'] : 'courses.php' ?>">Batal</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
