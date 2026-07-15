<?php

function redirectByRole(string $perfil): void
{
    switch ($perfil) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            exit;

        case 'responsavel':
            header("Location: ../responsavel/dashboard.php");
            exit;

        case 'colaborador':
            header("Location: ../colaborador/dashboard.php");
            exit;

        default:
            header("Location: ../auth/login.php");
            exit;
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] !== $role) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit;
    }
}

function requireAnyRole(array $roles): void
{
    requireLogin();

    if (!isset($_SESSION['perfil']) || !in_array($_SESSION['perfil'], $roles, true)) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit;
    }
}

function getCurrentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function getUserInitials(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));

    if ($partes === false || $partes === []) {
        return '--';
    }

    $iniciais = '';

    foreach (array_slice($partes, 0, 2) as $parte) {
        $iniciais .= strtoupper(substr($parte, 0, 1));
    }

    return $iniciais !== '' ? $iniciais : '--';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
