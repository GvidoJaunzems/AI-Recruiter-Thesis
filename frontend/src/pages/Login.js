import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Container,
  Paper,
  Typography,
  TextField,
  Button,
  Box,
  Alert,
  Tabs,
  Tab,
} from '@mui/material';
import { useAuth } from '../contexts/AuthContext';

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState(0); // 0 for user, 1 for admin
  const navigate = useNavigate();
  const { login } = useAuth();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const loginType = activeTab === 0 ? 'user' : 'admin';
      console.log(`Attempting ${loginType} login...`);
      
      const user = await login(email, password);
      
      console.log("Login successful, user data:", user);
      
      if (user && user.role === 'admin') {
        console.log("Admin user detected, navigating to /admin");
        navigate('/admin');
      } else {
        console.log("Non-admin user detected, navigating to /");
        navigate('/');
      }

    } catch (error) {
      const errorMessage = error.message || 'Login failed. Please check your credentials.';
      setError(errorMessage);
      console.error(`Login error:`, error);
    } finally {
      setLoading(false);
    }
  };

  const handleTabChange = (event, newValue) => {
    setActiveTab(newValue);
    setError(''); // Clear errors when switching tabs
  };

  return (
    <Container maxWidth="sm" sx={{ mt: 8 }}>
      <Paper elevation={3} sx={{ p: 4 }}>
        <Typography variant="h4" component="h1" gutterBottom align="center">
          Login
        </Typography>
        
        <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 2 }}>
          <Tabs value={activeTab} onChange={handleTabChange} centered>
            <Tab label="User Login" />
            <Tab label="Admin Login" />
          </Tabs>
        </Box>
        
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        
        <form onSubmit={handleSubmit}>
          <TextField
            fullWidth
            label="Email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            margin="normal"
            required
            autoComplete="email"
          />
          <TextField
            fullWidth
            label="Password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            margin="normal"
            required
            autoComplete="current-password"
          />
          <Box sx={{ mt: 3 }}>
            <Button
              fullWidth
              variant="contained"
              color={activeTab === 0 ? "primary" : "secondary"}
              type="submit"
              disabled={loading}
            >
              {loading ? 'Logging in...' : activeTab === 0 ? 'Login' : 'Admin Login'}
            </Button>
          </Box>
        </form>
      </Paper>
    </Container>
  );
}

export default Login; 