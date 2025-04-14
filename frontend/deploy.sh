#!/bin/bash

# Install dependencies
npm install

# Build the React application
npm run build

# Create a deployment directory if it doesn't exist
mkdir -p ../public

# Copy the built files to the public directory
cp -r build/* ../public/

echo "Frontend deployment completed!" 