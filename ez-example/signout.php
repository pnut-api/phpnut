<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/ez-settings.php';

$app = new EZphpnut();

// log out user
$app->deleteSession();

// redirect user after logging out
header('Location: index.php');
