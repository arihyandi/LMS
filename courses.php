<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'join' && $user['role'] === 'siswa') {
        $code = strtoupper(post('code'));
        $stmt = $pdo->prepare('SELECT id, title FROM courses WHERE code = ?');
        $stmt->execute([$code]);
        $course = $stmt->fetch();
        if (!$course) {
            flash('error', 'Kode kelas tidak ditemukan.');
            redirect('dashboard.php');
        }
        if (is_enrolled((int) $course['id'], $uid)) {
            flash('info', 'Anda sudah terdaftar di kelas ' . $course['title'] . '.');
        } else {
            $pdo->prepare('INSERT INTO enrollments (course_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$course['id'], $uid, now()]);
            flash('success', 'Berhasil bergabung ke kelas ' . $course['title'] . '.');
        }
        redirect('course.php?id=' . (int) $course['id']);
    }

    if ($action === 'leave' && $user['role'] === 'siswa') {
        $pdo->prepare('DELETE FROM enrollments WHERE course_id = ? AND user_id = ?')->execute([(int) post('course_id'), $uid]);
        flash('success', 'Anda telah keluar dari kelas.');
        redirect('courses.php');
    }

    if ($action === 'delete') {
        $course = course_or_abort((int) post('course_id'), $user, true);
        // Hapus file-file unggahan terkait kelas
        $files = $pdo->prepare('SELECT file_path FROM lessons WHERE course_id = ? UNION ALL SELECT file_path FROM assignments WHERE course_id = ?
                                UNION ALL SELECT s.file_path FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.course_id = ?');
        $files->execute([$course['id'], $course['id'], $course['id']]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) {
            delete_upload($f);
        }
        $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([$course['id']]);
        flash('success', 'Kelas "' . $course['title'] . '" telah dihapus.');
        redirect('courses.php');
    }
    redirect('courses.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$like = '%' . $search . '%';
$base = 'SELECT c.*, u.name AS teacher_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
         FROM courses c JOIN users u ON u.id = c.teacher_id';
if ($user['role'] === 'admin') {
    $stmt = $pdo->prepare("$base WHERE c.title LIKE ? ORDER BY c.title");
    $stmt->execute([$like]);
} elseif ($user['role'] === 'guru') {
    $stmt = $pdo->prepare("$base WHERE c.teacher_id = ? AND c.title LIKE ? ORDER BY c.title");
    $stmt->execute([$uid, $like]);
} else {
    $stmt = $pdo->prepare("$base JOIN enrollments en ON en.course_id = c.id AND en.user_id = ? WHERE c.title LIKE ? ORDER BY c.title");
    $stmt->execute([$uid, $like]);
}
$courses = $stmt->fetchAll();

$title = 'Kelas';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= $user['role'] === 'admin' ? 'Semua Kelas' : 'Kelas Saya' ?></h1>
    <div class="actions">
        <form method="get" class="actions">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari kelas…">
            <button class="btn secondary" type="submit">Cari</button>
        </form>
        <?php if ($user['role'] !== 'siswa'): ?><a class="btn" href="course_form.php">+ Buat Kelas</a><?php endif; ?>
    </div>
</div>

<?php if ($user['role'] === 'siswa'): ?>
<div class="card">
    <form method="post" class="actions">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="join">
        <input type="text" name="code" placeholder="Kode kelas" required maxlength="10" class="code-input">
        <button class="btn" type="submit">Gabung Kelas</button>
    </form>
</div>
<?php endif; ?>

<?php if (!$courses): ?>
    <div class="card empty">Belum ada kelas.</div>
<?php else: ?>
<div class="grid">
    <?php foreach ($courses as $c): ?>
    <div class="card course-card">
        <h3><a href="course.php?id=<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a></h3>
        <p class="muted small"><?= e(mb_strimwidth((string) $c['description'], 0, 140, '…')) ?></p>
        <div class="meta small muted">
            Guru: <?= e($c['teacher_name']) ?> · <?= (int) $c['students'] ?> siswa
            <?php if ($user['role'] !== 'siswa'): ?><br>Kode: <span class="code-box"><?= e($c['code']) ?></span><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
