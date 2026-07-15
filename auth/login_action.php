<?php
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$login = trim($_POST['login'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($login === '' || $password === '') {
    header("Location: login.php?erro=1");
    exit;
}

$sql = "SELECT * FROM utilizadores 
        WHERE (username = :login OR email = :login) 
        AND ativo = 1
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['login' => $login]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nome'] = $user['nome'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['perfil'] = $user['perfil'];

    redirectByRole($user['perfil']);
}

header("Location: login.php?erro=1");
exit;
