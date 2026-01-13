# Steganography Analysis Tool

A comprehensive web-based tool for analyzing images and detecting hidden messages, embedded files, and metadata using various steganography detection techniques.

## Features

- **Modern Web Interface**: Beautiful, responsive UI with drag-and-drop file upload
- **Multiple Analysis Tools**: Integrated with popular steganography detection tools
- **File Extraction**: Automatically extract embedded files and hidden data
- **Image Preview**: View uploaded images and extracted content
- **Download Support**: Download extracted files and analysis results
- **Auto-Cleanup**: Automatically removes uploaded files and analysis results after a configurable time period
- **Secure**: Input sanitization and secure file handling

## Supported File Types

- JPEG/JPG
- PNG
- GIF
- BMP
- WebP
- TIFF/TIF

## Analysis Tools

The tool integrates the following steganography analysis tools:

1. **Stegoveritas** - Deep analysis with multiple image transformations
2. **Foremost** - File carving and extraction
3. **Steghide** - Steghide steganography detection and extraction (with optional password)
4. **Outguess** - Outguess steganography detection
5. **Strings** - Text string extraction from binary files
6. **Exiv2** - EXIF metadata extraction
7. **ExifTool** - Comprehensive metadata analysis
8. **Binwalk** - Binary file analysis and embedded file detection
9. **xxd** - Hex dump analysis

## Requirements

### Server Requirements

- PHP 7.4 or higher
- Python 3 (for cleanup script)
- Web server (Apache/Nginx)

### Required Tools

The following command-line tools must be installed on your server:

- `stegoveritas` - [Installation Guide](https://github.com/bannsec/stegoVeritas)
- `foremost` - `apt-get install foremost` or `yum install foremost`
- `steghide` - `apt-get install steghide` or `yum install steghide`
- `outguess` - [Installation Guide](https://github.com/crorvick/outguess)
- `strings` - Usually pre-installed on Linux systems
- `exiv2` - `apt-get install exiv2` or `yum install exiv2`
- `exiftool` - [Installation Guide](https://exiftool.org/install.html)
- `binwalk` - `apt-get install binwalk` or `pip install binwalk`
- `xxd` - Usually pre-installed on Linux systems

## Installation

1. Clone or download this repository to your web server directory

```bash
git clone <repository-url> /var/www/html/stego
```

2. Set proper permissions on the uploads directory

```bash
mkdir -p uploads
chmod 755 uploads
chown www-data:www-data uploads  # Adjust user/group as needed
```

3. Ensure Python 3 is installed and accessible

```bash
python3 --version
```

4. Configure the application by editing `config.php`

```php
define('UPLOAD_DIR', 'uploads');  // Directory for uploads
define('DELETE_AFTER', 600);      // Auto-delete after 10 minutes
define('MAX_FILESIZE_BYTES', 10485760);  // 10MB max file size
```

5. Ensure all required tools are installed (see Requirements section)

6. Test the installation by accessing the tool in your web browser

## Configuration

Edit `config.php` to customize:

- **UPLOAD_DIR**: Directory where uploaded files and analysis results are stored (default: `uploads`)
- **DELETE_AFTER**: Time in seconds before auto-deletion (default: 600 = 10 minutes)
- **MAX_FILESIZE_BYTES**: Maximum upload file size (default: 10485760 = 10MB)
- **ALLOWED_FILE_EXTENSIONS**: Array of allowed file extensions
- **Tool Enable/Disable Flags**: Control which analysis tools are available

## Usage

1. Open the tool in your web browser
2. Upload an image file using drag-and-drop or file browser
3. (Optional) Enter a password if the image is protected with Steghide
4. Select which analysis tools to run (default: all enabled tools)
5. Click "Analyze Image"
6. Review the results:
   - View extracted files and download them
   - Check metadata information
   - Examine analysis reports
7. Files are automatically deleted after the configured time period

## File Structure

```
stego/
├── index.php              # Main application file
├── functions.php          # Core functions
├── config.php            # Configuration
├── deleteafter.py        # Cleanup script
├── .gitignore            # Git ignore rules
├── README.md             # This file
├── uploads/              # Upload directory (gitignored)
│   └── [md5-hash]/      # User upload folders
│       ├── [filename]   # Uploaded image
│       ├── stegoveritas/ # Stegoveritas results
│       └── foremost/     # Foremost extraction results
└── bin/                  # Additional tools
    └── stegsolve.jar     # Stegsolve Java tool
```

## Security Considerations

- **File Upload Limits**: Configure `MAX_FILESIZE_BYTES` to limit file sizes
- **Directory Permissions**: Ensure upload directory has proper permissions
- **Auto-Cleanup**: Files are automatically deleted after a configurable time period
- **Input Sanitization**: All user inputs are sanitized before use
- **Shell Command Escaping**: All shell commands use proper escaping
- **MIME Type Validation**: Files are validated by both extension and MIME type

## Troubleshooting

### Tool Not Found Errors

If you see errors about tools not being found:

1. Verify the tool is installed: `which toolname`
2. Check if the tool is in your PATH
3. Some tools may need full paths in the code

### Permission Errors

If you see permission errors:

1. Check directory permissions: `ls -la uploads`
2. Ensure web server user can write to uploads directory
3. Check PHP error logs for detailed error messages

### Files Not Auto-Deleting

If files are not being deleted:

1. Verify Python 3 is installed: `python3 --version`
2. Check if `deleteafter.py` has execute permissions: `chmod +x deleteafter.py`
3. Verify the cleanup script is being called (check web server logs)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

[Specify your license here]

## Credits

This tool integrates with the following open-source projects:

- Stegoveritas
- Foremost
- Steghide
- Outguess
- ExifTool
- Binwalk

## Support

For issues, questions, or feature requests, please open an issue on the repository.
