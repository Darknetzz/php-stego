#!/bin/bash
# Quick one-liner installation script - All tools
# Usage: sudo bash install-all.sh

# Detect package manager and install all tools
if command -v apt-get &> /dev/null; then
    echo "Detected: apt (Debian/Ubuntu)"
    apt-get update && \
    apt-get install -y python3-pip foremost steghide outguess exiv2 libimage-exiftool-perl binwalk && \
    pip3 install stegoveritas || echo "Some tools may need manual installation"
elif command -v yum &> /dev/null; then
    echo "Detected: yum (RHEL/CentOS)"
    yum install -y python3-pip foremost steghide outguess exiv2 perl-Image-ExifTool binwalk && \
    pip3 install stegoveritas || echo "Some tools may need manual installation"
elif command -v dnf &> /dev/null; then
    echo "Detected: dnf (Fedora)"
    dnf install -y python3-pip foremost steghide outguess exiv2 perl-Image-ExifTool binwalk && \
    pip3 install stegoveritas || echo "Some tools may need manual installation"
elif command -v pacman &> /dev/null; then
    echo "Detected: pacman (Arch Linux)"
    pacman -S --noconfirm python-pip foremost steghide outguess exiv2 perl-image-exiftool binwalk && \
    pip install stegoveritas || echo "Some tools may need manual installation"
else
    echo "Package manager not detected. Please use install-tools.sh for detailed installation."
    exit 1
fi

echo "Installation complete! Verifying..."
for tool in foremost steghide outguess exiv2 exiftool binwalk strings xxd; do
    if command -v $tool &> /dev/null; then
        echo "✓ $tool"
    else
        echo "✗ $tool (not found)"
    fi
done

if command -v stegoveritas &> /dev/null || python3 -m stegoveritas --version &> /dev/null; then
    echo "✓ stegoveritas"
else
    echo "✗ stegoveritas (check: python3 -m stegoveritas --version)"
fi
