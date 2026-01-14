#!/bin/bash
#
# Cleanup script to delete uploaded files and analysis results after a specified time.
# This script is called in the background to automatically clean up temporary files.
#

# Check if we have the required arguments
if [ $# -lt 2 ]; then
    exit 1
fi

objectToDelete="$1"
seconds="$2"

# Validate seconds is a number, default to 600 (10 minutes) if invalid
if ! [[ "$seconds" =~ ^[0-9]+$ ]]; then
    seconds=600
fi

# Validate path exists
if [ ! -e "$objectToDelete" ]; then
    exit 0  # Already deleted, nothing to do
fi

# Wait for specified time
sleep "$seconds"

# Delete the directory/file
if [ -d "$objectToDelete" ]; then
    rm -rf "$objectToDelete" 2>/dev/null
elif [ -f "$objectToDelete" ]; then
    rm -f "$objectToDelete" 2>/dev/null
fi

exit 0
