<?php
require_once __DIR__ . '/../lib/bootstrap.php';
use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$db = ConnectionFactory::read($config);

$stmt = $db->prepare("SELECT * FROM agreement_templates WHERE slug = 'erik-baker-advisor' LIMIT 1");
$stmt->execute();
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if ($template) {
    echo "=== ERIK BAKER AGREEMENT CONTENT ===
";
    echo "ID: " . $template['id'] . "
";
    echo "Name: " . $template['name'] . "
";
    echo "Version: " . $template['version'] . "
";
    echo "Body:
";
    echo $template['body'] . "
";
    echo "=== END CONTENT ===
";
} else {
    echo "Template 'erik-baker-advisor' not found.
";
}
