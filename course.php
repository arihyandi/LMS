<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];
$pdo = db();

$course = course_or_abort(get_int('id'), $user);
$cid = (int) $course['id'];
$canManage = can_manage_course($course, $user);
$tabs = ['materi' => 'Materi', 'tugas' => 'Tugas', 'kuis' => 'Kuis', 'pengumuman' => 'Pengumuman', 'anggota' => 'Anggota', 'nilai' => 'Nilai'];
$tab = $_GET['tab'] ?? 'materi';
if (!isset($tabs[$tab])) {
    $tab = 'materi';
}
$self = 'course.php?id=' . $cid;

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if (!$canManage) {
        abort(403, 'Hanya guru pengampu yang dapat melakukan aksi ini.');
    }
    switch ($action) {
        case 'announce':
            $t = post('title');
            $b = post('body');
            if ($t === '' || $b === '') {
                flash('error', 'Judul dan isi pengumuman wajib diisi.');
            } else {
                $pdo->prepare('INSERT INTO announcements (course_id, user_id, title, body, created_at) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$cid, $uid, mb_substr($t, 0, 150), $b, now()]);
                flash('success', 'Pengumuman dipublikasikan.');
            }
            redirect($self . '&tab=pengumuman');
        case 'delete_announcement':
            $pdo->prepare('DELETE FROM announcements WHERE id = ? AND course_id = ?')->execute([(int) post('announcement_id'), $cid]);
            flash('success', 'Pengumuman dihapus.');
            redirect($self . '&tab=pengumuman');
        case 'add_member':
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE username = ? AND role = 'siswa'");
            $stmt->execute([strtolower(post('username'))]);
            $student = $stmt->fetch();
            if (!$student) {
                flash('error', 'Siswa dengan username tersebut tidak ditemukan.');
            } elseif (is_enrolled($cid, (int) $student['id'])) {
                flash('info', $student['name'] . ' sudah terdaftar.');
            } else {
                $pdo->prepare('INSERT INTO enrollments (course_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$cid, $student['id'], now()]);
                flash('success', $student['name'] . ' ditambahkan ke kelas.');
            }
            redirect($self . '&tab=anggota');
        case 'remove_member':
            $pdo->prepare('DELETE FROM enrollments WHERE course_id = ? AND user_id = ?')->execute([$cid, (int) post('user_id')]);
            flash('success', 'Siswa dikeluarkan dari kelas.');
            redirect($self . '&tab=anggota');
        case 'regen_code':
            $pdo->prepare('UPDATE courses SET code = ? WHERE id = ?')->execute([generate_course_code(), $cid]);
            flash('success', 'Kode kelas baru telah dibuat.');
            redirect($self . '&tab=anggota');
    }
    redirect($self);
}

$title = $course['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($course['title']) ?></h1>
        <p class="muted">Guru: <?= e($course['teacher_name']) ?>
            <?php if ($canManage): ?> · Kode kelas: <span class="code-box"><?= e($course['code']) ?></span>
                <button type="button" class="btn sm secondary" data-copy="<?= e($course['code']) ?>">Salin</button><?php endif; ?></p>
    </div>
    <div class="actions">
        <?php if ($canManage): ?>
            <a class="btn secondary" href="course_form.php?id=<?= $cid ?>">Edit Kelas</a>
            <form method="post" action="courses.php" class="inline" data-confirm="Hapus kelas ini beserta seluruh materi, tugas, kuis, dan nilai? Tindakan ini tidak bisa dibatalkan.">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= $cid ?>">
                <button class="btn danger" type="submit">Hapus</button>
            </form>
        <?php elseif ($user['role'] === 'siswa'): ?>
            <form method="post" action="courses.php" class="inline" data-confirm="Keluar dari kelas ini?">
                <?= csrf_field() ?><input type="hidden" name="action" value="leave"><input type="hidden" name="course_id" value="<?= $cid ?>">
                <button class="btn secondary sm" type="submit">Keluar Kelas</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php if ($course['description']): ?><div class="card content"><?= nl2br_e($course['description']) ?></div><?php endif; ?>

<nav class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= $self ?>&amp;tab=<?= $key ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'materi'):
    $stmt = $pdo->prepare('SELECT l.*, (SELECT 1 FROM lesson_progress p WHERE p.lesson_id = l.id AND p.user_id = ?) AS done
                           FROM lessons l WHERE l.course_id = ? ORDER BY l.position, l.id');
    $stmt->execute([$uid, $cid]);
    $lessons = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header"><h2>Materi Pembelajaran</h2>
        <?php if ($canManage): ?><a class="btn" href="lesson_form.php?course_id=<?= $cid ?>">+ Tambah Materi</a><?php endif; ?></div>
    <?php if (!$lessons): ?><p class="empty">Belum ada materi.</p><?php else: ?>
    <ul class="list">
        <?php foreach ($lessons as $i => $l): ?>
        <li>
            <div><a class="title" href="lesson.php?id=<?= (int) $l['id'] ?>"><?= ($i + 1) . '. ' . e($l['title']) ?></a>
                <div class="muted small">
                    <?= youtube_id($l['video_url']) ? '🎬 Video · ' : '' ?><?= $l['file_path'] ? '📎 Lampiran · ' : '' ?><?= e(format_date($l['created_at'], false)) ?>
                </div></div>
            <?php if (!$canManage): ?><?= $l['done'] ? '<span class="badge green">Selesai</span>' : '<span class="badge gray">Belum</span>' ?><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'tugas'):
    $stmt = $pdo->prepare('SELECT a.*, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS sub_count,
                                  s.submitted_at AS my_submitted, s.score AS my_score
                           FROM assignments a LEFT JOIN submissions s ON s.assignment_id = a.id AND s.user_id = ?
                           WHERE a.course_id = ? ORDER BY a.due_at IS NULL, a.due_at, a.id');
    $stmt->execute([$uid, $cid]);
    $assignments = $stmt->fetchAll();
    $studentCount = (int) $pdo->query('SELECT COUNT(*) FROM enrollments WHERE course_id = ' . $cid)->fetchColumn();
?>
<div class="card">
    <div class="card-header"><h2>Tugas</h2>
        <?php if ($canManage): ?><a class="btn" href="assignment_form.php?course_id=<?= $cid ?>">+ Buat Tugas</a><?php endif; ?></div>
    <?php if (!$assignments): ?><p class="empty">Belum ada tugas.</p><?php else: ?>
    <ul class="list">
        <?php foreach ($assignments as $a): $late = $a['due_at'] && strtotime($a['due_at']) < time(); ?>
        <li>
            <div><a class="title" href="assignment.php?id=<?= (int) $a['id'] ?>"><?= e($a['title']) ?></a>
                <div class="muted small">Tenggat: <?= e(format_date($a['due_at'])) ?> · Nilai maks. <?= (int) $a['max_score'] ?></div></div>
            <?php if ($canManage): ?>
                <span class="badge"><?= (int) $a['sub_count'] ?>/<?= $studentCount ?> mengumpulkan</span>
            <?php elseif ($a['my_score'] !== null): ?>
                <span class="badge green">Nilai: <?= e((string) (float) $a['my_score']) ?></span>
            <?php elseif ($a['my_submitted']): ?>
                <span class="badge yellow">Menunggu penilaian</span>
            <?php else: ?>
                <span class="badge <?= $late ? 'red' : 'gray' ?>"><?= $late ? 'Terlambat' : 'Belum dikumpulkan' ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'kuis'):
    $sql = 'SELECT q.*, (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS qcount,
                   (SELECT COUNT(*) FROM quiz_attempts t WHERE t.quiz_id = q.id AND t.submitted_at IS NOT NULL) AS attempts,
                   t.score AS my_score, t.submitted_at AS my_submitted
            FROM quizzes q LEFT JOIN quiz_attempts t ON t.quiz_id = q.id AND t.user_id = ?
            WHERE q.course_id = ?' . ($canManage ? '' : ' AND q.published = 1') . ' ORDER BY q.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $cid]);
    $quizzes = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header"><h2>Kuis</h2>
        <?php if ($canManage): ?><a class="btn" href="quiz_form.php?course_id=<?= $cid ?>">+ Buat Kuis</a><?php endif; ?></div>
    <?php if (!$quizzes): ?><p class="empty">Belum ada kuis.</p><?php else: ?>
    <ul class="list">
        <?php foreach ($quizzes as $q): ?>
        <li>
            <div><a class="title" href="quiz.php?id=<?= (int) $q['id'] ?>"><?= e($q['title']) ?></a>
                <div class="muted small"><?= (int) $q['qcount'] ?> soal · <?= $q['time_limit'] ? (int) $q['time_limit'] . ' menit' : 'Tanpa batas waktu' ?></div></div>
            <?php if ($canManage): ?>
                <span class="actions">
                    <?= $q['published'] ? '<span class="badge green">Terbit</span>' : '<span class="badge gray">Draf</span>' ?>
                    <span class="badge"><?= (int) $q['attempts'] ?> dikerjakan</span>
                    <a class="btn sm secondary" href="quiz_form.php?id=<?= (int) $q['id'] ?>">Edit Soal</a>
                </span>
            <?php elseif ($q['my_submitted']): ?>
                <span class="badge green">Nilai: <?= e((string) (float) $q['my_score']) ?></span>
            <?php else: ?>
                <a class="btn sm" href="quiz.php?id=<?= (int) $q['id'] ?>">Kerjakan</a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'pengumuman'):
    $stmt = $pdo->prepare('SELECT a.*, u.name FROM announcements a JOIN users u ON u.id = a.user_id WHERE a.course_id = ? ORDER BY a.created_at DESC');
    $stmt->execute([$cid]);
    $items = $stmt->fetchAll();
?>
<?php if ($canManage): ?>
<div class="card">
    <h2>Buat Pengumuman</h2>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="announce">
        <label for="a_title">Judul</label>
        <input type="text" id="a_title" name="title" required maxlength="150">
        <label for="a_body">Isi</label>
        <textarea id="a_body" name="body" required></textarea>
        <div class="form-actions"><button class="btn" type="submit">Publikasikan</button></div>
    </form>
</div>
<?php endif; ?>
<?php if (!$items): ?><div class="card empty">Belum ada pengumuman.</div><?php endif; ?>
<?php foreach ($items as $a): ?>
<div class="card">
    <div class="card-header">
        <div><h3><?= e($a['title']) ?></h3><div class="muted small"><?= e($a['name']) ?> · <?= e(format_date($a['created_at'])) ?></div></div>
        <?php if ($canManage): ?>
        <form method="post" data-confirm="Hapus pengumuman ini?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="announcement_id" value="<?= (int) $a['id'] ?>">
            <button class="btn sm danger" type="submit">Hapus</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="content"><?= nl2br_e($a['body']) ?></div>
</div>
<?php endforeach; ?>

<?php elseif ($tab === 'anggota'):
    $stmt = $pdo->prepare('SELECT u.id, u.name, u.username, e.created_at FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY u.name');
    $stmt->execute([$cid]);
    $members = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-header"><h2>Anggota Kelas (<?= count($members) ?> siswa)</h2>
        <?php if ($canManage): ?>
        <div class="actions">
            <form method="post" class="actions">
                <?= csrf_field() ?><input type="hidden" name="action" value="add_member">
                <input type="text" name="username" placeholder="Username siswa" required>
                <button class="btn sm" type="submit">Tambah</button>
            </form>
            <form method="post" data-confirm="Buat kode kelas baru? Kode lama tidak bisa dipakai lagi.">
                <?= csrf_field() ?><input type="hidden" name="action" value="regen_code">
                <button class="btn sm secondary" type="submit">Ganti Kode Kelas</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <p class="muted small">Guru pengampu: <strong><?= e($course['teacher_name']) ?></strong></p>
    <?php if (!$members): ?><p class="empty">Belum ada siswa. Bagikan kode kelas <span class="code-box"><?= $canManage ? e($course['code']) : '' ?></span> kepada siswa.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>#</th><th>Nama</th><?php if ($canManage): ?><th>Username</th><th>Bergabung</th><th></th><?php endif; ?></tr>
        <?php foreach ($members as $i => $m): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($m['name']) ?></td>
            <?php if ($canManage): ?>
            <td><?= e($m['username']) ?></td><td><?= e(format_date($m['created_at'], false)) ?></td>
            <td><form method="post" data-confirm="Keluarkan <?= e($m['name']) ?> dari kelas?">
                <?= csrf_field() ?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                <button class="btn sm danger" type="submit">Keluarkan</button></form></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'nilai'):
    $stmt = $pdo->prepare('SELECT id, title, max_score FROM assignments WHERE course_id = ? ORDER BY due_at IS NULL, due_at, id');
    $stmt->execute([$cid]);
    $assignments = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT id, title FROM quizzes WHERE course_id = ? AND published = 1 ORDER BY created_at');
    $stmt->execute([$cid]);
    $quizzes = $stmt->fetchAll();
    if ($canManage) {
        $stmt = $pdo->prepare('SELECT u.id, u.name FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY u.name');
        $stmt->execute([$cid]);
        $students = $stmt->fetchAll();
    } else {
        $students = [['id' => $uid, 'name' => $user['name']]];
    }
    // Peta nilai: [user_id]['a'.id] / ['q'.id]
    $grades = [];
    $stmt = $pdo->prepare('SELECT s.user_id, s.assignment_id, s.score, a.max_score FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.course_id = ?');
    $stmt->execute([$cid]);
    foreach ($stmt as $r) {
        $grades[$r['user_id']]['a' . $r['assignment_id']] = $r['score'] === null ? 'pending' : round($r['score'] / max(1, $r['max_score']) * 100, 1);
    }
    $stmt = $pdo->prepare('SELECT t.user_id, t.quiz_id, t.score FROM quiz_attempts t JOIN quizzes q ON q.id = t.quiz_id WHERE q.course_id = ? AND t.submitted_at IS NOT NULL');
    $stmt->execute([$cid]);
    foreach ($stmt as $r) {
        $grades[$r['user_id']]['q' . $r['quiz_id']] = (float) $r['score'];
    }
?>
<div class="card">
    <div class="card-header"><h2>Rekap Nilai</h2>
        <div class="actions">
            <?php if ($canManage): ?><a class="btn sm secondary" href="export_grades.php?course_id=<?= $cid ?>">⬇ Unduh CSV (Excel)</a><?php endif; ?>
            <button type="button" class="btn sm secondary" data-print>🖨 Cetak</button>
        </div>
    </div>
    <p class="muted small">Semua nilai dikonversi ke skala 0–100. Rata-rata dihitung dari tugas yang sudah dinilai dan kuis yang sudah dikerjakan.</p>
    <?php if (!$students || (!$assignments && !$quizzes)): ?><p class="empty">Belum ada data nilai.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Nama</th>
            <?php foreach ($assignments as $a): ?><th class="num" title="Tugas">📝 <?= e($a['title']) ?></th><?php endforeach; ?>
            <?php foreach ($quizzes as $q): ?><th class="num" title="Kuis">✅ <?= e($q['title']) ?></th><?php endforeach; ?>
            <th class="num">Rata-rata</th></tr>
        <?php foreach ($students as $s): $g = $grades[$s['id']] ?? []; $vals = []; ?>
        <tr><td><?= e($s['name']) ?></td>
            <?php foreach (array_merge(array_map(fn ($a) => 'a' . $a['id'], $assignments), array_map(fn ($q) => 'q' . $q['id'], $quizzes)) as $key):
                $v = $g[$key] ?? null; if (is_float($v)) { $vals[] = $v; } ?>
                <td class="num"><?= $v === null ? '<span class="muted">-</span>' : ($v === 'pending' ? '<span class="badge yellow">?</span>' : e((string) $v)) ?></td>
            <?php endforeach; ?>
            <td class="num"><strong><?= $vals ? e((string) round(array_sum($vals) / count($vals), 1)) : '-' ?></strong></td></tr>
        <?php endforeach; ?>
    </table></div>
    <p class="muted small">Keterangan: <span class="badge yellow">?</span> sudah dikumpulkan, belum dinilai · - belum mengerjakan</p>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
