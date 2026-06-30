<?php

declare(strict_types=1);

require file_exists(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Security\Csrf;

Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');

if (!Csrf::validate($token)) {
    header('Location: index.php');
    exit;
}

Auth::logout();

header('Location: login.php');
exit;

