<?php
declare(strict_types=1);

/**
 * 部署腳本。網址要帶對的 token，以及 mode。
 * mode=check    只檢查環境，並對最新備份做 dry-run。不改網站。
 * mode=apply    先把備份裡的 garden.sqlite 蓋回正式庫，再在這個程序裡轉換。失敗就還原備份。
 * mode=rollback 從最新的 garden_backup_* 還原 garden 與 garden_private。
 * mode=cleanup  刪掉家目錄的 zip 和這支腳本。
 * check、apply、rollback 失敗或成功都不會刪掉這支腳本。
 */

const DEPLOY_TOKEN = '27fea771f41f1f87675e2c6463db30a1';

ob_start();
ini_set('display_errors', '0');

$DEPLOY = [
    'mode' => '',
    'changed' => false,
    'rolled' => false,
    'backup' => '',
    'garden' => '',
    'private' => '',
    'memory_was' => (string) ini_get('memory_limit'),
    'time_was' => (string) ini_get('max_execution_time'),
];

set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');
ignore_user_abort(true);

function deploy_env_report(): string
{
    global $DEPLOY;
    $disabled = trim((string) ini_get('disable_functions'));
    if ($disabled === '') {
        $disabled = '(none)';
    }
    return implode("\n", [
        'PHP ' . PHP_VERSION,
        'SAPI ' . PHP_SAPI,
        'memory_limit_was ' . ($DEPLOY['memory_was'] ?? ''),
        'memory_limit ' . ini_get('memory_limit'),
        'max_execution_time_was ' . ($DEPLOY['time_was'] ?? ''),
        'max_execution_time ' . ini_get('max_execution_time'),
        'disable_functions ' . $disabled,
        'zip_extension ' . (class_exists('ZipArchive') ? 'yes' : 'no'),
    ]);
}

function deploy_exception_text(Throwable $e): string
{
    $lines = [];
    $seen = 0;
    $current = $e;
    while ($current instanceof Throwable && $seen < 8) {
        $lines[] = get_class($current) . ': ' . $current->getMessage();
        $lines[] = $current->getFile() . ':' . $current->getLine();
        $current = $current->getPrevious();
        $seen++;
    }
    return implode("\n", $lines);
}

function deploy_fail(int $status, string $message): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo $message;
    if (!str_ends_with($message, "\n")) {
        echo "\n";
    }
    exit;
}

function deploy_ok(string $message): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo $message;
    if (!str_ends_with($message, "\n")) {
        echo "\n";
    }
}

function deploy_disabled(string $fn): bool
{
    $list = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return in_array($fn, $list, true);
}

function deploy_keep_script(string $rel): bool
{
    return preg_match('#(^|/)_deploy_[A-Za-z0-9]+\.php$#', $rel) === 1;
}

function deploy_rm_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('無法刪除暫存 ' . $path);
        }
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException('無法讀取暫存目錄 ' . $path);
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        deploy_rm_tree($path . '/' . $item);
    }
    if (!rmdir($path)) {
        throw new RuntimeException('無法刪除暫存目錄 ' . $path);
    }
}

function deploy_entry_rel(string $name): ?string
{
    $name = str_replace('\\', '/', $name);
    if ($name === '' || str_contains($name, "\0")) {
        throw new RuntimeException('zip 路徑不合法');
    }
    if (str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
        throw new RuntimeException('zip 路徑不合法');
    }
    if ($name === 'garden' || $name === 'garden/') {
        return null;
    }
    if (!str_starts_with($name, 'garden/')) {
        throw new RuntimeException('zip 最外層不是 garden/');
    }
    if (str_ends_with($name, '/')) {
        return null;
    }
    return substr($name, strlen('garden/'));
}

function deploy_skip(string $rel): bool
{
    return $rel === 'data' || str_starts_with($rel, 'data/') || deploy_keep_script($rel);
}

function deploy_put(string $garden, string $rel, string $bytes): void
{
    if (deploy_skip($rel)) {
        return;
    }
    $dest = $garden . '/' . $rel;
    $parent = dirname($dest);
    if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
        throw new RuntimeException('無法建立目錄 ' . $parent);
    }
    if (file_put_contents($dest, $bytes) === false) {
        throw new RuntimeException('無法寫入解壓檔 ' . $rel);
    }
}

function deploy_unzip_bin(): string
{
    foreach (['/usr/bin/unzip', '/bin/unzip'] as $bin) {
        if (is_executable($bin)) {
            return $bin;
        }
    }
    throw new RuntimeException('找不到 unzip');
}

function deploy_extract(string $zipPath, string $garden, string $home): void
{
    if (!is_file($zipPath)) {
        throw new RuntimeException('找不到 zip ' . $zipPath);
    }
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('無法開啟 zip');
        }
        $saw = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) {
                $zip->close();
                throw new RuntimeException('無法讀取 zip 清單');
            }
            $rel = deploy_entry_rel($name);
            if ($rel === null || deploy_skip($rel)) {
                continue;
            }
            $bytes = $zip->getFromIndex($i);
            if (!is_string($bytes)) {
                $zip->close();
                throw new RuntimeException('無法讀出 zip 內容 ' . $name);
            }
            deploy_put($garden, $rel, $bytes);
            $saw = true;
        }
        $zip->close();
        if (!$saw) {
            throw new RuntimeException('zip 沒有可解壓的程式檔');
        }
        return;
    }
    if (!function_exists('exec') || deploy_disabled('exec')) {
        throw new RuntimeException('無法解壓：沒有 ZipArchive，exec 也不可用');
    }
    $unzip = deploy_unzip_bin();
    $list = [];
    $code = 0;
    exec(escapeshellarg($unzip) . ' -Z1 ' . escapeshellarg($zipPath), $list, $code);
    if ($code !== 0) {
        throw new RuntimeException('無法讀取 zip 清單');
    }
    foreach ($list as $name) {
        $name = trim($name);
        if ($name !== '') {
            deploy_entry_rel($name);
        }
    }
    $tmp = $home . '/garden_unpack_' . bin2hex(random_bytes(4));
    if (str_starts_with($tmp, $home . '/public_html/') || file_exists($tmp) || !mkdir($tmp, 0755)) {
        throw new RuntimeException('無法建立暫存目錄');
    }
    try {
        $out = [];
        $code = 0;
        exec(escapeshellarg($unzip) . ' -q ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($tmp), $out, $code);
        if ($code !== 0) {
            throw new RuntimeException('解壓失敗');
        }
        $root = realpath($tmp);
        if ($root === false || !is_dir($root . '/garden')) {
            throw new RuntimeException('zip 最外層不是 garden/');
        }
        $saw = false;
        $prefix = $root . '/garden/';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/garden', FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('zip 內有連結');
            }
            if (!$file->isFile()) {
                continue;
            }
            $real = $file->getRealPath();
            if ($real === false || !str_starts_with($real, $prefix)) {
                throw new RuntimeException('解壓路徑超出暫存目錄');
            }
            $rel = substr($real, strlen($prefix));
            if (deploy_skip($rel)) {
                continue;
            }
            $bytes = file_get_contents($real);
            if (!is_string($bytes)) {
                throw new RuntimeException('無法讀出解壓檔 ' . $rel);
            }
            deploy_put($garden, $rel, $bytes);
            $saw = true;
        }
        if (!$saw) {
            throw new RuntimeException('zip 沒有可解壓的程式檔');
        }
    } finally {
        if (is_dir($tmp)) {
            deploy_rm_tree($tmp);
        }
    }
}

function deploy_sync(string $src, string $dst, bool $keepDeploy): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('備份目錄不存在 ' . $src);
    }
    if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
        throw new RuntimeException('無法建立 ' . $dst);
    }
    $srcFiles = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isLink()) {
            throw new RuntimeException('備份裡有連結 ' . $file->getPathname());
        }
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $rel = substr($path, strlen(rtrim($src, '/')) + 1);
        if ($keepDeploy && deploy_keep_script($rel)) {
            continue;
        }
        $srcFiles[$rel] = $path;
        $target = rtrim($dst, '/') . '/' . $rel;
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new RuntimeException('無法建立目錄 ' . $parent);
        }
        if (!copy($path, $target)) {
            throw new RuntimeException('無法還原 ' . $rel);
        }
    }
    if (!is_dir($dst)) {
        return;
    }
    $dstIt = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dst, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($dstIt as $file) {
        $path = $file->getPathname();
        $rel = substr($path, strlen(rtrim($dst, '/')) + 1);
        if ($keepDeploy && deploy_keep_script($rel)) {
            continue;
        }
        if ($file->isLink() || $file->isFile()) {
            if (!isset($srcFiles[$rel]) && !unlink($path)) {
                throw new RuntimeException('無法刪除 ' . $rel);
            }
            continue;
        }
        if ($file->isDir()) {
            $left = array_diff(scandir($path) ?: [], ['.', '..']);
            if ($left === []) {
                rmdir($path);
            }
        }
    }
}

function deploy_restore_all(string $backup, string $garden, string $private): void
{
    deploy_sync($backup . '/garden', $garden, true);
    deploy_sync($backup . '/garden_private', $private, false);
}

function deploy_latest_backup(string $home): string
{
    $dirs = glob($home . '/garden_backup_*', GLOB_ONLYDIR) ?: [];
    $good = [];
    foreach ($dirs as $dir) {
        if (!preg_match('#/garden_backup_\d{14}$#', $dir)) {
            continue;
        }
        if (str_contains($dir, '/public_html/')) {
            continue;
        }
        if (is_dir($dir . '/garden') && is_file($dir . '/garden_private/garden.sqlite')) {
            $good[] = $dir;
        }
    }
    sort($good, SORT_STRING);
    if ($good === []) {
        throw new RuntimeException('找不到備份 ' . $home . '/garden_backup_*');
    }
    return $good[count($good) - 1];
}

function deploy_install_db(string $src, string $dst): void
{
    if (!is_file($src)) {
        throw new RuntimeException('備份資料庫不存在 ' . $src);
    }
    foreach (array_merge(
        [$dst . '-wal', $dst . '-shm', $dst . '-journal'],
        glob($dst . '.bak-*') ?: [],
        glob($dst . '.tmp-*') ?: []
    ) as $side) {
        if (is_file($side) && !unlink($side)) {
            throw new RuntimeException('無法刪除 ' . $side);
        }
    }
    $tmp = $dst . '.tmp-' . bin2hex(random_bytes(4));
    if (!copy($src, $tmp)) {
        throw new RuntimeException('無法複製備份資料庫');
    }
    if (!rename($tmp, $dst)) {
        @unlink($tmp);
        throw new RuntimeException('無法換上備份資料庫');
    }
}

function deploy_counts(string $log): string
{
    $keep = [];
    foreach (preg_split("/\r\n|\n|\r/", $log) ?: [] as $line) {
        if (str_starts_with($line, 'COUNT ') || str_starts_with($line, 'VENDOR ')) {
            $keep[] = $line;
        }
    }
    if ($keep === []) {
        throw new RuntimeException("轉換沒有印出筆數\n" . $log);
    }
    return implode("\n", $keep) . "\n";
}

function deploy_home(): string
{
    $home = getenv('GARDEN_DEPLOY_HOME');
    if (!is_string($home) || $home === '' || str_contains($home, "\0")) {
        $home = '/home/irihvsuu';
    }
    $home = rtrim($home, '/');
    if ($home === '' || !str_starts_with($home, '/')) {
        throw new RuntimeException('家目錄不對');
    }
    return $home;
}

function deploy_mode_check(string $home, string $garden, string $private, string $zip, string $db): void
{
    $before = is_file($db) ? hash_file('sha256', $db) : '';
    $backup = deploy_latest_backup($home);
    $backupDb = $backup . '/garden_private/garden.sqlite';
    $tmp = $home . '/garden_check_' . bin2hex(random_bytes(4));
    $tmpGarden = $tmp . '/garden';
    if (!mkdir($tmpGarden, 0755, true)) {
        throw new RuntimeException('無法建立檢查暫存目錄');
    }
    try {
        deploy_extract($zip, $tmpGarden, $home);
        $script = $tmpGarden . '/migrate/v13_convert.php';
        $map = $tmpGarden . '/migrate/v13_map.php';
        if (!is_file($script) || !is_file($map)) {
            throw new RuntimeException('zip 裡沒有轉換程式');
        }
        require_once $script;
        if (!function_exists('v13_execute')) {
            throw new RuntimeException('轉換程式不能在同一個程序裡執行');
        }
        try {
            $log = v13_execute('dry-run', $backupDb, $map);
        } catch (V13Stop $e) {
            throw new RuntimeException("dry-run 沒有通過\n" . $e->log . "\n" . $e->getMessage(), 0, $e);
        }
    } finally {
        if (is_dir($tmp)) {
            deploy_rm_tree($tmp);
        }
    }
    $after = is_file($db) ? hash_file('sha256', $db) : '';
    if ($before !== $after) {
        throw new RuntimeException('check 改到正式資料庫了');
    }
    $lines = ['CHECK OK', deploy_env_report()];
    $lines[] = 'backup ' . $backup;
    $lines[] = 'backup_db_sha256 ' . hash_file('sha256', $backupDb);
    $lines[] = 'live_db_sha256 ' . $after;
    $lines[] = 'live_matches_backup ' . ($before !== '' && $before === hash_file('sha256', $backupDb) ? 'yes' : 'no');
    $lines[] = 'zip ' . (is_file($zip) ? 'yes' : 'no');
    $lines[] = 'garden_writable ' . (is_writable($garden) ? 'yes' : 'no');
    $lines[] = 'private_writable ' . (is_writable($private) ? 'yes' : 'no');
    foreach (preg_split("/\r\n|\n|\r/", $log) ?: [] as $line) {
        if (str_starts_with($line, 'DRY-RUN ') || str_starts_with($line, 'UNMAPPED ') || str_starts_with($line, 'NEED ')) {
            $lines[] = $line;
        }
    }
    $lines[] = '腳本保留';
    deploy_ok(implode("\n", $lines) . "\n");
}

function deploy_mode_apply(string $home, string $garden, string $private, string $zip, string $db): void
{
    global $DEPLOY;
    $backup = deploy_latest_backup($home);
    $DEPLOY['backup'] = $backup;
    $DEPLOY['changed'] = true;
    try {
        deploy_extract($zip, $garden, $home);
        deploy_install_db($backup . '/garden_private/garden.sqlite', $db);
        $script = $garden . '/migrate/v13_convert.php';
        $map = $garden . '/migrate/v13_map.php';
        if (!is_file($script) || !is_file($map)) {
            throw new RuntimeException('解壓後找不到轉換程式');
        }
        require_once $script;
        if (!function_exists('v13_execute')) {
            throw new RuntimeException('轉換程式不能在同一個程序裡執行');
        }
        set_time_limit(0);
        $log = v13_execute('apply', $db, $map);
        $counts = deploy_counts($log);
    } catch (Throwable $e) {
        $note = '';
        try {
            deploy_restore_all($backup, $garden, $private);
            $DEPLOY['rolled'] = true;
            $note = "\n已從 " . $backup . " 還原 garden 與 garden_private。";
        } catch (Throwable $restoreError) {
            $note = "\n還原失敗：\n" . deploy_exception_text($restoreError);
        }
        throw new RuntimeException('轉換失敗，已停下。zip 與腳本都還在。' . $note, 0, $e);
    }
    $DEPLOY['changed'] = false;
    $DEPLOY['rolled'] = true;
    deploy_ok("APPLY OK\n腳本保留\nzip 保留\n" . $counts);
}

function deploy_mode_rollback(string $home, string $garden, string $private): void
{
    global $DEPLOY;
    $backup = deploy_latest_backup($home);
    $DEPLOY['backup'] = $backup;
    deploy_restore_all($backup, $garden, $private);
    $DEPLOY['rolled'] = true;
    deploy_ok("ROLLBACK OK\nbackup " . $backup . "\n腳本保留\nzip 保留\n");
}

function deploy_mode_cleanup(string $home): void
{
    $zip = $home . '/garden-v14.1.zip';
    $lines = ['CLEANUP OK'];
    if (is_file($zip)) {
        if (!unlink($zip)) {
            throw new RuntimeException('無法刪除 zip');
        }
        $lines[] = 'zip deleted';
    } else {
        $lines[] = 'zip absent';
    }
    if (!unlink(__FILE__)) {
        throw new RuntimeException("無法刪除腳本\n" . implode("\n", $lines));
    }
    $lines[] = 'script deleted';
    deploy_ok(implode("\n", $lines) . "\n");
}

register_shutdown_function(static function (): void {
    global $DEPLOY;
    $error = error_get_last();
    $fatal = is_array($error) && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    if (!$fatal || $DEPLOY['mode'] !== 'apply' || !$DEPLOY['changed'] || $DEPLOY['rolled']) {
        return;
    }
    $note = '';
    try {
        if ($DEPLOY['backup'] !== '') {
            deploy_restore_all($DEPLOY['backup'], $DEPLOY['garden'], $DEPLOY['private']);
            $DEPLOY['rolled'] = true;
            $note = "\n已從 " . $DEPLOY['backup'] . " 還原 garden 與 garden_private。\n";
        }
    } catch (Throwable $e) {
        $note = "\n還原失敗：\n" . deploy_exception_text($e) . "\n";
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo "轉換失敗，已停下。zip 與腳本都還在。\n";
    echo (string) ($error['message'] ?? '') . "\n";
    echo (string) ($error['file'] ?? '') . ':' . (string) ($error['line'] ?? '') . "\n";
    echo deploy_env_report() . "\n";
    echo $note;
});

if (PHP_SAPI === 'cli') {
    deploy_fail(403, "請用網頁開啟。\n" . deploy_env_report());
}

$given = $_GET['token'] ?? '';
if (!is_string($given) || !hash_equals(DEPLOY_TOKEN, $given)) {
    deploy_fail(403, "token 不對。\n");
}

$mode = $_GET['mode'] ?? '';
if (!is_string($mode) || !in_array($mode, ['check', 'apply', 'rollback', 'cleanup'], true)) {
    deploy_fail(400, "要指定 mode=check、mode=apply、mode=rollback 或 mode=cleanup。沒有改檔。\n" . deploy_env_report());
}

try {
    $home = deploy_home();
    $garden = $home . '/public_html/garden';
    $private = $home . '/garden_private';
    $zip = $home . '/garden-v14.1.zip';
    $db = $private . '/garden.sqlite';
    $DEPLOY['mode'] = $mode;
    $DEPLOY['garden'] = $garden;
    $DEPLOY['private'] = $private;
    if (!is_dir($garden) || !is_dir($private)) {
        throw new RuntimeException('找不到網站目錄或私人目錄');
    }
    if ($mode === 'cleanup') {
        deploy_mode_cleanup($home);
        return;
    }
    if (!is_file($db)) {
        throw new RuntimeException('找不到資料庫 ' . $db);
    }
    if ($mode === 'rollback') {
        deploy_mode_rollback($home, $garden, $private);
        return;
    }
    if (!is_file($zip)) {
        throw new RuntimeException('找不到 zip ' . $zip);
    }
    if ($mode === 'check') {
        deploy_mode_check($home, $garden, $private, $zip, $db);
        return;
    }
    deploy_mode_apply($home, $garden, $private, $zip, $db);
} catch (Throwable $e) {
    deploy_fail(500, deploy_exception_text($e) . "\n" . deploy_env_report() . "\n腳本保留。\n");
}
