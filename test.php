<?php
// Display all PHP errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if SQLite3 extension is loaded
echo "SQLite3 extension loaded: " . (extension_loaded('sqlite3') ? 'Yes' : 'No') . "<br>";

// Check if GD extension is loaded
echo "GD extension loaded: " . (extension_loaded('gd') ? 'Yes' : 'No') . "<br>";

// Check directory permissions
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current script directory: " . __DIR__ . "<br>";

// Check if database directory is writable
$db_dir = __DIR__ . '/database';
echo "Database directory exists: " . (file_exists($db_dir) ? 'Yes' : 'No') . "<br>";
echo "Database directory is writable: " . (is_writable($db_dir) ? 'Yes' : 'No') . "<br>";

// Check if uploads directories are writable
$uploads_dir = __DIR__ . '/uploads';
$thumbnails_dir = __DIR__ . '/uploads/thumbnails';

if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
    echo "Created uploads directory<br>";
}

if (!file_exists($thumbnails_dir)) {
    mkdir($thumbnails_dir, 0755, true);
    echo "Created thumbnails directory<br>";
}

echo "Uploads directory is writable: " . (is_writable($uploads_dir) ? 'Yes' : 'No') . "<br>";
echo "Thumbnails directory is writable: " . (is_writable($thumbnails_dir) ? 'Yes' : 'No') . "<br>";

// Try to create a test SQLite database
try {
    $db_path = $db_dir . '/test.db';
    $db = new SQLite3($db_path);
    $db->exec('CREATE TABLE IF NOT EXISTS test (id INTEGER PRIMARY KEY, name TEXT)');
    $db->exec('INSERT INTO test (name) VALUES ("Test entry")');
    $db->close();
    echo "Successfully created and wrote to test database<br>";
} catch (Exception $e) {
    echo "Error with SQLite: " . $e->getMessage() . "<br>";
}

echo "PHP version: " . phpversion();
?>
