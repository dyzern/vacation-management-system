<?php
session_start();

if (isset($_SESSION['perfil'])) {
    switch ($_SESSION['perfil']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            exit;

        case 'responsavel':
            header("Location: responsavel/dashboard.php");
            exit;

        case 'colaborador':
            header("Location: colaborador/dashboard.php");
            exit;
    }
}

header("Location: auth/login.php");
exit;