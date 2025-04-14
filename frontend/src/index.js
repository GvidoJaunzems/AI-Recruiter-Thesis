import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import { AuthProvider } from './contexts/AuthContext';
import axios from 'axios';

// Configure axios defaults
// Set the base URL for all API requests
axios.defaults.baseURL = '/backend'; 

// Ensure default Content-Type is NOT set - Let axios handle it
// axios.defaults.headers.common['Content-Type'] = 'application/json'; // Stays removed

// Add token to requests if it exists (Request Interceptor)
axios.interceptors.request.use((config) => {
  // Add Authorization header if token exists
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  
  // Do NOT manually set Content-Type here.
  
  return config;
});

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <AuthProvider>
      <App />
    </AuthProvider>
  </React.StrictMode>
); 