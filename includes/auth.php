<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, name, username, email, role, must_change_password FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    $page = basename($_SERVER['SCRIPT_NAME']);
    if ($user['must_change_password'] && !in_array($page, ['profile.php', 'logout.php'], true)) {
        flash('warning', 'Demi keamanan, silakan ganti password Anda terlebih dahulu.');
        redirect('profile.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['csrf']);
}

function get_course(int $id): ?array
{
    $stmt = db()->prepare('SELECT c.*, u.name AS teacher_name FROM courses c JOIN users u ON u.id = c.teacher_id WHERE c.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Admin atau guru pemilik kelas. */
function can_manage_course(array $course, array $user): bool
{
    return $user['role'] === 'admin' || (int) $course['teacher_id'] === (int) $user['id'];
}

function is_enrolled(int $courseId, int $userId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM enrollments WHERE course_id = ? AND user_id = ?');
    $stmt->execute([$courseId, $userId]);
    return (bool) $stmt->fetchColumn();
}

/** Ambil kelas dan pastikan user boleh melihatnya; jika tidak, tampilkan error. */
function course_or_abort(int $courseId, array $user, bool $manage = false): array
{
    $course = get_course($courseId);
    if (!$course) {
        abort(404, 'Kelas tidak ditemukan.');
    }
    $canManage = can_manage_course($course, $user);
    if ($manage && !$canManage) {
        abort(403, 'Hanya guru pengampu yang dapat melakukan aksi ini.');
    }
    if (!$canManage && !is_enrolled($courseId, (int) $user['id'])) {
        abort(403, 'Anda belum terdaftar di kelas ini.');
    }
    return $course;
}
