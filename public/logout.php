<?php
$root = './';
require $root.'lib/definitions/site-settings.php';

session_destroy();

// Redirect to home
header('Location: /');
exit;