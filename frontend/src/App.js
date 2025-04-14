import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, useNavigate } from 'react-router-dom';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { Box, Button, Typography } from '@mui/material';
import Navbar from './components/Navbar';
import Home from './pages/Home';
import Jobs from './pages/Jobs';
import JobDetails from './pages/JobDetails';
import Login from './pages/Login';
import Register from './pages/Register';
import Apply from './pages/Apply';
import AdminDashboard from './pages/AdminDashboard';
import AdminDashboardRed from './pages/AdminDashboardRed';
import Profile from './pages/Profile';
import NotFound from './pages/NotFound';
import ApplicationForm from './pages/ApplicationForm';
import Applications from './pages/Applications';
import AdminApplications from './pages/AdminApplications';
import ApplicationReview from './pages/ApplicationReview';
import ProtectedRoute from './components/ProtectedRoute';
import { AuthProvider } from './contexts/AuthContext';
import TransparencyInfo from './pages/TransparencyInfo';
import AdminFairnessDashboard from './pages/AdminFairnessDashboard';

// Define themes
const blueTheme = createTheme({
  palette: {
    primary: {
      main: '#1976d2',
    },
    secondary: {
      main: '#dc004e',
    },
    mode: 'light',
  },
});

const redTheme = createTheme({
  palette: {
    primary: {
      main: '#d32f2f',
    },
    secondary: {
      main: '#c62828',
    },
    mode: 'light',
  },
});

// Define the Footer component separately
const AppFooter = ({ currentMode, setCurrentMode }) => {
  const navigate = useNavigate(); // OK here, will be inside Router context

  const handleSwitchMode = () => {
    const nextMode = currentMode === 'blue' ? 'red' : 'blue';
    setCurrentMode(nextMode); // Update state lifted to App
    // Navigate to the corresponding admin route if currently on an admin page
    if (window.location.pathname.startsWith('/admin')) {
      navigate(nextMode === 'red' ? '/admin-red' : '/admin');
    }
    // Add similar logic here if other routes need mode-based navigation
  };

  return (
    <Box component="footer" sx={{ p: 2, mt: 'auto', backgroundColor: 'background.paper', textAlign: 'center' }}>
      <Button variant="outlined" onClick={handleSwitchMode}>
        Switch to {currentMode === 'blue' ? 'Red' : 'Blue'} Version
      </Button>
      <Typography variant="caption" display="block" sx={{ mt: 1 }}>
        Currently in: {currentMode.toUpperCase()} Mode
      </Typography>
    </Box>
  );
};

function App() {
  // Lift mode state and effect here
  const [currentMode, setCurrentMode] = useState(() => localStorage.getItem('appMode') || 'blue');

  useEffect(() => {
   localStorage.setItem('appMode', currentMode);
   document.body.className = currentMode === 'red' ? 'red-mode' : 'blue-mode';
  }, [currentMode]);

  // Select theme based on lifted state
  const theme = currentMode === 'red' ? redTheme : blueTheme;

  return (
    <AuthProvider>
      <Router> {/* Router now wraps ThemeProvider and main structure */} 
        <ThemeProvider theme={theme}>
          <CssBaseline />
          <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
            <Navbar />
            <Box component="main" sx={{ flexGrow: 1, py: 4 }}>
              <Routes>
                <Route path="/" element={<Home />} />
                <Route path="/jobs" element={<Jobs />} />
                <Route path="/job/:id" element={<JobDetails />} />
                <Route path="/login" element={<Login />} />
                <Route path="/register" element={<Register />} />
                <Route path="/apply/:jobId" element={<ProtectedRoute><Apply /></ProtectedRoute>} />
                <Route path="/apply-v2/:jobId" element={<ProtectedRoute><ApplicationForm /></ProtectedRoute>} />
                <Route path="/profile" element={<ProtectedRoute><Profile /></ProtectedRoute>} />
                <Route path="/my-applications" element={<ProtectedRoute><Applications /></ProtectedRoute>} />
                <Route path="/admin" element={<ProtectedRoute roles={['admin']}><AdminDashboard /></ProtectedRoute>} />
                <Route path="/admin-red" element={<ProtectedRoute roles={['admin']}><AdminDashboardRed /></ProtectedRoute>} />
                <Route path="/admin/applications" element={<ProtectedRoute roles={['admin']}><AdminApplications /></ProtectedRoute>} />
                <Route path="/admin/applications/:applicationId" element={<ProtectedRoute roles={['admin']}><ApplicationReview /></ProtectedRoute>} />
                <Route path="/admin/transparency" element={<ProtectedRoute roles={['admin']}><TransparencyInfo /></ProtectedRoute>} />
                <Route path="/admin/fairness" element={<ProtectedRoute roles={['admin']}><AdminFairnessDashboard /></ProtectedRoute>} />
                <Route path="*" element={<NotFound />} />
              </Routes>
            </Box>
            {/* Render the new footer component, passing state and setter */}
            <AppFooter currentMode={currentMode} setCurrentMode={setCurrentMode} />
          </Box>
        </ThemeProvider>
      </Router>
    </AuthProvider>
  );
}

export default App; 