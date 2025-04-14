const express = require('express');
const path = require('path');
// Removed fs, bodyParser, child_process, etc. as they are not needed for this simplified server

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
// Serve static files from the React build directory
// IMPORTANT: Ensure this matches the output directory of your build process
app.use(express.static(path.join(__dirname, 'frontend/build')));

// --- Removed API Route Handling --- 
// In an Azure PHP App Service environment, the web server (Apache/Nginx)
// is typically configured to handle requests to .php files directly.
// The Node.js server is not needed to proxy or execute PHP.

// --- React Frontend Catch-all Route --- 
// Handles any requests that don't match static files
// Serves the main index.html file, allowing React Router to handle routing.
app.get('*', (req, res) => {
  const indexPath = path.join(__dirname, 'frontend/build/index.html');
  console.log(`Serving index.html for path: ${req.path}`);
  res.sendFile(indexPath, (err) => {
    if (err) {
      console.error("Error sending index.html:", err);
      res.status(500).send(err);
    }
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`Node.js server (for static files) listening on port ${PORT}`);
}); 