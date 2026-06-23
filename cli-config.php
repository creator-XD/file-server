<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

$entityManager = require __DIR__ . '/src/Config/doctrine.php';

ConsoleRunner::run(
    new SingleManagerProvider($entityManager)
);