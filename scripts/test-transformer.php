<?php
/**
 * Test LegacySqlTransformer
 */

require_once __DIR__ . '/../lib/bootstrap.php';

use NGN\Lib\DB\LegacySqlTransformer;

// Test data - a small sample INSERT from the legacy SQL
$testStatement = <<<'SQL'
INSERT INTO `posts` (`Id`, `Title`, `Slug`, `Body`, `Tags`, `Summary`, `TypeId`, `Published`, `Featured`, `Image`, `Created`, `Updated`, `Author`, `PublishedDate`, `IsUser`) VALUES
(1, 'Test Post One', 'test-post-one', '<p>This is a test post</p>', 'test,article', 'A summary', 1, 1, 0, NULL, '2025-01-01 10:00:00', '2025-01-02 15:30:00', 'John Doe', '2025-01-01 10:00:00', 0),
(2, 'Test Post Two', 'test-post-two', '<p>Another test</p>', 'news', 'Summary here', 2, 0, 1, 'image.jpg', '2025-01-03 12:00:00', '2025-01-04 14:00:00', 'Jane Doe', NULL, 1)
SQL;

$transformer = new LegacySqlTransformer();

echo "Original Statement:\n";
echo $testStatement . "\n\n";

$transformed = $transformer->transformStatement($testStatement, 'posts');

if ($transformed) {
    echo "Transformed Statement:\n";
    echo $transformed . "\n\n";
    echo "✓ Transformation successful!\n";
} else {
    echo "✗ Transformation failed!\n";
}
