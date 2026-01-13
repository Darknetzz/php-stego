<?php

// ─────────────────────────────────────────────────────────────────────────────────────────────── #
//                                            ADDERROR                                             #
// ─────────────────────────────────────────────────────────────────────────────────────────────── #
function addError(string $error) : array {
    global $errors;

    if (empty($errors)) {
        $errors = [];
    }

    array_push($errors, $error);
    return $errors;
}


// ─────────────────────────────────────────────────────────────────────────────────────────────── #
//                                          OUTPUTERRORS                                           #
// ─────────────────────────────────────────────────────────────────────────────────────────────── #
function outputErrors() {
    global $errors;

    $output = [];

    if (empty($errors)) {
        return null;
    }

    foreach ($errors as $error) {
        array_push($output, "<div class='alert alert-danger'>$error</div>");
    }

    return implode('<br>', $output);
}

// ─────────────────────────────────────────────────────────────────────────────────────────────── #
//                                           UPLOADCHECK                                           #
// ─────────────────────────────────────────────────────────────────────────────────────────────── #
function uploadCheck(bool $condition, string $error = "Could not upload your file") {

    # this might not be neccessary since we die here anyway?
    global $uploadOk;
    $uploadOk = false;

    if ($condition != true) {
        $errors = addError($error);
        $errorOutput = outputErrors();
        
        // Output error in the current page context
        if (!empty($errorOutput)) {
            echo "<div class='card shadow-lg mb-4'>";
            echo "<div class='card-body p-4'>";
            echo $errorOutput;
            echo "</div></div>";
        } else {
            echo "<div class='card shadow-lg mb-4'>";
            echo "<div class='card-body p-4'>";
            echo "<div class='alert alert-danger mb-0'><h5><i class='bi bi-exclamation-triangle'></i> Error</h5><p class='mb-0'>".htmlspecialchars($error)."</p></div>";
            echo "</div></div>";
        }
        
        exit();
    }
}

// ─────────────────────────────────────────────────────────────────────────────────────────────── #
//                                           UPLOADIMAGE                                           #
// ─────────────────────────────────────────────────────────────────────────────────────────────── #
function uploadImage() {

    try {
        // Check if file was actually uploaded
        if (!isset($_FILES["fileToUpload"]) || !is_uploaded_file($_FILES["fileToUpload"]["tmp_name"])) {
            uploadCheck(false, "No file was uploaded or file upload failed. Please try again.");
            return false;
        }

        # Check that a file is being uploaded
        uploadCheck(!empty($_FILES["fileToUpload"]["name"]), "The fileToUpload is empty.");

        $name     = $_FILES["fileToUpload"]["name"];
        $tmp_name = $_FILES["fileToUpload"]["tmp_name"];
        $size     = $_FILES["fileToUpload"]["size"];
        $size_mb  = round($size / 1000000, 2);

        // Sanitize filename
        $name = basename($name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        
        $target_dir = "";
        $target_file = $target_dir . $name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $uploadOk = true;
        
        // Generate checksum for folder name
        $checksum = md5_file($tmp_name);
        if ($checksum === false) {
            uploadCheck(false, "Unable to generate file checksum.");
        }
        
        // Ensure upload directory exists
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                uploadCheck(false, "Unable to create upload directory: ".UPLOAD_DIR);
            }
        }
        
        $outputFolder = UPLOAD_DIR."/".$checksum;
        $fileNameOnServer = $checksum.".".$imageFileType;
        $fullPathToFile = $outputFolder."/".$fileNameOnServer;

        # Make sure we remove the folder if it's already there
        if (is_dir($outputFolder)) {
            $rmResult = shell_exec("rm -rf ".escapeshellarg($outputFolder)." 2>&1");
            // Wait a moment for deletion to complete
            usleep(500000); // 0.5 seconds
        }

        # Create output folder
        if (!is_dir($outputFolder)) {
            if (!mkdir($outputFolder, 0755, true)) {
                uploadCheck(false, "Unable to create output directory: ".$outputFolder);
            }
        }

        # Move the file
        $move_file = move_uploaded_file($tmp_name, $fullPathToFile);
        uploadCheck($move_file, "Unable to move file ".$tmp_name." to ".$fullPathToFile);

        uploadCheck(file_exists($fullPathToFile), "Your file was uploaded, but couldn't be moved to its destination. Please try again.");

        # Verify that it's actually an image
        $imageInfo = @getimagesize($fullPathToFile);
        uploadCheck($imageInfo !== false, "File is not a valid image or image is corrupted.");
        
        # Additional validation - check MIME type
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        if (isset($imageInfo['mime']) && !in_array(strtolower($imageInfo['mime']), $allowedMimes)) {
            uploadCheck(false, "Invalid image MIME type: ".$imageInfo['mime']);
        }

        // Display upload information (moved to index.php for better formatting)
        // File info is now shown in the main interface

        // $fileNameOnServer = sha1($target_file).".".$imageFileType;
        // $outputFolder = substr($fileNameOnServer,0,10).substr(sha1(date('Y-m-d H:i:s')), 0,4);

        // uploadCheck(!file_exists($outputFolder), "File is already uploaded: <a href='$checksum'>$checksum</a>");

        $maxSizeMB = round(MAX_FILESIZE_BYTES / 1000000, 2);
        $fileSizeMB = round($size / 1000000, 2);
        uploadCheck($size < MAX_FILESIZE_BYTES, "Sorry, your file is too large ($fileSizeMB MB). Maximum filesize: $maxSizeMB MB");

        uploadCheck(in_array($imageFileType, ALLOWED_FILE_EXTENSIONS), "Sorry, only the following image formats are allowed: ".implode(', ', ALLOWED_FILE_EXTENSIONS).". Your file: .$imageFileType");

        # Make sure we quit execution, and inform the user that the upload failed
        if ($uploadOk !== true) {
            addError("There was an error uploading your file. You should see the relevant errors on this page. Exiting...");
            outputErrors();
            die();
        }

        return $fullPathToFile;

    } catch (Throwable $t) {
        echo "<div class='card shadow-lg mb-4'>";
        echo "<div class='card-body p-4'>";
        echo "<div class='alert alert-danger mb-0'>";
        echo "<h5 class='mb-2'><i class='bi bi-exclamation-triangle'></i> Upload Function Error</h5>";
        echo "<p>".htmlspecialchars($t->getMessage())."</p>";
        echo "<pre class='bg-dark text-light p-3 rounded overflow-auto' style='max-height: 400px;'>";
        echo htmlspecialchars($t->getTraceAsString());
        echo "</pre>";
        echo "</div>";
        echo "</div></div>";
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────────────────────── #
//                                         FORMATBYTES                                             #
// ─────────────────────────────────────────────────────────────────────────────────────────────── #
function formatBytes($bytes, $precision = 2) {
    if ($bytes < 0) {
        return '0 B';
    }
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// ─────────────────────────────────────────────────────────────────────────────────────────────── #
//                                         INSTALLTOOLS                                            #
// ─────────────────────────────────────────────────────────────────────────────────────────────── #
function installTools($tools) {
    $results = [];
    $commands = [];
    
    // Detect package manager
    $packageManager = null;
    $aptResult = shell_exec('which apt-get 2>/dev/null');
    $yumResult = shell_exec('which yum 2>/dev/null');
    $dnfResult = shell_exec('which dnf 2>/dev/null');
    $pacmanResult = shell_exec('which pacman 2>/dev/null');
    $brewResult = shell_exec('which brew 2>/dev/null');
    
    if (!empty(trim($aptResult ?? ''))) {
        $packageManager = 'apt';
    } elseif (!empty(trim($yumResult ?? ''))) {
        $packageManager = 'yum';
    } elseif (!empty(trim($dnfResult ?? ''))) {
        $packageManager = 'dnf';
    } elseif (!empty(trim($pacmanResult ?? ''))) {
        $packageManager = 'pacman';
    } elseif (!empty(trim($brewResult ?? ''))) {
        $packageManager = 'brew';
    }
    
    if (!$packageManager) {
        return ['error' => 'Could not detect package manager. Manual installation required.'];
    }
    
    // Tool installation commands
    $installCommands = [
        'stegoveritas' => [
            'apt' => 'apt-get update && apt-get install -y python3-pip && pip3 install stegoveritas',
            'yum' => 'yum install -y python3-pip && pip3 install stegoveritas',
            'dnf' => 'dnf install -y python3-pip && pip3 install stegoveritas',
            'pacman' => 'pacman -S --noconfirm python-pip && pip install stegoveritas',
            'brew' => 'brew install python3 && pip3 install stegoveritas',
            'manual' => 'pip3 install stegoveritas'
        ],
        'foremost' => [
            'apt' => 'apt-get update && apt-get install -y foremost',
            'yum' => 'yum install -y foremost',
            'dnf' => 'dnf install -y foremost',
            'pacman' => 'pacman -S --noconfirm foremost',
            'brew' => 'brew install foremost',
            'manual' => 'See https://github.com/korczis/foremost'
        ],
        'steghide' => [
            'apt' => 'apt-get update && apt-get install -y steghide',
            'yum' => 'yum install -y steghide',
            'dnf' => 'dnf install -y steghide',
            'pacman' => 'pacman -S --noconfirm steghide',
            'brew' => 'brew install steghide',
            'manual' => 'See https://github.com/StefanoDeVuono/steghide'
        ],
        'outguess' => [
            'apt' => 'apt-get update && apt-get install -y outguess',
            'yum' => 'yum install -y outguess',
            'dnf' => 'dnf install -y outguess',
            'pacman' => 'pacman -S --noconfirm outguess',
            'brew' => 'brew install outguess',
            'manual' => 'See https://github.com/crorvick/outguess'
        ],
        'exiv2' => [
            'apt' => 'apt-get update && apt-get install -y exiv2',
            'yum' => 'yum install -y exiv2',
            'dnf' => 'dnf install -y exiv2',
            'pacman' => 'pacman -S --noconfirm exiv2',
            'brew' => 'brew install exiv2',
            'manual' => 'See https://exiv2.org'
        ],
        'exiftool' => [
            'apt' => 'apt-get update && apt-get install -y libimage-exiftool-perl',
            'yum' => 'yum install -y perl-Image-ExifTool',
            'dnf' => 'dnf install -y perl-Image-ExifTool',
            'pacman' => 'pacman -S --noconfirm perl-image-exiftool',
            'brew' => 'brew install exiftool',
            'manual' => 'See https://exiftool.org/install.html'
        ],
        'binwalk' => [
            'apt' => 'apt-get update && apt-get install -y binwalk',
            'yum' => 'yum install -y binwalk',
            'dnf' => 'dnf install -y binwalk',
            'pacman' => 'pacman -S --noconfirm binwalk',
            'brew' => 'brew install binwalk',
            'manual' => 'pip3 install binwalk'
        ]
    ];
    
    foreach ($tools as $tool) {
        // Tool is already a key (stegoveritas, foremost, etc.)
        if (isset($installCommands[$tool])) {
            $cmd = $installCommands[$tool][$packageManager] ?? ($installCommands[$tool]['manual'] ?? '');
            if ($cmd) {
                $commands[$tool] = $cmd;
            }
        }
    }
    
    return [
        'packageManager' => $packageManager,
        'commands' => $commands
    ];
}

function attemptAutoInstall($tool) {
    // Check if we can use sudo (this is a security-sensitive operation)
    // In production, you might want to disable this or require admin approval
    // $tool should already be a tool key (stegoveritas, foremost, etc.)
    
    $installInfo = installTools([$tool]);
    if (isset($installInfo['error'])) {
        return ['error' => $installInfo['error']];
    }
    
    $cmd = $installInfo['commands'][$tool] ?? null;
    if (!$cmd) {
        return ['error' => "No installation command available for $tool"];
    }
    
    // Try to run with sudo (will fail if no sudo access)
    $fullCommand = "sudo $cmd 2>&1";
    $output = shell_exec($fullCommand);
    
    // Verify installation
    $checkCmd = "which " . escapeshellarg($toolKey) . " 2>/dev/null";
    $result = shell_exec($checkCmd);
    $installed = !empty(trim($result ?? ''));
    
    return [
        'success' => $installed,
        'output' => $output ?? '',
        'command' => $fullCommand
    ];
}

  ?>