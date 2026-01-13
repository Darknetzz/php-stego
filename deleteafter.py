#!/usr/bin/env python3
"""
Cleanup script to delete uploaded files and analysis results after a specified time.
This script is called in the background to automatically clean up temporary files.
"""

import sys
import os
import time
import shutil
import logging

# Configure logging (suppress output to avoid cluttering)
logging.basicConfig(level=logging.ERROR)

def main():
    if len(sys.argv) < 3:
        sys.exit(1)
    
    objectToDelete = sys.argv[1]
    try:
        seconds = int(sys.argv[2])
    except (ValueError, IndexError):
        seconds = 600  # Default 10 minutes
    
    # Validate path exists
    if not os.path.exists(objectToDelete):
        sys.exit(0)  # Already deleted, nothing to do
    
    # Wait for specified time
    time.sleep(seconds)
    
    # Delete the directory/file
    try:
        if os.path.isdir(objectToDelete):
            shutil.rmtree(objectToDelete, ignore_errors=True)
        elif os.path.isfile(objectToDelete):
            os.remove(objectToDelete)
    except Exception:
        # Silent failure - log if needed but don't crash
        pass

if __name__ == "__main__":
    main()