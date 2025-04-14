import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Container,
  Grid,
  Card,
  CardContent,
  Typography,
  Button,
  Box,
  TextField,
  InputAdornment,
  CircularProgress,
  Alert,
} from '@mui/material';
import SearchIcon from '@mui/icons-material/Search';
import axios from 'axios';
import { API_URL } from '../config';

function Jobs() {
  const [jobs, setJobs] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    fetchJobs();
  }, []);

  const fetchJobs = async () => {
    try {
      setLoading(true);
      console.log('Fetching jobs from:', `${API_URL}/jobs.php`);
      
      try {
        // Try the PHP endpoint first
        const response = await axios.get(`${API_URL}/jobs.php`);
        console.log('Jobs response:', response.data);
        setJobs(response.data.jobs || []);
      } catch (apiErr) {
        console.error('PHP endpoint failed, trying JSON fallback');
        // If PHP endpoint fails, try the JSON file as fallback
        const fallbackUrl = `${API_URL}/jobs.json`;
        console.log('Using fallback URL:', fallbackUrl);
        
        const fallbackResponse = await axios.get(fallbackUrl);
        console.log('Fallback jobs response:', fallbackResponse.data);
        setJobs(fallbackResponse.data.jobs || []);
      }
    } catch (error) {
      console.error('Failed to fetch jobs:', error);
      setError('Unable to load jobs. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

  const filteredJobs = jobs.filter(job =>
    job.title?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    job.company?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    job.location?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Box sx={{ mb: 4 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          Available Jobs
        </Typography>
        <TextField
          fullWidth
          variant="outlined"
          placeholder="Search jobs..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <SearchIcon />
              </InputAdornment>
            ),
          }}
        />
      </Box>

      {loading ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', my: 4 }}>
          <CircularProgress />
        </Box>
      ) : error ? (
        <Alert severity="error" sx={{ my: 2 }}>{error}</Alert>
      ) : filteredJobs.length === 0 ? (
        <Alert severity="info" sx={{ my: 2 }}>No jobs found. Please check back later.</Alert>
      ) : (
        <Grid container spacing={3}>
          {filteredJobs.map((job) => (
            <Grid item xs={12} sm={6} md={4} key={job.id}>
              <Card>
                <CardContent>
                  <Typography variant="h6" component="h2" gutterBottom>
                    {job.title}
                  </Typography>
                  <Typography color="textSecondary" gutterBottom>
                    {job.company}
                  </Typography>
                  <Typography variant="body2" color="textSecondary" gutterBottom>
                    {job.location}
                  </Typography>
                  <Typography variant="body2" paragraph>
                    {job.description?.substring(0, 150)}...
                  </Typography>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="body2" color="textSecondary">
                      Posted: {job.created_at ? new Date(job.created_at).toLocaleDateString() : 'Recently'}
                    </Typography>
                    <Button
                      variant="contained"
                      color="primary"
                      onClick={() => navigate(`/job/${job.id}`)}
                    >
                      View Details
                    </Button>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}
    </Container>
  );
}

export default Jobs; 