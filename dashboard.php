<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];
$pdo = db();

$title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Halo, <?= e($user['name']) ?> 👋</h1>
        <p class="muted"><?= e(format_date(now(), false)) ?> · <?= e(role_label($user['role'])) ?></p>
    </div>
</div>

<?php if ($user['role'] === 'admin'):
    $counts = $pdo->query("SELECT role, COUNT(*) AS n FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
    $courseCount = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $recent = $pdo->query('SELECT c.*, u.name AS teacher_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
                           FROM courses c JOIN users u ON u.id = c.teacher_id ORDER BY c.created_at DESC LIMIT 6')->fetchAll();
?>
    <div class="stats">
        <div class="stat"><div class="num"><?= $courseCount ?></div><div class="muted">Kelas</div></div>
        <div class="stat"><div class="num"><?= (int) ($counts['guru'] ?? 0) ?></div><div class="muted">Guru</div></div>
        <div class="stat"><div class="num"><?= (int) ($counts['siswa'] ?? 0) ?></div><div class="muted">Siswa</div></div>
        <div class="stat"><div class="num"><?= (int) ($counts['admin'] ?? 0) ?></div><div class="muted">Admin</div></div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Kelas Terbaru</h2><a class="btn sm secondary" href="admin_users.php">Kelola Pengguna</a></div>
        <?php if (!$recent): ?><p class="empty">Belum ada kelas.</p><?php else: ?>
        <ul class="list">
            <?php foreach ($recent as $c): ?>
            <li><div><a class="title" href="course.php?id=<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a>
                <div class="muted small">Guru: <?= e($c['teacher_name']) ?> · <?= (int) $c['students'] ?> siswa</div></div></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

<?php elseif ($user['role'] === 'guru'):
    $stmt = $pdo->prepare('SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
                           FROM courses c WHERE c.teacher_id = ? ORDER BY c.created_at DESC');
    $stmt->execute([$uid]);
    $courses = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT s.id, s.submitted_at, u.name AS student, a.id AS assignment_id, a.title, c.title AS course
                           FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN courses c ON c.id = a.course_id
                           JOIN users u ON u.id = s.user_id
                           WHERE c.teacher_id = ? AND s.score IS NULL ORDER BY s.submitted_at LIMIT 10');
    $stmt->execute([$uid]);
    $ungraded = $stmt->fetchAll();
    $totalStudents = array_sum(array_column($courses, 'students'));
?>
    <div class="stats">
        <div class="stat"><div class="num"><?= count($courses) ?></div><div class="muted">Kelas Saya</div></div>
        <div class="stat"><div class="num"><?= (int) $totalStudents ?></div><div class="muted">Total Siswa</div></div>
        <div class="stat"><div class="num"><?= count($ungraded) ?></div><div class="muted">Tugas Belum Dinilai</div></div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Tugas Perlu Dinilai</h2></div>
        <?php if (!$ungraded): ?><p class="empty">Semua tugas sudah dinilai. 🎉</p><?php else: ?>
        <ul class="list">
            <?php foreach ($ungraded as $s): ?>
            <li><div><span class="title"><?= e($s['student']) ?></span> — <?= e($s['title']) ?>
                <div class="muted small"><?= e($s['course']) ?> · dikumpulkan <?= e(format_date($s['submitted_at'])) ?></div></div>
                <a class="btn sm" href="assignment.php?id=<?= (int) $s['assignment_id'] ?>">Nilai</a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="card-header"><h2>Kelas Saya</h2><a class="btn" href="course_form.php">+ Buat Kelas</a></div>
    <?php if (!$courses): ?><div class="card empty">Anda belum membuat kelas. Klik "Buat Kelas" untuk memulai.</div><?php else: ?>
    <div class="grid">
        <?php foreach ($courses as $c): ?>
        <div class="card course-card">
            <h3><a href="course.php?id=<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a></h3>
            <p class="muted small"><?= e(mb_strimwidth((string) $c['description'], 0, 120, '…')) ?></p>
            <div class="meta small muted">Kode: <span class="code-box"><?= e($c['code']) ?></span> · <?= (int) $c['students'] ?> siswa</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php else: // siswa
    $stmt = $pdo->prepare('SELECT c.*, u.name AS teacher_name,
                              (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count,
                              (SELECT COUNT(*) FROM lesson_progress p JOIN lessons l ON l.id = p.lesson_id WHERE l.course_id = c.id AND p.user_id = ?) AS done_count
                           FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN users u ON u.id = c.teacher_id
                           WHERE e.user_id = ? ORDER BY c.title');
    $stmt->execute([$uid, $uid]);
    $courses = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT a.*, c.title AS course FROM assignments a
                           JOIN enrollments e ON e.course_id = a.course_id AND e.user_id = ?
                           JOIN courses c ON c.id = a.course_id
                           WHERE NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.user_id = ?)
                           ORDER BY a.due_at IS NULL, a.due_at LIMIT 10');
    $stmt->execute([$uid, $uid]);
    $todo = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT q.*, c.title AS course FROM quizzes q
                           JOIN enrollments e ON e.course_id = q.course_id AND e.user_id = ?
                           JOIN courses c ON c.id = q.course_id
                           WHERE q.published = 1 AND NOT EXISTS (SELECT 1 FROM quiz_attempts t WHERE t.quiz_id = q.id AND t.user_id = ? AND t.submitted_at IS NOT NULL)
                           ORDER BY q.created_at DESC LIMIT 10');
    $stmt->execute([$uid, $uid]);
    $quizzes = $stmt->fetchAll();
?>
    <div class="card">
        <h2>Gabung Kelas</h2>
        <form method="post" action="courses.php" class="actions">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="join">
            <input type="text" name="code" placeholder="Masukkan kode kelas, mis. AB12CD" required maxlength="10" class="code-input">
            <button class="btn" type="submit">Gabung</button>
        </form>
    </div>
    <div class="form-row">
        <div class="card">
            <h2>📝 Tugas Belum Dikumpulkan</h2>
            <?php if (!$todo): ?><p class="empty">Tidak ada tugas tertunda.</p><?php else: ?>
            <ul class="list">
                <?php foreach ($todo as $a): $late = $a['due_at'] && strtotime($a['due_at']) < time(); ?>
                <li><div><a class="title" href="assignment.php?id=<?= (int) $a['id'] ?>"><?= e($a['title']) ?></a>
                    <div class="muted small"><?= e($a['course']) ?> · Tenggat: <?= e(format_date($a['due_at'])) ?></div></div>
                    <?php if ($late): ?><span class="badge red">Terlambat</span><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2>✅ Kuis Tersedia</h2>
            <?php if (!$quizzes): ?><p class="empty">Tidak ada kuis baru.</p><?php else: ?>
            <ul class="list">
                <?php foreach ($quizzes as $q): ?>
                <li><div><a class="title" href="quiz.php?id=<?= (int) $q['id'] ?>"><?= e($q['title']) ?></a>
                    <div class="muted small"><?= e($q['course']) ?><?= $q['time_limit'] ? ' · ' . (int) $q['time_limit'] . ' menit' : '' ?></div></div></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
    <h2>Kelas Saya</h2>
    <?php if (!$courses): ?><div class="card empty">Anda belum bergabung ke kelas mana pun. Minta kode kelas kepada guru Anda.</div><?php else: ?>
    <div class="grid">
        <?php foreach ($courses as $c): $pct = $c['lesson_count'] ? (int) round($c['done_count'] / $c['lesson_count'] * 100) : 0; ?>
        <div class="card course-card">
            <h3><a href="course.php?id=<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a></h3>
            <p class="muted small">Guru: <?= e($c['teacher_name']) ?></p>
            <div class="meta">
                <progress class="progress" value="<?= $pct ?>" max="100"></progress>
                <div class="muted small">Materi selesai: <?= (int) $c['done_count'] ?>/<?= (int) $c['lesson_count'] ?> (<?= $pct ?>%)</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
