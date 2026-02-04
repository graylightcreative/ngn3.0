<?php
$root = '../';
require_once $root . 'definitions/site-settings.php';
require_once $root . 'controllers/EmailController.php';
require_once $root . 'controllers/ResponseController.php';
$_POST = json_decode(file_get_contents('php://input'), true);
$response = makeResponse();


// Run rankings