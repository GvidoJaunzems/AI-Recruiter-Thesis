// API URL configuration - use relative paths
// Set to empty string because axios default baseURL handles the '/backend' prefix
const API_ENDPOINT = ''; 
export const API_URL = API_ENDPOINT;

// Log the API URL to help with debugging
console.log('Using API URL (BaseURL handles prefix):', API_URL);
console.log('Current environment:', process.env.NODE_ENV);

// Other global configuration settings can be added here
export const APP_NAME = 'Recruiter App';
export const APP_VERSION = '1.0.0'; 