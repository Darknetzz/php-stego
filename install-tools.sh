#!/bin/bash

# Steganography Analysis Tool - Installation Script
# This script installs all required tools for the steganography analysis application
# Run with: sudo bash install-tools.sh

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    print_error "Please run as root or with sudo"
    echo "Usage: sudo bash install-tools.sh"
    exit 1
fi

# Detect package manager
print_info "Detecting package manager..."
PACKAGE_MANAGER=""

if command -v apt-get &> /dev/null; then
    PACKAGE_MANAGER="apt"
    print_success "Detected: apt (Debian/Ubuntu)"
elif command -v yum &> /dev/null; then
    PACKAGE_MANAGER="yum"
    print_success "Detected: yum (RHEL/CentOS)"
elif command -v dnf &> /dev/null; then
    PACKAGE_MANAGER="dnf"
    print_success "Detected: dnf (Fedora)"
elif command -v pacman &> /dev/null; then
    PACKAGE_MANAGER="pacman"
    print_success "Detected: pacman (Arch Linux)"
elif command -v brew &> /dev/null; then
    PACKAGE_MANAGER="brew"
    print_warning "Detected: brew (macOS). Some tools may not be available."
else
    print_error "Could not detect package manager. Please install tools manually."
    exit 1
fi

# Update package lists
if [ "$PACKAGE_MANAGER" = "apt" ]; then
    print_info "Updating package lists..."
    apt-get update
elif [ "$PACKAGE_MANAGER" = "yum" ] || [ "$PACKAGE_MANAGER" = "dnf" ]; then
    print_info "Updating package lists..."
    $PACKAGE_MANAGER check-update || true
fi

# Installation functions
install_package() {
    local package=$1
    local tool_name=$2
    
    print_info "Installing $tool_name..."
    
    case $PACKAGE_MANAGER in
        apt)
            apt-get install -y "$package" || {
                print_error "Failed to install $tool_name ($package)"
                return 1
            }
            ;;
        yum)
            yum install -y "$package" || {
                print_error "Failed to install $tool_name ($package)"
                return 1
            }
            ;;
        dnf)
            dnf install -y "$package" || {
                print_error "Failed to install $tool_name ($package)"
                return 1
            }
            ;;
        pacman)
            pacman -S --noconfirm "$package" || {
                print_error "Failed to install $tool_name ($package)"
                return 1
            }
            ;;
        brew)
            brew install "$package" || {
                print_error "Failed to install $tool_name ($package)"
                return 1
            }
            ;;
    esac
    
    print_success "$tool_name installed successfully"
    return 0
}

install_pip_package() {
    local package=$1
    local tool_name=$2
    
    print_info "Installing $tool_name via pip..."
    
    if command -v pip3 &> /dev/null; then
        pip3 install "$package" || {
            print_error "Failed to install $tool_name via pip3"
            return 1
        }
    elif command -v pip &> /dev/null; then
        pip install "$package" || {
            print_error "Failed to install $tool_name via pip"
            return 1
        }
    else
        print_error "pip/pip3 not found. Installing python3-pip..."
        if [ "$PACKAGE_MANAGER" = "apt" ]; then
            apt-get install -y python3-pip
            pip3 install "$package" || return 1
        elif [ "$PACKAGE_MANAGER" = "yum" ] || [ "$PACKAGE_MANAGER" = "dnf" ]; then
            $PACKAGE_MANAGER install -y python3-pip
            pip3 install "$package" || return 1
        elif [ "$PACKAGE_MANAGER" = "pacman" ]; then
            pacman -S --noconfirm python-pip
            pip install "$package" || return 1
        elif [ "$PACKAGE_MANAGER" = "brew" ]; then
            brew install python3
            pip3 install "$package" || return 1
        else
            print_error "Cannot install pip. Please install python3-pip manually."
            return 1
        fi
    fi
    
    print_success "$tool_name installed successfully"
    return 0
}

# Track installation results
INSTALLED=()
FAILED=()

# Install system packages
print_info "========================================="
print_info "Installing system packages..."
print_info "========================================="

# Foremost
if install_package "foremost" "Foremost"; then
    INSTALLED+=("foremost")
else
    FAILED+=("foremost")
fi

# Steghide
if install_package "steghide" "Steghide"; then
    INSTALLED+=("steghide")
else
    FAILED+=("steghide")
fi

# Outguess
if install_package "outguess" "Outguess"; then
    INSTALLED+=("outguess")
else
    FAILED+=("outguess")
fi

# Exiv2
if install_package "exiv2" "Exiv2"; then
    INSTALLED+=("exiv2")
else
    FAILED+=("exiv2")
fi

# ExifTool (different package names for different systems)
print_info "Installing ExifTool..."
case $PACKAGE_MANAGER in
    apt)
        if install_package "libimage-exiftool-perl" "ExifTool"; then
            INSTALLED+=("exiftool")
        else
            FAILED+=("exiftool")
        fi
        ;;
    yum|dnf)
        if install_package "perl-Image-ExifTool" "ExifTool"; then
            INSTALLED+=("exiftool")
        else
            FAILED+=("exiftool")
        fi
        ;;
    pacman)
        if install_package "perl-image-exiftool" "ExifTool"; then
            INSTALLED+=("exiftool")
        else
            FAILED+=("exiftool")
        fi
        ;;
    brew)
        if install_package "exiftool" "ExifTool"; then
            INSTALLED+=("exiftool")
        else
            FAILED+=("exiftool")
        fi
        ;;
esac

# Binwalk
if install_package "binwalk" "Binwalk"; then
    INSTALLED+=("binwalk")
else
    # Try pip as fallback
    print_warning "Binwalk not available via package manager, trying pip..."
    if install_pip_package "binwalk" "Binwalk"; then
        INSTALLED+=("binwalk")
    else
        FAILED+=("binwalk")
    fi
fi

# Install Python packages
print_info "========================================="
print_info "Installing Python packages..."
print_info "========================================="

# Install Python pip if not available
if ! command -v pip3 &> /dev/null && ! command -v pip &> /dev/null; then
    print_info "Installing python3-pip..."
    case $PACKAGE_MANAGER in
        apt)
            apt-get install -y python3-pip
            ;;
        yum|dnf)
            $PACKAGE_MANAGER install -y python3-pip
            ;;
        pacman)
            pacman -S --noconfirm python-pip
            ;;
        brew)
            brew install python3
            ;;
    esac
fi

# Stegoveritas
if install_pip_package "stegoveritas" "Stegoveritas"; then
    INSTALLED+=("stegoveritas")
else
    FAILED+=("stegoveritas")
fi

# Check for tools that are usually pre-installed
print_info "========================================="
print_info "Checking pre-installed tools..."
print_info "========================================="

check_tool() {
    local tool=$1
    if command -v "$tool" &> /dev/null; then
        print_success "$tool is available"
        INSTALLED+=("$tool")
        return 0
    else
        print_warning "$tool is not available (usually pre-installed on Linux)"
        FAILED+=("$tool")
        return 1
    fi
}

# Strings (usually pre-installed)
check_tool "strings"

# xxd (usually pre-installed)
check_tool "xxd"

# Summary
echo ""
print_info "========================================="
print_info "Installation Summary"
print_info "========================================="

if [ ${#INSTALLED[@]} -gt 0 ]; then
    print_success "Successfully installed/available (${#INSTALLED[@]}):"
    for tool in "${INSTALLED[@]}"; do
        echo "  ✓ $tool"
    done
fi

echo ""

if [ ${#FAILED[@]} -gt 0 ]; then
    print_error "Failed or unavailable (${#FAILED[@]}):"
    for tool in "${FAILED[@]}"; do
        echo "  ✗ $tool"
    done
    echo ""
    print_warning "Some tools may need manual installation. Check the README.md for manual installation instructions."
else
    print_success "All tools installed successfully!"
fi

echo ""
print_info "Verifying installations..."
echo ""

# Verify installations
verify_tool() {
    local tool=$1
    if command -v "$tool" &> /dev/null; then
        local version_output
        version_output=$($tool --version 2>&1 | head -1 || $tool -v 2>&1 | head -1 || $tool -h 2>&1 | head -1 || echo "Installed")
        echo -e "${GREEN}✓${NC} $tool: $version_output"
        return 0
    else
        echo -e "${RED}✗${NC} $tool: Not found"
        return 1
    fi
}

# Verify critical tools
for tool in foremost steghide outguess exiv2 exiftool binwalk strings xxd; do
    verify_tool "$tool" || true
done

# Verify Python tools (they might be installed as modules)
if command -v stegoveritas &> /dev/null || python3 -m stegoveritas --version &> /dev/null; then
    echo -e "${GREEN}✓${NC} stegoveritas: Installed"
else
    echo -e "${RED}✗${NC} stegoveritas: Not found (check with: python3 -m stegoveritas --version)"
fi

echo ""
print_success "Installation script completed!"
print_info "You can now use the steganography analysis tool."
echo ""
