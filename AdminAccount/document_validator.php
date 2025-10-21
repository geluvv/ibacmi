<?php
// Document validation function for server-side validation
function validateDocumentType($file, $docType) {
    // UPDATED FOR SECURITY: Immediate rejection for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'confidence' => 0,
            'message' => 'Upload error: ' . getUploadErrorMessage($file['error'])
        ];
    }

    // UPDATED FOR SECURITY: Strict file size and type checks
    $maxFileSize = 10 * 1024 * 1024; // 10MB limit
    if ($file['size'] > $maxFileSize) {
        return [
            'valid' => false,
            'confidence' => 0,
            'message' => 'File size exceeds 10MB limit'
        ];
    }

    $allowedMimeTypes = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'doc' => ['application/msword'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'bmp' => ['image/bmp'],
        'webp' => ['image/webp'],
        'tiff' => ['image/tiff']
    ];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowedMimeTypes[$fileExt]) || !in_array($file['type'], $allowedMimeTypes[$fileExt])) {
        return [
            'valid' => false,
            'confidence' => 0,
            'message' => 'Invalid file type: ' . $file['type']
        ];
    }

    // UPDATED FOR SECURITY: For images, verify integrity
    if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'])) {
        if (!getimagesize($file['tmp_name'])) {
            return [
                'valid' => false,
                'confidence' => 0,
                'message' => 'Invalid or corrupted image file'
            ];
        }
    }

    // UPDATED FOR SECURITY: Keyword mappings - stricter rules
    $KEYWORD_MAPPINGS = [
        'card138' => [
            'primary_keywords' => ['card', 'report', 'grade', 'school', 'subjects', 'form 138', 'form 137', '138', 'student', 'record'],
            'secondary_keywords' => ['enrollment', 'semester', 'year', 'name', 'course'],
            'negative_keywords' => ['birth certificate', 'marriage certificate', 'transcript'],
            'min_primary' => 1,
            'min_total' => 1,
            'document_name' => 'Card 138'
        ],
        'moral' => [
            'primary_keywords' => ['good moral','moral character','character certificate','good conduct','moral','character','conduct'],
            'secondary_keywords' => ['behavior','discipline','ethics','reputation','good','certificate'],
            'negative_keywords' => ['birth','marriage','transcript'],
            'min_primary' => 1,
            'min_total' => 1,
            'document_name' => 'Certificate of Good Moral'
        ],
        'birth' => [
            'primary_keywords' => ['birth certificate','birth record','civil registry','psa','birth','certificate', 'live birth', 'certificate of live birth'],
            'secondary_keywords' => ['born','registry','civil','government'],
            'negative_keywords' => ['marriage','moral','transcript'],
            'min_primary' => 1,
            'min_total' => 1,
            'document_name' => 'PSA Birth Certificate'
        ],
        'marriage' => [
            'primary_keywords' => ['marriage certificate','certificate of marriage','marriage contract','marriage','wedding'],
            'secondary_keywords' => ['married','spouse','civil registry'],
            'negative_keywords' => ['birth','moral','transcript'],
            'min_primary' => 1,
            'min_total' => 1,
            'document_name' => 'PSA Marriage Certificate'
        ],
        'tor' => [
            'primary_keywords' => ['transcript of records','transcript','tor','academic record'],
            'secondary_keywords' => ['grades','subjects','units','semester','gpa','course'],
            'negative_keywords' => ['birth','marriage','moral','dismissal'],
            'min_primary' => 1,
            'min_total' => 2,
            'document_name' => 'Transcript of Records'
        ],
        'honorable' => [
            'primary_keywords' => ['honorable dismissal','transfer credential','dismissal'],
            'secondary_keywords' => ['good standing','transfer','clearance','credential'],
            'negative_keywords' => ['birth','marriage','moral','transcript'],
            'min_primary' => 1,
            'min_total' => 1,
            'document_name' => 'Honorable Dismissal'
        ],
        'gradeslip' => [
            'primary_keywords' => ['grade slip','report card','grade report','academic report','grades','grade','slip'],
            'secondary_keywords' => ['semester','final grade','academic performance','subjects','term','quarter'],
            'negative_keywords' => ['birth','marriage','moral','dismissal'],
            'min_primary' => 1,
            'min_total' => 1,
            'document_name' => 'Grade Slip'
        ],
        'id' => [
            'skip_validation' => true,
            'document_name' => '2x2 Picture'
        ]
    ];

    $config = $KEYWORD_MAPPINGS[$docType] ?? null;
    if (!$config) {
        return [
            'valid' => true,
            'confidence' => 70,
            'message' => 'No validation rules configured for this document type'
        ];
    }

    if (isset($config['skip_validation']) && $config['skip_validation']) {
        // For photos/IDs, just verify it's an image
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'])) {
            return [
                'valid' => true,
                'confidence' => 100,
                'message' => 'Image file accepted'
            ];
        } else {
            return [
                'valid' => false,
                'confidence' => 0,
                'message' => 'Expected image file for ' . $config['document_name']
            ];
        }
    }

    // UPDATED: Check filename FIRST before text extraction (more lenient approach)
    $filename = str_replace(' ', '', strtolower($file['name']));
    $foundInFilename = [];
    
    foreach ($config['primary_keywords'] as $keyword) {
        $cleanKeyword = str_replace(' ', '', strtolower($keyword));
        if (strpos($filename, $cleanKeyword) !== false) {
            $foundInFilename[] = $keyword;
        }
    }
    
    // If filename contains primary keywords, accept it immediately (UPDATED: More lenient)
    if (!empty($foundInFilename)) {
        return [
            'valid' => true,
            'confidence' => 80,
            'message' => 'Valid ' . $config['document_name'] . ' - filename match: ' . implode(', ', $foundInFilename)
        ];
    }

    // UPDATED FOR SECURITY: Extract text and validate content
    $text = extractTextFromFile($file);
    $text = normalizeText($text);

    // UPDATED: More lenient minimum text length (reduced from 50 to 15)
    if (strlen($text) < 15) {
        // UPDATED: Accept the file anyway with lower confidence (more lenient)
        return [
            'valid' => true,
            'confidence' => 60,
            'message' => 'Accepted ' . $config['document_name'] . ' - text extraction may have failed, will be manually reviewed'
        ];
    }

    // UPDATED: Check for primary keywords in extracted text
    $foundPrimary = [];
    foreach ($config['primary_keywords'] as $keyword) {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        if (preg_match($pattern, $text)) {
            $foundPrimary[] = $keyword;
        }
    }

    $foundSecondary = [];
    foreach ($config['secondary_keywords'] ?? [] as $keyword) {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        if (preg_match($pattern, $text)) {
            $foundSecondary[] = $keyword;
        }
    }

    $foundNegative = [];
    foreach ($config['negative_keywords'] ?? [] as $keyword) {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        if (preg_match($pattern, $text)) {
            $foundNegative[] = $keyword;
        }
    }

    $totalKeywords = count($foundPrimary) + count($foundSecondary);
    $hasPrimary = count($foundPrimary) >= ($config['min_primary'] ?? 1);
    $hasTotal = $totalKeywords >= ($config['min_total'] ?? 1);
    $hasNegative = count($foundNegative) > 0;

    if ($hasNegative && count($foundPrimary) == 0) {
        return [
            'valid' => false,
            'confidence' => 20,
            'message' => 'Wrong document type detected - contains: ' . implode(', ', $foundNegative)
        ];
    }

    // UPDATED: More lenient validation - accept if we found ANY keywords
    if (!$hasPrimary && $totalKeywords == 0) {
        // UPDATED: Still accept with warning (more lenient)
        return [
            'valid' => true,
            'confidence' => 50,
            'message' => 'Accepted ' . $config['document_name'] . ' - no keywords found, will be manually reviewed'
        ];
    }

    // UPDATED: Calculate confidence
    $confidence = 40; // Start at 40 instead of 50
    $confidence += min(40, count($foundPrimary) * 20);
    $confidence += min(20, count($foundSecondary) * 10);
    $confidence += min(10, floor(min(100, strlen($text) / 100)));
    $confidence = min(100, $confidence);

    // UPDATED: More lenient acceptance (accept if confidence >= 50 instead of 70)
    $isValid = $confidence >= 50 || $hasPrimary;

    $message = $isValid
        ? "Valid {$config['document_name']} - found: " . implode(', ', array_merge($foundPrimary, $foundSecondary))
        : "Invalid {$config['document_name']} - insufficient keywords";

    return [
        'valid' => $isValid,
        'confidence' => $confidence,
        'message' => $message
    ];
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File exceeds upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File exceeds MAX_FILE_SIZE directive in HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

function extractTextFromFile($file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!file_exists($file['tmp_name'])) {
        return '';
    }

    switch ($ext) {
        case 'pdf':
            return extractTextFromPDF($file['tmp_name']);
        case 'docx':
            return extractTextFromDOCX($file['tmp_name']);
        case 'doc':
            return extractTextFromDOC($file['tmp_name']);
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp':
        case 'webp':
        case 'tiff':
            return extractTextFromImage($file['tmp_name']);
        default:
            return '';
    }
}

function extractTextFromPDF($filePath) {
    // Simple PDF text extraction using pdftotext if available
    $text = '';
    if (function_exists('shell_exec') && is_executable('/usr/bin/pdftotext')) {
        $text = shell_exec("/usr/bin/pdftotext -layout " . escapeshellarg($filePath) . " -");
    }

    // Fallback: try to read raw content
    if (empty($text)) {
        $content = file_get_contents($filePath);
        // Extract readable text from PDF (very basic)
        if (preg_match_all('/\(([^)]+)\)/i', $content, $matches)) {
            $text = implode(' ', $matches[1]);
        }
    }

    return $text;
}

function extractTextFromDOCX($filePath) {
    // Simple DOCX text extraction
    $zip = new ZipArchive();
    if ($zip->open($filePath) === true) {
        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xmlContent) {
            $text = strip_tags($xmlContent);
            return $text;
        }
    }
    return '';
}

function extractTextFromDOC($filePath) {
    // For older DOC files, try to extract text
    $content = file_get_contents($filePath);
    // This is a very basic extraction - in practice, you'd need a proper DOC parser
    $text = preg_replace('/[^\x20-\x7E]/', ' ', $content);
    return $text;
}

function extractTextFromImage($filePath) {
    // For images, try to extract text from filename or return filename as text
    $filename = basename($filePath);
    $filename = pathinfo($filename, PATHINFO_FILENAME);

    // Return filename as potential text content
    return strtolower(str_replace(['_', '-', '.'], ' ', $filename));
}

function normalizeText($text) {
    if (empty($text)) return '';

    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);

    // Common document type normalizations
    $text = preg_replace('/form\s*1?38/i', 'form 138', $text);
    $text = preg_replace('/\btor\b/i', 'transcript of records', $text);
    $text = preg_replace('/\bpsa\b/i', 'psa', $text);

    return strtolower(trim($text));
}
?>
