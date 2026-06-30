<?php

declare(strict_types=1);

$config = require __DIR__ . '/config/app.php';
require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Connection;
use App\Security\Csrf;

Auth::startSession();

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!Csrf::validate($token)) {
        $error = 'Nieprawidłowe żądanie — spróbuj ponownie.';
    } else {
        $email    = trim((string) ($_POST['email']    ?? ''));
        $password =      (string) ($_POST['password'] ?? '');

        $auth = new Auth(Connection::get($config['db']));

        if ($auth->login($email, $password)) {
            header('Location: index.php');
            exit;
        }

        $error = 'Nieprawidłowy e-mail lub hasło.';
    }
}

$appName   = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');
$errorHtml = $error !== ''
    ? '<p class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie — <?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=6">
</head>
<body class="page-login">
    <h1><?= $appName ?></h1>
    <?= $errorHtml ?>
    <form method="post" action="login.php">
        <?= Csrf::field() ?>
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus
               value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <label for="password">Hasło</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Zaloguj się</button>
    </form>
</body>
</html>

