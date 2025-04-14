import React, { useState, useEffect, useContext } from 'react';
import { useParams, Link as RouterLink, useNavigate } from 'react-router-dom';
import {
  Container, Paper, Typography, Box, Grid, Chip, Divider,
  Button, CircularProgress, Alert, List, ListItem, ListItemIcon, ListItemText
} from '@mui/material';
import { styled } from '@mui/material/styles';
import WorkIcon from '@mui/icons-material/Work';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import AttachMoneyIcon from '@mui/icons-material/AttachMoney';
import BusinessIcon from '@mui/icons-material/Business';
import DateRangeIcon from '@mui/icons-material/DateRange';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { AuthContext } from '../contexts/AuthContext';
import axios from 'axios';
import { API_URL } from '../config';

// Styled components
const JobDetailsPaper = styled(Paper)(({ theme }) => ({
  padding: theme.spacing(4),
  marginTop: theme.spacing(3),
  marginBottom: theme.spacing(3),
}));

const JobDetails = () => {
  const { id } = useParams();
  const { currentUser, getHeaders } = useContext(AuthContext);
  const [job, setJob] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [applied, setApplied] = useState(false);
  const navigate = useNavigate();

  // Fetch job details
  useEffect(() => {
    const fetchJobDetails = async () => {
      try {
        setLoading(true);
        console.log(`Fetching job details for ID: ${id} from: ${API_URL}/jobs.php`);
        
        // First try to get the full job list and filter for the one we want
        const response = await axios.get(`${API_URL}/jobs.php`);
        console.log('Response data:', response.data);
        
        if (response.data && response.data.jobs) {
          // Find the job with the matching ID
          const foundJob = response.data.jobs.find(job => job.id === Number(id));
          
          if (foundJob) {
            console.log('Found job:', foundJob);
            setJob(foundJob);
          } else {
            console.error('Job not found in the response data');
            setError('Job not found.');
          }
        } else {
          console.error('Unexpected response format:', response.data);
          setError('Failed to load job details. Unexpected response format.');
        }
        
        // Check if user has already applied for this job
        if (currentUser) {
          try {
            const appliedResponse = await axios.get(
              `${API_URL}/applications.php`,
              { headers: getHeaders() }
            );
            
            if (appliedResponse.data && appliedResponse.data.applications) {
              // Check if user has applied to this specific job
              const hasApplied = appliedResponse.data.applications.some(
                app => app.job_id === Number(id) && app.user_id === currentUser.id
              );
              
              setApplied(hasApplied);
            }
          } catch (err) {
            console.error('Error checking application status:', err);
          }
        }
        
      } catch (err) {
        console.error('Error fetching job details:', err);
        setError('Failed to load job details. Please try again later.');
      } finally {
        setLoading(false);
      }
    };
    
    if (id) {
      fetchJobDetails();
    }
  }, [id, currentUser, getHeaders]);

  // Format date
  const formatDate = (dateString) => {
    if (!dateString) return 'Not specified';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  // Split requirements into list items
  const parseRequirements = (requirements) => {
    if (!requirements) return ['No specific requirements listed.'];
    
    // Check if requirements is already an array
    if (Array.isArray(requirements)) {
      return requirements;
    }
    
    // Try to split by line breaks or bullet points
    const reqList = requirements
      .split(/[\n•]+/)
      .map(item => item.trim())
      .filter(item => item.length > 0);
    
    return reqList.length > 0 ? reqList : [requirements];
  };

  return (
    <Container maxWidth="md">
      <Button 
        startIcon={<ArrowBackIcon />} 
        onClick={() => navigate('/jobs')}
        sx={{ mb: 2 }}
      >
        Back to Jobs
      </Button>

      {loading ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', mt: 8 }}>
          <CircularProgress />
        </Box>
      ) : error ? (
        <Alert severity="error" sx={{ mt: 4 }}>{error}</Alert>
      ) : job ? (
        <JobDetailsPaper>
          <Box sx={{ mb: 3 }}>
            <Typography variant="h4" gutterBottom>
              {job.title}
            </Typography>
            <Grid container spacing={2} alignItems="center">
              <Grid item>
                <Chip
                  icon={<BusinessIcon />}
                  label={job.company}
                  color="primary"
                  variant="outlined"
                />
              </Grid>
              <Grid item>
                <Chip
                  icon={<LocationOnIcon />}
                  label={job.location}
                  variant="outlined"
                />
              </Grid>
              {job.salary_range && (
                <Grid item>
                  <Chip
                    icon={<AttachMoneyIcon />}
                    label={job.salary_range}
                    variant="outlined"
                  />
                </Grid>
              )}
              <Grid item>
                <Chip
                  icon={<DateRangeIcon />}
                  label={`Posted: ${formatDate(job.created_at)}`}
                  variant="outlined"
                />
              </Grid>
            </Grid>
          </Box>

          <Divider sx={{ my: 3 }} />

          <Box sx={{ mb: 4 }}>
            <Typography variant="h5" gutterBottom>
              Job Description
            </Typography>
            <Typography variant="body1" paragraph>
              {job.description}
            </Typography>
          </Box>

          <Box sx={{ mb: 4 }}>
            <Typography variant="h5" gutterBottom>
              Requirements
            </Typography>
            <List>
              {parseRequirements(job.requirements).map((req, index) => (
                <ListItem key={index} sx={{ py: 0.5 }}>
                  <ListItemIcon sx={{ minWidth: 32 }}>
                    <CheckCircleIcon color="primary" fontSize="small" />
                  </ListItemIcon>
                  <ListItemText primary={req} />
                </ListItem>
              ))}
            </List>
          </Box>

          <Divider sx={{ my: 3 }} />

          <Box sx={{ display: 'flex', justifyContent: 'center', mt: 4 }}>
            {applied ? (
              <Alert severity="info" sx={{ width: '100%', mb: 2 }}>
                You have already applied for this position. You can check your application status in your applications dashboard.
              </Alert>
            ) : currentUser ? (
              <Box>
                <Button
                  variant="contained"
                  color="primary"
                  component={RouterLink}
                  to={`/apply/${job.id}`}
                  startIcon={<WorkIcon />}
                  sx={{ mr: 2 }}
                >
                  Apply Now
                </Button>
              </Box>
            ) : (
              <Button
                variant="contained"
                color="primary"
                component={RouterLink}
                to={`/login?redirect=/job/${job.id}`}
              >
                Login to Apply
              </Button>
            )}
          </Box>
        </JobDetailsPaper>
      ) : (
        <Alert severity="warning" sx={{ mt: 4 }}>Job not found.</Alert>
      )}
    </Container>
  );
};

export default JobDetails; 