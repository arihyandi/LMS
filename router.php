<?php
// Router untuk server bawaan PHP:  php -S localhost:8000 router.php
// Memblokir akses langsung ke folder/berkas internal.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(includes|storage|\.git)(/|$)#', $path) || preg_match('#^/(config|router)\.php$|\.(sqlite|md|htaccess)$#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
