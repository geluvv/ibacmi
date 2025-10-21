<?php
header('Content-Type: application/json');

try {
    // Test if db_connect.php exists
    $dbPath = '../db_connect.php';
    
    if (!file_exists($dbPath)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'db_connect.php not found',
            'path_tried' => realpath($dbPath),
            'current_dir' => __DIR__
        ]);
        exit;
    }
    
    require_once $dbPath;
    
    // Check what variables are available
    $availableVars = [];
    if (isset($pdo)) $availableVars[] = 'pdo';
    if (isset($conn)) $availableVars[] = 'conn';
    if (isset($connection)) $availableVars[] = 'connection';
    if (isset($mysqli)) $availableVars[] = 'mysqli';
    
    if (empty($availableVars)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No database connection variable found',
            'available_variables' => array_keys(get_defined_vars())
        ]);
        exit;
    }
    
    // Try to use the connection
    $db = $pdo ?? $conn ?? $connection ?? $mysqli;
    
    // Test query
    if ($db instanceof PDO) {
        $result = $db->query("SHOW TABLES");
        $tables = $result->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Database connection works',
            'connection_type' => 'PDO',
            'available_vars' => $availableVars,
            'tables' => $tables
        ]);
    } else if ($db instanceof mysqli) {
        echo json_encode([
            'status' => 'error',
            'message' => 'MySQLi connection detected. Need to convert code to MySQLi.',
            'connection_type' => 'MySQLi'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unknown connection type',
            'type' => gettype($db)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>