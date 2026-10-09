<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM lessons WHERE id = ?');
$stmt->execute([get_int('id')]);
$lesson = $stmt->fetch() ?: abort(404, 'Materi tidak ditemukan.');
$course = course_or_abort((int) $lesson['course_id'], $user);
$canManage = can_manage_course($course, $user);

if (is_post()) {
    verify_csrf();
    if (post('action') === 'complete') {
        $pdo->prepare('INSERT OR IGNORE INTO lesson_progress (user_id, lesson_id, completed_at) VALUES (?, ?, ?)')->execute([$uid, $lesson['id'], now()]);
        flash('success', 'Materi ditandai selesai.');
    } elseif (post('action') === 'uncomplete') {
        $pdo->prepare('DELETE FROM lesson_progress WHERE user_id = ? AND lesson_id = ?')->execute([$uid, $lesson['id']]);
    }
    redirect('lesson.php?id=' . (int) $lesson['id']);
}

$stmt = $pdo->prepare('SELECT 1 FROM lesson_progress WHERE user_id = ? AND lesson_id = ?');
$stmt->execute([$uid, $lesson['id']]);
$done = (bool) $stmt->fetchColumn();

// Navigasi materi sebelumnya / berikutnya
$stmt = $pdo->prepare('SELECT id, title FROM lessons WHERE course_id = ? ORDER BY position, id');
$stmt->execute([$lesson['course_id']]);
$all = $stmt->fetchAll();
$idx = array_search((int) $lesson['id'], array_map('intval', array_column($all, 'id')), true);
$prev = $all[$idx - 1] ?? null;
$next = $all[$idx + 1] ?? null;
$yt = youtube_id($lesson['video_url']);

$title = $lesson['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <p class="muted small"><a href="course.php?id=<?= (int) $course['id'] ?>&amp;tab=materi">← <?= e($course['title']) ?></a></p>
    <div class="card-header">
        <h1><?= e($lesson['title']) ?></h1>
        <?php if ($canManage): ?><a class="btn secondary sm" href="lesson_form.php?id=<?= (int) $lesson['id'] ?>">Edit</a><?php endif; ?>
    </div>
    <?php if ($yt): ?>
        <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="Video materi" allowfullscreen
            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"></iframe></div>
    <?php endif; ?>
    <?php if ($lesson['content']): ?><div class="content"><?= nl2br_e($lesson['content']) ?></div><?php endif; ?>
    <?php if ($lesson['file_path']): ?>
        <a class="attachment" href="download.php?type=lesson&amp;id=<?= (int) $lesson['id'] ?>">📎 <?= e($lesson['file_name']) ?></a>
    <?php endif; ?>
</div>
<div class="actions">
    <?php if ($prev): ?><a class="btn secondary" href="lesson.php?id=<?= (int) $prev['id'] ?>">← Sebelumnya</a><?php endif; ?>
    <?php if (!$canManage): ?>
        <form method="post" class="inline">
            <?= csrf_field() ?>
            <?php if ($done): ?>
                <input type="hidden" name="action" value="uncomplete"><button class="btn secondary" type="submit">✔ Sudah selesai (batalkan)</button>
            <?php else: ?>
                <input type="hidden" name="action" value="complete"><button class="btn success" type="submit">Tandai Selesai</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    <?php if ($next): ?><a class="btn" href="lesson.php?id=<?= (int) $next['id'] ?>">Berikutnya →</a><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
