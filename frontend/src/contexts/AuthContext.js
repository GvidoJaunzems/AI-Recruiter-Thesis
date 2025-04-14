import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { API_URL } from '../config';
import { jwtDecode } from 'jwt-decode';

// Create the Auth context
export const AuthContext = createContext(null);

// Custom hook to use the auth context
export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

// Authentication provider component
export const AuthProvider = ({ children }) => {
  const [currentUser, setCurrentUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isAdmin, setIsAdmin] = useState(false);
  const [token, setToken] = useState(localStorage.getItem('token')); // Initialize token from localStorage

  // Define logout function with useCallback
  const logout = useCallback(() => {
    console.log("Logging out...");
    localStorage.removeItem('token');
    setCurrentUser(null);
    setIsAdmin(false);
    setToken(null); // Clear token state
    // Optionally clear other session-related state
  }, []);

  // Helper to get Authorization header
  const getAuthHeader = useCallback(() => {
    const token = localStorage.getItem('token');
    return token ? { 'Authorization': `Bearer ${token}` } : {};
  }, []);

  // Fetch user data with the stored token
  const fetchUserData = useCallback(async () => {
    setLoading(true);
    setError(null);
    const currentToken = localStorage.getItem('token'); // Use current token from storage
    if (!currentToken) {
        console.log("No token found, skipping user fetch.");
        setCurrentUser(null);
        setIsAdmin(false);
        setToken(null); // Ensure token state is also cleared
        setLoading(false);
        return;
    }

    // Update token state if it differs (e.g., initial load)
    setToken(currentToken);

    try {
      const url = `${API_URL}/user_profile.php`;
      console.log('Fetching user profile from:', url);

      const response = await axios.get(url, {
        headers: { 'Authorization': `Bearer ${currentToken}` } // Use currentToken for the request
      });

      const userData = response.data;
      console.log("User data fetched:", userData);
      setCurrentUser(userData);
      setIsAdmin(userData && userData.role === 'admin'); 

    } catch (err) {
      console.error('Error fetching user data:', err.response || err);
      if (err.response && (err.response.status === 401 || err.response.status === 404)) {
        console.log("Token invalid or expired, logging out.");
        logout(); 
      } else {
        setError('Failed to fetch user data.');
      }
      setCurrentUser(null); 
      setIsAdmin(false);
      setToken(null); // Clear token state on error too
    } finally {
      setLoading(false);
    }
  }, [logout]); // Removed getAuthHeader dependency as we use localStorage directly

  // Check for authenticated user on load
  useEffect(() => {
    console.log("AuthProvider mounted, checking token...");
    const storedToken = localStorage.getItem('token');
    if (storedToken) {
        try {
            const decoded = jwtDecode(storedToken);
            const currentTime = Date.now() / 1000;
            if (decoded.exp < currentTime) {
                console.log("Token expired, removing.");
                logout(); // Use logout which clears state
                setLoading(false);
            } else {
                console.log("Token exists and is not expired, fetching user data...");
                setToken(storedToken); // Set token state immediately
                fetchUserData().finally(() => setLoading(false));
            }
        } catch (error) {
            console.error("Error decoding token:", error);
            logout(); // Use logout which clears state
            setLoading(false);
        }
    } else {
        console.log("No token found.");
        setCurrentUser(null);
        setToken(null); // Ensure token state is null
        setLoading(false);
    }
  }, [fetchUserData, logout]); // Added logout dependency

  // Login user
  const login = async (loginIdentifier, password) => {
    try {
      setLoading(true);
      setError(null);
      const url = `${API_URL}/auth_login.php`;
      console.log('Logging in user at:', url, 'with identifier:', loginIdentifier);

      const response = await axios.post(url, {
        loginIdentifier, 
        password
      });

      const { token: receivedToken, user } = response.data;
      console.log("Login successful, received token:", receivedToken);
      console.log("Login successful, received user data:", user);
      localStorage.setItem('token', receivedToken);
      setToken(receivedToken); // Set the token state

      setCurrentUser(user);
      setIsAdmin(user && user.role === 'admin'); 
      
      setLoading(false);
      return user; 

    } catch (err) {
      console.error('Login error:', err.response || err);
      const errorMessage = err.response?.data?.error || 'Login failed. Please check your credentials.';
      setError(errorMessage);
      setLoading(false);
      setCurrentUser(null); 
      setIsAdmin(false);
      setToken(null); // Clear token state on failure
      throw new Error(errorMessage);
    }
  };

  // Register user
  const registerUser = async (userData) => {
    console.log('Registering user with data:', userData);
    try {
      const apiUrl = `${API_URL}/auth_register.php`;
      console.log('API URL for registration:', apiUrl);
      const response = await axios.post(apiUrl, userData);
      console.log('Registration response:', response.data);

      if ((response.status === 201 || response.status === 200) && response.data.token && response.data.user) {
        console.log("Registration successful, auto-logging in...");
        const receivedToken = response.data.token;
        localStorage.setItem('token', receivedToken); 
        setToken(receivedToken); // Set the token state
        setCurrentUser(response.data.user); 
        axios.defaults.headers.common['Authorization'] = `Bearer ${receivedToken}`; 
        return { success: true, user: response.data.user }; 
      } else if (response.status === 201 || response.status === 200) {
         console.warn("Registration succeeded but backend didn't return token/user for auto-login.");
         return { success: true, user: null }; 
      } else {
        console.warn("Registration resulted in unexpected status:", response.status);
        return { success: false, error: response.data.error || 'Registration failed with unexpected status' };
      }
    } catch (error) {
      console.error('Registration failed:', error);
      const errorMessage = error.response?.data?.error || error.message || 'An unknown error occurred during registration.';
      return { success: false, error: errorMessage }; 
    }
  };

  // Context value
  const value = {
    currentUser, 
    loading,
    error,
    isAdmin, 
    token, // <-- Add token state to the context value
    login,
    logout,
    registerUser,
    fetchUserData, 
    getAuthHeader 
    // Remove adminLogin from exposed context
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}; 