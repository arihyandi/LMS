<?php
declare(strict_types=1);

/** Escape output HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Teks multi-baris yang aman (escape + baris baru jadi <br>). */
function nl2br_e(?string $value): string
{
    return nl2br(e($value));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Validasi token CSRF untuk setiap request POST. */
function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Permintaan tidak valid (token CSRF). Silakan muat ulang halaman.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $key): int
{
    return (int) ($_GET[$key] ?? 0);
}

function abort(int $code, string $message): never
{
    global $config;
    http_response_code($code);
    $title = 'Error ' . $code;
    require __DIR__ . '/header.php';
    echo '<div class="card"><h2>' . e($title) . '</h2><p>' . e($message) . '</p>'
        . '<p><a class="btn" href="dashboard.php">Kembali ke Dashboard</a></p></div>';
    require __DIR__ . '/footer.php';
    exit;
}

function format_date(?string $dt, bool $withTime = true): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime($dt);
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $s = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $s . ', ' . date('H:i', $ts) : $s;
}

/** Ubah input datetime-local (Y-m-d\TH:i) menjadi format database. */
function parse_datetime_local(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $dt ? $dt->format('Y-m-d H:i:00') : null;
}

function to_datetime_local(?string $dt): string
{
    return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
}

/** Ambil ID video YouTube dari URL, null jika bukan URL YouTube yang valid. */
function youtube_id(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    $pattern = '~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';
    return preg_match($pattern, $url, $m) ? $m[1] : null;
}

function generate_course_code(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = db()->prepare('SELECT 1 FROM courses WHERE code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

/**
 * Simpan file unggahan dengan nama acak. Mengembalikan [path, nama_asli],
 * null jika tidak ada file, atau melempar RuntimeException jika gagal validasi.
 */
function handle_upload(string $field): ?array
{
    global $config;
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if (!is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah file (ukuran terlalu besar atau koneksi terputus).');
    }
    if ($file['size'] > $config['max_upload_mb'] * 1024 * 1024) {
        throw new RuntimeException('Ukuran file maksimal ' . $config['max_upload_mb'] . ' MB.');
    }
    $original = basename((string) $file['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $config['allowed_ext'], true)) {
        throw new RuntimeException('Tipe file tidak diizinkan. Diizinkan: ' . implode(', ', $config['allowed_ext']));
    }
    if (!is_dir($config['upload_dir'])) {
        mkdir($config['upload_dir'], 0775, true);
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $config['upload_dir'] . '/' . $stored)) {
        throw new RuntimeException('Gagal menyimpan file.');
    }
    $original = mb_substr(preg_replace('/[\x00-\x1F\x7F"\\\\\/]/u', '_', $original), 0, 150);
    return [$stored, $original];
}

function delete_upload(?string $stored): void
{
    global $config;
    if ($stored && preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', $stored)) {
        $path = $config['upload_dir'] . '/' . $stored;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function role_label(string $role): string
{
    return ['admin' => 'Admin', 'guru' => 'Guru', 'siswa' => 'Siswa'][$role] ?? $role;
}
