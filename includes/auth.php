<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['employee_email']) || !empty($_SESSION['admin_user_id']);
}

function current_admin_email(): string
{
    return (string) ($_SESSION['employee_email'] ?? $_SESSION['sso_email'] ?? $_SESSION['admin_username'] ?? '');
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function get_active_employee(string $email): ?array
{
    $email = normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM employees WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $employee = $stmt->fetch();

    return $employee ?: null;
}

function employee_can_access_admin(string $email): bool
{
    $employee = get_active_employee($email);
    if (!$employee) {
        return false;
    }

    return (int) $employee['can_access_admin'] === 1;
}

function employee_is_super_admin(string $email): bool
{
    $employee = get_active_employee($email);
    if (!$employee || !array_key_exists('is_super_admin', $employee)) {
        return false;
    }

    return (int) $employee['is_super_admin'] === 1;
}

function require_login(): void
{
    $email = current_admin_email();
    if (!is_logged_in()) {
        logout_admin();
        header('Location: login.php');
        exit;
    }

    if (!employee_can_access_admin($email)) {
        header('Location: ../index.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
}

function audit_log(string $action, string $entityType, ?int $entityId = null, ?string $entityName = null, array $details = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (actor_email, action, entity_type, entity_id, entity_name, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            current_admin_email(),
            $action,
            $entityType,
            $entityId,
            $entityName,
            empty($details) ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    } catch (Throwable $exception) {
        // Logging should never break admin work, especially before the migration is applied.
    }
}

function login_admin(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    if (filter_var($user['username'], FILTER_VALIDATE_EMAIL) && !employee_can_access_admin($user['username'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    if (filter_var($user['username'], FILTER_VALIDATE_EMAIL)) {
        $_SESSION['employee_email'] = strtolower($user['username']);
    }
    csrf_token();

    return true;
}

function login_sso_employee(string $email): bool
{
    $email = normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $domain = substr(strrchr($email, '@') ?: '', 1);
    if (!empty(SSO_ALLOWED_EMAIL_DOMAINS) && !in_array($domain, SSO_ALLOWED_EMAIL_DOMAINS, true)) {
        return false;
    }

    $employee = get_active_employee($email);
    if (!$employee) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['employee_email'] = $email;
    $_SESSION['employee_name'] = (string) ($employee['name'] ?? '');
    $_SESSION['sso_email'] = $email;

    if ((int) $employee['can_access_admin'] === 1) {
        $stmt = db()->prepare('SELECT id, username FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $unusablePasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO admin_users (username, password_hash, is_active) VALUES (?, ?, 1)');
            $stmt->execute([$email, $unusablePasswordHash]);
            $user = [
                'id' => (int) db()->lastInsertId(),
                'username' => $email,
            ];
        }

        $_SESSION['admin_user_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = $user['username'];
    }

    csrf_token();

    return true;
}

function login_sso_admin(string $email): bool
{
    return login_sso_employee($email) && employee_can_access_admin($email);
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}
