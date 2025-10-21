<?php
require_once '../db_connect.php';
$conn->query("UPDATE archival_settings SET last_run = NOW() WHERE id = 1");
echo "Last run timestamp updated\n";
?>
