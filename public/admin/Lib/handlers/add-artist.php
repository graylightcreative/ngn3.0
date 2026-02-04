<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$root = $_SERVER['DOCUMENT_ROOT'] . '/';
require $root.'lib/definitions/site-settings.php';

$config = new Config();
$pdo = ConnectionFactory::write($config);

// Get artist title from incoming url
$artistTitle = !isset($_GET['a']) ? die('No artist title sent') : $_GET['a'];


// 1. Does Artist/Label Exist Already?
$checkArtistStmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`artists` WHERE LOWER(name) = :artistName LIMIT 1");
$checkArtistStmt->execute([':artistName' => strtolower($artistTitle)]);
$checkArtist = $checkArtistStmt->fetch(PDO::FETCH_ASSOC);

if($checkArtist) die(ucwords($artistTitle).' exists as an artist');

$checkLabelStmt = $pdo->prepare("SELECT id FROM `ngn_2025`.`labels` WHERE LOWER(name) = :artistName LIMIT 1");
$checkLabelStmt->execute([':artistName' => strtolower($artistTitle)]);
$checkLabel = $checkLabelStmt->fetch(PDO::FETCH_ASSOC);

if($checkLabel) die(ucwords($artistTitle).' exists as a label');

// 2. Radio or SMR?
// 3. Do we have label data? (Check SMR / Radio)
// 4. Add Artist w/ Default Settings
// 5. Add Label (If Applicable)
// 6. Add Label To Artist (If Applicable)



// Verify this title cannot be found by name


//  Radio Spins or SMR?

// 1. Check radio spins for artist title
$artistTitle = strtolower($artistTitle);
if(strpos($artistTitle, 'the ') === 0){
   // string has "The" at the beginning
    $artistTitle = str_replace('the ', '', $artistTitle);
}
echo 'Checking ' . $artistTitle . '<br>';

$query = $pdo->prepare("SELECT artist_name FROM `ngn_spins_2025`.`station_spins` WHERE LOWER(artist_name) LIKE :artistTitle");
$query->bindValue(':artistTitle', '%' . $artistTitle . '%', PDO::PARAM_STR);
$query->execute();
$radioSpins = $query->fetchAll(PDO::FETCH_ASSOC);

$query = $pdo->prepare("SELECT artist AS Artists, label AS Label FROM `ngn_smr_2025`.`smr_chart` WHERE LOWER(artist) LIKE :artistTitle");
$query->bindValue(':artistTitle', '%' . $artistTitle . '%', PDO::PARAM_STR);
$query->execute();
$smr = $query->fetchAll(PDO::FETCH_ASSOC);

$artistObject = [
    'Title' => ucwords($artistTitle), // Keep Title for display_name
    'Slug' => createSlug($artistTitle),
    'RoleId' => 3, // RoleId 3 = Artist
    'Email' => createSlug($artistTitle) . '@nextgennoise.com',
    'Password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
    'LabelId' => null, // Will be set later if found
];

$findLabel = false;
// Check radio for user
// This doesn't help us currently because radio doesn't list the label
if($radioSpins and !$smr){
    // radio spins only
    // no label

} else if($radioSpins and $smr){

    // both
    // get label from SMR if artist is first name in list
    $label = $smr[0]['Label'];
    $findLabel = findLabelId($pdo, $label);

} else if(!$radioSpins and $smr) {
    // smr only
    // get label from SMR if artist is first name in list
    $label = $smr[0]['Label'];
    $findLabel = findLabelId($pdo, $label);
}

if($findLabel) {
    $artistObject['LabelId'] = $findLabel;
} else {
    if(!empty($label)){
        echo 'Label does not exist. Creating label...<br>';
        // make label
        $labelObject = [
            'name' => ucwords($label),
            'slug' => createSlug($label),
            'email' => createSlug($label) . '@nextgennoise.com',
            'password_hash' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            'role_id' => 7, // RoleId 7 = Label
        ];

        // Insert into ngn_2025.users
        $insertUserStmt = $pdo->prepare("INSERT INTO `ngn_2025`.`users` (email, password_hash, display_name, username, role_id, status) VALUES (:email, :password_hash, :display_name, :username, :role_id, 'active')");
        $insertUserStmt->execute([
            ':email' => $labelObject['email'],
            ':password_hash' => $labelObject['password_hash'],
            ':display_name' => $labelObject['name'],
            ':username' => $labelObject['slug'],
            ':role_id' => $labelObject['role_id'],
        ]);
        $newLabelUserId = $pdo->lastInsertId();
        if(!$newLabelUserId) die('Could not add new label user');

        // Insert into ngn_2025.labels
        $insertLabelStmt = $pdo->prepare("INSERT INTO `ngn_2025`.`labels` (id, user_id, slug, name) VALUES (:id, :user_id, :slug, :name)");
        $insertLabelStmt->execute([
            ':id' => $newLabelUserId, // Use the same ID for simplicity
            ':user_id' => $newLabelUserId,
            ':slug' => $labelObject['slug'],
            ':name' => $labelObject['name'],
        ]);
        $newLabelId = $pdo->lastInsertId();
        if(!$newLabelId) die('Could not add new label');

        echo $labelObject['name'] . ' successfully created<br>';
        $artistObject['label_id'] = $newLabelId;
    }
}

// Insert into ngn_2025.users
$insertUserStmt = $pdo->prepare("INSERT INTO `ngn_2025`.`users` (email, password_hash, display_name, username, role_id, status) VALUES (:email, :password_hash, :display_name, :username, :role_id, 'active')");
$insertUserStmt->execute([
    ':email' => $artistObject['Email'],
    ':password_hash' => $artistObject['Password'],
    ':display_name' => $artistObject['Title'],
    ':username' => $artistObject['Slug'],
    ':role_id' => $artistObject['RoleId'],
]);
$newArtistUserId = $pdo->lastInsertId();
if(!$newArtistUserId) die('Could not add new artist user');

// Insert into ngn_2025.artists
$insertArtistStmt = $pdo->prepare("INSERT INTO `ngn_2025`.`artists` (id, user_id, slug, name, label_id) VALUES (:id, :user_id, :slug, :name, :label_id)");
$insertArtistStmt->execute([
    ':id' => $newArtistUserId, // Use the same ID for simplicity
    ':user_id' => $newArtistUserId,
    ':slug' => $artistObject['Slug'],
    ':name' => $artistObject['Title'],
    ':label_id' => $artistObject['LabelId'] ?? null,
]);
$newArtistId = $pdo->lastInsertId();
if(!$newArtistId) die('Could not add artist');

echo 'Added artist';



function findLabelId(PDO $pdo, $labelTitle){
    $query = $pdo->prepare("SELECT id FROM `ngn_2025`.`labels` WHERE LOWER(name) LIKE :labelTitle");
    $query->bindValue(':labelTitle', '%' . $labelTitle . '%', PDO::PARAM_STR);
    $query->execute();
    $find = $query->fetch(PDO::FETCH_ASSOC); // Use fetch not fetchAll for single result
    if($find) {
        return $find['id'];
    } else {
        return false;
    }
}