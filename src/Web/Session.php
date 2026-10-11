<?php

declare(strict_types=1);

namespace App\Web;

/** Session PHP : jeton CSRF et messages flash (affichés une seule fois). */
final class Session
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public function csrfToken(): string
    {
        return (string) $_SESSION['csrf'];
    }

    public function checkCsrf(mixed $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }

    /** @param bool $html le message est déjà du HTML sûr (construit avec View::h) */
    public function flash(string $message, string $type = 'ok', bool $html = false): void
    {
        $_SESSION['flashes'][] = ['message' => $message, 'type' => $type, 'html' => $html];
    }

    /** @return list<array{message:string,type:string,html:bool}> */
    public function takeFlashes(): array
    {
        $flashes = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);
        return $flashes;
    }
}
