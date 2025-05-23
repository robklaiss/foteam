<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo '<h2>Database User Check</h2>';

// Connect to database
$db = db_connect();

// List all users (without passwords)
echo '<h3>All Users in Database:</h3>';
$result = $db->query("SELECT id, username, email, is_admin FROM users");

echo '<table border="1" cellpadding="5">';
echo '<tr><th>ID</th><th>Username</th><th>Email</th><th>Is Admin</th></tr>';

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo '<tr>';
    echo '<td>' . $row['id'] . '</td>';
    echo '<td>' . $row['username'] . '</td>';
    echo '<td>' . $row['email'] . '</td>';
    echo '<td>' . ($row['is_admin'] ? 'Yes' : 'No') . '</td>';
    echo '</tr>';
}

echo '</table>';

// Close connection
$db->close();

echo '<p><a href="login_test.php">Go to login test</a></p>';
?>
