<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Connect to the database
$db = db_connect();

// Set user ID 1 as admin (assuming this is your user)
$db->exec("UPDATE users SET is_admin = 1 WHERE id = 1");

// Show all users and their admin status
echo "<h3>Users in the system:</h3>";
echo "<ul>";
$all_users = $db->query("SELECT id, username, email, is_admin FROM users");
while ($row = $all_users->fetchArray(SQLITE3_ASSOC)) {
    $admin_status = $row['is_admin'] ? 'YES' : 'No';
    echo "<li>ID: {$row['id']} - {$row['username']} ({$row['email']}) - Admin: {$admin_status}</li>";
}
echo "</ul>";

echo "<p>User with ID 1 has been set as an administrator.</p>";
echo "<p>You can now <a href='admin/index.php'>access the admin panel</a> (make sure you're logged in as this user).</p>";

// Close the database connection
$db->close();
?>