import React, { useState, useEffect, useContext } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
  Container, Paper, Typography, Box, Grid, Card, CardContent,
  Chip, LinearProgress, CircularProgress, Button, Alert, Link
} from '@mui/material';
import { styled } from '@mui/material/styles';
import { AuthContext } from '../contexts/AuthContext';
import axios from 'axios';
import { API_URL } from '../config';
import CandidateFeedbackModal from '../components/CandidateFeedbackModal';

// Styled components
const ApplicationCard = styled(Card)(({ theme }) => ({
  marginBottom: theme.spacing(2),
  transition: 'transform 0.2s',
  '&:hover': {
    transform: 'translateY(-4px)',
    boxShadow: theme.shadows[4],
  },
}));

const StatusChip = styled(Chip)(({ theme, status }) => {
  let color;
  switch (status) {
    case 'pending':
      color = theme.palette.warning.main;
      break;
    case 'approved':
      color = theme.palette.success.main;
      break;
    case 'rejected':
      color = theme.palette.error.main;
      break;
    default:
      color = theme.palette.info.main;
  }
  return {
    backgroundColor: color,
    color: '#fff',
    fontWeight: 500,
  };
});

const Applications = () => {
  const { getAuthHeader } = useContext(AuthContext);
  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // State for feedback modal
  const [feedbackModalOpen, setFeedbackModalOpen] = useState(false);
  const [selectedApplicationId, setSelectedApplicationId] = useState(null);

  // Fetch user's applications
  useEffect(() => {
    const fetchApplications = async () => {
      try {
        setLoading(true);
        setError(''); // Clear previous errors
        // Use getAuthHeader from context
        const headers = getAuthHeader();
        console.log("MyApplications Fetch: Using headers:", headers);
        if (!headers) {
          console.error("MyApplications Fetch: No headers found, user likely not authenticated.");
          throw new Error("User not authenticated.");
        }

        const response = await axios.get(
          `${API_URL}/applications.php`,
          { headers } // Pass headers
        );

        // Check if response.data.applications exists and is an array
        if (response.data && Array.isArray(response.data.applications)) {
          setApplications(response.data.applications);
        } else {
          console.warn("Unexpected response structure:", response.data);
          setApplications([]); // Set to empty array if structure is wrong
          // Optionally set an error message
          // setError('Could not parse application data.');
        }

      } catch (err) {
        console.error("Error fetching applications:", err);
        const errorMsg = err.response?.data?.error || err.message || 'Failed to load your applications. Please try again later.';
        setError(errorMsg);
        setApplications([]); // Clear applications on error
      } finally {
        setLoading(false);
      }
    };

    fetchApplications();
  }, [getAuthHeader]); // Add getAuthHeader to dependency array

  // Format date
  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  // Get status text and color
  const getStatusDisplay = (status) => {
    switch (status) {
      case 'pending':
        return 'Pending Review';
      case 'approved':
        return 'Approved';
      case 'rejected':
        return 'Not Selected';
      case 'interview':
        return 'Interview Stage';
      default:
        return 'Unknown';
    }
  };

  // Calculate application progress
  const getApplicationProgress = (status) => {
    switch (status) {
      case 'pending':
        return 25;
      case 'screening':
        return 50;
      case 'interview':
        return 75;
      case 'approved':
        return 100;
      case 'rejected':
        return 100;
      default:
        return 0;
    }
  };

  const handleOpenFeedback = (applicationId) => {
    setSelectedApplicationId(applicationId);
    setFeedbackModalOpen(true);
  };

  const handleCloseFeedback = () => {
    setFeedbackModalOpen(false);
    setSelectedApplicationId(null);
  };

  return (
    <Container maxWidth="lg">
      <Box sx={{ mb: 4 }}>
        <Typography variant="h4" gutterBottom>
          My Applications
        </Typography>
        <Typography variant="body1" color="textSecondary" paragraph>
          Track the status of your job applications
        </Typography>
      </Box>

      {loading ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', my: 4 }}>
          <CircularProgress />
        </Box>
      ) : error ? (
        <Alert severity="error" sx={{ mb: 3, p: 2, mt: 2 }} variant="filled">
          <Typography variant="h6" component="div">Error Loading Applications</Typography>
          <Typography sx={{ mt: 1 }}>{error}</Typography>
          <Typography variant="caption" display="block" sx={{ mt: 1 }}>
            (If the error is 'User not authenticated' or similar, please try logging out and logging back in.)
          </Typography>
        </Alert>
      ) : applications.length === 0 ? (
        <Paper sx={{ p: 4, textAlign: 'center' }}>
          <Typography variant="h6" gutterBottom>
            You haven't applied to any jobs yet
          </Typography>
          <Typography variant="body1" paragraph>
            Browse available job opportunities and submit your first application.
          </Typography>
          <Button 
            component={RouterLink} 
            to="/jobs" 
            variant="contained" 
            color="primary"
            sx={{ mt: 2 }}
          >
            Browse Jobs
          </Button>
        </Paper>
      ) : (
        <Grid container spacing={3}>
          {applications.map((application) => (
            <Grid item xs={12} key={application.id}>
              <ApplicationCard>
                <CardContent>
                  <Grid container spacing={2}>
                    <Grid item xs={12} sm={8}>
                      <Typography variant="h5" component="h2" gutterBottom>
                        <Link
                          component={RouterLink}
                          to={`/jobs/${application.job_id}`}
                          color="inherit"
                          underline="hover"
                        >
                          {application.job_title || 'Job Title'}
                        </Link>
                      </Typography>
                      <Typography variant="subtitle1" color="textSecondary" gutterBottom>
                        {application.company_name || 'Company'} • {application.location || 'Location'}
                      </Typography>
                      <Typography variant="body2" paragraph>
                        Applied on: {formatDate(application.created_at)}
                      </Typography>
                      
                      <Box sx={{ mt: 2 }}>
                        <Typography variant="body2" gutterBottom>
                          Application Status:
                        </Typography>
                        <Box sx={{ display: 'flex', alignItems: 'center', mb: 1 }}>
                          <StatusChip 
                            label={getStatusDisplay(application.status)} 
                            status={application.status} 
                            size="small"
                          />
                        </Box>
                        <Box sx={{ width: '100%', mt: 1 }}>
                          <LinearProgress 
                            variant="determinate" 
                            value={getApplicationProgress(application.status)} 
                            sx={{ height: 8, borderRadius: 4 }}
                          />
                        </Box>
                      </Box>
                    </Grid>
                    
                    <Grid item xs={12} sm={4} sx={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
                      <Box>
                        <Typography variant="body2" sx={{ mb: 1 }}>
                          <strong>Last Updated:</strong> {formatDate(application.updated_at)}
                        </Typography>
                      </Box>
                      <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2, gap: 1 }}>
                        {application.status === 'rejected' && (
                          <Button 
                            variant="outlined" 
                            color="secondary" 
                            size="small"
                            onClick={() => handleOpenFeedback(application.id)}
                          >
                            View Feedback
                          </Button>
                        )}
                        <Button
                          variant="outlined"
                          color="primary"
                          component={RouterLink}
                          to={`/jobs/${application.job_id}`}
                          size="small"
                        >
                          View Job
                        </Button>
                      </Box>
                    </Grid>
                  </Grid>
                </CardContent>
              </ApplicationCard>
            </Grid>
          ))}
        </Grid>
      )}

      {/* Render the modal */}
      <CandidateFeedbackModal 
        open={feedbackModalOpen} 
        onClose={handleCloseFeedback} 
        applicationId={selectedApplicationId} 
      />
    </Container>
  );
};

export default Applications; 