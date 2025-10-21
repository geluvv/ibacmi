<?php
echo "<h2>PHPMailer Installation Check</h2>";

// Check current directory
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";

// Check if vendor/autoload.php exists
$vendorPath = __DIR__ . '/vendor/autoload.php';
echo "<p>Vendor autoload: " . ($vendorPath) . " - ";
echo file_exists($vendorPath) ? "✓ EXISTS" : "✗ NOT FOUND";
echo "</p>";

// Check if PHPMailer folder exists
$phpmailerFolder = __DIR__ . '/PHPMailer';
echo "<p>PHPMailer folder: " . ($phpmailerFolder) . " - ";
echo file_exists($phpmailerFolder) ? "✓ EXISTS" : "✗ NOT FOUND";
echo "</p>";

// Check if PHPMailer/src folder exists
$phpmailerSrc = __DIR__ . '/PHPMailer/src';
echo "<p>PHPMailer/src folder: " . ($phpmailerSrc) . " - ";
echo file_exists($phpmailerSrc) ? "✓ EXISTS" : "✗ NOT FOUND";
echo "</p>";

// Check specific files
$files = [
    'PHPMailer/src/PHPMailer.php',
    'PHPMailer/src/Exception.php',
    'PHPMailer/src/SMTP.php'
];

echo "<h3>Required Files:</h3><ul>";
foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    echo "<li>" . $file . " - ";
    echo file_exists($fullPath) ? "✓ EXISTS" : "✗ NOT FOUND";
    echo "</li>";
}
echo "</ul>";

// List contents of PHPMailer folder if it exists
if (file_exists($phpmailerFolder)) {
    echo "<h3>Contents of PHPMailer folder:</h3><ul>";
    $contents = scandir($phpmailerFolder);
    foreach ($contents as $item) {
        if ($item != '.' && $item != '..') {
            $itemPath = $phpmailerFolder . '/' . $item;
            echo "<li>" . $item . " (" . (is_dir($itemPath) ? "FOLDER" : "FILE") . ")</li>";
        }
    }
    echo "</ul>";
    
    // If src folder exists, list its contents
    if (file_exists($phpmailerSrc)) {
        echo "<h3>Contents of PHPMailer/src folder:</h3><ul>";
        $srcContents = scandir($phpmailerSrc);
        foreach ($srcContents as $item) {
            if ($item != '.' && $item != '..') {
                echo "<li>" . $item . "</li>";
            }
        }
        echo "</ul>";
    }
}
?>