<?php

$root = $_SERVER['DOCUMENT_ROOT'] .'/';
$adminRoot = $root . 'admin/';

require $root . 'lib/definitions/site-settings.php';
require $adminRoot.'lib/definitions/admin-settings.php';

$response = new Response();

$key = $_POST['key'] ?? $response->kill('no key provided');
$userid = $_POST['user_id'] ?? $response->kill('no user provided');

$user = read('users','id', $userid);
if(!$user) $response->kill('user not found');

$checkKey = read('ApiKeys','value', $key);
if($checkKey) $response->kill('key already exists');

$data = [
    'Value' => $key,
    'UserId' => $user['Id'],
    'Type' => 'root'
];

add('ApiKeys', $data) or $response->kill('error adding key');

header('location:'. $adminRoot . 'api-keys');