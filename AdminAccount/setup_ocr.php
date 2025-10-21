<?php
/**
 * OCR Setup Guide for Document Validation
 * This file helps administrators set up OCR capabilities
 */

echo "<h2>OCR Setup Guide for Document Validation</h2>";

echo "<h3>Required Software:</h3>";
echo "<ol>";
echo "<li><strong>Tesseract OCR</strong> - For image text extraction</li>";
echo "<li><strong>Poppler Utils</strong> - For PDF text extraction</li>";
echo "</ol>";

echo "<h3>Windows Installation:</h3>";
echo "<h4>1. Install Tesseract OCR:</h4>";
echo "<ul>";
echo "<li>Download from: <a href='https://github.com/UB-Mannheim/tesseract/wiki'>https://github.com/UB-Mannheim/tesseract/wiki</a></li>";
echo "<li>Install to default location (usually C:\\Program Files\\Tesseract-OCR\\)</li>";
echo "<li>Add to PATH: C:\\Program Files\\Tesseract-OCR\\</li>";
echo "</ul>";

echo "<h4>2. Install Poppler Utils:</h4>";
echo "<ul>";
echo "<li>Download from: <a href='https://github.com/oschwartz10612/poppler-windows/releases/'>https://github.com/oschwartz10612/poppler-windows/releases/</a></li>";
echo "<li>Extract to C:\\poppler\\</li>";
echo "<li>Add to PATH: C:\\poppler\\Library\\bin\\</li>";
echo "</ul>";

echo "<h3>Testing Installation:</h3>";

// Test Tesseract
$tesseractAvailable = false;
if (function_exists('exec')) {
    exec('tesseract --version 2>&1', $output, $returnCode);
    if ($returnCode === 0) {
        $tesseractAvailable = true;
        echo "<p style='color: green;'>✓ Tesseract OCR is available</p>";
    } else {
        echo "<p style='color: red;'>✗ Tesseract OCR is not available</p>";
    }
}

// Test Poppler
$popplerAvailable = false;
if (function_exists('exec')) {
    exec('pdftotext -v 2>&1', $output, $returnCode);
    if ($returnCode === 0) {
        $popplerAvailable = true;
        echo "<p style='color: green;'>✓ Poppler Utils (pdftotext) is available</p>";
    } else {
        echo "<p style='color: red;'>✗ Poppler Utils (pdftotext) is not available</p>";
    }
}

if (!$tesseractAvailable || !$popplerAvailable) {
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ffcccc;'>";
    echo "<strong>Note:</strong> Without OCR tools, the system will fall back to basic filename validation. ";
    echo "For full content-based validation, please install the required software above.";
    echo "</div>";
}

echo "<h3>Alternative: Docker Installation</h3>";
echo "<p>For easier setup, you can use Docker with pre-installed OCR tools:</p>";
echo "<pre>";
echo "docker pull tesseractshadow/tesseract4re\n";
echo "# Or create a custom Dockerfile with required tools";
echo "</pre>";
?>