<?php

$root = $_SERVER['DOCUMENT_ROOT'] .'/';
require $root.'lib/definitions/site-settings.php';

$response = new Response();

$_POST = json_decode(file_get_contents('php://input'), true);

if(!isset($_POST)) $response->killWithMessage('No post data provided');