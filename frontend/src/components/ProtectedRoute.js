import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { CircularProgress, Box } from '@mui/material';

// Protected Route component to handle authentication and authorization
const ProtectedRoute = ({ children, roles, adminOnly }) => {
  const { currentUser, loading, isAdmin } = useAuth();

  // Show loading indicator while authentication state is being determined
  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '50vh' }}>
        <CircularProgress />
      </Box>
    );
  }

  // Redirect to login if not authenticated
  if (!currentUser) {
    return <Navigate to="/login" replace />;
  }

  // Check if admin access is required and user is not an admin
  if (adminOnly && !isAdmin) {
    return <Navigate to="/" replace />;
  }

  // Check if specific roles are required and user doesn't have required role
  if (roles && Array.isArray(roles) && roles.length > 0) {
    const userRole = isAdmin ? 'admin' : 'user';
    if (!roles.includes(userRole)) {
      return <Navigate to="/" replace />;
    }
  }

  // User is authenticated and authorized, render the protected content
  return children;
};

export default ProtectedRoute; 