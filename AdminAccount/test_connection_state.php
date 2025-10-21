<?php
require_once '../db_connect.php';

echo "=== GOOGLE DRIVE CONNECTION STATE ===\n\n";

$result = $conn->query("SELECT setting_name, setting_value FROM system_settings WHERE setting_name LIKE 'google_drive_%' ORDER BY setting_name");

if ($result->num_rows === 0) {
    echo "❌ NO GOOGLE DRIVE SETTINGS FOUND IN DATABASE\n";
} else {
    while ($row = $result->fetch_assoc()) {
        $name = $row['setting_name'];
        $value = $row['setting_value'];
        
        if (strlen($value) > 50) {
            $display = substr($value, 0, 50) . '... (' . strlen($value) . ' chars)';
        } else {
            $display = $value;
        }
        
        echo "$name: $display\n";
    }
}

echo "\n=== END ===\n";
