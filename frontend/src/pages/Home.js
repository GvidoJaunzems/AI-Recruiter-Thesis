import React, { useState, useEffect } from 'react';
import {
  Container,
  Typography,
  Button,
  Box,
  Grid,
  Card,
  CardContent,
  CardActions,
  CircularProgress,
  Alert,
} from '@mui/material';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';
import { API_URL } from '../config';

function Home() {
  const navigate = useNavigate();
  const [featuredJobs, setFeaturedJobs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchJobs = async () => {
      setLoading(true);
      setError(null);
      try {
        const response = await axios.get(`${API_URL}/jobs.php`);
        const allJobs = response.data.jobs || [];
        setFeaturedJobs(allJobs.slice(0, 3));
        console.log('Fetched featured jobs from main endpoint:', allJobs.slice(0, 3));
      } catch (err) {
        setError('Failed to load featured jobs');
        console.error('Error fetching jobs for homepage:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchJobs();
  }, []);

  return (
    <Box>
      {/* Hero Section */}
      <Box
        sx={{
          bgcolor: 'primary.main',
          color: 'white',
          py: 8,
          mb: 6,
        }}
      >
        <Container>
          <Grid container spacing={4} alignItems="center">
            <Grid item xs={12} md={6}>
              <Typography variant="h2" component="h1" gutterBottom>
                Find Your Dream Job
              </Typography>
              <Typography variant="h5" sx={{ mb: 4 }}>
                Connect with top employers and opportunities that match your skills
              </Typography>
              <Button
                variant="contained"
                color="secondary"
                size="large"
                onClick={() => navigate('/jobs')}
              >
                Browse Jobs
              </Button>
            </Grid>
          </Grid>
        </Container>
      </Box>

      {/* Featured Jobs Section */}
      <Container>
        <Typography variant="h4" component="h2" gutterBottom>
          Featured Jobs
        </Typography>
        {loading ? (
          <Box display="flex" justifyContent="center" my={4}>
            <CircularProgress />
          </Box>
        ) : error ? (
          <Alert severity="error" sx={{ my: 2 }}>
            {error}
          </Alert>
        ) : (
          <Grid container spacing={4}>
            {featuredJobs.length > 0 ? featuredJobs.map((job) => (
              <Grid item xs={12} md={4} key={job.id}>
                <Card sx={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
                  <CardContent sx={{ flexGrow: 1 }}>
                    <Typography variant="h6" gutterBottom noWrap title={job.title}>
                      {job.title}
                    </Typography>
                    <Typography color="textSecondary" gutterBottom>
                      {job.company}
                    </Typography>
                    <Typography variant="body2" color="textSecondary">
                      {job.location}
                    </Typography>
                    <Typography variant="body2" sx={{ mt: 1, height: '3em', overflow: 'hidden' }}>
                      {job.description ? job.description.substring(0, 100) + '...' : 'No description available.'}
                    </Typography>
                  </CardContent>
                  <CardActions>
                    <Button
                      size="small"
                      color="primary"
                      onClick={() => navigate(`/job/${job.id}`)}
                    >
                      View Details
                    </Button>
                  </CardActions>
                </Card>
              </Grid>
            )) : (
              <Grid item xs={12}>
                <Alert severity="info">No featured jobs available at the moment.</Alert>
              </Grid>
            )}
          </Grid>
        )}
      </Container>
    </Box>
  );
}

export default Home; 