<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM assignments WHERE id = ?');
$stmt->execute([get_int('id')]);
$assignment = $stmt->fetch() ?: abort(404, 'Tugas tidak ditemukan.');
$aid = (int) $assignment['id'];
$course = course_or_abort((int) $assignment['course_id'], $user);
$canManage = can_manage_course($course, $user);
$self = 'assignment.php?id=' . $aid;
$isLate = $assignment['due_at'] && strtotime($assignment['due_at']) < time();

$stmt = $pdo->prepare('SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ?');
$stmt->execute([$aid, $uid]);
$mine = $stmt->fetch() ?: null;

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'submit' && !$canManage) {
        if ($mine && $mine['score'] !== null) {
            flash('error', 'Tugas sudah dinilai dan tidak bisa diubah lagi.');
            redirect($self);
        }
        $text = post('answer_text');
        try {
            $upload = handle_upload('file');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
            redirect($self);
        }
        $filePath = $mine['file_path'] ?? null;
        $fileName = $mine['file_name'] ?? null;
        if ($upload) {
            delete_upload($filePath);
            [$filePath, $fileName] = $upload;
        }
        if ($text === '' && !$filePath) {
            flash('error', 'Isi jawaban atau unggah file terlebih dahulu.');
            redirect($self);
        }
        if ($mine) {
            $pdo->prepare('UPDATE submissions SET answer_text = ?, file_path = ?, file_name = ?, submitted_at = ? WHERE id = ?')
                ->execute([$text, $filePath, $fileName, now(), $mine['id']]);
        } else {
            $pdo->prepare('INSERT INTO submissions (assignment_id, user_id, answer_text, file_path, file_name, submitted_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$aid, $uid, $text, $filePath, $fileName, now()]);
        }
        flash('success', 'Tugas berhasil dikumpulkan.');
        redirect($self);
    }

    if ($action === 'grade' && $canManage) {
        $scoreRaw = post('score');
        $score = is_numeric($scoreRaw) ? (float) $scoreRaw : null;
        if ($score === null || $score < 0 || $score > (float) $assignment['max_score']) {
            flash('error', 'Nilai harus antara 0 dan ' . (int) $assignment['max_score'] . '.');
        } else {
            $pdo->prepare('UPDATE submissions SET score = ?, feedback = ?, graded_at = ? WHERE id = ? AND assignment_id = ?')
                ->execute([$score, post('feedback'), now(), (int) post('submission_id'), $aid]);
            flash('success', 'Nilai disimpan.');
        }
        redirect($self . '#s' . (int) post('submission_id'));
    }
    redirect($self);
}

$title = $assignment['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <p class="muted small"><a href="course.php?id=<?= (int) $course['id'] ?>&amp;tab=tugas">← <?= e($course['title']) ?></a></p>
    <div class="card-header">
        <h1><?= e($assignment['title']) ?></h1>
        <?php if ($canManage): ?><a class="btn secondary sm" href="assignment_form.php?id=<?= $aid ?>">Edit</a><?php endif; ?>
    </div>
    <p class="muted small">Tenggat: <strong><?= e(format_date($assignment['due_at'])) ?></strong>
        <?= $isLate ? '<span class="badge red">Sudah lewat</span>' : '' ?> · Nilai maksimal: <?= (int) $assignment['max_score'] ?></p>
    <?php if ($assignment['description']): ?><div class="content"><?= nl2br_e($assignment['description']) ?></div><?php endif; ?>
    <?php if ($assignment['file_path']): ?>
        <a class="attachment" href="download.php?type=assignment&amp;id=<?= $aid ?>">📎 <?= e($assignment['file_name']) ?></a>
    <?php endif; ?>
</div>

<?php if (!$canManage): ?>
<div class="card">
    <h2>Jawaban Saya</h2>
    <?php if ($mine): ?>
        <p class="small">Dikumpulkan: <?= e(format_date($mine['submitted_at'])) ?>
            <?php if ($assignment['due_at'] && $mine['submitted_at'] > $assignment['due_at']): ?><span class="badge red">Terlambat</span><?php endif; ?></p>
        <?php if ($mine['score'] !== null): ?>
            <div class="alert alert-success">Nilai: <strong><?= e((string) (float) $mine['score']) ?></strong> / <?= (int) $assignment['max_score'] ?>
                <?php if ($mine['feedback']): ?><br>Catatan guru: <?= nl2br_e($mine['feedback']) ?><?php endif; ?></div>
        <?php endif; ?>
        <?php if ($mine['answer_text']): ?><div class="content card"><?= nl2br_e($mine['answer_text']) ?></div><?php endif; ?>
        <?php if ($mine['file_path']): ?><a class="attachment" href="download.php?type=submission&amp;id=<?= (int) $mine['id'] ?>">📎 <?= e($mine['file_name']) ?></a><?php endif; ?>
    <?php endif; ?>
    <?php if (!$mine || $mine['score'] === null): ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="action" value="submit">
            <?php if ($isLate): ?><div class="alert alert-warning">Tenggat sudah lewat. Tugas tetap bisa dikumpulkan tetapi akan ditandai terlambat.</div><?php endif; ?>
            <label for="answer_text">Jawaban Teks</label>
            <textarea id="answer_text" name="answer_text" rows="6"><?= e($mine['answer_text'] ?? '') ?></textarea>
            <label for="file"><?= !empty($mine['file_path']) ? 'Ganti File' : 'Unggah File' ?> (opsional)</label>
            <input type="file" id="file" name="file">
            <div class="hint">Maks. <?= (int) $config['max_upload_mb'] ?> MB. Format: <?= e(implode(', ', $config['allowed_ext'])) ?></div>
            <div class="form-actions"><button class="btn" type="submit"><?= $mine ? 'Perbarui Jawaban' : 'Kumpulkan Tugas' ?></button></div>
        </form>
    <?php endif; ?>
</div>

<?php else:
    $stmt = $pdo->prepare('SELECT u.id AS user_id, u.name, s.* FROM enrollments e JOIN users u ON u.id = e.user_id
                           LEFT JOIN submissions s ON s.user_id = u.id AND s.assignment_id = ?
                           WHERE e.course_id = ? ORDER BY s.submitted_at IS NULL, u.name');
    $stmt->execute([$aid, $course['id']]);
    $rows = $stmt->fetchAll();
    $submitted = count(array_filter($rows, fn ($r) => $r['submitted_at']));
?>
<div class="card">
    <div class="card-header"><h2>Jawaban Siswa</h2><span class="badge"><?= $submitted ?>/<?= count($rows) ?> mengumpulkan</span></div>
    <?php if (!$rows): ?><p class="empty">Belum ada siswa di kelas ini.</p><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <div class="question" id="s<?= (int) $r['id'] ?>">
        <div class="card-header">
            <h3><?= e($r['name']) ?></h3>
            <?php if (!$r['submitted_at']): ?><span class="badge gray">Belum mengumpulkan</span>
            <?php elseif ($r['score'] !== null): ?><span class="badge green">Nilai: <?= e((string) (float) $r['score']) ?></span>
            <?php else: ?><span class="badge yellow">Belum dinilai</span><?php endif; ?>
        </div>
        <?php if ($r['submitted_at']): ?>
            <p class="muted small">Dikumpulkan <?= e(format_date($r['submitted_at'])) ?>
                <?php if ($assignment['due_at'] && $r['submitted_at'] > $assignment['due_at']): ?><span class="badge red">Terlambat</span><?php endif; ?></p>
            <?php if ($r['answer_text']): ?><div class="content"><?= nl2br_e($r['answer_text']) ?></div><?php endif; ?>
            <?php if ($r['file_path']): ?><a class="attachment" href="download.php?type=submission&amp;id=<?= (int) $r['id'] ?>">📎 <?= e($r['file_name']) ?></a><?php endif; ?>
            <form method="post" class="form-row">
                <?= csrf_field() ?><input type="hidden" name="action" value="grade"><input type="hidden" name="submission_id" value="<?= (int) $r['id'] ?>">
                <div><label>Nilai (0–<?= (int) $assignment['max_score'] ?>)</label>
                    <input type="number" name="score" step="0.01" min="0" max="<?= (int) $assignment['max_score'] ?>" value="<?= $r['score'] !== null ? e((string) (float) $r['score']) : '' ?>" required></div>
                <div><label>Catatan / Umpan Balik</label>
                    <input type="text" name="feedback" value="<?= e($r['feedback']) ?>"></div>
                <div><label>&nbsp;</label><button class="btn" type="submit">Simpan Nilai</button></div>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
