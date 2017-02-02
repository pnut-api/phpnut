<?php

require_once '../EZphpnut.php';

$app = new EZphpnut();

// log out user
$app->deleteSession();

// redirect user after logging out
header('Location: index.php');

?>
