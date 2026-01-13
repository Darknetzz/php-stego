<?php

// Directory for storing uploaded files and analysis results
define('UPLOAD_DIR', 'uploads');

// Directory for local binaries (fallback when tools aren't in PATH)
define('BIN_DIR', __DIR__ . '/bin');

// Auto-delete files after this many seconds (default: 10 minutes)
define('DELETE_AFTER', 600);

// Allowed file extensions for upload
define('ALLOWED_FILE_EXTENSIONS', [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'bmp',
    'webp',
    'tiff',
    'tif',
]);

// Maximum file size in bytes (default: 10MB)
define('MAX_FILESIZE_BYTES', 10485760);

// Enable/disable specific analysis tools
define('ENABLE_STEGOVERITAS', true);
define('ENABLE_FOREMOST', true);
define('ENABLE_STEGHIDE', true);
define('ENABLE_OUTGUESS', true);
define('ENABLE_STRINGS', true);
define('ENABLE_EXIV2', true);
define('ENABLE_EXIFTOOL', true);
define('ENABLE_BINWALK', true);
define('ENABLE_XXD', true);

// Initialize array for errors
$errors = [];
?>