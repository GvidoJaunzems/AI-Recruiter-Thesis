/**
 * This script ensures that the backend JSON files are copied to the correct locations
 * for both development and production environments.
 */
const fs = require('fs');
const path = require('path');

// Define source and destination directories
const sourceDir = path.join(__dirname, 'frontend', 'public', 'backend');
const destDir1 = path.join(__dirname, 'backend');
const destDir2 = path.join(__dirname, 'frontend', 'build', 'backend');

// Create directories if they don't exist
console.log('Ensuring directories exist...');
if (!fs.existsSync(sourceDir)) {
  fs.mkdirSync(sourceDir, { recursive: true });
  console.log(`Created directory: ${sourceDir}`);
}

if (!fs.existsSync(destDir1)) {
  fs.mkdirSync(destDir1, { recursive: true });
  console.log(`Created directory: ${destDir1}`);
}

if (!fs.existsSync(destDir2)) {
  fs.mkdirSync(destDir2, { recursive: true });
  console.log(`Created directory: ${destDir2}`);
}

// Define the JSON files to copy
const jsonFiles = [
  'auth_register.json',
  'auth_login.json',
  'user_profile.json',
  'admin_login.json',
  'jobs.json',
  'applications.json'
];

// Create default content for each file if it doesn't exist
const defaultContent = {
  'auth_register.json': {
    "message": "Registration successful",
    "token": "eyJ1c2VyX2lkIjoxMjMsInVzZXJuYW1lIjoiZ3ZpZG9qYXUiLCJleHAiOjE3MDk5MjM2NDMsInJvbGUiOiJ1c2VyIn0=",
    "user": {
      "id": 123,
      "username": "mockuser",
      "email": "user@example.com",
      "first_name": "Mock",
      "last_name": "User",
      "role": "user",
      "created_at": "2023-03-08 12:00:00"
    }
  },
  'auth_login.json': {
    "message": "Login successful",
    "token": "eyJ1c2VyX2lkIjoxMjMsImVtYWlsIjoidXNlckBleGFtcGxlLmNvbSIsImV4cCI6MTcwOTkyMzY0Mywicm9sZSI6InVzZXIifQ==",
    "user": {
      "id": 123,
      "username": "mockuser",
      "email": "user@example.com",
      "first_name": "Mock",
      "last_name": "User",
      "role": "user",
      "created_at": "2023-03-08 12:00:00"
    }
  },
  'user_profile.json': {
    "id": 123,
    "username": "mockuser",
    "email": "user@example.com",
    "first_name": "Mock",
    "last_name": "User",
    "role": "user",
    "phone": "555-123-4567",
    "bio": "This is a mock user profile",
    "created_at": "2023-03-08 12:00:00"
  },
  'admin_login.json': {
    "message": "Login successful",
    "token": "eyJ1c2VyX2lkIjo5OTksImVtYWlsIjoiYWRtaW5AZXhhbXBsZS5jb20iLCJleHAiOjE3MDk5MjM2NDMsInJvbGUiOiJhZG1pbiJ9",
    "user": {
      "id": 999,
      "username": "admin",
      "email": "admin@example.com",
      "first_name": "Admin",
      "last_name": "User",
      "role": "admin",
      "created_at": "2023-03-08 12:00:00"
    }
  },
  'jobs.json': {
    "jobs": [
      {
        "id": 1,
        "title": "Frontend Developer",
        "company": "Tech Solutions Inc.",
        "location": "New York, NY",
        "description": "We are seeking a skilled Frontend Developer to join our dynamic team. The ideal candidate will have strong experience with React, JavaScript, and modern frontend frameworks.",
        "requirements": "3+ years of experience with React, JavaScript, HTML and CSS. Experience with state management like Redux or Context API. Knowledge of responsive design principles.",
        "salary_range": "$80,000 - $110,000",
        "created_at": "2023-03-10 10:00:00"
      },
      {
        "id": 2,
        "title": "Backend Engineer",
        "company": "DataFlow Systems",
        "location": "Remote",
        "description": "Join our backend team to develop scalable APIs and services. You'll work on challenging problems and help build the core infrastructure of our platform.",
        "requirements": "Experience with Node.js, Python, or Java. Knowledge of database systems like MySQL, PostgreSQL or MongoDB. Understanding of RESTful API design principles.",
        "salary_range": "$90,000 - $120,000",
        "created_at": "2023-03-12 14:30:00"
      },
      {
        "id": 3,
        "title": "Full Stack Developer",
        "company": "Web Innovators",
        "location": "San Francisco, CA",
        "description": "Looking for a versatile Full Stack Developer who can work across the entire stack. You'll be responsible for developing and maintaining both frontend and backend components.",
        "requirements": "Experience with JavaScript/TypeScript, React, Node.js. Knowledge of SQL and NoSQL databases. Understanding of CI/CD pipelines.",
        "salary_range": "$100,000 - $140,000",
        "created_at": "2023-03-15 09:45:00"
      }
    ],
    "message": "Jobs retrieved successfully"
  },
  'applications.json': {
    "applications": [
      {
        "id": 1,
        "job_id": 1,
        "user_id": 123,
        "user_name": "Mock User",
        "job_title": "Frontend Developer",
        "cover_letter": "I am excited to apply for the Frontend Developer position at Tech Solutions Inc. With my experience in React and modern JavaScript frameworks, I believe I would be a great addition to your team.",
        "resume_url": "https://example.com/resumes/123456.pdf",
        "status": "Pending",
        "created_at": "2023-03-15 11:30:00"
      },
      {
        "id": 2,
        "job_id": 2,
        "user_id": 124,
        "user_name": "Jane Smith",
        "job_title": "Backend Engineer",
        "cover_letter": "My background in building scalable APIs and services with Node.js makes me a strong candidate for the Backend Engineer position at DataFlow Systems.",
        "resume_url": "https://example.com/resumes/234567.pdf",
        "status": "Reviewing",
        "created_at": "2023-03-16 14:45:00"
      },
      {
        "id": 3,
        "job_id": 3,
        "user_id": 125,
        "user_name": "John Doe",
        "job_title": "Full Stack Developer",
        "cover_letter": "As a Full Stack Developer with experience in both frontend and backend technologies, I am excited about the opportunity to join Web Innovators and contribute to your innovative projects.",
        "resume_url": "https://example.com/resumes/345678.pdf",
        "status": "Approved",
        "created_at": "2023-03-17 09:15:00"
      }
    ],
    "message": "Applications retrieved successfully"
  }
};

// Create and copy each JSON file
jsonFiles.forEach(file => {
  // Check if the source file exists, if not create it
  const sourcePath = path.join(sourceDir, file);
  if (!fs.existsSync(sourcePath)) {
    fs.writeFileSync(
      sourcePath, 
      JSON.stringify(defaultContent[file], null, 2),
      'utf8'
    );
    console.log(`Created file: ${sourcePath}`);
  }

  // Copy to backend directory
  const destPath1 = path.join(destDir1, file);
  fs.copyFileSync(sourcePath, destPath1);
  console.log(`Copied to: ${destPath1}`);

  // Copy to build/backend directory
  const destPath2 = path.join(destDir2, file);
  try {
    fs.copyFileSync(sourcePath, destPath2);
    console.log(`Copied to: ${destPath2}`);
  } catch (err) {
    console.log(`Note: Could not copy to build directory (may not exist yet): ${destPath2}`);
  }
});

// Add PHP fallbacks that return JSON
const phpEndpoints = [
  { name: 'auth_register.php', json: 'auth_register.json' },
  { name: 'auth_login.php', json: 'auth_login.json' },
  { name: 'user_profile.php', json: 'user_profile.json' },
  { name: 'jobs.php', json: 'jobs.json' },
  { name: 'applications.php', json: 'applications.json' },
  { name: 'job_delete.php', json: 'jobs.json' }
];

phpEndpoints.forEach(endpoint => {
  const phpContent = `<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// This is a fallback PHP script that simply returns the JSON from ${endpoint.json}
$jsonFile = __DIR__ . '/${endpoint.json}';

if (file_exists($jsonFile)) {
    echo file_get_contents($jsonFile);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'JSON file not found']);
}
?>`;

  const phpPath = path.join(destDir1, endpoint.name);
  fs.writeFileSync(phpPath, phpContent, 'utf8');
  console.log(`Created PHP fallback: ${phpPath}`);

  // Copy to public/backend as well
  const publicPhpPath = path.join(sourceDir, endpoint.name);
  fs.writeFileSync(publicPhpPath, phpContent, 'utf8');
  console.log(`Created PHP fallback in public: ${publicPhpPath}`);
});

console.log('Backend files setup complete!'); 