<?php
// Enable error reporting for debugging - MUST be at the very top
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Disable output buffering so errors are immediately visible
if (ob_get_level()) {
    ob_end_clean();
}

require_once('config.php');
require_once('functions.php');

// Handle tool availability check - MUST be before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'checkTools') {
    header('Content-Type: application/json; charset=utf-8');
    
    // Function to check if a tool is available
    $checkTool = function($toolName) {
        $checkCommand = "which " . escapeshellarg($toolName) . " 2>/dev/null";
        $cmdPathResult = shell_exec($checkCommand);
        $cmdPath = $cmdPathResult !== null ? trim($cmdPathResult) : '';
        
        // If not found in PATH, check bin/ folder as fallback
        if (empty($cmdPath)) {
            $localBinPath = BIN_DIR . '/' . $toolName;
            if (file_exists($localBinPath) && is_executable($localBinPath)) {
                $cmdPath = $localBinPath;
            } else {
                return ['available' => false, 'error' => "Tool '$toolName' not found. Please install it to use this analysis module."];
            }
        }
        
        // Verify the tool is executable
        if (!is_executable($cmdPath)) {
            return ['available' => false, 'error' => "Tool '$toolName' found but is not executable"];
        }
        
        // Try a simple test to check for library loading errors (non-blocking)
        // Check if timeout command is available, otherwise skip timeout
        $hasTimeout = shell_exec('which timeout 2>/dev/null') !== null;
        $timeoutPrefix = $hasTimeout ? 'timeout 2 ' : '';
        $testCommand = $timeoutPrefix . escapeshellarg($cmdPath) . " --version 2>&1 || " . 
                      $timeoutPrefix . escapeshellarg($cmdPath) . " -v 2>&1 || " . 
                      $timeoutPrefix . escapeshellarg($cmdPath) . " -h 2>&1 || echo 'ok'";
        $testOutput = shell_exec($testCommand);
        
        // Check for library loading errors (only if we got output)
        if ($testOutput && (
            strpos($testOutput, 'error while loading shared libraries') !== false || 
            strpos($testOutput, 'cannot open shared object file') !== false ||
            strpos($testOutput, 'No such file or directory') !== false
        )) {
            return ['available' => false, 'error' => "Tool '$toolName' found but cannot run due to missing library dependencies"];
        }
        
        return ['available' => true, 'path' => $cmdPath];
    };
    
    // Check all tools that have checkboxes
    $toolsToCheck = ['stegoveritas', 'foremost', 'steghide', 'strings'];
    $results = [];
    $unavailableTools = [];
    
    foreach ($toolsToCheck as $tool) {
        $toolResult = $checkTool($tool);
        $results[$tool] = $toolResult;
        if (!($toolResult['available'] ?? false)) {
            $unavailableTools[] = $tool;
        }
    }
    
    // Generate installation commands for unavailable tools
    $installInfo = [];
    if (!empty($unavailableTools)) {
        $installInfo = installTools($unavailableTools);
    }
    
    // Handle error case
    $installCommands = [];
    $packageManager = 'unknown';
    if (!empty($installInfo) && !isset($installInfo['error'])) {
        $installCommands = $installInfo['commands'] ?? [];
        $packageManager = $installInfo['packageManager'] ?? 'unknown';
    }
    
    echo json_encode([
        'tools' => $results,
        'installCommands' => $installCommands,
        'packageManager' => $packageManager,
        'toolKeys' => $unavailableTools
    ], JSON_PRETTY_PRINT);
    exit;
}

// Handle auto-install action - MUST be before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'install') {
    // Ensure JSON response headers are sent first
    header('Content-Type: application/json; charset=utf-8');
    
    // Check if this is a POST request with confirmation
    $input = json_decode(file_get_contents('php://input'), true);
    $confirm = $_POST['confirm'] ?? $input['confirm'] ?? false;
    
    if (!$confirm) {
        echo json_encode(['success' => false, 'message' => 'Confirmation required']);
        exit;
    }
    
    $tools = [];
    if (isset($_GET['tools'])) {
        $tools = json_decode($_GET['tools'], true);
    } elseif (isset($input['tools'])) {
        $tools = $input['tools'];
    }
    
    if (empty($tools) || !is_array($tools)) {
        echo json_encode(['success' => false, 'message' => 'No tools specified']);
        exit;
    }
    
    $installed = [];
    $failed = [];
    
    // Map display names to tool keys
    $toolMapping = [
        'stegoveritas' => 'stegoveritas',
        'foremost' => 'foremost',
        'steghideInfo' => 'steghide',
        'steghideE' => 'steghide',
        'outguess' => 'outguess',
        'strings' => 'strings',
        'exiv2' => 'exiv2',
        'exif' => 'exiftool',
        'binwalk' => 'binwalk',
        'xxd' => 'xxd'
    ];
    
    foreach ($tools as $tool) {
        $toolKey = $toolMapping[$tool] ?? strtolower($tool);
        try {
            $result = attemptAutoInstall($toolKey);
            if ($result['success'] ?? false) {
                $installed[] = $tool;
            } else {
                $failed[] = $tool . ': ' . ($result['error'] ?? 'Installation failed');
            }
        } catch (Exception $e) {
            $failed[] = $tool . ': ' . $e->getMessage();
        }
    }
    
    $message = count($installed) > 0 
        ? "Successfully installed " . count($installed) . " tool(s)"
        : "Installation failed. Manual installation may be required.";
    
    echo json_encode([
        'success' => count($installed) > 0,
        'message' => $message,
        'installed' => $installed,
        'failed' => $failed
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steganography Analysis Tool - Detect Hidden Messages</title>
    
    <!-- CSS only -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-iYQeCzEYFbKjA/T2uDLTpkwGzCiq6soy8tYaI1GyVh/UjpbCx/TYkiZhlZB6+fzT" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
    
    <!-- JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous"></script>

    <style>
        /* Force dark mode everywhere */
        body {
            background: #121212 !important;
            color: #e0e0e0 !important;
            min-height: 100vh;
        }
        
        /* Bootstrap overrides for dark mode */
        .card {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
            border-color: #333 !important;
        }
        
        .card-header {
            background-color: #2d2d2d !important;
            border-bottom-color: #444 !important;
            color: #e0e0e0 !important;
        }
        
        .card-body {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        
        .bg-light, .bg-light * {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        
        .text-muted {
            color: #999 !important;
        }
        
        .form-control {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
        
        .form-control:focus {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #0d6efd !important;
        }
        
        .form-control::placeholder {
            color: #888 !important;
        }
        
        .upload-area {
            border: 3px dashed #0d6efd !important;
            transition: all 0.3s ease;
            cursor: pointer;
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        
        .upload-area:hover {
            background-color: #333 !important;
            border-color: #198754 !important;
        }
        
        .upload-area.dragover {
            background-color: #2d4d2d !important;
            border-color: #198754 !important;
        }
        
        .upload-area img {
            max-height: 300px;
            max-width: 100%;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .upload-area .image-preview-container {
            position: relative;
        }
        
        .upload-area .upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }
        
        .upload-area:hover .upload-overlay {
            display: flex;
        }
        
        .file-input {
            display: none;
        }
        
        .code-block {
            background: #1a1a1a !important;
            color: #f8f8f2 !important;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        pre {
            background-color: #1a1a1a !important;
            color: #f8f8f2 !important;
            border-color: #333 !important;
        }
        
        img {
            max-width: 100%;
            border-radius: 0.375rem;
        }
        
        .form-check-input:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .form-check-label.disabled-tool {
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        /* Alert overrides */
        .alert-success {
            background-color: #1e3a1e !important;
            border-color: #198754 !important;
            color: #d4edda !important;
        }
        
        .alert-danger {
            background-color: #3a1e1e !important;
            border-color: #dc3545 !important;
            color: #f8d7da !important;
        }
        
        .alert-warning {
            background-color: #3a3a1e !important;
            border-color: #ffc107 !important;
            color: #fff3cd !important;
        }
        
        .alert-info {
            background-color: #1e2a3a !important;
            border-color: #0dcaf0 !important;
            color: #d1ecf1 !important;
        }
        
        .alert-warning .text-dark {
            color: #fff3cd !important;
        }
        
        /* Button overrides */
        .btn-primary {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7 !important;
            border-color: #0a58ca !important;
        }
        
        .btn-outline-secondary {
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        
        .btn-outline-secondary:hover {
            background-color: #555 !important;
            border-color: #555 !important;
            color: #fff !important;
        }
        
        .btn-success {
            background-color: #198754 !important;
            border-color: #198754 !important;
        }
        
        .btn-sm {
            background-color: inherit;
        }
        
        /* Badge overrides */
        .badge {
            color: #fff !important;
        }
        
        .badge.bg-secondary {
            background-color: #6c757d !important;
        }
        
        .badge.bg-danger {
            background-color: #dc3545 !important;
        }
        
        /* Header backgrounds */
        .bg-primary {
            background-color: #0d6efd !important;
        }
        
        .bg-success {
            background-color: #198754 !important;
        }
        
        .bg-warning {
            background-color: #ffc107 !important;
        }
        
        .text-white {
            color: #e0e0e0 !important;
        }
        
        .text-dark {
            color: #e0e0e0 !important;
        }
        
        /* Text color overrides */
        h1, h2, h3, h4, h5, h6, p, label, span, div, li {
            color: #e0e0e0 !important;
        }
        
        .text-primary {
            color: #6ea8fe !important;
        }
        
        /* Input group overrides */
        .input-group-text {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
        
        /* List overrides */
        ul, ol {
            color: #e0e0e0 !important;
        }
        
        /* Link overrides */
        a {
            color: #6ea8fe !important;
        }
        
        a:hover {
            color: #86b7fe !important;
        }
        
        /* Spinner overrides */
        .spinner-border {
            color: #0d6efd !important;
        }
        
        /* Dropdown and other Bootstrap components */
        .dropdown-menu {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
        }
        
        .dropdown-item {
            color: #e0e0e0 !important;
        }
        
        .dropdown-item:hover {
            background-color: #333 !important;
            color: #fff !important;
        }
        
        /* Modal overrides */
        .modal-content {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        
        .modal-header {
            border-bottom-color: #444 !important;
        }
        
        .modal-footer {
            border-top-color: #444 !important;
        }
        
        /* Close button */
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        /* Image thumbnail border */
        .img-thumbnail {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="text-center text-white mb-5">
        <h1 class="display-4 fw-bold mb-3"><i class="bi bi-shield-lock"></i> Steganography Analysis Tool</h1>
        <p class="lead opacity-75">Upload an image to detect hidden messages, embedded files, and metadata</p>
    </div>

    <div class="card shadow-lg mb-4">
        <div class="card-body p-4">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" id="uploadform">
            <div class="upload-area rounded p-5 text-center bg-light mb-4" id="uploadArea">
                <div class="mb-4">
                    <i class="bi bi-cloud-upload text-primary" style="font-size: 4rem;"></i>
                </div>
                <h4 class="mb-2">Drag & Drop your image here</h4>
                <p class="text-muted mb-0">or click to browse</p>
                <input type="file" name="fileToUpload" id="fileToUpload" class="file-input" accept="image/*" required>
                <div id="fileName" class="mt-3 d-none">
                    <strong><i class="bi bi-file-image"></i> <span id="fileNameText"></span></strong>
                </div>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <label for="steghidepw" class="form-label fw-bold">
                        <i class="bi bi-key"></i> Steghide Password (Optional)
                    </label>
                    <input type="text" class="form-control" name="steghidepw" id="steghidepw" 
                           placeholder="Enter password if image is protected">
                    <label for="outguesspw" class="form-label fw-bold mt-3">
                        <i class="bi bi-key"></i> Outguess Key (Optional)
                    </label>
                    <input type="text" class="form-control" name="outguesspw" id="outguesspw" 
                           placeholder="Enter key for outguess extraction">
                    <label for="outguess_derivations" class="form-label fw-bold mt-3">
                        <i class="bi bi-123"></i> Outguess Key Derivations (Optional)
                    </label>
                    <input type="number" class="form-control" name="outguess_derivations" id="outguess_derivations" 
                           placeholder="Number of key derivations to try (default: 1)" min="1" max="100">
                    <small class="form-text text-muted">Try multiple key derivations when extracting (1-100)</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">
                        <i class="bi bi-gear"></i> Analysis Options
                    </label>
                    <div class="card bg-light p-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="tools[]" value="stegoveritas" id="tool_stegoveritas" checked>
                            <label class="form-check-label" for="tool_stegoveritas">Stegoveritas (Deep Analysis)</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="tools[]" value="foremost" id="tool_foremost" checked>
                            <label class="form-check-label" for="tool_foremost">Foremost (File Extraction)</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="tools[]" value="steghide" id="tool_steghide" checked>
                            <label class="form-check-label" for="tool_steghide">Steghide Analysis</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tools[]" value="strings" id="tool_strings" checked>
                            <label class="form-check-label" for="tool_strings">Strings (Text Extraction)</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <input type="hidden" name="submit" value="1">
                <button type="submit" class="btn btn-primary btn-lg px-5" id="analyzebtn">
                    <i class="bi bi-search"></i> Analyze Image
                </button>
            </div>

            <div class="d-none mt-4" id="progressContainer">
                <div class="alert alert-info d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-3" role="status" aria-hidden="true"></div>
                    <div>
                        <strong>Analyzing image...</strong><br>
                        <small>This may take a few moments. Please wait.</small>
                    </div>
                </div>
            </div>
        </form>
        </div>
    </div>

<?php
// Check if image file is being uploaded - check for file upload instead of submit button
// (Some browsers/clicks don't always send button values)
if(isset($_FILES["fileToUpload"]) && isset($_FILES["fileToUpload"]["tmp_name"]) && !empty($_FILES["fileToUpload"]["tmp_name"])) {
  
  error_log("=== FILE UPLOAD DETECTED ===");
  error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
  error_log("POST data: " . print_r($_POST, true));
  error_log("Files data: " . print_r($_FILES, true));

  try {
    $fullPathToFile = uploadImage();

    if (empty($fullPathToFile)) {
      echo "<div class='card shadow-lg mb-4'>";
      echo "<div class='card-body p-4'>";
      echo "<div class='alert alert-danger mb-0'>";
      echo "<h5 class='mb-2'><i class='bi bi-exclamation-triangle'></i> Upload Failed</h5>";
      echo "<p class='mb-0'>Unable to upload file. Function uploadImage returned false.</p>";
      echo "</div>";
      echo "</div></div>";
      exit;
    }

    $outputFolder = dirname($fullPathToFile);
    $fileName = htmlspecialchars(basename($_FILES["fileToUpload"]["name"]));
    $selectedTools = isset($_POST['tools']) ? $_POST['tools'] : [];

    // Store image path for JavaScript to update upload area
    $uploadedImagePath = $fullPathToFile;
    
    // Display upload success message
    echo "<div class='card shadow-lg mb-4'>";
    echo "<div class='card-body p-4'>";
    echo "<div class='alert alert-success'><i class='bi bi-check-circle'></i> File <strong>$fileName</strong> uploaded successfully!</div>";
    echo "<div class='alert alert-warning'><i class='bi bi-clock'></i> All data will be automatically deleted after ".DELETE_AFTER." seconds (".round(DELETE_AFTER/60)." minutes).</div>";
    echo "</div></div>";

    // Display analysis summary card
    echo "<div class='card shadow-lg mb-4 border-success'>";
    echo "<div class='card-body p-4 bg-success text-white rounded'>";
    echo "<h4 class='mb-3'><i class='bi bi-graph-up'></i> Analysis Summary</h4>";
    echo "<p class='mb-0'>Running multiple steganography detection tools on your image...</p>";
    echo "</div></div>";

    // Helper function to check if a command exists and run it
    $runCommand = function($command, $moduleName) {
        // First check if command exists
        $commandParts = explode(' ', $command);
        $baseCmd = $commandParts[0];
        $checkCommand = "which " . escapeshellarg($baseCmd) . " 2>/dev/null";
        $cmdPathResult = shell_exec($checkCommand);
        $cmdPath = $cmdPathResult !== null ? trim($cmdPathResult) : '';
        
        // If not found in PATH, check bin/ folder as fallback
        if (empty($cmdPath)) {
            $localBinPath = BIN_DIR . '/' . $baseCmd;
            if (file_exists($localBinPath) && is_executable($localBinPath)) {
                $cmdPath = $localBinPath;
                // Replace the base command in the command string with the full path
                $command = $cmdPath . ' ' . implode(' ', array_slice($commandParts, 1));
            } else {
                return ['error' => "Tool '$moduleName' not found. Please install it to use this analysis module."];
            }
        }
        
        // Run the command and capture both stdout and stderr
        $output = shell_exec($command . ' 2>&1');
        $output = $output !== null ? $output : '';
        
        // Check if output contains "not found" error
        if (strpos($output, 'not found') !== false || strpos($output, 'command not found') !== false) {
            return ['error' => "Tool '$moduleName' not found. Please install it to use this analysis module."];
        }
        
        // Check for library loading errors (missing shared libraries)
        if (strpos($output, 'error while loading shared libraries') !== false || 
            strpos($output, 'cannot open shared object file') !== false) {
            $libError = "Tool '$moduleName' found but cannot run due to missing library dependencies. ";
            if ($cmdPath === BIN_DIR . '/' . $baseCmd) {
                $libError .= "The local binary requires libraries that are not installed. ";
                $libError .= "Try installing the system package: sudo apt-get install $baseCmd";
            } else {
                $libError .= "Please install required library dependencies.";
            }
            return ['error' => $libError];
        }
        
        return ['output' => $output];
    };

    // Get selected tools or default to all
    $runAll = empty($selectedTools) || in_array('stegoveritas', $selectedTools) || in_array('foremost', $selectedTools) || in_array('steghide', $selectedTools) || in_array('strings', $selectedTools);

    // Initialize module results array
    $module = [];
    $moduleResults = [];
    $totalModules = 0;
    $successfulModules = 0;

    // Shell modules - only run if selected
    if ($runAll || in_array('stegoveritas', $selectedTools)) {
        $result = $runCommand('stegoveritas -out '.escapeshellarg($outputFolder.'/stegoveritas').' '.escapeshellarg($fullPathToFile), 'stegoveritas');
        $module['stegoveritas'] = $result['output'] ?? '';
        $module['stegoveritas_error'] = $result['error'] ?? null;
    }
    
    if ($runAll || in_array('foremost', $selectedTools)) {
        $result = $runCommand('foremost -i '.escapeshellarg($fullPathToFile).' -o '.escapeshellarg($outputFolder."/foremost"), 'foremost');
        $module['foremost'] = $result['output'] ?? '';
        $module['foremost_error'] = $result['error'] ?? null;
    }
    
    if ($runAll || in_array('steghide', $selectedTools)) {
        $result = $runCommand('steghide info '.escapeshellarg($fullPathToFile), 'steghide');
        $module['steghideInfo'] = $result['output'] ?? '';
        $module['steghideInfo_error'] = $result['error'] ?? null;
        $module['steghideP'] = (isset($_POST['steghidepw']) && !empty($_POST['steghidepw'])) ? escapeshellcmd($_POST['steghidepw']) : null;
        if (!empty($module['steghideP']) && !isset($module['steghideInfo_error'])) {
            $result = $runCommand('steghide extract -sf '.escapeshellarg($fullPathToFile).' -p "'.$module['steghideP'].'"', 'steghide');
            $module['steghideE'] = $result['output'] ?? '';
            $module['steghideE_error'] = $result['error'] ?? null;
        }
    }
    
    // Outguess analysis with optional key and key derivations
    $outguessCmd = 'outguess -r '.escapeshellarg($fullPathToFile);
    $module['outguessP'] = (isset($_POST['outguesspw']) && !empty($_POST['outguesspw'])) ? escapeshellcmd($_POST['outguesspw']) : null;
    $outguessDerivations = (isset($_POST['outguess_derivations']) && !empty($_POST['outguess_derivations'])) ? intval($_POST['outguess_derivations']) : 1;
    
    if (!empty($module['outguessP'])) {
        $outguessCmd .= ' -k '.escapeshellarg($module['outguessP']);
    }
    
    if ($outguessDerivations > 1) {
        $outguessCmd .= ' -x '.$outguessDerivations;
    }
    
    $result = $runCommand($outguessCmd, 'outguess');
    $module['outguess'] = $result['output'] ?? '';
    $module['outguess_error'] = $result['error'] ?? null;
    
    // Improve outguess error detection
    if (empty($module['outguess_error']) && !empty($module['outguess'])) {
        // Check for common outguess error patterns
        $outguessOutput = $module['outguess'];
        if (strpos($outguessOutput, 'Reading ') === false && 
            strpos($outguessOutput, 'Extracted') === false &&
            (strpos($outguessOutput, 'error') !== false || 
             strpos($outguessOutput, 'Error') !== false ||
             strpos($outguessOutput, 'failed') !== false ||
             strpos($outguessOutput, 'Failed') !== false ||
             strpos($outguessOutput, 'cannot') !== false ||
             strpos($outguessOutput, 'unable') !== false)) {
            // This looks like an error, but outguess doesn't use stderr for all errors
            // Store it as output but mark it for display
        }
    }
    
    if ($runAll || in_array('strings', $selectedTools)) {
        $result = $runCommand('strings '.escapeshellarg($fullPathToFile), 'strings');
        $module['strings'] = $result['output'] ?? '';
        $module['strings_error'] = $result['error'] ?? null;
    }
    
    $result = $runCommand('exiv2 '.escapeshellarg($fullPathToFile), 'exiv2');
    $module['exiv2'] = $result['output'] ?? '';
    $module['exiv2_error'] = $result['error'] ?? null;
    
    $result = $runCommand('exiftool '.escapeshellarg($fullPathToFile), 'exiftool');
    $module['exif'] = $result['output'] ?? '';
    $module['exif_error'] = $result['error'] ?? null;
    
    $result = $runCommand('binwalk '.escapeshellarg($fullPathToFile), 'binwalk');
    $module['binwalk'] = $result['output'] ?? '';
    $module['binwalk_error'] = $result['error'] ?? null;
    
    $result = $runCommand('xxd '.escapeshellarg($fullPathToFile), 'xxd');
    $module['xxd'] = $result['output'] ?? '';
    $module['xxd_error'] = $result['error'] ?? null;

    // Process Foremost results
    $foremostaudit = "";
    $foremostimg = "";
    $foremostFiles = [];
    $auditFile = $outputFolder."/foremost/audit.txt";
    if (file_exists($auditFile)) {
        $auditContent = file_get_contents($auditFile);
        if ($auditContent !== false) {
            $foremostaudit = "<div class='bg-light p-3 rounded mb-3'><h5 class='mb-3'><i class='bi bi-file-text'></i> Extraction Audit</h5><pre class='code-block p-3 rounded'>".htmlspecialchars($auditContent)."</pre></div>";
        }
    }
    
    $foremostExtractedFiles = glob($outputFolder."/foremost/*/*");
    if ($foremostExtractedFiles) {
        $foremostimg .= "<div class='bg-light p-3 rounded mb-3'><h5 class='mb-3'><i class='bi bi-file-earmark-image'></i> Extracted Files</h5>";
        foreach ($foremostExtractedFiles as $extractedFile) {
            $fileExt = strtolower(pathinfo($extractedFile, PATHINFO_EXTENSION));
            $fileNameOnly = basename($extractedFile);
            $foremostFiles[] = $extractedFile;
            
            $foremostimg .= "<div class='card mb-3'>";
            $foremostimg .= "<div class='card-body'>";
            $foremostimg .= "<h6 class='card-title'><i class='bi bi-file-earmark'></i> $fileNameOnly</h6>";
            
            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                $foremostimg .= "<img src='".htmlspecialchars($extractedFile)."' alt='Extracted image' class='img-thumbnail mb-2 d-block'>";
            }
            
            $foremostimg .= "<div class='mt-2'>";
            $foremostimg .= "<a href='".htmlspecialchars($extractedFile)."' class='btn btn-sm btn-primary' download><i class='bi bi-download'></i> Download</a> ";
            $foremostimg .= "<span class='badge bg-secondary ms-2'>Size: ".formatBytes(filesize($extractedFile))."</span>";
            $foremostimg .= "</div></div></div>";
        }
        $foremostimg .= "</div>";
    }

    // Process Stegoveritas results
    $stegoveritasFiles = [];
    $stegoveritasImages = [];
    $stegoveritasOutput = "<div class='bg-light p-3 rounded mb-3'>";
    
    $stegoveritasDir = $outputFolder."/stegoveritas";
    if (is_dir($stegoveritasDir)) {
        $stegoveritasFilesList = glob($stegoveritasDir."/*");
        if ($stegoveritasFilesList) {
            $stegoveritasOutput .= "<h5 class='mb-3'><i class='bi bi-images'></i> Analysis Results</h5>";
            $stegoveritasOutput .= "<div class='row g-3'>";
            
            foreach ($stegoveritasFilesList as $stegoveritasFile) {
                if (is_dir($stegoveritasFile)) continue;
                
                $fileExt = strtolower(pathinfo($stegoveritasFile, PATHINFO_EXTENSION));
                $fileNameOnly = basename($stegoveritasFile);
                
                if ($fileExt == 'png' || $fileExt == 'jpg' || $fileExt == 'jpeg') {
                    $stegoveritasImages[] = $stegoveritasFile;
                    $stegoveritasOutput .= "<div class='col-md-6'>";
                    $stegoveritasOutput .= "<div class='card h-100'>";
                    $stegoveritasOutput .= "<div class='card-body'>";
                    $stegoveritasOutput .= "<h6 class='card-title'><i class='bi bi-image'></i> $fileNameOnly</h6>";
                    $stegoveritasOutput .= "<img src='".htmlspecialchars($stegoveritasFile)."' alt='Analysis result' class='img-thumbnail mb-2 w-100'>";
                    $stegoveritasOutput .= "<div><a href='".htmlspecialchars($stegoveritasFile)."' class='btn btn-sm btn-primary' download><i class='bi bi-download'></i> Download</a></div>";
                    $stegoveritasOutput .= "</div></div></div>";
                } else {
                    $stegoveritasFiles[] = $stegoveritasFile;
                    $stegoveritasOutput .= "<div class='col-md-12'>";
                    $stegoveritasOutput .= "<a href='".htmlspecialchars($stegoveritasFile)."' class='btn btn-sm btn-outline-primary' download><i class='bi bi-file-earmark'></i> $fileNameOnly</a>";
                    $stegoveritasOutput .= "</div>";
                }
            }
            $stegoveritasOutput .= "</div>";
        }
    }
    $stegoveritasOutput .= "</div>";

    // Process and collect errors first
    $moduleIcons = [
        'stegoveritas' => 'bi-shield-check',
        'foremost' => 'bi-file-earmark-zip',
        'steghideInfo' => 'bi-key',
        'steghideE' => 'bi-unlock',
        'outguess' => 'bi-search',
        'strings' => 'bi-type',
        'exiv2' => 'bi-info-circle',
        'exif' => 'bi-camera',
        'binwalk' => 'bi-diagram-3',
        'xxd' => 'bi-hexagon'
    ];

    $missingTools = [];
    $availableTools = [];
    $errorsData = []; // Store error information
    
    // First pass: Collect all errors
    foreach ($module as $moduleName => $output) {
        // Skip error entries and password fields
        if (strpos($moduleName, '_error') !== false || $moduleName == 'steghideP' || $moduleName == 'outguessP') {
            continue;
        }
        
        // Check for tool errors
        $errorKey = $moduleName . '_error';
        if (isset($module[$errorKey])) {
            $missingTools[] = $moduleName;
            $errorsData[$moduleName] = [
                'error' => $module[$errorKey],
                'icon' => $moduleIcons[$moduleName] ?? 'bi-gear'
            ];
        } else {
            if ($moduleName != 'steghideP' && $moduleName != 'outguessP') {
                $availableTools[] = $moduleName;
            }
        }
    }
    
    // Store missing tools data for JavaScript modal
    // Map display names to tool keys for installation
    $toolMapping = [
        'stegoveritas' => 'stegoveritas',
        'foremost' => 'foremost',
        'steghideInfo' => 'steghide',
        'steghideE' => 'steghide',
        'outguess' => 'outguess',
        'strings' => 'strings',
        'exiv2' => 'exiv2',
        'exif' => 'exiftool',
        'binwalk' => 'binwalk',
        'xxd' => 'xxd'
    ];
    
    $toolKeys = [];
    foreach ($missingTools as $tool) {
        $toolKey = $toolMapping[$tool] ?? strtolower($tool);
        if (!in_array($toolKey, $toolKeys)) {
            $toolKeys[] = $toolKey;
        }
    }
    
    $installInfo = installTools($toolKeys);
    $packageManager = $installInfo['packageManager'] ?? 'unknown';
    $installCommands = $installInfo['commands'] ?? [];
    
    // Create formatted missing tools data for JavaScript
    $missingToolsData = [];
    foreach ($errorsData as $toolName => $errorInfo) {
        $displayName = ucfirst(str_replace(['steghide', 'Info', 'E'], ['Steghide ', 'Info', 'Extraction'], $toolName));
        $toolKey = $toolMapping[$toolName] ?? strtolower($toolName);
        $missingToolsData[$toolName] = [
            'name' => $displayName,
            'error' => $errorInfo['error'],
            'icon' => $errorInfo['icon'],
            'toolKey' => $toolKey
        ];
    }

    // Second pass: Display successful module results only
    foreach ($module as $moduleName => $output) {
        $skip = false;
        $collapse = false;
        $extraOut = "";
        $icon = $moduleIcons[$moduleName] ?? 'bi-gear';

        // Skip error entries and password fields
        if (strpos($moduleName, '_error') !== false || $moduleName == 'steghideP' || $moduleName == 'outguessP') {
            continue;
        }
        
        // Skip modules with errors (will be shown in modal)
        $errorKey = $moduleName . '_error';
        if (isset($module[$errorKey])) {
            continue; // Skip modules with errors
        }
        
        if ($moduleName == 'steghideE') {
            if (empty($_POST['steghidepw']) || empty($module['steghideP'])) {
                $skip = true;
            }
        }

        if ($moduleName == 'stegoveritas') {
            $extraOut = $stegoveritasOutput;
            $collapse = false;
        }
        
        if ($moduleName == 'foremost') {
            $extraOut = $foremostaudit.$foremostimg;
            $collapse = false;
        }
        
        if ($moduleName == 'strings' || $moduleName == 'xxd') {
            $collapse = true;
        }
        
        // Skip empty outputs unless it's a special case
        if (empty($output) && $moduleName != 'stegoveritas' && $moduleName != 'foremost') {
            $skip = true;
        }

        if ($skip) continue;

        $totalModules++;
        if (!empty($output) || !empty($extraOut)) {
            $successfulModules++;
        }

        // Format output (no errors here, they're displayed at the top)
        $formattedOutput = "";
        $rawOutput = !empty($output) ? $output : '';
        $outputId = 'output_' . $moduleName;
        
        if ($collapse) {
            $outputContent = !empty($output) ? htmlspecialchars($output) : 'No output';
            $formattedOutput = '
            <div class="mb-3">
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#'.$moduleName.'" aria-expanded="false">
                <i class="bi bi-chevron-down"></i> Show Output
                </button>
            </div>
            <div class="collapse" id="'.$moduleName.'">
                <div class="mb-2">
                    <button class="btn btn-sm btn-outline-secondary me-2" type="button" onclick="copyOutputToClipboard(\''.$outputId.'\')">
                        <i class="bi bi-clipboard"></i> Copy to Clipboard
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="saveOutputToFile(\''.$outputId.'\', \''.$moduleName.'\')">
                        <i class="bi bi-download"></i> Save to File
                    </button>
                </div>
                <pre class="code-block p-3 rounded" id="'.$outputId.'" data-raw-output="'.htmlspecialchars($rawOutput, ENT_QUOTES).'">'.$outputContent.'</pre>
            </div>
            ';
        } else {
            if (!empty($output)) {
                $outputContent = htmlspecialchars($output);
                $formattedOutput = '
                <div class="mb-2">
                    <button class="btn btn-sm btn-outline-secondary me-2" type="button" onclick="copyOutputToClipboard(\''.$outputId.'\')">
                        <i class="bi bi-clipboard"></i> Copy to Clipboard
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="saveOutputToFile(\''.$outputId.'\', \''.$moduleName.'\')">
                        <i class="bi bi-download"></i> Save to File
                    </button>
                </div>
                <div class="bg-light p-3 rounded mb-3">
                    <pre class="code-block p-3 rounded" id="'.$outputId.'" data-raw-output="'.htmlspecialchars($rawOutput, ENT_QUOTES).'">'.$outputContent.'</pre>
                </div>
                ';
            }
        }

        $headerBg = 'bg-primary text-white';
        
        echo "
        <div class='card shadow-lg mb-4'>
        <div class='card-header $headerBg'>
            <h5 class='mb-0'><i class='bi $icon me-2'></i> ".ucfirst(str_replace(['steghide', 'Info', 'E'], ['Steghide ', 'Info', 'Extraction'], $moduleName))."</h5>
        </div>
        <div class='card-body'>
          $formattedOutput
          $extraOut
        </div>
        </div>
        ";
    }

    // Display summary
    echo "<div class='card shadow-lg mb-4'>";
    echo "<div class='card-body p-4'>";
    echo "<div class='alert alert-info mb-0'>";
    echo "<h5 class='mb-2'><i class='bi bi-check-circle'></i> Analysis Complete</h5>";
    echo "<p class='mb-0' id='summaryText'>Ran <strong>$successfulModules</strong> of <strong>$totalModules</strong> analysis modules successfully.";
    if (!empty($missingTools)) {
        echo " <strong>".count($missingTools)."</strong> tool(s) not available.";
        echo " <i class='bi bi-question-circle ms-1' id='summaryQuestionMark' style='cursor: pointer; font-size: 1.1em; opacity: 0.8;' title='Click to see missing tools and installation commands'></i>";
    }
    echo "</p>";
    echo "</div>";
    echo "</div></div>";
    
    // Store missing tools data in JavaScript
    if (!empty($missingTools)) {
        echo "<script>";
        echo "const analysisMissingTools = " . json_encode($missingToolsData) . ";\n";
        echo "const analysisInstallCommands = " . json_encode($installCommands) . ";\n";
        echo "const analysisPackageManager = " . json_encode($packageManager) . ";\n";
        echo "const analysisToolKeys = " . json_encode($toolKeys) . ";\n";
        echo "</script>";
    }
    
    // Store uploaded image path for JavaScript
    if (isset($uploadedImagePath)) {
        echo "<script>";
        echo "const uploadedImagePath = " . json_encode($uploadedImagePath) . ";\n";
        echo "</script>";
    }

    // Schedule cleanup
    shell_exec('bash deleteafter.sh '.escapeshellarg($outputFolder).' '.DELETE_AFTER.' > /dev/null 2>&1 &');
    
  } catch (Throwable $t) {
    echo "<div class='card shadow-lg mb-4'>";
    echo "<div class='card-body p-4'>";
    echo "<div class='alert alert-danger mb-0'>";
    echo "<h5 class='mb-2'><i class='bi bi-exclamation-triangle'></i> Error</h5>";
    echo "<p>An error occurred during analysis: ".htmlspecialchars($t->getMessage())."</p>";
    echo "<pre class='bg-dark text-light p-3 rounded overflow-auto' style='max-height: 400px;'>";
    echo htmlspecialchars($t->getTraceAsString());
    echo "</pre>";
    echo "</div>";
    echo "</div></div>";
  }
} else {
    // Show error if file upload failed
    if (isset($_FILES["fileToUpload"]["error"]) && $_FILES["fileToUpload"]["error"] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped the file upload'
        ];
        $errorCode = $_FILES["fileToUpload"]["error"];
        $errorMsg = $uploadErrors[$errorCode] ?? 'Unknown upload error (Code: '.$errorCode.')';
        echo "<div class='card shadow-lg mb-4'>";
        echo "<div class='card-body p-4'>";
        echo "<div class='alert alert-danger mb-0'>";
        echo "<h5 class='mb-2'><i class='bi bi-exclamation-triangle'></i> Upload Error</h5>";
        echo "<p class='mb-0'>".htmlspecialchars($errorMsg)."</p>";
        echo "</div>";
        echo "</div></div>";
    }
}
?>

<script>
// Check tool availability on page load
function checkToolAvailability() {
    fetch('?action=checkTools')
        .then(response => response.json())
        .then(data => {
            // Support both old format (flat object) and new format (nested with tools)
            const toolsData = data.tools || data;
            
            // Map tool names to checkbox IDs
            const toolCheckboxMap = {
                'stegoveritas': 'tool_stegoveritas',
                'foremost': 'tool_foremost',
                'steghide': 'tool_steghide',
                'strings': 'tool_strings'
            };
            
            // Process each tool
            for (const [toolName, checkboxId] of Object.entries(toolCheckboxMap)) {
                const checkbox = document.getElementById(checkboxId);
                const label = checkbox ? checkbox.closest('.form-check') : null;
                
                if (!checkbox || !label) continue;
                
                const toolStatus = toolsData[toolName];
                
                if (!toolStatus || !toolStatus.available) {
                    // Disable the checkbox
                    checkbox.disabled = true;
                    checkbox.checked = false;
                    
                    // Add warning styling to label
                    const labelElement = label.querySelector('label');
                    if (labelElement) {
                        labelElement.classList.add('disabled-tool', 'text-muted');
                    }
                    label.classList.add('text-muted');
                    label.style.opacity = '0.7';
                    
                    // Add warning icon and message
                    const warningIcon = document.createElement('i');
                    warningIcon.className = 'bi bi-exclamation-triangle text-warning ms-2';
                    warningIcon.title = toolStatus?.error || 'Tool not available';
                    
                    // Check if warning already exists
                    if (!label.querySelector('.bi-exclamation-triangle')) {
                        if (labelElement) {
                            labelElement.appendChild(warningIcon);
                        } else {
                            label.appendChild(warningIcon);
                        }
                        
                        // Don't add warning text to individual tools - will be shown in modal
                    }
                } else {
                    // Tool is available - ensure checkbox is enabled
                    checkbox.disabled = false;
                    const labelElement = label.querySelector('label');
                    if (labelElement) {
                        labelElement.classList.remove('disabled-tool', 'text-muted');
                    }
                    label.classList.remove('text-muted');
                    label.style.opacity = '1';
                }
            }
            
            // Show summary alert if any tools are unavailable
            // toolsData is already defined above, reuse it
            const unavailableTools = Object.keys(toolsData).filter(tool => 
                !toolsData[tool] || !toolsData[tool].available
            );
            
            // Get installation commands from response (if available)
            const installCommands = data.installCommands || {};
            const packageManager = data.packageManager || 'unknown';
            const toolKeys = data.toolKeys || unavailableTools;
            
            // Build unavailable tools data with tool names and errors
            const unavailableToolsData = {};
            for (const tool of unavailableTools) {
                const toolStatus = toolsData[tool];
                unavailableToolsData[tool] = {
                    name: tool.charAt(0).toUpperCase() + tool.slice(1),
                    error: toolStatus?.error || 'Tool not available',
                    toolKey: tool
                };
            }
            
            if (unavailableTools.length > 0) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-warning alert-dismissible fade show mt-3';
                
                // Create question mark icon with click handler
                const questionMarkIcon = document.createElement('i');
                questionMarkIcon.className = 'bi bi-question-circle ms-2';
                questionMarkIcon.style.cursor = 'pointer';
                questionMarkIcon.style.fontSize = '1.1em';
                questionMarkIcon.style.textDecoration = 'none';
                questionMarkIcon.style.opacity = '0.8';
                questionMarkIcon.title = 'Click to see details';
                questionMarkIcon.addEventListener('click', function() {
                    showMissingToolsModal(unavailableToolsData, installCommands, packageManager, toolKeys);
                });
                questionMarkIcon.addEventListener('mouseenter', function() {
                    this.style.opacity = '1';
                    this.style.textDecoration = 'underline';
                });
                questionMarkIcon.addEventListener('mouseleave', function() {
                    this.style.opacity = '0.8';
                    this.style.textDecoration = 'none';
                });
                
                const alertContent = document.createElement('span');
                alertContent.innerHTML = `
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>Warning:</strong> ${unavailableTools.length} tool(s) are not available and have been disabled. 
                    These tools will be skipped during analysis.
                `;
                alertDiv.appendChild(alertContent);
                alertDiv.appendChild(questionMarkIcon);
                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'btn-close';
                closeButton.setAttribute('data-bs-dismiss', 'alert');
                closeButton.setAttribute('aria-label', 'Close');
                alertDiv.appendChild(closeButton);
                
                // Insert after the analysis options card
                const optionsCard = document.querySelector('.col-md-6:last-child .card');
                if (optionsCard && !document.querySelector('.tool-availability-warning')) {
                    alertDiv.classList.add('tool-availability-warning');
                    optionsCard.parentNode.insertBefore(alertDiv, optionsCard.nextSibling);
                }
                
                // Store unavailable tools data for modal
                alertDiv.setAttribute('data-unavailable-tools', JSON.stringify(unavailableToolsData));
            }
        })
        .catch(error => {
            console.error('Error checking tool availability:', error);
        });
}

// Function to show missing tools modal
function showMissingToolsModal(unavailableToolsData, installCommands = null, packageManager = null, toolKeys = null) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('missingToolsModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'missingToolsModal';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-labelledby', 'missingToolsModalLabel');
        modal.setAttribute('aria-hidden', 'true');
        document.body.appendChild(modal);
    }
    
    // Build tools list
    let toolsList = '';
    for (const [toolKey, toolData] of Object.entries(unavailableToolsData)) {
        toolsList += `
            <div class="mb-3">
                <h6 class="text-warning mb-1">
                    <i class="bi ${toolData.icon || 'bi-exclamation-triangle'} me-2"></i>${toolData.name}
                </h6>
                <p class="text-muted mb-0 ms-4">${toolData.error || 'Tool not available'}</p>
            </div>
        `;
    }
    
    // Build installation commands section
    let installCommandsHtml = '';
    if (installCommands && packageManager && packageManager !== 'unknown' && Object.keys(installCommands).length > 0) {
        let commandsList = '';
        // Map tool keys to module names for display
        const toolKeyToModuleName = {};
        for (const [moduleName, toolData] of Object.entries(unavailableToolsData)) {
            if (toolData.toolKey) {
                toolKeyToModuleName[toolData.toolKey] = moduleName;
            }
        }
        
        for (const toolKey of (toolKeys || Object.keys(installCommands))) {
            if (installCommands[toolKey]) {
                const cmd = installCommands[toolKey];
                // Find the module name that corresponds to this tool key
                const moduleName = toolKeyToModuleName[toolKey] || toolKey;
                const toolName = unavailableToolsData[moduleName]?.name || toolKey.charAt(0).toUpperCase() + toolKey.slice(1);
                const uniqueId = 'modal_cmd_' + toolKey.replace(/[^a-zA-Z0-9]/g, '_');
                commandsList += `
                    <div class="mb-3">
                        <label class="form-label fw-bold">${toolName}:</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="sudo ${cmd}" readonly id="${uniqueId}">
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('${uniqueId}')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                `;
            }
        }
        
        if (commandsList) {
            installCommandsHtml = `
                <div class="card bg-light mb-3 mt-4">
                    <div class="card-header">
                        <strong><i class="bi bi-terminal me-2"></i>Installation Commands (Detected: ${packageManager})</strong>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Copy and paste these commands into your terminal (requires root/sudo access):</p>
                        ${commandsList}
                    </div>
                </div>
            `;
        }
    }
    
    modal.innerHTML = `
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="missingToolsModalLabel">
                        <i class="bi bi-exclamation-triangle text-warning me-2"></i>Missing Tools
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">The following analysis tools are not available and have been disabled:</p>
                    ${toolsList}
                    ${installCommandsHtml}
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> These tools will be skipped during analysis. 
                        To enable them, please install the required tools using the installation commands above.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Check tools when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkToolAvailability();
    
    // Update upload area with image preview if image is uploaded
    if (typeof uploadedImagePath !== 'undefined' && uploadedImagePath) {
        const uploadArea = document.getElementById('uploadArea');
        if (uploadArea) {
            uploadArea.innerHTML = `
                <div class="image-preview-container">
                    <img src="${uploadedImagePath}" alt="Uploaded image" class="img-thumbnail">
                    <div class="upload-overlay">
                        <div class="text-center text-white">
                            <i class="bi bi-cloud-upload" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-0">Click to upload a new image</p>
                        </div>
                    </div>
                </div>
                <input type="file" name="fileToUpload" id="fileToUpload" class="file-input" accept="image/*" required>
            `;
            
            // Reattach event listeners
            const fileInput = document.getElementById('fileToUpload');
            if (fileInput) {
                uploadArea.addEventListener('click', () => fileInput.click());
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });
                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });
                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    if (e.dataTransfer.files.length > 0) {
                        fileInput.files = e.dataTransfer.files;
                    }
                });
            }
        }
    }
    
    // Add click handler for summary question mark (if analysis missing tools exist)
    if (typeof analysisMissingTools !== 'undefined' && Object.keys(analysisMissingTools).length > 0) {
        const summaryQuestionMark = document.getElementById('summaryQuestionMark');
        if (summaryQuestionMark) {
            summaryQuestionMark.addEventListener('click', function() {
                showMissingToolsModal(
                    analysisMissingTools,
                    analysisInstallCommands,
                    analysisPackageManager,
                    analysisToolKeys
                );
            });
            summaryQuestionMark.addEventListener('mouseenter', function() {
                this.style.opacity = '1';
                this.style.textDecoration = 'underline';
            });
            summaryQuestionMark.addEventListener('mouseleave', function() {
                this.style.opacity = '0.8';
                this.style.textDecoration = 'none';
            });
        }
    }
});

// Drag and drop functionality
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileToUpload');
const fileName = document.getElementById('fileName');
const fileNameText = document.getElementById('fileNameText');

uploadArea.addEventListener('click', () => fileInput.click());

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        updateFileName();
    }
});

fileInput.addEventListener('change', updateFileName);

function updateFileName() {
    console.log('File input changed, files:', fileInput.files);
    if (fileInput.files.length > 0) {
        fileNameText.textContent = fileInput.files[0].name;
        fileName.classList.remove('d-none');
        fileName.classList.add('d-block');
        console.log('File name updated to:', fileInput.files[0].name);
    }
}

// Copy to clipboard function (for input elements)
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999); // For mobile devices
    navigator.clipboard.writeText(element.value).then(function() {
        const btn = element.nextElementSibling;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        setTimeout(function() {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(function(err) {
        // Fallback for older browsers
        document.execCommand('copy');
        alert('Command copied to clipboard!');
    });
}

// Copy output to clipboard function (for pre elements)
function copyOutputToClipboard(outputId) {
    const element = document.getElementById(outputId);
    if (!element) return;
    
    // Get raw output from data attribute, or fall back to text content
    const textToCopy = element.getAttribute('data-raw-output') || element.textContent || element.innerText;
    
    navigator.clipboard.writeText(textToCopy).then(function() {
        // Find the copy button and update it
        const container = element.closest('.collapse') || element.parentElement;
        const copyBtn = container.querySelector('button[onclick*="copyOutputToClipboard"]');
        if (copyBtn) {
            const originalHTML = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="bi bi-check"></i> Copied!';
            copyBtn.classList.remove('btn-outline-secondary');
            copyBtn.classList.add('btn-success');
            setTimeout(function() {
                copyBtn.innerHTML = originalHTML;
                copyBtn.classList.remove('btn-success');
                copyBtn.classList.add('btn-outline-secondary');
            }, 2000);
        }
    }).catch(function(err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = textToCopy;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Output copied to clipboard!');
    });
}

// Save output to file function
function saveOutputToFile(outputId, moduleName) {
    const element = document.getElementById(outputId);
    if (!element) return;
    
    // Get raw output from data attribute, or fall back to text content
    const textToSave = element.getAttribute('data-raw-output') || element.textContent || element.innerText;
    
    // Create a blob with the text
    const blob = new Blob([textToSave], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    
    // Create a temporary anchor element and trigger download
    const a = document.createElement('a');
    a.href = url;
    a.download = moduleName + '_output.txt';
    document.body.appendChild(a);
    a.click();
    
    // Clean up
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Attempt auto-install
function attemptInstall() {
    const btn = document.getElementById('autoInstallBtn');
    const status = document.getElementById('installStatus');
    const missingTools = <?php echo json_encode($missingTools ?? []); ?>;
    
    if (!missingTools || missingTools.length === 0) {
        status.innerHTML = '<div class="alert alert-warning">No tools to install.</div>';
        return;
    }
    
    if (!confirm('This will attempt to install the missing tools using sudo. Continue?')) {
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Installing...';
    status.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Attempting to install tools... This may take a few minutes.</div>';
    
    // Send AJAX request to install endpoint
    const url = window.location.pathname + '?action=install&tools=' + encodeURIComponent(JSON.stringify(missingTools));
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ confirm: 1 })
    })
    .then(response => {
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
            });
        }
        return response.json();
    })
    .then(data => {
        let html = '';
        if (data.success) {
            html = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' + (data.message || 'Installation completed') + '</div>';
            if (data.installed && data.installed.length > 0) {
                html += '<div class="alert alert-success mt-2"><strong>Successfully installed:</strong> ' + data.installed.join(', ') + '</div>';
            }
            if (data.failed && data.failed.length > 0) {
                html += '<div class="alert alert-warning mt-2"><strong>Failed to install:</strong><ul class="mb-0"><li>' + data.failed.join('</li><li>') + '</li></ul></div>';
            }
            // Reload page after 3 seconds to refresh tool status
            setTimeout(() => location.reload(), 3000);
        } else {
            html = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> ' + (data.message || 'Installation failed') + '</div>';
            if (data.failed && data.failed.length > 0) {
                html += '<div class="alert alert-warning mt-2"><strong>Errors:</strong><ul class="mb-0"><li>' + data.failed.join('</li><li>') + '</li></ul></div>';
            }
        }
        status.innerHTML = html;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download"></i> Attempt Auto-Install (requires sudo access)';
    })
    .catch(error => {
        console.error('Install error:', error);
        status.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Error: ' + error.message + '<br><small>Check browser console for details.</small></div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download"></i> Attempt Auto-Install (requires sudo access)';
    });
}

// Debug: Log page load
console.log('Page loaded. Form element:', document.getElementById('uploadform'));
console.log('File input element:', document.getElementById('fileToUpload'));

// Show progress on submit
const form = document.getElementById('uploadform');
if (form) {
    console.log('Attaching submit event listener to form');
    form.addEventListener('submit', function(e) {
        console.log('Form submit event fired');
        
        // Check if file is selected
        var fileInput = document.getElementById('fileToUpload');
        console.log('File input:', fileInput);
        console.log('Files:', fileInput.files);
        console.log('File count:', fileInput.files ? fileInput.files.length : 'null');
        
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select a file to upload.');
            console.log('Form submission prevented: No file selected');
            return false;
        }
        
        // Check if any tools are selected
        const toolCheckboxes = document.querySelectorAll('input[name="tools[]"]:not(:disabled)');
        const selectedTools = document.querySelectorAll('input[name="tools[]"]:not(:disabled):checked');
        
        if (toolCheckboxes.length === 0) {
            e.preventDefault();
            alert('No analysis tools are available. Please install at least one tool before uploading.');
            console.log('Form submission prevented: No tools available');
            return false;
        }
        
        if (selectedTools.length === 0) {
            e.preventDefault();
            alert('Please select at least one analysis tool.');
            console.log('Form submission prevented: No tools selected');
            return false;
        }
        
        console.log('File selected:', fileInput.files[0].name, 'Size:', fileInput.files[0].size);
        console.log('Form submitting - NOT preventing default...');
        
        document.getElementById('progressContainer').classList.remove('d-none');
    document.getElementById('progressContainer').classList.add('d-block');
        const btn = document.getElementById('analyzebtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analyzing...';
        }
        
        // Don't prevent default - allow form to submit normally
        // Form will submit via normal POST
        return true;
    });
    console.log('Submit event listener attached successfully');
} else {
    console.error('Form element not found!');
}
</script>

</div>
</body>
</html>