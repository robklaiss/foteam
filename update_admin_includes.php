<?php
$adminDir = __DIR__ . '/admin';
$files = [
    'create_marathon.php',
    'edit_marathon.php',
    'manage_marathons.php',
    'manage_photographers.php',
    'marathon_actions.php',
    'marathon_photographer_actions.php',
    'photographer_actions.php'
];

foreach ($files as $file) {
    $filePath = $adminDir . '/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        // Skip if already has bootstrap include
        if (strpos($content, 'bootstrap.php') !== false) {
            echo "Skipping $file - already has bootstrap include\n";
            continue;
        }
        
        // Add bootstrap include after opening PHP tag
        $content = preg_replace(
            '/<\?php\s*require_once\s*[\'\"]\.\.\/includes\/config\.php[\'\"]\s*;/',
            '<?php\n// Include bootstrap first to configure environment and settings\nrequire_once dirname(__DIR__) . \'/includes/bootstrap.php\';\n\n// Include config and functions\nrequire_once dirname(__DIR__) . \'/includes/config.php\';',
            $content,
            1
        );
        
        file_put_contents($filePath, $content);
        echo "Updated $file\n";
    } else {
        echo "File not found: $file\n";
    }
}

echo "Update complete!\n";
