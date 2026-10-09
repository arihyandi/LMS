<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'guru');
$pdo = db();

$lesson = null;
if ($id = get_int('id')) {
    $stmt = $pdo->prepare('SELECT * FROM lessons WHERE id = ?');
    $stmt->execute([$id]);
    $lesson = $stmt->fetch() ?: abort(404, 'Materi tidak ditemukan.');
}
$course = course_or_abort($lesson ? (int) $lesson['course_id'] : get_int('course_id'), $user, true);
$cid = (int) $course['id'];

$data = [
    'title' => $lesson['title'] ?? '',
    'content' => $lesson['content'] ?? '',
    'video_url' => $lesson['video_url'] ?? '',
    'position' => (int) ($lesson['position'] ?? $pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM lessons WHERE course_id = ' . $cid)->fetchColumn()),
];
$error = '';

if (is_post()) {
    verify_csrf();
    if (post('action') === 'delete' && $lesson) {
        delete_upload($lesson['file_path']);
        $pdo->prepare('DELETE FROM lessons WHERE id = ?')->execute([$lesson['id']]);
        flash('success', 'Materi dihapus.');
        redirect('course.php?id=' . $cid . '&tab=materi');
    }
    $data = ['title' => post('title'), 'content' => post('content'), 'video_url' => post('video_url'), 'position' => (int) post('position')];
    if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
        $error = 'Judul materi wajib diisi (maks. 150 karakter).';
    } elseif ($data['video_url'] !== '' && !youtube_id($data['video_url'])) {
        $error = 'Link video harus berupa link YouTube yang valid.';
    }
    $upload = null;
    if (!$error) {
        try {
            $upload = handle_upload('file');
        } catch (RuntimeException $ex) {
            $error = $ex->getMessage();
        }
    }
    if (!$error) {
        $filePath = $lesson['file_path'] ?? null;
        $fileName = $lesson['file_name'] ?? null;
        if ($upload || isset($_POST['remove_file'])) {
            delete_upload($filePath);
            [$filePath, $fileName] = $upload ?? [null, null];
        }
        if ($lesson) {
            $pdo->prepare('UPDATE lessons SET title = ?, content = ?, video_url = ?, file_path = ?, file_name = ?, position = ? WHERE id = ?')
                ->execute([$data['title'], $data['content'], $data['video_url'] ?: null, $filePath, $fileName, $data['position'], $lesson['id']]);
            $lid = (int) $lesson['id'];
        } else {
            $pdo->prepare('INSERT INTO lessons (course_id, title, content, video_url, file_path, file_name, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$cid, $data['title'], $data['content'], $data['video_url'] ?: null, $filePath, $fileName, $data['position'], now()]);
            $lid = (int) $pdo->lastInsertId();
        }
        flash('success', 'Materi disimpan.');
        redirect('lesson.php?id=' . $lid);
    }
}

$title = $lesson ? 'Edit Materi' : 'Tambah Materi';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <p class="muted small"><a href="course.php?id=<?= $cid ?>&amp;tab=materi">← <?= e($course['title']) ?></a></p>
    <h1><?= e($title) ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-row">
            <div><label for="title">Judul Materi</label>
                <input type="text" id="title" name="title" value="<?= e($data['title']) ?>" required maxlength="150"></div>
            <div><label for="position">Urutan</label>
                <input type="number" id="position" name="position" value="<?= (int) $data['position'] ?>" min="0"></div>
        </div>
        <label for="content">Isi Materi</label>
        <textarea id="content" name="content" rows="12" placeholder="Tulis penjelasan materi di sini…"><?= e($data['content']) ?></textarea>
        <label for="video_url">Link Video YouTube (opsional)</label>
        <input type="url" id="video_url" name="video_url" value="<?= e($data['video_url']) ?>" placeholder="https://www.youtube.com/watch?v=...">
        <label for="file">Lampiran File (opsional)</label>
        <input type="file" id="file" name="file">
        <div class="hint">Maks. <?= (int) $config['max_upload_mb'] ?> MB. Format: <?= e(implode(', ', $config['allowed_ext'])) ?></div>
        <?php if (!empty($lesson['file_name'])): ?>
            <p class="small">File saat ini: <strong><?= e($lesson['file_name']) ?></strong>
                <label class="inline small"><input type="checkbox" name="remove_file" value="1"> Hapus file</label></p>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn" type="submit">Simpan</button>
            <a class="btn secondary" href="course.php?id=<?= $cid ?>&amp;tab=materi">Batal</a>
        </div>
    </form>
    <?php if ($lesson): ?>
    <form method="post" data-confirm="Hapus materi ini?" class="form-actions">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <button class="btn danger" type="submit">Hapus Materi</button>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
