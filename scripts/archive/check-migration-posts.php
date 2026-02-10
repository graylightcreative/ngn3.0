<?php
/**
 * Check posts migration results
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

$config = new Config();
$db = ConnectionFactory::write($config);

// Check posts count
$result = $db->query("SELECT COUNT(*) as cnt FROM posts");
$posts = $result->fetch()['cnt'];
echo "Posts in database: $posts\n";

if ($posts > 0) {
    // Show a few posts
    $result = $db->query("SELECT Id, Title, Status, PublishedAt FROM posts ORDER BY PublishedAt DESC LIMIT 10");
    echo "\nLatest posts:\n";
    while ($row = $result->fetch()) {
        echo sprintf("  [%d] %s - Status: %s - Published: %s\n",
            $row['Id'],
            substr($row['Title'], 0, 50),
            $row['Status'],
            $row['PublishedAt']
        );
    }

    // Check status breakdown
    echo "\nStatus Breakdown:\n";
    $result = $db->query("SELECT Status, COUNT(*) as cnt FROM posts GROUP BY Status");
    while ($row = $result->fetch()) {
        echo "  {$row['Status']}: {$row['cnt']}\n";
    }
} else {
    echo "⚠ No posts found in database\n";
}

// Also check if there are any tables with 'posts' in the name
echo "\n\nSearching for posts-related tables:\n";
$result = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%post%'");
while ($row = $result->fetch()) {
    $table = $row['TABLE_NAME'];
    $count = $db->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch()['cnt'];
    echo "  $table: $count records\n";
}
