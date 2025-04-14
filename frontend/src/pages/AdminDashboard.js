import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, Link as RouterLink } from 'react-router-dom';
import {
  Container,
  Paper,
  Typography,
  Button,
  Box,
  Grid,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  IconButton,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Alert,
  CircularProgress,
  Tabs,
  Tab,
  FormControlLabel,
  Checkbox,
  Select,
  MenuItem,
  InputLabel,
  FormControl,
} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import AddIcon from '@mui/icons-material/Add';
import InfoIcon from '@mui/icons-material/Info';
import AssessmentIcon from '@mui/icons-material/Assessment';
import { useAuth } from '../contexts/AuthContext';
import axios from 'axios';
import { API_URL } from '../config';
import AdapterDateFns from '@mui/lab/AdapterDateFns';
import LocalizationProvider from '@mui/lab/LocalizationProvider';
import DatePicker from '@mui/lab/DatePicker';

function AdminDashboard() {
  const navigate = useNavigate();
  const { currentUser, token } = useAuth();
  const [jobs, setJobs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [openDialog, setOpenDialog] = useState(false);
  const [editingJob, setEditingJob] = useState(null);
  const [activeTab, setActiveTab] = useState(0);
  
  // --- State for Applications Tab ---
  const [applications, setApplications] = useState([]);
  const [loadingApplications, setLoadingApplications] = useState(false);
  const [errorApplications, setErrorApplications] = useState('');

  // --- State for Application Filters ---
  const defaultFilters = {
    name: '',
    jobTitle: '',
    status: '',
    applyDateStart: null,
    applyDateEnd: null,
    skill: '',
    locationKeyword: '',
    minTotalYears: '',
    highestDegree: '',
    minExp: '',
    maxExp: '',
    education: '',
    relocate: false,
    sponsorship: false,
    minScore: '',
  };
  const [filters, setFilters] = useState(defaultFilters);

  // --- State for Sorting ---
  const defaultSortConfig = { field: 'created_at', direction: 'DESC' };
  const [sortConfig, setSortConfig] = useState(defaultSortConfig);

  // Function to handle filter changes (Modified to handle dates)
  const handleFilterChange = (eventOrDate, name) => {
    if (eventOrDate instanceof Date || eventOrDate === null) {
      setFilters(prevFilters => ({
        ...prevFilters,
        [name]: eventOrDate,
      }));
    } else {
      const { name: inputName, value, type, checked } = eventOrDate.target;
      setFilters(prevFilters => ({
        ...prevFilters,
        [inputName]: type === 'checkbox' ? checked : value,
      }));
    }
  };

  // --- Function to handle Sorting Change ---
  const handleSortChange = (event) => {
    const { name, value } = event.target;
    const newSortConfig = { ...sortConfig, [name]: value };
    setSortConfig(newSortConfig);
    fetchApplications(filters, newSortConfig);
  };

  const [formData, setFormData] = useState({
    title: '',
    company: '',
    location: '',
    description: '',
    requirements: '',
    salary_range: '',
  });

  // Fetch Jobs (existing function - no changes needed here for now)
  const fetchJobs = useCallback(async () => {
    // Ensure token exists before fetching
    if (!token) {
      setError('Authentication token not found.');
      setLoading(false);
      return;
    }
    try {
      setLoading(true);
      setError('');
      
      console.log('Fetching jobs data from:', `${API_URL}/jobs.php`);
      
      // Fetch jobs data only
      let jobsData = [];
      try {
        const jobsResponse = await axios.get(`${API_URL}/jobs.php`);
        console.log('Jobs response:', jobsResponse.data);
        jobsData = jobsResponse.data.jobs || [];
      } catch (apiErr) {
        console.error('PHP jobs endpoint failed, trying JSON fallback');
        try {
          const fallbackUrl = `${API_URL}/jobs.json`;
          console.log('Using fallback URL for jobs:', fallbackUrl);
          const fallbackResponse = await axios.get(fallbackUrl);
          console.log('Fallback jobs response:', fallbackResponse.data);
          jobsData = fallbackResponse.data.jobs || [];
        } catch (fallbackErr) {
          console.error('Failed to fetch jobs from fallback:', fallbackErr);
          jobsData = [];
        }
      }
            
      setJobs(jobsData);
      
    } catch (error) {
      console.error('Error fetching jobs data:', error);
      setError('Failed to load jobs. ' + (error.response?.data?.error || error.message || ''));
    } finally {
      setLoading(false);
    }
  }, [token]);

  // --- Fetch Applications Function (Modified) ---
  const fetchApplications = useCallback(async (currentFilters, currentSortConfig) => {
    // Ensure token exists
    if (!token) {
      setErrorApplications('Authentication token not found.');
      setLoadingApplications(false);
      return;
    }
    
    setLoadingApplications(true);
    setErrorApplications('');

    try {
      // Construct query parameters from filters
      const params = new URLSearchParams();
      
      // --- Standard SQL Filters ---
      if (currentFilters.name) params.append('name', currentFilters.name);
      if (currentFilters.jobTitle) params.append('jobTitle', currentFilters.jobTitle);
      if (currentFilters.status) params.append('status', currentFilters.status);
      if (currentFilters.applyDateStart) params.append('applyDateStart', currentFilters.applyDateStart.toISOString().split('T')[0]);
      if (currentFilters.applyDateEnd) params.append('applyDateEnd', currentFilters.applyDateEnd.toISOString().split('T')[0]);
      if (currentFilters.minExp) params.append('minExp', currentFilters.minExp);
      if (currentFilters.maxExp) params.append('maxExp', currentFilters.maxExp);
      if (currentFilters.education) params.append('education', currentFilters.education); // Keep the old SQL filter for non-AI education level
      if (currentFilters.relocate) params.append('relocate', 'true');
      if (currentFilters.sponsorship) params.append('sponsorship', 'true');
      if (currentFilters.minScore) params.append('minScore', currentFilters.minScore);

      // --- AI-Based PHP Filters (Corrected Parameter Names) ---
      if (currentFilters.skill) params.append('skill', currentFilters.skill); // Parameter name should be 'skill'
      if (currentFilters.locationKeyword) params.append('locationKeyword', currentFilters.locationKeyword); // Parameter name should be 'locationKeyword'
      if (currentFilters.minTotalYears) params.append('minTotalYears', currentFilters.minTotalYears); // Parameter name should be 'minTotalYears'
      if (currentFilters.highestDegree) params.append('highestDegree', currentFilters.highestDegree); // Parameter name should be 'highestDegree'

      // --- Sorting ---
      if (currentSortConfig && currentSortConfig.field) {
          params.append('sortField', currentSortConfig.field);
          params.append('sortDirection', currentSortConfig.direction || 'DESC');
      }
      
      const queryString = params.toString();
      const url = `${API_URL}/applications.php${queryString ? '?' + queryString : ''}`;
      console.log('Fetching applications from:', url);

      const response = await axios.get(url, {
        headers: { Authorization: `Bearer ${token}` }
      });

      console.log('Applications response:', response.data);
      setApplications(response.data.applications || []);

    } catch (error) {
      console.error('Error fetching applications:', error);
      setErrorApplications('Failed to load applications. ' + (error.response?.data?.error || error.message || ''));
      setApplications([]);
    } finally {
      setLoadingApplications(false);
    }
  }, [token]);

  // useEffect to check admin status and fetch initial data for the active tab
  useEffect(() => {
    if (!currentUser || currentUser.role !== 'admin') {
      console.log('User is not admin, redirecting to home');
      navigate('/');
      return;
    }

    if (activeTab === 0) {
      fetchJobs();
    } else if (activeTab === 1) {
      fetchApplications(filters, sortConfig);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentUser, navigate, activeTab, fetchJobs, fetchApplications]);

  const handleOpenDialog = (job = null) => {
    if (job) {
      setEditingJob(job);
      setFormData({
        title: job.title || '',
        company: job.company || '',
        location: job.location || '',
        description: job.description || '',
        requirements: job.requirements || '',
        salary_range: job.salary_range || ''
      });
    } else {
      setEditingJob(null);
      setFormData({
        title: '',
        company: '',
        location: '',
        description: '',
        requirements: '',
        salary_range: ''
      });
    }
    setOpenDialog(true);
  };

  const handleCloseDialog = () => {
    setOpenDialog(false);
    setEditingJob(null);
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      if (editingJob) {
        // TODO: Implement job update in a future enhancement
        alert('Job editing is not implemented yet');
      } else {
        console.log('Creating new job with data:', formData);
        console.log('POST URL:', `${API_URL}/jobs.php`);
        
        const response = await axios.post(`${API_URL}/jobs.php`, formData);
        console.log('Job creation response:', response.data);
        
        if (response.data && response.data.job) {
          handleCloseDialog();
          fetchJobs();
        }
      }
    } catch (error) {
      console.error('Error saving job:', error);
      setError('Failed to save job: ' + (error.response?.data?.error || ''));
    }
  };

  const handleDelete = async (jobId) => {
    if (window.confirm('Are you sure you want to delete this job?')) {
      try {
        console.log('Deleting job with ID:', jobId);
        console.log('DELETE URL:', `${API_URL}/job_delete.php?id=${jobId}`);
        
        await axios.delete(`${API_URL}/job_delete.php?id=${jobId}`);
        console.log('Job deleted successfully');
        
        // Refresh the jobs list after deletion
        fetchJobs();
      } catch (error) {
        console.error('Error deleting job:', error);
        setError('Failed to delete job: ' + (error.response?.data?.error || ''));
      }
    }
  };

  // --- Handlers for Application Filters (Modified) ---
  const handleApplyFilters = () => {
    fetchApplications(filters, sortConfig);
  };

  const handleResetFilters = () => {
    setFilters(defaultFilters);
    setSortConfig(defaultSortConfig);
    fetchApplications(defaultFilters, defaultSortConfig);
  };
  // --------------------------------------

  const handleTabChange = (event, newValue) => {
    setActiveTab(newValue);
    setError('');
  };

  // --- Render Helper for Applications Tab (Modified) ---
  const renderApplicationsTab = () => {
    if (loadingApplications) {
      return (
        <Box sx={{ display: 'flex', justifyContent: 'center', my: 4 }}>
          <CircularProgress />
        </Box>
      );
    }

    return (
      <Box>
        <Typography variant="h6" gutterBottom>Filter & Sort Applications</Typography>
        <Box sx={{ mb: 2, p: 2, border: '1px solid #ccc', borderRadius: '4px' }}>
          <Grid container spacing={2} alignItems="center">
             <Grid item xs={12} sm={6} md={3}>
                 <TextField 
                   label="Applicant Name" 
                   name="name" 
                   value={filters.name} 
                   onChange={handleFilterChange} 
                   fullWidth 
                   size="small"
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
                 <TextField 
                   label="Job Title" 
                   name="jobTitle" 
                   value={filters.jobTitle} 
                   onChange={handleFilterChange} 
                   fullWidth 
                   size="small"
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
              <Grid item xs={12} sm={6} md={3}>
                  <FormControl fullWidth size="small">
                    <InputLabel id="status-filter-label">Status</InputLabel>
                    <Select
                      labelId="status-filter-label"
                      label="Status"
                      name="status"
                      value={filters.status}
                      onChange={handleFilterChange}
                    >
                      <MenuItem value=""><em>Any</em></MenuItem>
                      <MenuItem value="pending">Pending</MenuItem>
                      <MenuItem value="reviewed">Reviewed</MenuItem>
                      <MenuItem value="accepted">Accepted</MenuItem>
                      <MenuItem value="rejected">Rejected</MenuItem>
                    </Select>
                  </FormControl>
              </Grid>
             <Grid item xs={12} sm={6} md={3}>
                <FormControl fullWidth size="small">
                  <InputLabel id="education-filter-label">Education Level</InputLabel>
                  <Select
                    labelId="education-filter-label"
                    label="Education Level"
                    name="education"
                    value={filters.education}
                    onChange={handleFilterChange}
                  >
                    <MenuItem value=""><em>Any</em></MenuItem>
                    <MenuItem value="high-school">High School</MenuItem>
                    <MenuItem value="associates">Associates</MenuItem>
                    <MenuItem value="bachelors">Bachelors</MenuItem>
                    <MenuItem value="masters">Masters</MenuItem>
                    <MenuItem value="phd">PhD</MenuItem>
                  </Select>
                </FormControl>
             </Grid>

             <Grid item xs={12} sm={6} md={3}>
                <TextField 
                   label="Min Experience" 
                   name="minExp" 
                   value={filters.minExp} 
                   onChange={handleFilterChange} 
                   type="number"
                   fullWidth 
                   size="small"
                   InputProps={{ inputProps: { min: 0 } }} 
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
                <TextField 
                   label="Max Experience" 
                   name="maxExp" 
                   value={filters.maxExp} 
                   onChange={handleFilterChange} 
                   type="number"
                   fullWidth 
                   size="small"
                   InputProps={{ inputProps: { min: 0 } }} 
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
              <Grid item xs={12} sm={6} md={3}>
                 <TextField 
                   label="Min AI Score" 
                   name="minScore" 
                   value={filters.minScore} 
                   onChange={handleFilterChange} 
                   type="number"
                   fullWidth 
                   size="small"
                   InputProps={{ inputProps: { min: 0, max: 100 } }} 
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
               <LocalizationProvider dateAdapter={AdapterDateFns}>
                  <DatePicker
                    label="Apply Date From"
                    value={filters.applyDateStart}
                    onChange={(newValue) => handleFilterChange(newValue, 'applyDateStart')}
                    renderInput={(params) => <TextField {...params} fullWidth size="small" InputLabelProps={{ shrink: true }} />}
                  />
                </LocalizationProvider>
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
               <LocalizationProvider dateAdapter={AdapterDateFns}>
                  <DatePicker
                    label="Apply Date To"
                    value={filters.applyDateEnd}
                    onChange={(newValue) => handleFilterChange(newValue, 'applyDateEnd')}
                    renderInput={(params) => <TextField {...params} fullWidth size="small" InputLabelProps={{ shrink: true }} />}
                  />
                </LocalizationProvider>
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
                <FormControlLabel 
                    control={<Checkbox checked={filters.relocate} onChange={handleFilterChange} name="relocate" />}
                    label="Willing to Relocate"
                 />
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
                <FormControlLabel 
                    control={<Checkbox checked={filters.sponsorship} onChange={handleFilterChange} name="sponsorship" />}
                    label="Requires Sponsorship"
                 />
             </Grid>

             {/* --- NEW AI Filter Inputs --- */}
             <Grid item xs={12}><hr /></Grid> {/* Separator */}
             <Grid item xs={12}><Typography variant="subtitle2">AI Extracted Data Filters:</Typography></Grid>

             <Grid item xs={12} sm={6} md={3}>
                 <TextField 
                   label="Skill (AI)" 
                   name="skill" 
                   value={filters.skill} 
                   onChange={handleFilterChange} 
                   fullWidth 
                   size="small"
                   helperText="Searches extracted skills"
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
                 <TextField 
                   label="Location Keyword (AI)" 
                   name="locationKeyword" 
                   value={filters.locationKeyword} 
                   onChange={handleFilterChange} 
                   fullWidth 
                   size="small"
                   helperText="Searches extracted locations"
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
                <TextField 
                   label="Min Total Years (AI)" 
                   name="minTotalYears" 
                   value={filters.minTotalYears} 
                   onChange={handleFilterChange} 
                   type="number"
                   fullWidth 
                   size="small"
                   helperText="Min total years (approx)"
                   InputProps={{ inputProps: { min: 0 } }} 
                   InputLabelProps={{ shrink: true }}
                />
             </Grid>
             <Grid item xs={12} sm={6} md={3}>
                <FormControl fullWidth size="small">
                  <InputLabel id="highest-degree-ai-filter-label">Highest Degree (AI)</InputLabel>
                  <Select
                    labelId="highest-degree-ai-filter-label"
                    label="Highest Degree (AI)"
                    name="highestDegree"
                    value={filters.highestDegree}
                    onChange={handleFilterChange}
                  >
                    <MenuItem value=""><em>Any</em></MenuItem>
                    <MenuItem value="high-school">High School</MenuItem>
                    <MenuItem value="associates">Associates</MenuItem>
                    <MenuItem value="bachelors">Bachelors</MenuItem>
                    <MenuItem value="masters">Masters</MenuItem>
                    <MenuItem value="phd">PhD</MenuItem>
                    {/* Add other potential values if needed */}
                  </Select>
                   <Typography variant="caption" sx={{ mt: 0.5 }}>Uses AI guess</Typography>
                </FormControl>
             </Grid>
             {/* --- End AI Filter Inputs --- */}
            
             <Grid item xs={12} display="flex" justifyContent="flex-end" alignItems="center" gap={1}>
                <Button variant="outlined" onClick={handleResetFilters}>Reset Filters & Sort</Button>
                <Button variant="contained" onClick={handleApplyFilters}>Apply Filters</Button>
             </Grid>
          </Grid>
        </Box>

        <Box sx={{ mb: 2, p: 1, display: 'flex', gap: 2, alignItems: 'center' }}>
            <Typography variant="body2">Sort By:</Typography>
             <FormControl size="small" sx={{ minWidth: 150 }}>
               <InputLabel id="sort-field-label">Field</InputLabel>
               <Select
                 labelId="sort-field-label"
                 label="Field"
                 name="field"
                 value={sortConfig.field}
                 onChange={handleSortChange}
               >
                 <MenuItem value="created_at">Apply Date</MenuItem>
                 <MenuItem value="ai_score">AI Score</MenuItem>
                 <MenuItem value="applicant_name">Applicant Name</MenuItem>
                 <MenuItem value="job_title">Job Title</MenuItem>
                 <MenuItem value="status">Status</MenuItem>
               </Select>
             </FormControl>
             <FormControl size="small" sx={{ minWidth: 100 }}>
                <InputLabel id="sort-direction-label">Direction</InputLabel>
                <Select
                  labelId="sort-direction-label"
                  label="Direction"
                  name="direction"
                  value={sortConfig.direction}
                  onChange={handleSortChange}
                >
                  <MenuItem value="DESC">Descending</MenuItem>
                  <MenuItem value="ASC">Ascending</MenuItem>
                </Select>
             </FormControl>
         </Box>

        {errorApplications && <Alert severity="error" sx={{ mb: 2 }}>{errorApplications}</Alert>}
        
        {applications.length === 0 && !loadingApplications && (
          <Alert severity="info">No applications found matching the current criteria.</Alert>
        )}

        {applications.length > 0 && (
           <TableContainer component={Paper} sx={{ mt: 2 }}>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Applicant</TableCell>
                  <TableCell>Job Title</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>AI Score</TableCell> 
                  <TableCell>Applied On</TableCell>
                  <TableCell>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                 {applications.map((app) => (
                  <TableRow key={app.id}>
                    <TableCell>{app.applicant_name || 'N/A'}</TableCell>
                    <TableCell>{app.job_title || 'N/A'}</TableCell>
                    <TableCell>{app.status || 'N/A'}</TableCell>
                    <TableCell>{app.ai_score ?? 'N/A'}</TableCell> 
                    <TableCell>{app.created_at ? new Date(app.created_at).toLocaleDateString() : 'N/A'}</TableCell>
                    <TableCell>
                       <Button 
                          component={RouterLink} 
                          to={`/admin/applications/${app.id}`}
                          size="small"
                       >
                         View
                       </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Box>
    );
  };

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Paper elevation={3} sx={{ p: 3, mb: 4 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', mb: 2, flexWrap: 'wrap', gap: 1 }}>
          <Typography variant="h4" component="h1" gutterBottom>
            Admin Dashboard
          </Typography>
           <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
               <Button 
                  component={RouterLink} 
                  to="/admin/transparency" 
                  variant="outlined" 
                  size="small"
                  startIcon={<InfoIcon />}
                >
                   AI Transparency Info
                </Button>
                <Button 
                    component={RouterLink} 
                    to="/admin/fairness" 
                    variant="outlined" 
                    size="small"
                    startIcon={<AssessmentIcon />}
                    color="secondary"
                    >
                    Fairness Metrics
                 </Button>
           </Box>
        </Box>
        
        {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
        
        <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 3 }}>
          <Tabs value={activeTab} onChange={handleTabChange} aria-label="Admin Tabs">
            <Tab label="Manage Jobs" id="admin-tab-0" aria-controls="admin-tabpanel-0" />
            <Tab label="Review Applications" id="admin-tab-1" aria-controls="admin-tabpanel-1" />
          </Tabs>
        </Box>
        
        <Box
            role="tabpanel"
            hidden={activeTab !== 0}
            id="admin-tabpanel-0"
            aria-labelledby="admin-tab-0"
        >
          {activeTab === 0 && (
             loading ? (
                  <Box sx={{ display: 'flex', justifyContent: 'center', my: 4 }}>
                    <CircularProgress />
                  </Box>
                ) : (
                <Box>
                    <Box sx={{ mb: 2, display: 'flex', justifyContent: 'flex-end' }}>
                      <Button
                        variant="contained"
                        color="primary"
                        startIcon={<AddIcon />}
                        onClick={() => handleOpenDialog()}
                      >
                        Add New Job
                      </Button>
                    </Box>
                    
                    {jobs.length === 0 ? (
                      <Alert severity="info">No job listings found. Create your first job listing!</Alert>
                    ) : (
                      <TableContainer component={Paper}>
                        <Table>
                          <TableHead>
                            <TableRow>
                              <TableCell>ID</TableCell>
                              <TableCell>Title</TableCell>
                              <TableCell>Company</TableCell>
                              <TableCell>Location</TableCell>
                              <TableCell>Actions</TableCell>
                            </TableRow>
                          </TableHead>
                          <TableBody>
                            {jobs.map((job) => (
                              <TableRow key={job.id}>
                                <TableCell>{job.id}</TableCell>
                                <TableCell>{job.title}</TableCell>
                                <TableCell>{job.company}</TableCell>
                                <TableCell>{job.location}</TableCell>
                                <TableCell>
                                  <IconButton 
                                    aria-label="edit" 
                                    onClick={() => handleOpenDialog(job)} 
                                    disabled
                                    title="Edit (Not Implemented)"
                                  >
                                    <EditIcon />
                                  </IconButton>
                                  <IconButton aria-label="delete" onClick={() => handleDelete(job.id)}>
                                    <DeleteIcon />
                                  </IconButton>
                                </TableCell>
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      </TableContainer>
                    )}
                  </Box>
                 )
          )}
        </Box>

        <Box
          role="tabpanel"
          hidden={activeTab !== 1}
          id="admin-tabpanel-1"
          aria-labelledby="admin-tab-1"
        >
          {activeTab === 1 && renderApplicationsTab()}
        </Box>

         <Dialog open={openDialog} onClose={handleCloseDialog} maxWidth="md" fullWidth>
          <DialogTitle>{editingJob ? 'Edit Job' : 'Add New Job'}</DialogTitle>
            <form onSubmit={handleSubmit}>
                <DialogContent>
                {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
                <Grid container spacing={2}>
                    <Grid item xs={12} sm={6}>
                    <TextField
                        required
                        fullWidth
                        margin="dense"
                        id="title"
                        name="title"
                        label="Job Title"
                        value={formData.title}
                        onChange={handleChange}
                    />
                    </Grid>
                    <Grid item xs={12} sm={6}>
                    <TextField
                        required
                        fullWidth
                        margin="dense"
                        id="company"
                        name="company"
                        label="Company Name"
                        value={formData.company}
                        onChange={handleChange}
                    />
                    </Grid>
                    <Grid item xs={12} sm={6}>
                    <TextField
                        required
                        fullWidth
                        margin="dense"
                        id="location"
                        name="location"
                        label="Location"
                        value={formData.location}
                        onChange={handleChange}
                    />
                    </Grid>
                    <Grid item xs={12} sm={6}>
                    <TextField
                        fullWidth
                        margin="dense"
                        id="salary_range"
                        name="salary_range"
                        label="Salary Range (Optional)"
                        value={formData.salary_range}
                        onChange={handleChange}
                    />
                    </Grid>
                    <Grid item xs={12}>
                    <TextField
                        required
                        fullWidth
                        margin="dense"
                        id="description"
                        name="description"
                        label="Job Description"
                        multiline
                        rows={4}
                        value={formData.description}
                        onChange={handleChange}
                    />
                    </Grid>
                    <Grid item xs={12}>
                    <TextField
                        fullWidth
                        margin="dense"
                        id="requirements"
                        name="requirements"
                        label="Requirements (Optional)"
                        multiline
                        rows={3}
                        value={formData.requirements}
                        onChange={handleChange}
                    />
                    </Grid>
                </Grid>
                </DialogContent>
                <DialogActions>
                <Button onClick={handleCloseDialog}>Cancel</Button>
                <Button type="submit" variant="contained" disabled={editingJob !== null}>{editingJob ? 'Save Changes (Disabled)' : 'Create Job'}</Button>
                </DialogActions>
            </form>
        </Dialog>

      </Paper>
    </Container>
  );
}

export default AdminDashboard; 