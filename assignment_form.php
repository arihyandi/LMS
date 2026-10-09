<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'guru');
$pdo = db();

$assignment = null;
if ($id = get_int('id')) {
    $stmt = $pdo->prepare('SELECT * FROM assignments WHERE id = ?');
    $stmt->execute([$id]);
    $assignment = $stmt->fetch() ?: abort(404, 'Tugas tidak ditemukan.');
}
$course = course_or_abort($assignment ? (int) $assignment['course_id'] : get_int('course_id'), $user, true);
$cid = (int) $course['id'];

$data = [
    'title' => $assignment['title'] ?? '',
    'description' => $assignment['description'] ?? '',
    'due_at' => to_datetime_local($assignment['due_at'] ?? null),
    'max_score' => (int) ($assignment['max_score'] ?? 100),
];
$error = '';

if (is_post()) {
    verify_csrf();
    if (post('action') === 'delete' && $assignment) {
        delete_upload($assignment['file_path']);
        $files = $pdo->prepare('SELECT file_path FROM submissions WHERE assignment_id = ?');
        $files->execute([$assignment['id']]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) {
            delete_upload($f);
        }
        $pdo->prepare('DELETE FROM assignments WHERE id = ?')->execute([$assignment['id']]);
        flash('success', 'Tugas dihapus.');
        redirect('course.php?id=' . $cid . '&tab=tugas');
    }
    $data = ['title' => post('title'), 'description' => post('description'), 'due_at' => post('due_at'), 'max_score' => (int) post('max_score')];
    $dueAt = parse_datetime_local($data['due_at']);
    if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
        $error = 'Judul tugas wajib diisi (maks. 150 karakter).';
    } elseif ($data['due_at'] !== '' && !$dueAt) {
        $error = 'Format tenggat waktu tidak valid.';
    } elseif ($data['max_score'] < 1 || $data['max_score'] > 1000) {
        $error = 'Nilai maksimal harus antara 1 dan 1000.';
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
        $filePath = $assignment['file_path'] ?? null;
        $fileName = $assignment['file_name'] ?? null;
        if ($upload || isset($_POST['remove_file'])) {
            delete_upload($filePath);
            [$filePath, $fileName] = $upload ?? [null, null];
        }
        if ($assignment) {
            $pdo->prepare('UPDATE assignments SET title = ?, description = ?, due_at = ?, max_score = ?, file_path = ?, file_name = ? WHERE id = ?')
                ->execute([$data['title'], $data['description'], $dueAt, $data['max_score'], $filePath, $fileName, $assignment['id']]);
            $aid = (int) $assignment['id'];
        } else {
            $pdo->prepare('INSERT INTO assignments (course_id, title, description, due_at, max_score, file_path, file_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$cid, $data['title'], $data['description'], $dueAt, $data['max_score'], $filePath, $fileName, now()]);
            $aid = (int) $pdo->lastInsertId();
        }
        flash('success', 'Tugas disimpan.');
        redirect('assignment.php?id=' . $aid);
    }
}

$title = $assignment ? 'Edit Tugas' : 'Buat Tugas';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <p class="muted small"><a href="course.php?id=<?= $cid ?>&amp;tab=tugas">← <?= e($course['title']) ?></a></p>
    <h1><?= e($title) ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="title">Judul Tugas</label>
        <input type="text" id="title" name="title" value="<?= e($data['title']) ?>" required maxlength="150">
        <label for="description">Instruksi</label>
        <textarea id="description" name="description" rows="8"><?= e($data['description']) ?></textarea>
        <div class="form-row">
            <div><label for="due_at">Tenggat Waktu (opsional)</label>
                <input type="datetime-local" id="due_at" name="due_at" value="<?= e($data['due_at']) ?>"></div>
            <div><label for="max_score">Nilai Maksimal</label>
                <input type="number" id="max_score" name="max_score" value="<?= (int) $data['max_score'] ?>" min="1" max="1000" required></div>
        </div>
        <label for="file">Lampiran Soal (opsional)</label>
        <input type="file" id="file" name="file">
        <div class="hint">Maks. <?= (int) $config['max_upload_mb'] ?> MB.</div>
        <?php if (!empty($assignment['file_name'])): ?>
            <p class="small">File saat ini: <strong><?= e($assignment['file_name']) ?></strong>
                <label class="inline small"><input type="checkbox" name="remove_file" value="1"> Hapus file</label></p>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn" type="submit">Simpan</button>
            <a class="btn secondary" href="course.php?id=<?= $cid ?>&amp;tab=tugas">Batal</a>
        </div>
    </form>
    <?php if ($assignment): ?>
    <form method="post" data-confirm="Hapus tugas ini beserta semua jawaban siswa?" class="form-actions">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <button class="btn danger" type="submit">Hapus Tugas</button>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
