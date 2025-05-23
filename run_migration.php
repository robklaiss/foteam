<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Set up appropriate headers
header('Content-Type: text/html; charset=UTF-8');

// Function to run the migration
function run_migration() {
    try {
        $db = db_connect();
        
        // Read the migration SQL file
        $migration_file = __DIR__ . '/../database/migrations/add_photographer_marathons_table.sql';
        $migration_sql = file_get_contents($migration_file);
        
        if (!$migration_sql) {
            throw new Exception("Could not read migration file: $migration_file");
        }
        
        // Execute the migration
        $result = $db->exec($migration_sql);
        
        if ($result === false) {
            throw new Exception("Migration failed: " . $db->lastErrorMsg());
        }
        
        $db->close();
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// Run the migration and store the result
$migration_result = run_migration();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h1>Database Migration</h1>
        
        <div class="card mb-4">
            <div class="card-header">Migration Status</div>
            <div class="card-body">
                <?php if ($migration_result === true): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">Migration Successful!</h4>
                        <p>The <code>photographer_marathons</code> table has been created successfully.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Migration Failed</h4>
                        <p>Error: <?php echo $migration_result; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Next Steps</div>
            <div class="card-body">
                <p>Now you can:</p>
                <ul>
                    <li>Return to <a href="admin/manage_marathons.php">Manage Marathons</a> to verify the issue is resolved.</li>
                    <li>Assign photographers to marathons through the administrator interface.</li>
                </ul>
                <p class="mt-3">
                    <a href="index.php" class="btn btn-primary">Return to Homepage</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
