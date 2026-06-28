<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

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
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim((string) ($_POST['email']    ?? ''));
        $password =      (string) ($_POST['password'] ?? '');

        $auth = new Auth(Connection::get($config['db']));

        if ($auth->login($email, $password)) {
            header('Location: index.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}

$appName   = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');
$errorHtml = $error !== ''
    ? '<p class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in — <?= $appName ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="page-login">
    <h1><?= $appName ?></h1>
    <?= $errorHtml ?>
    <form method="post" action="login.php">
        <?= Csrf::field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus
               value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Log in</button>
    </form>
</body>
</html>
