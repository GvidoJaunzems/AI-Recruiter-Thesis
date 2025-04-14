import React, { useState, useEffect, useContext } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Container, Paper, Typography, TextField, Button, Box, CircularProgress,
  Alert, Divider
} from '@mui/material';
import { styled } from '@mui/material/styles';
import { AuthContext } from '../contexts/AuthContext';
import axios from 'axios';
import { API_URL } from '../config';

// Styled components
const FormPaper = styled(Paper)(({ theme }) => ({
  padding: theme.spacing(4),
  marginTop: theme.spacing(3),
  marginBottom: theme.spacing(3),
}));

const Apply = () => {
  const { jobId } = useParams();
  const navigate = useNavigate();
  const { currentUser, token } = useContext(AuthContext);
  const [job, setJob] = useState(null);
  const [coverLetter, setCoverLetter] = useState('');
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);

  // Fetch job details
  useEffect(() => {
    const fetchJobDetails = async () => {
      if (!jobId) {
        console.error('No job ID provided');
        setError('Missing job ID');
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        console.log(`Fetching job details for application: ${jobId} (type: ${typeof jobId})`);
        
        // First try to fetch all jobs and filter for the one we want
        const response = await axios.get(`${API_URL}/jobs.php`);
        console.log('Jobs response data:', response.data);
        
        if (response.data && response.data.jobs) {
          // Make sure we treat the ID in a consistent way to avoid type mismatches
          // Convert both to strings for comparison
          const targetId = String(jobId).trim();
          console.log(`Looking for job with ID: ${targetId}`);
          
          // Find the job regardless of ID type by converting to strings for comparison
          const foundJob = response.data.jobs.find(job => {
            const jobIdString = String(job.id).trim();
            const matches = jobIdString === targetId;
            console.log(`Comparing job ${job.title} - ID: "${jobIdString}" with "${targetId}" - Match: ${matches}`);
            return matches;
          });
          
          if (foundJob) {
            console.log('Found job for application:', foundJob);
            setJob(foundJob);
          } else {
            console.error('Job not found in the response data. Available jobs:', 
              response.data.jobs.map(j => `ID: ${j.id} - ${j.title}`).join(', '));
            setError('Job not found. Please go back to the jobs list.');
          }
        } else if (response.data && !response.data.error) {
          // Direct response with a single job
          console.log('Single job response:', response.data);
          setJob(response.data);
        } else {
          console.error('Unexpected response format:', response.data);
          setError('Failed to load job details. Unexpected response format.');
        }
        
        setLoading(false);
      } catch (err) {
        console.error('Error fetching job details for application:', err);
        setError('Failed to load job details. Please try again later.');
        setLoading(false);
      }
    };
    
    fetchJobDetails();
  }, [jobId]);

  // Handle file input change
  const handleFileChange = (e) => {
    if (e.target.files.length > 0) {
      setFile(e.target.files[0]);
    }
  };

  // Submit application
  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!currentUser) {
      setError('You must be logged in to apply');
      return;
    }
    
    if (!file) {
      setError('Please upload your resume');
      return;
    }

    if (!job) {
      setError('Job information is missing. Please try again.');
      return;
    }
    
    try {
      setSubmitting(true);
      setError('');
      
      // Log job details before submission
      console.log('Submitting application for job:', job);
      
      // Create form data for file upload
      const formData = new FormData();
      formData.append('resume', file);
      formData.append('cover_letter', coverLetter);
      
      // Ensure job id is properly converted to string
      const jobId = job.id ? String(job.id).trim() : '';
      formData.append('job_id', jobId);
      
      console.log(`Submitting application for job ID: "${jobId}", title: "${job.title}"`);
      
      // Construct correct headers with real token
      const headers = { 
        Authorization: `Bearer ${token}` 
        // Let Axios handle Content-Type for FormData
      };
      console.log('Using Authorization header:', headers);

      // Submit application - Use correct endpoint and headers
      const response = await axios.post(
        `${API_URL}/applications.php`, // Correct endpoint
        formData,
        { 
          headers: headers // Use correct headers with real token
        }
      );
      
      console.log('Application submission response:', response.data);
      
      setSuccess(true);
      setTimeout(() => {
        navigate('/applications');
      }, 3000);
      
    } catch (err) {
      console.error('Application submission error:', err);
      const errorMessage = err.response?.data?.error || 'Failed to submit application. Please try again.';
      console.error('Error details:', errorMessage);
      setError(errorMessage);
    } finally {
      setSubmitting(false);
    }
  };
  
  // Success message
  if (success) {
    return (
      <Container maxWidth="md">
        <FormPaper>
          <Box textAlign="center" py={4}>
            <Typography variant="h5" gutterBottom>
              Application Submitted Successfully!
            </Typography>
            <Typography variant="body1" paragraph>
              Thank you for your application. You will be redirected to your applications page shortly.
            </Typography>
            <CircularProgress />
          </Box>
        </FormPaper>
      </Container>
    );
  }

  return (
    <Container maxWidth="md">
      <FormPaper>
        {loading ? (
          <Box textAlign="center" py={4}>
            <CircularProgress />
          </Box>
        ) : error ? (
          <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>
        ) : (
          <>
            <Typography variant="h4" gutterBottom align="center">
              Quick Apply
            </Typography>
            
            <Box textAlign="center" mb={3}>
              <Typography variant="subtitle1" color="text.secondary">
                Looking for a more comprehensive application process?
              </Typography>
              <Button
                onClick={() => navigate(`/apply-v2/${jobId}`)}
                color="primary"
                sx={{ mt: 1 }}
              >
                Switch to Detailed Application
              </Button>
            </Box>
            
            <Divider sx={{ my: 3 }} />
            
            {job && (
              <Box mb={4}>
                <Typography variant="h5" gutterBottom>
                  {job.title}
                </Typography>
                <Typography variant="subtitle1" color="text.secondary" gutterBottom>
                  {job.company} • {job.location}
                </Typography>
              </Box>
            )}
            
            <form onSubmit={handleSubmit}>
              <Box mb={3}>
                <Typography variant="h6" gutterBottom>
                  Upload Resume
                </Typography>
                <input
                  accept=".pdf,.doc,.docx"
                  style={{ display: 'none' }}
                  id="resume-file"
                  type="file"
                  onChange={handleFileChange}
                />
                <label htmlFor="resume-file">
                  <Button
                    variant="outlined"
                    component="span"
                    fullWidth
                  >
                    {file ? `Selected: ${file.name}` : 'Select Resume (PDF, DOC, DOCX)'}
                  </Button>
                </label>
                <Typography variant="caption" color="text.secondary">
                  Max file size: 5MB
                </Typography>
              </Box>
              
              <Box mb={3}>
                <TextField
                  label="Cover Letter (Optional)"
                  multiline
                  rows={6}
                  fullWidth
                  variant="outlined"
                  value={coverLetter}
                  onChange={(e) => setCoverLetter(e.target.value)}
                  placeholder="Tell us why you're interested in this position and why you would be a good fit."
                />
              </Box>
              
              {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}
              
              <Button
                type="submit"
                variant="contained"
                color="primary"
                fullWidth
                size="large"
                disabled={submitting || !file}
              >
                {submitting ? <CircularProgress size={24} /> : 'Submit Application'}
              </Button>
            </form>
          </>
        )}
      </FormPaper>
    </Container>
  );
};

export default Apply; 