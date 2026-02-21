<?php
echo "<pre>";
$projectRoot = dirname(__DIR__);
$path1 = $projectRoot . '/public/lib/images/site/2026/NGN-Emblem-Light.png';
$path2 = $projectRoot . '/lib/images/site/2026/NGN-Emblem-Light.png';

echo "Checking Symlinked Path: $path1
";
echo "  Result: " . (file_exists($path1) ? "FOUND" : "NOT FOUND") . "
";

echo "
Checking Direct Path: $path2
";
echo "  Result: " . (file_exists($path2) ? "FOUND" : "NOT FOUND") . "
";

$path3 = $projectRoot . '/public/lib/images/users/coldwards/coldward-logo-1.jpg';
echo "
Checking Artist Path: $path3
";
echo "  Result: " . (file_exists($path3) ? "FOUND" : "NOT FOUND") . "
";
echo "</pre>";
