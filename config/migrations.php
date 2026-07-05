<?php
/**
 * Database Migrations
 * Run this file once to apply all pending migrations
 * Access via: http://localhost/hourawebsystem/config/migrations.php
 */

require_once 'database.php';

// Check if this is being accessed directly via web
$runMigrations = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['run']) && $_GET['run'] === 'true') {
    // For security, you might want to add IP checking or auth
    $runMigrations = true;
}

$migrations = [
    // Migration 1: Add last_login column to users table
    [
        'name' => 'Add last_login column to users table',
        'sql' => "ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER updated_at"
    ],
    // Migration 2: Add suspended_until column to users table
    [
        'name' => 'Add suspended_until column to users table',
        'sql' => "ALTER TABLE users ADD COLUMN suspended_until DATETIME NULL AFTER status"
    ]
];

$results = [];

if ($runMigrations) {
    foreach ($migrations as $migration) {
        try {
            $conn->exec($migration['sql']);
            $results[] = [
                'status' => 'success',
                'migration' => $migration['name'],
                'message' => 'Migration completed successfully'
            ];
        } catch (PDOException $e) {
            // Check if column already exists
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                $results[] = [
                    'status' => 'already_exists',
                    'migration' => $migration['name'],
                    'message' => 'Column already exists'
                ];
            } else {
                $results[] = [
                    'status' => 'error',
                    'migration' => $migration['name'],
                    'message' => $e->getMessage()
                ];
            }
        }
    }
    
    // Output results
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'results' => $results
    ], JSON_PRETTY_PRINT);
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Migrations</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; }
            .button { 
                background: #2196F3; 
                color: white; 
                padding: 10px 20px; 
                border: none; 
                border-radius: 5px; 
                cursor: pointer;
                font-size: 16px;
            }
            .button:hover { background: #1976D2; }
            .warning { 
                background: #fff3cd; 
                border: 1px solid #ffc107; 
                padding: 10px; 
                border-radius: 5px;
                margin: 20px 0;
            }
            ul { margin: 10px 0; }
            li { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Database Migrations</h1>
            <div class="warning">
                <strong>⚠️ Important:</strong> Click the button below to apply pending database migrations.
            </div>
            
            <h3>Pending Migrations:</h3>
            <ul>
                <?php foreach ($migrations as $migration): ?>
                    <li><?php echo htmlspecialchars($migration['name']); ?></li>
                <?php endforeach; ?>
            </ul>
            
            <button class="button" onclick="runMigrations()">Run Migrations</button>
            
            <div id="result" style="margin-top: 20px;"></div>
        </div>
        
        <script>
            function runMigrations() {
                if (!confirm('Are you sure you want to run the migrations? This will modify your database.')) {
                    return;
                }
                
                const button = document.querySelector('.button');
                button.disabled = true;
                button.textContent = 'Running...';
                
                fetch('migrations.php?run=true')
                    .then(response => response.json())
                    .then(data => {
                        let html = '<h3>Migration Results:</h3>';
                        data.results.forEach(result => {
                            const color = result.status === 'success' ? 'green' : 
                                        result.status === 'already_exists' ? 'blue' : 'red';
                            html += `<div style="color: ${color}; padding: 10px; margin: 5px 0; border: 1px solid ${color}; border-radius: 3px;">
                                <strong>${result.migration}</strong><br>
                                ${result.message}
                            </div>`;
                        });
                        document.getElementById('result').innerHTML = html;
                        button.disabled = false;
                        button.textContent = 'Run Migrations';
                    })
                    .catch(error => {
                        document.getElementById('result').innerHTML = `<div style="color: red;">Error: ${error}</div>`;
                        button.disabled = false;
                        button.textContent = 'Run Migrations';
                    });
            }
        </script>
    </body>
    </html>
    <?php
}
