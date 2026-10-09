<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([get_int('id')]);
$quiz = $stmt->fetch() ?: abort(404, 'Kuis tidak ditemukan.');
$qid = (int) $quiz['id'];
$course = course_or_abort((int) $quiz['course_id'], $user);
$canManage = can_manage_course($course, $user);
$self = 'quiz.php?id=' . $qid;
if (!$canManage && !$quiz['published']) {
    abort(404, 'Kuis belum tersedia.');
}

$stmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY position, id');
$stmt->execute([$qid]);
$questions = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?');
$stmt->execute([$qid, $uid]);
$attempt = $stmt->fetch() ?: null;

// Sisa waktu (detik) untuk percobaan yang sedang berjalan
$remaining = null;
if ($attempt && !$attempt['submitted_at'] && $quiz['time_limit']) {
    $remaining = strtotime($attempt['started_at']) + $quiz['time_limit'] * 60 - time();
}

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'start' && !$canManage && !$attempt) {
        $pdo->prepare('INSERT OR IGNORE INTO quiz_attempts (quiz_id, user_id, started_at) VALUES (?, ?, ?)')->execute([$qid, $uid, now()]);
        redirect($self);
    }

    if ($action === 'submit' && !$canManage && $attempt && !$attempt['submitted_at']) {
        $answers = [];
        $correct = 0;
        // Toleransi 30 detik untuk keterlambatan jaringan; jawaban setelah itu tidak dihitung.
        $expired = $remaining !== null && $remaining < -30;
        foreach ($questions as $q) {
            $ans = $_POST['answer'][$q['id']] ?? '';
            $ans = is_string($ans) && in_array($ans, ['A', 'B', 'C', 'D'], true) && !$expired ? $ans : '';
            $answers[$q['id']] = $ans;
            if ($ans === $q['correct']) {
                $correct++;
            }
        }
        $total = count($questions);
        $score = $total ? round($correct / $total * 100, 2) : 0;
        $pdo->prepare('UPDATE quiz_attempts SET submitted_at = ?, correct_count = ?, total = ?, score = ?, answers = ? WHERE id = ? AND submitted_at IS NULL')
            ->execute([now(), $correct, $total, $score, json_encode($answers), $attempt['id']]);
        flash('success', $expired ? 'Waktu habis. Kuis ditutup.' : 'Kuis selesai dikerjakan!');
        redirect($self);
    }

    if ($action === 'reset' && $canManage) {
        $pdo->prepare('DELETE FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?')->execute([$qid, (int) post('user_id')]);
        flash('success', 'Percobaan siswa direset. Siswa dapat mengerjakan ulang.');
        redirect($self);
    }
    redirect($self);
}

$title = $quiz['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <p class="muted small"><a href="course.php?id=<?= (int) $course['id'] ?>&amp;tab=kuis">← <?= e($course['title']) ?></a></p>
    <div class="card-header">
        <h1><?= e($quiz['title']) ?></h1>
        <?php if ($canManage): ?><a class="btn secondary sm" href="quiz_form.php?id=<?= $qid ?>">Edit Soal</a><?php endif; ?>
    </div>
    <p class="muted small"><?= count($questions) ?> soal · <?= $quiz['time_limit'] ? (int) $quiz['time_limit'] . ' menit' : 'Tanpa batas waktu' ?></p>
    <?php if ($quiz['description']): ?><div class="content"><?= nl2br_e($quiz['description']) ?></div><?php endif; ?>
</div>

<?php if ($canManage):
    $stmt = $pdo->prepare('SELECT u.id, u.name, t.started_at, t.submitted_at, t.score, t.correct_count, t.total
                           FROM enrollments e JOIN users u ON u.id = e.user_id
                           LEFT JOIN quiz_attempts t ON t.user_id = u.id AND t.quiz_id = ?
                           WHERE e.course_id = ? ORDER BY u.name');
    $stmt->execute([$qid, $course['id']]);
    $rows = $stmt->fetchAll();
    $scores = array_map('floatval', array_column(array_filter($rows, fn ($r) => $r['submitted_at']), 'score'));
?>
<div class="stats">
    <div class="stat"><div class="num"><?= count($scores) ?>/<?= count($rows) ?></div><div class="muted">Sudah Mengerjakan</div></div>
    <div class="stat"><div class="num"><?= $scores ? round(array_sum($scores) / count($scores), 1) : '-' ?></div><div class="muted">Rata-rata</div></div>
    <div class="stat"><div class="num"><?= $scores ? max($scores) : '-' ?></div><div class="muted">Tertinggi</div></div>
    <div class="stat"><div class="num"><?= $scores ? min($scores) : '-' ?></div><div class="muted">Terendah</div></div>
</div>
<div class="card">
    <h2>Hasil Siswa</h2>
    <?php if (!$rows): ?><p class="empty">Belum ada siswa di kelas ini.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Nama</th><th>Status</th><th class="num">Benar</th><th class="num">Nilai</th><th></th></tr>
        <?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['name']) ?></td>
            <td><?php if ($r['submitted_at']): ?><span class="badge green">Selesai</span> <span class="muted small"><?= e(format_date($r['submitted_at'])) ?></span>
                <?php elseif ($r['started_at']): ?><span class="badge yellow">Sedang mengerjakan</span>
                <?php else: ?><span class="badge gray">Belum</span><?php endif; ?></td>
            <td class="num"><?= $r['submitted_at'] ? (int) $r['correct_count'] . '/' . (int) $r['total'] : '-' ?></td>
            <td class="num"><strong><?= $r['submitted_at'] ? e((string) (float) $r['score']) : '-' ?></strong></td>
            <td><?php if ($r['started_at']): ?>
                <form method="post" data-confirm="Reset hasil <?= e($r['name']) ?>? Siswa harus mengerjakan ulang."><?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                    <button class="btn sm secondary" type="submit">Reset</button></form>
            <?php endif; ?></td></tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<?php elseif (!$attempt): ?>
<div class="card">
    <?php if (!$questions): ?><p class="empty">Kuis ini belum memiliki soal.</p><?php else: ?>
    <p>Kuis hanya dapat dikerjakan <strong>satu kali</strong>.
        <?= $quiz['time_limit'] ? 'Waktu akan berjalan setelah Anda menekan tombol mulai dan jawaban terkirim otomatis saat waktu habis.' : '' ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="start">
        <button class="btn" type="submit">Mulai Kerjakan</button></form>
    <?php endif; ?>
</div>

<?php elseif (!$attempt['submitted_at']): ?>
<?php if ($remaining !== null): ?><div class="timer" id="quiz-timer" data-remaining="<?= max(0, (int) $remaining) ?>"><span>⏱</span></div><?php endif; ?>
<form method="post" id="quiz-form" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="submit">
    <?php foreach ($questions as $i => $q): ?>
    <div class="question">
        <p><strong><?= $i + 1 ?>.</strong> <?= nl2br_e($q['question']) ?></p>
        <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
            <label class="opt"><input type="radio" name="answer[<?= (int) $q['id'] ?>]" value="<?= $opt ?>">
                <span><?= $opt ?>. <?= e($q['option_' . strtolower($opt)]) ?></span></label>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <button class="btn" type="submit">Kirim Jawaban</button>
</form>

<?php else:
    $answers = json_decode((string) $attempt['answers'], true) ?: [];
?>
<div class="card">
    <div class="card-header">
        <div><h2>Hasil Anda</h2><p class="muted small">Selesai <?= e(format_date($attempt['submitted_at'])) ?></p></div>
        <div class="score-big"><?= e((string) (float) $attempt['score']) ?></div>
    </div>
    <p>Jawaban benar: <strong><?= (int) $attempt['correct_count'] ?></strong> dari <?= (int) $attempt['total'] ?> soal.</p>
</div>
<div class="card">
    <h2>Pembahasan</h2>
    <?php foreach ($questions as $i => $q): $mine = $answers[$q['id']] ?? ''; ?>
    <div class="question">
        <p><strong><?= $i + 1 ?>.</strong> <?= nl2br_e($q['question']) ?>
            <?= $mine === $q['correct'] ? '<span class="badge green">Benar</span>' : '<span class="badge red">' . ($mine === '' ? 'Tidak dijawab' : 'Salah') . '</span>' ?></p>
        <?php foreach (['A', 'B', 'C', 'D'] as $opt):
            $cls = $opt === $q['correct'] ? 'correct' : ($opt === $mine ? 'wrong' : ''); ?>
            <div class="opt <?= $cls ?>"><?= $opt ?>. <?= e($q['option_' . strtolower($opt)]) ?>
                <?= $opt === $mine ? ' ← jawaban Anda' : '' ?></div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
