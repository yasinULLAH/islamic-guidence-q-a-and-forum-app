<?php
require_once 'config.php'; // For DB_PATH

echo "Attempting to initialize database...\n";

try {
    // Delete existing database file if it exists
    if (file_exists(DB_PATH)) {
        unlink(DB_PATH);
        echo "Existing database file deleted: " . DB_PATH . "\n";
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/database.sql');

    if ($sql === false) {
        throw new Exception("Failed to read database.sql file.");
    }

    // Execute all SQL statements
    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "SQL Error: " . $e->getMessage() . " in statement: " . substr($statement, 0, 100) . "...\n";
                throw $e; // Re-throw to stop execution on first error
            }
        }
    }

    echo "Database initialized successfully!\n";

    // Verify tables
    $tables_query = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $tables_query->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables created: " . implode(', ', $tables) . "\n";

} catch (Exception $e) {
    echo "Database initialization failed: " . $e->getMessage() . "\n";
    error_log("Database initialization script error: " . $e->getMessage());
}
?>
