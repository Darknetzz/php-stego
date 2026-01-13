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
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .upload-area {
            border: 3px dashed #0d6efd;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            background-color: #e9ecef;
            border-color: #198754;
        }
        
        .upload-area.dragover {
            background-color: #d1e7dd;
            border-color: #198754;
        }
        
        .file-input {
            display: none;
        }
        
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        img {
            max-width: 100%;
            border-radius: 0.375rem;
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

    // Display upload success and file preview
    echo "<div class='card shadow-lg mb-4'>";
    echo "<div class='card-body p-4'>";
    echo "<div class='alert alert-success'><i class='bi bi-check-circle'></i> File <strong>$fileName</strong> uploaded successfully!</div>";
    echo "<div class='alert alert-warning'><i class='bi bi-clock'></i> All data will be automatically deleted after ".DELETE_AFTER." seconds (".round(DELETE_AFTER/60)." minutes).</div>";
    
    // File preview
    echo "<div class='text-center my-4'>";
    echo "<h5 class='mb-3'><i class='bi bi-image'></i> Image Preview</h5>";
    echo "<img src='".htmlspecialchars($fullPathToFile)."' alt='Uploaded image' class='img-thumbnail' style='max-height: 300px;'>";
    echo "</div>";
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
    
    $result = $runCommand('outguess -r '.escapeshellarg($fullPathToFile), 'outguess');
    $module['outguess'] = $result['output'] ?? '';
    $module['outguess_error'] = $result['error'] ?? null;
    
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

    // Process and display module results
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
    
    foreach ($module as $moduleName => $output) {
        $skip = false;
        $collapse = false;
        $extraOut = "";
        $icon = $moduleIcons[$moduleName] ?? 'bi-gear';
        $hasError = false;
        $errorMessage = '';

        // Skip error entries and password fields
        if (strpos($moduleName, '_error') !== false || $moduleName == 'steghideP') {
            continue;
        }
        
        // Check for tool errors
        $errorKey = $moduleName . '_error';
        if (isset($module[$errorKey])) {
            $hasError = true;
            $errorMessage = $module[$errorKey];
            $missingTools[] = $moduleName;
            // Still show the module but with error message
        } else {
            if ($moduleName != 'steghideP') {
                $availableTools[] = $moduleName;
            }
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
        
        // Skip empty outputs unless it's a special case or has an error (we want to show errors)
        if (empty($output) && $moduleName != 'stegoveritas' && $moduleName != 'foremost' && !$hasError) {
            $skip = true;
        }

        if ($skip) continue;

        $totalModules++;
        if (!empty($output) || !empty($extraOut)) {
            $successfulModules++;
        }

        // Format output
        $formattedOutput = "";
        $alertClass = $hasError ? 'alert-warning' : '';
        
        if ($hasError) {
            $formattedOutput = '<div class="alert alert-warning mb-3"><i class="bi bi-exclamation-triangle"></i> <strong>Tool Not Available:</strong> '.htmlspecialchars($errorMessage).'</div>';
        } elseif ($collapse) {
            $outputContent = !empty($output) ? htmlspecialchars($output) : 'No output';
            $formattedOutput = '
            <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#'.$moduleName.'" aria-expanded="false">
            <i class="bi bi-chevron-down"></i> Show Output
            </button>
            <div class="collapse" id="'.$moduleName.'">
            <pre class="code-block p-3 rounded">'.$outputContent.'</pre>
            </div>
            ';
        } else {
            if (!empty($output)) {
                $formattedOutput = '<div class="bg-light p-3 rounded mb-3"><pre class="code-block p-3 rounded">'.htmlspecialchars($output).'</pre></div>';
            }
        }

        $headerBg = $hasError ? 'bg-warning text-dark' : 'bg-primary text-white';
        
        echo "
        <div class='card shadow-lg mb-4'>
        <div class='card-header $headerBg'>
            <h5 class='mb-0'><i class='bi $icon me-2'></i> ".ucfirst(str_replace(['steghide', 'Info', 'E'], ['Steghide ', 'Info', 'Extraction'], $moduleName)).($hasError ? ' <span class="badge bg-danger">Not Installed</span>' : '')."</h5>
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
    
    if (!empty($missingTools)) {
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
        
        // Create reverse mapping for display
        $reverseMapping = [];
        foreach ($missingTools as $tool) {
            $toolKey = $toolMapping[$tool] ?? strtolower($tool);
            if (!isset($reverseMapping[$toolKey])) {
                $reverseMapping[$toolKey] = [];
            }
            $reverseMapping[$toolKey][] = $tool;
        }
        
        echo "<div class='alert alert-warning mb-3'>";
        echo "<h5 class='mb-3'><i class='bi bi-exclamation-triangle'></i> Missing Tools</h5>";
        echo "<p class='mb-2'>The following analysis tools are not installed on the server:</p>";
        echo "<ul class='mb-3'>";
        foreach ($missingTools as $tool) {
            $toolName = ucfirst(str_replace(['steghide', 'Info', 'E'], ['Steghide ', 'Info', 'Extraction'], $tool));
            echo "<li><strong>$toolName</strong></li>";
        }
        echo "</ul>";
        
        // Show installation instructions
        if ($packageManager !== 'unknown' && !empty($installCommands)) {
            echo "<div class='card bg-light mb-3'>";
            echo "<div class='card-header'><strong><i class='bi bi-terminal'></i> Installation Commands (Detected: $packageManager)</strong></div>";
            echo "<div class='card-body'>";
            echo "<p class='mb-3'>Copy and paste these commands into your terminal (requires root/sudo access):</p>";
            
            foreach ($toolKeys as $toolKey) {
                if (isset($installCommands[$toolKey])) {
                    // Get display names for this tool key
                    $displayNames = $reverseMapping[$toolKey] ?? [$toolKey];
                    $toolName = ucfirst(str_replace(['steghide', 'Info', 'E'], ['Steghide ', 'Info', 'Extraction'], $displayNames[0]));
                    $cmd = $installCommands[$toolKey];
                    echo "<div class='mb-3'>";
                    echo "<label class='form-label fw-bold'>$toolName:</label>";
                    echo "<div class='input-group'>";
                    echo "<input type='text' class='form-control font-monospace' value='sudo $cmd' readonly id='cmd_$toolKey'>";
                    echo "<button class='btn btn-outline-secondary' type='button' onclick='copyToClipboard(\"cmd_$toolKey\")'><i class='bi bi-clipboard'></i> Copy</button>";
                    echo "</div>";
                    echo "</div>";
                }
            }
            
            // Auto-install button (if server allows)
            echo "<hr>";
            echo "<div class='alert alert-info mb-0'>";
            echo "<strong><i class='bi bi-info-circle'></i> Auto-Install Option:</strong> ";
            echo "<button type='button' class='btn btn-sm btn-primary ms-2' onclick='attemptInstall()' id='autoInstallBtn'>";
            echo "<i class='bi bi-download'></i> Attempt Auto-Install (requires sudo access)";
            echo "</button>";
            echo "<div id='installStatus' class='mt-2'></div>";
            echo "</div>";
            
            echo "</div></div>";
        } else {
            echo "<div class='alert alert-info mb-0'>";
            echo "<p class='mb-2'><strong>Manual Installation Required</strong></p>";
            echo "<p class='mb-0'><small>Package manager not detected or installation commands not available. Please refer to README.md for manual installation instructions.</small></p>";
            echo "</div>";
        }
        
        echo "</div>";
    }
    
    echo "<div class='alert alert-info mb-0'>";
    echo "<h5 class='mb-2'><i class='bi bi-check-circle'></i> Analysis Complete</h5>";
    echo "<p class='mb-0'>Ran <strong>$successfulModules</strong> of <strong>$totalModules</strong> analysis modules successfully.";
    if (!empty($missingTools)) {
        echo " <strong>".count($missingTools)."</strong> tool(s) not available.";
    }
    echo "</p>";
    echo "</div>";
    echo "</div></div>";

    // Schedule cleanup
    shell_exec('python3 deleteafter.py '.escapeshellarg($outputFolder).' '.DELETE_AFTER.' > /dev/null 2>&1 &');
    
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

// Copy to clipboard function
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