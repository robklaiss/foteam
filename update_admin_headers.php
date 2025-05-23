<?php
$adminDir = __DIR__ . '/admin';
$files = [
    'create_marathon.php',
    'edit_marathon.php',
    'manage_marathons.php',
    'manage_photographers.php',
    'index.php'
];

$headerReplacement = '// Include header
require_once dirname(__DIR__) . \'/includes/header.php\';';

foreach ($files as $file) {
    $filePath = $adminDir . '/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        // Update the header include to use absolute path
        $content = preg_replace(
            '/\/\/ Include header\s+include\s+[\'\"]\.\.\/includes\/header\.php[\'\"]\s*;/',
            $headerReplacement,
            $content
        );
        
        file_put_contents($filePath, $content);
        echo "Updated header in $file\n";
    } else {
        echo "File not found: $file\n";
    }
}

echo "Header updates complete!\n";
