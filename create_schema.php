<?php

use Doctrine\ORM\Tools\SchemaTool;
use App\Entity\User;
use App\Entity\Session;

$entityManager = require __DIR__ . '/src/Config/doctrine.php';

$schemaTool = new SchemaTool($entityManager);

$classes = [
    $entityManager->getClassMetadata(User::class),
    $entityManager->getClassMetadata(Session::class),
];

try {
    $schemaTool->createSchema($classes);
    echo "Database schema created successfully\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}