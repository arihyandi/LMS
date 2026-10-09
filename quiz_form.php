<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'guru');
$pdo = db();

$quiz = null;
if ($id = get_int('id')) {
    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
    $stmt->execute([$id]);
    $quiz = $stmt->fetch() ?: abort(404, 'Kuis tidak ditemukan.');
}
$course = course_or_abort($quiz ? (int) $quiz['course_id'] : get_int('course_id'), $user, true);
$cid = (int) $course['id'];
$error = '';

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'save_quiz') {
        $t = post('title');
        $d = post('description');
        $limit = max(0, min(600, (int) post('time_limit')));
        if ($t === '' || mb_strlen($t) > 150) {
            $error = 'Judul kuis wajib diisi (maks. 150 karakter).';
        } elseif ($quiz) {
            $pdo->prepare('UPDATE quizzes SET title = ?, description = ?, time_limit = ? WHERE id = ?')->execute([$t, $d, $limit, $quiz['id']]);
            flash('success', 'Pengaturan kuis disimpan.');
            redirect('quiz_form.php?id=' . (int) $quiz['id']);
        } else {
            $pdo->prepare('INSERT INTO quizzes (course_id, title, description, time_limit, published, created_at) VALUES (?, ?, ?, ?, 0, ?)')
                ->execute([$cid, $t, $d, $limit, now()]);
            flash('success', 'Kuis dibuat. Sekarang tambahkan soal-soalnya.');
            redirect('quiz_form.php?id=' . (int) $pdo->lastInsertId());
        }
    } elseif ($quiz) {
        $qid = (int) $quiz['id'];
        switch ($action) {
            case 'save_question':
                $fields = ['question' => post('question'), 'a' => post('option_a'), 'b' => post('option_b'), 'c' => post('option_c'), 'd' => post('option_d')];
                $correct = post('correct');
                if (in_array('', $fields, true) || !in_array($correct, ['A', 'B', 'C', 'D'], true)) {
                    flash('error', 'Pertanyaan, keempat pilihan jawaban, dan kunci jawaban wajib diisi.');
                } elseif ($questionId = (int) post('question_id')) {
                    $pdo->prepare('UPDATE questions SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct = ? WHERE id = ? AND quiz_id = ?')
                        ->execute([...array_values($fields), $correct, $questionId, $qid]);
                    flash('success', 'Soal diperbarui.');
                } else {
                    $pos = (int) $pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM questions WHERE quiz_id = ' . $qid)->fetchColumn();
                    $pdo->prepare('INSERT INTO questions (quiz_id, question, option_a, option_b, option_c, option_d, correct, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$qid, ...array_values($fields), $correct, $pos]);
                    flash('success', 'Soal ditambahkan.');
                }
                redirect('quiz_form.php?id=' . $qid . '#soal');
            case 'delete_question':
                $pdo->prepare('DELETE FROM questions WHERE id = ? AND quiz_id = ?')->execute([(int) post('question_id'), $qid]);
                flash('success', 'Soal dihapus.');
                redirect('quiz_form.php?id=' . $qid . '#soal');
            case 'toggle_publish':
                $count = (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE quiz_id = ' . $qid)->fetchColumn();
                if (!$quiz['published'] && $count === 0) {
                    flash('error', 'Tambahkan minimal satu soal sebelum menerbitkan kuis.');
                } else {
                    $pdo->prepare('UPDATE quizzes SET published = ? WHERE id = ?')->execute([$quiz['published'] ? 0 : 1, $qid]);
                    flash('success', $quiz['published'] ? 'Kuis dikembalikan ke draf.' : 'Kuis diterbitkan dan bisa dikerjakan siswa.');
                }
                redirect('quiz_form.php?id=' . $qid);
            case 'delete_quiz':
                $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$qid]);
                flash('success', 'Kuis dihapus.');
                redirect('course.php?id=' . $cid . '&tab=kuis');
        }
    }
}

$questions = [];
$editing = null;
if ($quiz) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY position, id');
    $stmt->execute([$quiz['id']]);
    $questions = $stmt->fetchAll();
    $editId = get_int('edit');
    foreach ($questions as $q) {
        if ((int) $q['id'] === $editId) {
            $editing = $q;
        }
    }
}

$title = $quiz ? 'Kelola Kuis' : 'Buat Kuis';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <p class="muted small"><a href="course.php?id=<?= $cid ?>&amp;tab=kuis">← <?= e($course['title']) ?></a></p>
    <div class="card-header">
        <h1><?= e($title) ?></h1>
        <?php if ($quiz): ?>
        <div class="actions">
            <?= $quiz['published'] ? '<span class="badge green">Terbit</span>' : '<span class="badge gray">Draf</span>' ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_publish">
                <button class="btn sm <?= $quiz['published'] ? 'secondary' : 'success' ?>" type="submit"><?= $quiz['published'] ? 'Jadikan Draf' : 'Terbitkan' ?></button></form>
            <a class="btn sm secondary" href="quiz.php?id=<?= (int) $quiz['id'] ?>">Lihat Hasil</a>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_quiz">
        <div class="form-row">
            <div><label for="title">Judul Kuis</label>
                <input type="text" id="title" name="title" value="<?= e($quiz['title'] ?? post('title')) ?>" required maxlength="150"></div>
            <div><label for="time_limit">Batas Waktu (menit, 0 = tanpa batas)</label>
                <input type="number" id="time_limit" name="time_limit" value="<?= (int) ($quiz['time_limit'] ?? 0) ?>" min="0" max="600"></div>
        </div>
        <label for="description">Petunjuk</label>
        <textarea id="description" name="description" rows="3"><?= e($quiz['description'] ?? post('description')) ?></textarea>
        <div class="form-actions"><button class="btn" type="submit"><?= $quiz ? 'Simpan Pengaturan' : 'Buat Kuis' ?></button></div>
    </form>
</div>

<?php if ($quiz): ?>
<div class="card" id="soal">
    <h2>Daftar Soal (<?= count($questions) ?>)</h2>
    <?php if (!$questions): ?><p class="empty">Belum ada soal.</p><?php endif; ?>
    <?php foreach ($questions as $i => $q): ?>
    <div class="question">
        <div class="card-header">
            <strong><?= $i + 1 ?>. <?= nl2br_e($q['question']) ?></strong>
            <div class="actions">
                <a class="btn sm secondary" href="quiz_form.php?id=<?= (int) $quiz['id'] ?>&amp;edit=<?= (int) $q['id'] ?>#form-soal">Edit</a>
                <form method="post" class="inline" data-confirm="Hapus soal ini?"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_question"><input type="hidden" name="question_id" value="<?= (int) $q['id'] ?>">
                    <button class="btn sm danger" type="submit">Hapus</button></form>
            </div>
        </div>
        <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
            <div class="opt <?= $q['correct'] === $opt ? 'correct' : '' ?>"><?= $opt ?>. <?= e($q['option_' . strtolower($opt)]) ?><?= $q['correct'] === $opt ? ' ✔' : '' ?></div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="card" id="form-soal">
    <h2><?= $editing ? 'Edit Soal' : 'Tambah Soal' ?></h2>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_question">
        <input type="hidden" name="question_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <label for="question">Pertanyaan</label>
        <textarea id="question" name="question" rows="3" required><?= e($editing['question'] ?? '') ?></textarea>
        <div class="form-row">
            <?php foreach (['a', 'b', 'c', 'd'] as $o): ?>
            <div><label for="option_<?= $o ?>">Pilihan <?= strtoupper($o) ?></label>
                <input type="text" id="option_<?= $o ?>" name="option_<?= $o ?>" value="<?= e($editing['option_' . $o] ?? '') ?>" required></div>
            <?php endforeach; ?>
        </div>
        <label for="correct">Kunci Jawaban</label>
        <select id="correct" name="correct" required>
            <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($editing['correct'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $editing ? 'Simpan Perubahan' : 'Tambah Soal' ?></button>
            <?php if ($editing): ?><a class="btn secondary" href="quiz_form.php?id=<?= (int) $quiz['id'] ?>#soal">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<form method="post" data-confirm="Hapus kuis ini beserta semua hasil siswa?">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_quiz">
    <button class="btn danger" type="submit">Hapus Kuis</button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
