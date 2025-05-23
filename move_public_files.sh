#!/bin/bash

# Script to move PHP files from /public to root directory
# Create a backup first
echo "Creating backup of current directory structure..."
mkdir -p backup_before_move
cp -r public backup_before_move/
cp -r includes backup_before_move/

# Move PHP files from public to root
echo "Moving PHP files from public to root..."
for file in public/*.php public/.htaccess; do
  if [ -f "$file" ]; then
    filename=$(basename "$file")
    if [ -f "$filename" ]; then
      echo "Warning: $filename already exists in root, backing up to ${filename}.bak"
      mv "$filename" "${filename}.bak"
    fi
    cp "$file" ./
    echo "Moved $file to ./$filename"
  fi
done

# Create directories if they don't exist
echo "Creating necessary directories..."
mkdir -p uploads/thumbnails
mkdir -p database

# Update file paths in moved files
echo "Checking file paths in moved files..."
for file in *.php; do
  # Replace relative paths to includes
  if grep -q "../includes" "$file"; then
    sed -i '' 's|../includes|includes|g' "$file"
    echo "Updated paths in $file"
  fi
done

echo "File movement completed. Please verify everything works correctly."
echo "You can find a backup of your original files in the backup_before_move directory."
