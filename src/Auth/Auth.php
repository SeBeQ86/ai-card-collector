<?php

declare(strict_types=1);

namespace App\Auth;

use PDO;

final class Auth
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $secure = (getenv('APP_ENV') === 'production');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }

    public function login(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user === false || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id'    => (int)    $_SESSION['user_id'],
            'email' => (string) $_SESSION['user_email'],
        ];
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }
}
