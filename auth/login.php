<?php
session_start();

if (isset($_SESSION['perfil'])) {
    switch ($_SESSION['perfil']) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            exit;
        case 'responsavel':
            header("Location: ../responsavel/dashboard.php");
            exit;
        case 'colaborador':
            header("Location: ../colaborador/dashboard.php");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Controlo de Férias</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="../assets/js/main.js" defer></script>
</head>
<body class="login-page">

    <div class="login-background">
        <div class="wave wave-1"></div>
        <div class="wave wave-2"></div>
        <div class="wave wave-3"></div>
    </div>

    <header class="login-header">
        <img src="../assets/img/ribas-logo.png" alt="Ribas" class="login-logo">
    </header>

    <main class="login-main">
        <div class="login-card">
            <h1>Sistema de Controlo de Férias</h1>
            <p class="login-subtitle">Inicie sessão na sua conta</p>

            <?php if (isset($_GET['erro'])): ?>
                <div class="alert alert-error">Utilizador ou palavra-passe inválidos.</div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success">Sessão terminada com sucesso.</div>
            <?php endif; ?>

            <form action="login_action.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="login">Utilizador</label>
                    <input
                        type="text"
                        name="login"
                        id="login"
                        placeholder="ex: joao.silva"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Palavra-passe</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Introduza a sua palavra-passe"
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn">Entrar</button>

                <a href="#" class="forgot-password">Esqueceu-se da palavra-passe?</a>
            </form>
        </div>
    </main>

</body>
</html>