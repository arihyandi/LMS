<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'guru');
$pdo = db();

$course = course_or_abort(get_int('course_id'), $user, true);
$cid = (int) $course['id'];

$stmt = $pdo->prepare('SELECT id, title, max_score FROM assignments WHERE course_id = ? ORDER BY due_at IS NULL, due_at, id');
$stmt->execute([$cid]);
$assignments = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT id, title FROM quizzes WHERE course_id = ? AND published = 1 ORDER BY created_at');
$stmt->execute([$cid]);
$quizzes = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT u.id, u.name, u.username FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY u.name');
$stmt->execute([$cid]);
$students = $stmt->fetchAll();

$grades = [];
$stmt = $pdo->prepare('SELECT s.user_id, s.assignment_id, s.score, a.max_score FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.course_id = ? AND s.score IS NOT NULL');
$stmt->execute([$cid]);
foreach ($stmt as $r) {
    $grades[$r['user_id']]['a' . $r['assignment_id']] = round($r['score'] / max(1, $r['max_score']) * 100, 1);
}
$stmt = $pdo->prepare('SELECT t.user_id, t.quiz_id, t.score FROM quiz_attempts t JOIN quizzes q ON q.id = t.quiz_id WHERE q.course_id = ? AND t.submitted_at IS NOT NULL');
$stmt->execute([$cid]);
foreach ($stmt as $r) {
    $grades[$r['user_id']]['q' . $r['quiz_id']] = (float) $r['score'];
}

/** Cegah formula injection saat dibuka di Excel. */
function csv_cell(string $v): string
{
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

$filename = 'nilai-' . preg_replace('/[^A-Za-z0-9]+/', '-', $course['title']) . '-' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
$header = ['No', 'Nama', 'Username'];
foreach ($assignments as $a) {
    $header[] = 'Tugas: ' . $a['title'];
}
foreach ($quizzes as $q) {
    $header[] = 'Kuis: ' . $q['title'];
}
$header[] = 'Rata-rata';
fputcsv($out, array_map('csv_cell', $header), ';', '"', '\\');

foreach ($students as $i => $s) {
    $row = [$i + 1, $s['name'], $s['username']];
    $vals = [];
    $keys = array_merge(array_map(fn ($a) => 'a' . $a['id'], $assignments), array_map(fn ($q) => 'q' . $q['id'], $quizzes));
    foreach ($keys as $k) {
        $v = $grades[$s['id']][$k] ?? null;
        if ($v !== null) {
            $vals[] = $v;
        }
        $row[] = $v === null ? '' : str_replace('.', ',', (string) $v);
    }
    $row[] = $vals ? str_replace('.', ',', (string) round(array_sum($vals) / count($vals), 1)) : '';
    fputcsv($out, array_map(fn ($c) => csv_cell((string) $c), $row), ';', '"', '\\');
}
fclose($out);
