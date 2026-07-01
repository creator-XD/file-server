<?php

passthru(
    'vendor/bin/doctrine-migrations migrate '
    . '--configuration=migrations.php '
    . '--db-configuration=migrations-db.php '
    . '--no-interaction',
    $exitCode
);

exit($exitCode);