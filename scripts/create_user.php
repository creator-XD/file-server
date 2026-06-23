<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Entity\User;

$login = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$login || !$password) {
    echo "Usage: php scripts/create_user.php <login> <password>\n";
    exit(1);
}

$entityManager = require __DIR__ . '/../src/Config/doctrine.php';

// проверка, есть ли пользователь
$userRepo = $entityManager->getRepository(User::class);

$existingUser = $userRepo->findOneBy(['login' => $login]);

if ($existingUser) {
    echo "User already exists\n";
    exit(1);
}

// создаём пользователя
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$user = new User($login, $passwordHash);

$entityManager->persist($user);
$entityManager->flush();

echo "User created successfully\n";