<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
const STORAGE = ROOT . '/storage';
function config(): array {
    static $config;
    if ($config === null) {
        $config = require ROOT . '/config/example.php';
        if (is_file(ROOT . '/config/local.php')) $config = array_replace($config, require ROOT . '/config/local.php');
    }
    return $config;
}
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = config();
        $pdo = new PDO("mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
function query(string $sql, array $values = []): PDOStatement {
    $stmt = db()->prepare($sql); $stmt->execute($values); return $stmt;
}
function h($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page, array $params = []): string { return 'index.php?' . http_build_query(['page' => $page] + $params); }
function redirect(string $page, array $params = []): never { header('Location: ' . url($page, $params)); exit; }
function csrf(): string { return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'; }
function check_csrf(): void {
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); throw new InvalidArgumentException('La sesión del formulario venció. Recargá la página y volvé a intentar.');
    }
}
function text_input(string $name, int $max = 10000): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Formato de campo incorrecto.');
    $value = trim($value);
    if (mb_strlen($value) > $max) throw new InvalidArgumentException("El campo $name supera el máximo de $max caracteres.");
    return $value;
}
function choice(string $name, array $choices, string $default): string {
    $value = text_input($name, 80) ?: $default;
    if (!in_array($value, $choices, true)) throw new InvalidArgumentException('Opción de ' . $name . ' incorrecta.');
    return $value;
}
function optional_integer(string $name, int $min, int $max): ?int {
    $v = text_input($name, 30);
    if ($v === '') return null;
    if (filter_var($v, FILTER_VALIDATE_INT) === false || (int)$v < $min || (int)$v > $max) throw new InvalidArgumentException("Revisá el valor de $name.");
    return (int)$v;
}
function id_param(): int {
    $id = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id === false || $id < 0) throw new InvalidArgumentException('Código de ficha incorrecto.');
    return $id;
}
function ensure_storage(): void {
    if (!is_dir(STORAGE) && !mkdir(STORAGE, 0770, true)) throw new RuntimeException('No se pudo crear storage.');
    if (!is_file(STORAGE . '/.htaccess')) file_put_contents(STORAGE . '/.htaccess', "Require all denied\n");
}
function admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    return query('SELECT id,email FROM admins WHERE id=? AND active=1', [$_SESSION['admin_id']])->fetch() ?: null;
}
function flash(string $message): void { $_SESSION['flash'] = $message; }
function report_error(Throwable $e): string {
    if ($e instanceof InvalidArgumentException) return $e->getMessage();
    try { ensure_storage(); error_log(date('c') . ' ' . (string)$e . "\n", 3, STORAGE . '/errors.log'); } catch (Throwable $ignored) {}
    return 'No se pudo completar la operación. Revisá la conexión en config/local.php y el registro storage/errors.log.';
}
function login_attempt(bool $failed = false, bool $clear = false): bool {
    ensure_storage();
    $key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $f = fopen(STORAGE . '/login-' . $key . '.json', 'c+');
    if (!$f || !flock($f, LOCK_EX)) throw new RuntimeException('No se pudo registrar el acceso.');
    try {
        $data = json_decode(stream_get_contents($f), true) ?: ['start'=>time(), 'count'=>0];
        if (time() - $data['start'] >= 900 || $clear) $data = ['start'=>time(), 'count'=>0];
        if ($failed) $data['count']++;
        rewind($f); ftruncate($f, 0); fwrite($f, json_encode($data));
        return $data['count'] < 10;
    } finally { flock($f, LOCK_UN); fclose($f); }
}
function setup_admin(): void {
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) throw new InvalidArgumentException('La cuenta inicial se crea desde localhost en la computadora del servidor.');
    $email = text_input('email', 255); $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Ingresá un correo válido.');
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) throw new InvalidArgumentException('La contraseña debe tener entre 12 y 72 bytes.');
    if ((int)query("SELECT GET_LOCK('prodigo_initial_admin', 5)")->fetchColumn() !== 1) throw new RuntimeException('Instalación ocupada.');
    try {
        if ((int)query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) throw new InvalidArgumentException('La cuenta inicial ya existe. Ingresá con tu contraseña.');
        query('INSERT INTO admins (email,password_hash) VALUES (?,?)', [$email,password_hash($password,PASSWORD_DEFAULT)]);
        session_regenerate_id(true); $_SESSION['admin_id'] = (int)db()->lastInsertId();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    } finally { query("SELECT RELEASE_LOCK('prodigo_initial_admin')"); }
}
function do_login(): void {
    if (!login_attempt()) throw new InvalidArgumentException('Demasiados intentos. Esperá 15 minutos antes de volver a ingresar.');
    $email = text_input('email', 255); $password = $_POST['password'] ?? '';
    if (!is_string($password)) throw new InvalidArgumentException('Contraseña incorrecta.');
    $user = query('SELECT * FROM admins WHERE email=? AND active=1', [$email])->fetch();
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password, $hash) || !$user) {
        login_attempt(true); throw new InvalidArgumentException('Correo o contraseña incorrectos.');
    }
    login_attempt(false, true); session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$user['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1');
    session_name('prodigo_admin');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' blob:; style-src 'self'; script-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store');
}
