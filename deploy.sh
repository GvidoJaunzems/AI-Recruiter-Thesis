#!/bin/bash

# ----------------------
# Deployment Script for Azure
# ----------------------

echo "Starting deployment process..."

# Install npm packages
echo "Installing npm packages..."
npm install

# Build the frontend
echo "Building frontend..."
cd frontend
npm install
npm run build
cd ..

# Copy backend files to the right location
echo "Setting up backend files..."
if [ ! -d "backend" ]; then
  mkdir -p backend
fi

# Copy fallback JSON files
cp -r frontend/public/backend/*.json backend/

echo "Deployment completed successfully!"

# Exit with success code
exit 0 