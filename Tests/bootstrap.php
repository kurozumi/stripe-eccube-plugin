<?php
$loader = require __DIR__.'/../../../../vendor/autoload.php';

$envFile = __DIR__.'/../../../../.env';
if (file_exists($envFile)) {
    (new \Symfony\Component\Dotenv\Dotenv())->load($envFile);
}
