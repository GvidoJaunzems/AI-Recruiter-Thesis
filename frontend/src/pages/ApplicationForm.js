import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { 
  Container, Paper, Typography, Button, Box, Stepper, Step, StepLabel, 
  TextField, CircularProgress, Grid, Alert, MenuItem, FormControlLabel, 
  Checkbox, Radio, RadioGroup, FormControl, FormLabel, Divider,
  IconButton
} from '@mui/material';
import { styled } from '@mui/material/styles';
import { useAuth } from '../contexts/AuthContext';
import axios from 'axios';
import { API_URL } from '../config';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';

// Styled components
const ApplicationPaper = styled(Paper)(({ theme }) => ({
  padding: theme.spacing(4),
  marginTop: theme.spacing(3),
  marginBottom: theme.spacing(3),
}));

const StepContent = styled(Box)(({ theme }) => ({
  padding: theme.spacing(3),
  marginTop: theme.spacing(2),
  marginBottom: theme.spacing(2),
}));

// Define the mapping for education levels with corrected quotes
const educationLevelMap = [
  { value: 'high_school', label: "High School Diploma" },
  { value: 'associates', label: "Associate Degree" },
  { value: 'bachelors', label: "Bachelor's Degree" },
  { value: 'masters', label: "Master's Degree" },
  { value: 'phd', label: "Ph.D. or Doctorate" },
  { value: 'other', label: "Other" }
];

const ApplicationForm = () => {
  const { jobId } = useParams();
  const { currentUser, getAuthHeader } = useAuth();
  
  // State for job and form data
  const [job, setJob] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeStep, setActiveStep] = useState(0);
  const [resumeFile, setResumeFile] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);
  
  // Form data state
  const [formData, setFormData] = useState({
    // Personal Information
    cover_letter: '',
    current_employer: '',
    current_job_title: '',
    years_of_experience: '',
    
    // Work Experience - Now an array
    work_experience: [{ 
        id: Date.now(), // Simple unique ID for list key
        job_title: '', 
        company: '', 
        start_date: '', 
        end_date: '', // Can be empty for current job
        description: '' 
    }],
    
    // Education - Now an array for details
    highest_education: '', // Keep highest level separate
    education_details: [{ 
        id: Date.now(), // Simple unique ID for list key
        institution: '', 
        degree: '', 
        field_of_study: '', 
        graduation_date: '' // Or start/end dates if preferred
    }],
    
    // Skills & Qualifications
    skills: '',
    certifications: '',
    languages: '',
    
    // Additional Information
    referral_source: '',
    willing_to_relocate: false,
    available_start_date: '',
    salary_expectations: '',
    
    // Legal Information
    legally_authorized_to_work: false,
    require_sponsorship: false,
    
    // Diversity Information (Optional)
    gender: '',
    ethnicity: '',
    veteran_status: '',
    disability_status: ''
  });
  
  // Define steps for the application process
  const steps = [
    'Job Details',
    'Personal Information',
    'Work Experience',
    'Education',
    'Skills & Qualifications',
    'Additional Questions',
    'Documents',
    'Review & Submit'
  ];
  
  // Education level options
  // const educationOptions = [ ... ]; 
  
  // Ethnicity options
  const ethnicityOptions = [
    'White',
    'Black or African American',
    'Hispanic or Latino',
    'Asian',
    'Native American or Alaska Native',
    'Native Hawaiian or Other Pacific Islander',
    'Two or More Races',
    'Prefer not to say'
  ];
  
  // Referral source options
  const referralOptions = [
    'Job Board',
    'Company Website',
    'LinkedIn',
    'Employee Referral',
    'University/College',
    'Other'
  ];
  
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
        console.log(`Fetching job details for application form: ${jobId}`);
        
        // First try to fetch all jobs and filter for the one we want
        const response = await axios.get(`${API_URL}/jobs.php`);
        console.log('Jobs response data for application form:', response.data);
        
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
            console.log('Found job for detailed application:', foundJob);
            setJob(foundJob);
          } else {
            console.error('Job not found in the response data. Available jobs:', 
              response.data.jobs.map(j => `ID: ${j.id} - ${j.title}`).join(', '));
            setError('Job not found. Please go back to the jobs list.');
          }
        } else if (response.data && !response.data.error) {
          // Direct response with a single job
          console.log('Single job response for application form:', response.data);
          setJob(response.data);
        } else {
          console.error('Unexpected response format:', response.data);
          setError('Failed to load job details. Unexpected response format.');
        }
        
        setLoading(false);
      } catch (err) {
        console.error('Error fetching job details for application form:', err);
        setError('Failed to load job details. Please try again later.');
        setLoading(false);
      }
    };
    
    fetchJobDetails();
  }, [jobId]);
  
  // Handle form field changes
  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };
  
  // Handle resume file upload
  const handleFileChange = (e) => {
    if (e.target.files.length > 0) {
      setResumeFile(e.target.files[0]);
    }
  };
  
  // Navigate through steps
  const handleNext = () => {
    setActiveStep((prevStep) => prevStep + 1);
    window.scrollTo(0, 0);
  };
  
  const handleBack = () => {
    setActiveStep((prevStep) => prevStep - 1);
    window.scrollTo(0, 0);
  };
  
  // Submit application
  const handleSubmit = async () => {
    if (!currentUser) {
      setError('You must be logged in to apply');
      return;
    }
    if (!job) {
      setError('Job information is missing. Please try again.');
      return;
    }
    
    try {
      setSubmitting(true);
      setError('');
      console.log('Submitting detailed application for job ID:', jobId);
      
      const submitData = new FormData();
      
      if (resumeFile) {
        submitData.append('resume', resumeFile);
      } else {
        setError('Resume file is required.');
        setSubmitting(false);
        return;
      }
      
      submitData.append('job_id', String(jobId));
      
      // Append other form fields, stringifying arrays
      Object.keys(formData).forEach(key => {
        let value = formData[key];
        
        // Stringify work_experience and education_details arrays
        if (key === 'work_experience' || key === 'education_details') {
            try {
                value = JSON.stringify(value);
            } catch (e) {
                console.error(`Error stringifying ${key}:`, e);
                // Handle error appropriately, maybe set error state or use empty array string
                value = JSON.stringify([]); 
            }
        } else if (typeof value === 'boolean') {
            value = value ? '1' : '0';
        }
        
        // Only append if value is not null/undefined (empty strings are okay)
        if (value !== null && value !== undefined) {
            submitData.append(key, value);
        }
      });

      // Log FormData contents (for debugging)
      // Note: FormData is tricky to log directly. This shows keys.
      console.log('FormData keys being sent:', [...submitData.keys()]);
      // You can iterate and log values for non-file entries if needed:
      // for (let [key, value] of submitData.entries()) { 
      //   if (!(value instanceof File)) { 
      //     console.log(`FormData[${key}]:`, value); 
      //   } else {
      //     console.log(`FormData[${key}]: File - ${value.name}`); 
      //   }
      // }

      // Get auth headers
      const authHeaders = getAuthHeader();
      if (!authHeaders) {
        throw new Error('Authentication details are missing.');
      }
      console.log('Using Authorization header:', authHeaders);

      // Make the API call
      const url = `${API_URL}/applications.php`;
      console.log('Submitting application to:', url);
      console.log('[ApplyForm] Type of data being sent:', submitData instanceof FormData ? 'FormData' : typeof submitData);

      const response = await axios.post(url, submitData, {
        headers: {
          ...authHeaders,
          // Content-Type is set automatically by browser for FormData
        }
      });

      console.log('Application submitted successfully:', response.data);
      setSubmitting(false);
      setSuccess(true); // Show success message
      // Optionally redirect or clear form after success
      // navigate('/my-applications'); 

    } catch (err) {
      console.error('Error submitting application:', err);
      const errorMsg = err.response?.data?.error || err.message || 'An error occurred while submitting your application.';
      setError(errorMsg);
      setSubmitting(false);
    }
  };
  
  // --- ADDED: Handlers for Work Experience Array --- 
  const handleWorkExperienceChange = (index, field, value) => {
      const updatedExperience = [...formData.work_experience];
      updatedExperience[index] = { ...updatedExperience[index], [field]: value };
      setFormData(prev => ({ ...prev, work_experience: updatedExperience }));
  };

  const addWorkExperience = () => {
      setFormData(prev => ({ 
          ...prev, 
          work_experience: [
              ...prev.work_experience, 
              { 
                id: Date.now(), 
                job_title: '', 
                company: '', 
                start_date: '', 
                end_date: '', 
                description: '' 
              }
          ]
      }));
  };

  const removeWorkExperience = (index) => {
      if (formData.work_experience.length <= 1) return; // Don't remove the last one
      const updatedExperience = formData.work_experience.filter((_, i) => i !== index);
      setFormData(prev => ({ ...prev, work_experience: updatedExperience }));
  };
  // -----------------------------------------------------
  
  // --- ADDED: Handlers for Education Details Array --- 
  const handleEducationChange = (index, field, value) => {
      const updatedEducation = [...formData.education_details];
      updatedEducation[index] = { ...updatedEducation[index], [field]: value };
      setFormData(prev => ({ ...prev, education_details: updatedEducation }));
  };

  const addEducation = () => {
      setFormData(prev => ({ 
          ...prev, 
          education_details: [
              ...prev.education_details, 
              { 
                id: Date.now(), 
                institution: '', 
                degree: '', 
                field_of_study: '', 
                graduation_date: ''
              }
          ]
      }));
  };

  const removeEducation = (index) => {
      if (formData.education_details.length <= 1) return; // Don't remove the last one
      const updatedEducation = formData.education_details.filter((_, i) => i !== index);
      setFormData(prev => ({ ...prev, education_details: updatedEducation }));
  };
  // ----------------------------------------------------
  
  // Render different steps based on activeStep
  const renderStepContent = (step) => {
    switch (step) {
      case 0: // Job Details
        return (
          <StepContent>
            <Typography variant="h5" gutterBottom>{job?.title || 'Loading job details...'}</Typography>
            {job ? (
              <Box>
                <Typography variant="subtitle1" color="textSecondary">{job.company} - {job.location}</Typography>
                <Divider sx={{ my: 2 }} />
                <Typography variant="h6">Description</Typography>
                <Typography paragraph sx={{ whiteSpace: 'pre-wrap' }}>{job.description}</Typography>
                <Typography variant="h6">Requirements</Typography>
                <Typography paragraph sx={{ whiteSpace: 'pre-wrap' }}>{job.requirements}</Typography>
                <Typography variant="h6">Salary Range</Typography>
                <Typography paragraph>{job.salary_range || 'Not specified'}</Typography>
              </Box>
            ) : (
              <CircularProgress />
            )}
          </StepContent>
        );
        
      case 1: // Personal Information
        return (
          <StepContent>
            <Typography variant="h6" gutterBottom>About You</Typography>
            <Grid container spacing={3}>
              <Grid item xs={12} sm={6}>
                <TextField 
                  fullWidth 
                  label="Current Employer (Optional)" 
                  name="current_employer"
                  value={formData.current_employer}
                  onChange={handleChange}
                  variant="outlined"
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField 
                  fullWidth 
                  label="Current Job Title (Optional)" 
                  name="current_job_title"
                  value={formData.current_job_title}
                  onChange={handleChange}
                  variant="outlined"
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                 <TextField 
                  fullWidth 
                  label="Total Years of Professional Experience"
                  name="years_of_experience"
                  type="number"
                  value={formData.years_of_experience}
                  onChange={handleChange}
                  variant="outlined"
                  InputProps={{ inputProps: { min: 0 } }} // Ensure non-negative
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Cover Letter (Optional)"
                  name="cover_letter"
                  multiline
                  rows={6}
                  value={formData.cover_letter}
                  onChange={handleChange}
                  variant="outlined"
                  placeholder="Tell us why you're a great fit for this role..."
                />
              </Grid>
            </Grid>
          </StepContent>
        );
        
      case 2: // Work Experience
        return (
          <StepContent>
            <Typography variant="h6" gutterBottom>Work Experience</Typography>
            {formData.work_experience.map((exp, index) => (
                // Display each entry - Simple List for now, Card later maybe
                <Box key={exp.id} sx={{ mb: 3, p: 2, border: '1px solid #ddd', borderRadius: 1 }}> 
                    <Typography variant="subtitle1" gutterBottom>
                        Position #{index + 1}
                        {formData.work_experience.length > 1 && (
                             <IconButton 
                                size="small" 
                                onClick={() => removeWorkExperience(index)} 
                                aria-label={`Remove position ${index + 1}`}
                                sx={{ ml: 1, color: 'error.main' }} 
                             >
                                <DeleteIcon />
                            </IconButton>
                        )}
                    </Typography>
                    <Grid container spacing={2}>
                        <Grid item xs={12} sm={6}>
                            <TextField 
                                fullWidth 
                                label="Job Title"
                                value={exp.job_title}
                                onChange={(e) => handleWorkExperienceChange(index, 'job_title', e.target.value)}
                                variant="outlined"
                                required // Make fields required
                            />
                        </Grid>
                         <Grid item xs={12} sm={6}>
                            <TextField 
                                fullWidth 
                                label="Company"
                                value={exp.company}
                                onChange={(e) => handleWorkExperienceChange(index, 'company', e.target.value)}
                                variant="outlined"
                                required
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField 
                                fullWidth 
                                label="Start Date"
                                type="month" // Use month input type
                                value={exp.start_date}
                                onChange={(e) => handleWorkExperienceChange(index, 'start_date', e.target.value)}
                                variant="outlined"
                                InputLabelProps={{ shrink: true }}
                                required
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField 
                                fullWidth 
                                label="End Date (Leave blank if current)"
                                type="month" // Use month input type
                                value={exp.end_date}
                                onChange={(e) => handleWorkExperienceChange(index, 'end_date', e.target.value)}
                                variant="outlined"
                                InputLabelProps={{ shrink: true }}
                            />
                        </Grid>
                        <Grid item xs={12}>
                            <TextField 
                                fullWidth 
                                label="Description / Responsibilities"
                                multiline
                                rows={3}
                                value={exp.description}
                                onChange={(e) => handleWorkExperienceChange(index, 'description', e.target.value)}
                                variant="outlined"
                            />
                        </Grid>
                    </Grid>
                </Box>
            ))}
            <Button 
                variant="outlined"
                startIcon={<AddIcon />}
                onClick={addWorkExperience}
                sx={{ mt: 2 }}
            >
                Add Another Position
            </Button>
          </StepContent>
        );
        
      case 3: // Education
        return (
          <StepContent>
            <Typography variant="h6" gutterBottom>Education</Typography>
            {/* Highest Level - Still separate */}
            <TextField 
              select 
              fullWidth 
              label="Highest Level of Education Achieved"
              name="highest_education"
              value={formData.highest_education}
              onChange={handleChange} // Use standard handleChange for this single field
              variant="outlined"
              required
              sx={{ mb: 3 }} // Add margin below
            >
              <MenuItem value="" disabled><em>Select level...</em></MenuItem>
              {educationLevelMap.map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </TextField>
            
            <Divider sx={{ mb: 3 }} />
            
            <Typography variant="subtitle1" gutterBottom>Education History</Typography>
            {formData.education_details.map((edu, index) => (
                <Box key={edu.id} sx={{ mb: 3, p: 2, border: '1px solid #ddd', borderRadius: 1 }}> 
                    <Typography variant="subtitle1" gutterBottom>
                        Entry #{index + 1}
                        {formData.education_details.length > 1 && (
                             <IconButton 
                                size="small" 
                                onClick={() => removeEducation(index)} 
                                aria-label={`Remove education entry ${index + 1}`}
                                sx={{ ml: 1, color: 'error.main' }} 
                             >
                                <DeleteIcon />
                            </IconButton>
                        )}
                    </Typography>
                    <Grid container spacing={2}>
                        <Grid item xs={12} sm={6}>
                            <TextField 
                                fullWidth 
                                label="Institution Name"
                                value={edu.institution}
                                onChange={(e) => handleEducationChange(index, 'institution', e.target.value)}
                                variant="outlined"
                                required
                            />
                        </Grid>
                         <Grid item xs={12} sm={6}>
                            <TextField 
                                fullWidth 
                                label="Degree (e.g., B.S., M.A.)"
                                value={edu.degree}
                                onChange={(e) => handleEducationChange(index, 'degree', e.target.value)}
                                variant="outlined"
                                required
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField 
                                fullWidth 
                                label="Field of Study (e.g., Computer Science)"
                                value={edu.field_of_study}
                                onChange={(e) => handleEducationChange(index, 'field_of_study', e.target.value)}
                                variant="outlined"
                                required
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                           <TextField 
                                fullWidth 
                                label="Graduation Date (or Expected)"
                                type="month" // Or "date" if day is desired
                                value={edu.graduation_date}
                                onChange={(e) => handleEducationChange(index, 'graduation_date', e.target.value)}
                                variant="outlined"
                                InputLabelProps={{ shrink: true }}
                                // Consider making this optional or required based on needs
                            />
                        </Grid>
                    </Grid>
                </Box>
            ))}
            <Button 
                variant="outlined"
                startIcon={<AddIcon />}
                onClick={addEducation}
                sx={{ mt: 2 }}
            >
                Add Another Education Entry
            </Button>
          </StepContent>
        );
        
      case 4: // Skills & Qualifications
        return (
          <StepContent>
            <Typography variant="h6" gutterBottom>
              Skills & Qualifications
            </Typography>
            <Grid container spacing={3}>
              <Grid item xs={12}>
                <TextField
                  required
                  fullWidth
                  multiline
                  rows={4}
                  label="Skills"
                  name="skills"
                  value={formData.skills}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                  placeholder="List your relevant technical and soft skills."
                  helperText="Separate skills with commas (e.g., JavaScript, React, Team Leadership)"
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  multiline
                  rows={3}
                  label="Certifications"
                  name="certifications"
                  value={formData.certifications}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                  placeholder="List any relevant certifications."
                  helperText="Include certification name, issuing organization, and date (if applicable)"
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Languages"
                  name="languages"
                  value={formData.languages}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                  placeholder="List languages you speak and your proficiency level."
                  helperText="e.g., English (Native), Spanish (Intermediate), French (Basic)"
                />
              </Grid>
            </Grid>
          </StepContent>
        );
        
      case 5: // Additional Questions
        return (
          <StepContent>
            <Typography variant="h6" gutterBottom>
              Additional Information
            </Typography>
            <Grid container spacing={3}>
              <Grid item xs={12}>
                <TextField
                  select
                  fullWidth
                  label="How did you hear about this position?"
                  name="referral_source"
                  value={formData.referral_source}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                >
                  {referralOptions.map(option => (
                    <MenuItem key={option} value={option}>
                      {option}
                    </MenuItem>
                  ))}
                </TextField>
              </Grid>
              <Grid item xs={12} sm={6}>
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={formData.willing_to_relocate}
                      onChange={handleChange}
                      name="willing_to_relocate"
                    />
                  }
                  label="I am willing to relocate for this position"
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Earliest Available Start Date"
                  name="available_start_date"
                  type="date"
                  value={formData.available_start_date}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                  InputLabelProps={{ shrink: true }}
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Salary Expectations"
                  name="salary_expectations"
                  value={formData.salary_expectations}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                  placeholder="e.g., $60,000 - $75,000"
                />
              </Grid>
              <Grid item xs={12}>
                <Typography variant="h6" gutterBottom sx={{ mt: 2 }}>
                  Legal Information
                </Typography>
              </Grid>
              <Grid item xs={12}>
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={formData.legally_authorized_to_work}
                      onChange={handleChange}
                      name="legally_authorized_to_work"
                    />
                  }
                  label="I am legally authorized to work in the United States"
                />
              </Grid>
              <Grid item xs={12}>
                <FormControlLabel
                  control={
                    <Checkbox
                      checked={formData.require_sponsorship}
                      onChange={handleChange}
                      name="require_sponsorship"
                    />
                  }
                  label="I will require sponsorship for employment visa status"
                />
              </Grid>
              <Grid item xs={12}>
                <Typography variant="h6" gutterBottom sx={{ mt: 2 }}>
                  Diversity Information (Optional)
                </Typography>
                <Typography variant="body2" color="textSecondary" paragraph>
                  This information is collected for diversity monitoring purposes only and will not affect your application.
                </Typography>
              </Grid>
              <Grid item xs={12} sm={6}>
                <FormControl component="fieldset">
                  <FormLabel component="legend">Gender</FormLabel>
                  <RadioGroup
                    name="gender"
                    value={formData.gender}
                    onChange={handleChange}
                  >
                    <FormControlLabel value="male" control={<Radio />} label="Male" />
                    <FormControlLabel value="female" control={<Radio />} label="Female" />
                    <FormControlLabel value="non-binary" control={<Radio />} label="Non-binary" />
                    <FormControlLabel value="prefer-not-to-say" control={<Radio />} label="Prefer not to say" />
                  </RadioGroup>
                </FormControl>
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  select
                  fullWidth
                  label="Ethnicity"
                  name="ethnicity"
                  value={formData.ethnicity}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                >
                  {ethnicityOptions.map(option => (
                    <MenuItem key={option} value={option}>
                      {option}
                    </MenuItem>
                  ))}
                </TextField>
              </Grid>
              <Grid item xs={12} sm={6}>
                <FormControl component="fieldset">
                  <FormLabel component="legend">Veteran Status</FormLabel>
                  <RadioGroup
                    name="veteran_status"
                    value={formData.veteran_status}
                    onChange={handleChange}
                  >
                    <FormControlLabel value="veteran" control={<Radio />} label="I am a veteran" />
                    <FormControlLabel value="not-veteran" control={<Radio />} label="I am not a veteran" />
                    <FormControlLabel value="prefer-not-to-say" control={<Radio />} label="Prefer not to say" />
                  </RadioGroup>
                </FormControl>
              </Grid>
              <Grid item xs={12} sm={6}>
                <FormControl component="fieldset">
                  <FormLabel component="legend">Disability Status</FormLabel>
                  <RadioGroup
                    name="disability_status"
                    value={formData.disability_status}
                    onChange={handleChange}
                  >
                    <FormControlLabel value="disability" control={<Radio />} label="I have a disability" />
                    <FormControlLabel value="no-disability" control={<Radio />} label="I do not have a disability" />
                    <FormControlLabel value="prefer-not-to-say" control={<Radio />} label="Prefer not to say" />
                  </RadioGroup>
                </FormControl>
              </Grid>
            </Grid>
          </StepContent>
        );
        
      case 6: // Documents
        return (
          <StepContent>
            <Typography variant="h6" gutterBottom>
              Documents
            </Typography>
            <Grid container spacing={3}>
              <Grid item xs={12}>
                <Box
                  component="label"
                  htmlFor="resume-upload"
                  sx={{
                    display: 'block',
                    border: '2px dashed #ccc',
                    borderRadius: 2,
                    padding: 3,
                    textAlign: 'center',
                    cursor: 'pointer'
                  }}
                >
                  <input
                    id="resume-upload"
                    type="file"
                    accept=".pdf,.doc,.docx"
                    onChange={handleFileChange}
                    style={{ display: 'none' }}
                  />
                  <Typography variant="body1" gutterBottom>
                    {resumeFile ? `Selected file: ${resumeFile.name}` : 'Upload your resume (PDF, DOC, or DOCX)'}
                  </Typography>
                  <Button
                    variant="contained"
                    component="span"
                    color="primary"
                    sx={{ mt: 1 }}
                  >
                    Browse Files
                  </Button>
                  <Typography variant="caption" display="block" sx={{ mt: 1 }}>
                    Max file size: 5MB
                  </Typography>
                </Box>
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  multiline
                  rows={6}
                  label="Cover Letter"
                  name="cover_letter"
                  value={formData.cover_letter}
                  onChange={handleChange}
                  variant="outlined"
                  margin="normal"
                  placeholder="Write your cover letter here..."
                  helperText="Explain why you are interested in this position and why you would be a good fit."
                />
              </Grid>
            </Grid>
          </StepContent>
        );
        
      case 7: // Review & Submit
        return (
          <StepContent>
            <Typography variant="h6" gutterBottom>Review Your Application</Typography>
            <Divider sx={{ my: 2 }} />
            
            {/* Personal Info Section */}
            <Typography variant="subtitle1" gutterBottom>Personal Information</Typography>
            <Typography><strong>Current Employer:</strong> {formData.current_employer || 'N/A'}</Typography>
            <Typography><strong>Current Job Title:</strong> {formData.current_job_title || 'N/A'}</Typography>
            <Typography><strong>Years of Experience:</strong> {formData.years_of_experience || 'N/A'}</Typography>
            <Typography gutterBottom><strong>Cover Letter:</strong></Typography>
            <Typography paragraph sx={{ whiteSpace: 'pre-wrap', maxHeight: '150px', overflowY: 'auto', border: '1px solid #eee', p: 1, background: '#f9f9f9' }}>
              {formData.cover_letter || 'N/A'}
            </Typography>
            <Divider sx={{ my: 2 }} />

            {/* Work Experience Section - Updated */}
            <Typography variant="subtitle1" gutterBottom>Work Experience</Typography>
            {formData.work_experience && formData.work_experience.length > 0 && formData.work_experience[0]?.job_title ? (
              formData.work_experience.map((exp, index) => (
                <Box key={exp.id || index} sx={{ mb: 2, pl: 2, borderLeft: '3px solid #eee' }}>
                  <Typography><strong>Position #{index + 1}: {exp.job_title}</strong> at {exp.company}</Typography>
                  <Typography variant="body2" color="textSecondary">
                    {exp.start_date} - {exp.end_date || 'Present'}
                  </Typography>
                  <Typography variant="body2" paragraph sx={{ whiteSpace: 'pre-wrap', mt: 1 }}>
                    {exp.description || 'No description provided.'}
                  </Typography>
                </Box>
              ))
            ) : (
              <Typography>No work experience provided.</Typography>
            )}
            <Divider sx={{ my: 2 }} />

            {/* Education Section - Updated */}
            <Typography variant="subtitle1" gutterBottom>Education</Typography>
            <Typography><strong>Highest Level Achieved:</strong> {educationLevelMap.find(opt => opt.value === formData.highest_education)?.label || 'N/A'}</Typography>
            <Typography variant="subtitle2" gutterBottom sx={{mt: 1}}>Education History:</Typography>
             {formData.education_details && formData.education_details.length > 0 && formData.education_details[0]?.institution ? (
              formData.education_details.map((edu, index) => (
                 <Box key={edu.id || index} sx={{ mb: 2, pl: 2 }}>
                   <Typography><strong>{edu.degree}</strong> in {edu.field_of_study}</Typography>
                  <Typography variant="body2" color="textSecondary">
                    {edu.institution} - Graduated: {edu.graduation_date || 'N/A'}
                  </Typography>
                </Box>
              ))
            ) : (
              <Typography>No specific education history provided.</Typography>
            )}
            <Divider sx={{ my: 2 }} />

            {/* Skills & Qualifications Section */}
            <Typography variant="subtitle1" gutterBottom>Skills & Qualifications</Typography>
            <Typography><strong>Skills:</strong> {formData.skills || 'N/A'}</Typography>
            <Typography><strong>Certifications:</strong> {formData.certifications || 'N/A'}</Typography>
            <Typography><strong>Languages:</strong> {formData.languages || 'N/A'}</Typography>
            <Divider sx={{ my: 2 }} />
            
            {/* Additional Questions Section */}
            <Typography variant="subtitle1" gutterBottom>Additional Questions</Typography>
            <Typography><strong>Referral Source:</strong> {formData.referral_source || 'N/A'}</Typography>
            <Typography><strong>Willing to Relocate:</strong> {formData.willing_to_relocate ? 'Yes' : 'No'}</Typography>
            <Typography><strong>Available Start Date:</strong> {formData.available_start_date || 'N/A'}</Typography>
            <Typography><strong>Salary Expectations:</strong> {formData.salary_expectations || 'N/A'}</Typography>
             <Divider sx={{ my: 2 }} />

            {/* Legal Info Section */}
            <Typography variant="subtitle1" gutterBottom>Legal Information</Typography>
            <Typography><strong>Legally Authorized to Work:</strong> {formData.legally_authorized_to_work ? 'Yes' : 'No'}</Typography>
            <Typography><strong>Require Sponsorship:</strong> {formData.require_sponsorship ? 'Yes' : 'No'}</Typography>
            <Divider sx={{ my: 2 }} />

            {/* Documents Section */}
             <Typography variant="subtitle1" gutterBottom>Documents</Typography>
             <Typography><strong>Resume:</strong> {resumeFile ? resumeFile.name : 'No file selected'}</Typography>
             <Divider sx={{ my: 2 }} />

            {/* Confirmation Checkbox (Optional but good practice) */}
            {/* <FormControlLabel control={<Checkbox required />} label="I confirm that the information provided is accurate." /> */}
            
            {error && <Alert severity="error" sx={{ mt: 2 }}>{error}</Alert>}
          </StepContent>
        );
        
      default:
        return null;
    }
  };
  
  // Success message
  if (success) {
    return (
      <Container maxWidth="md">
        <ApplicationPaper>
          <Box textAlign="center" py={4}>
            <Typography variant="h5" gutterBottom>
              Application Submitted Successfully!
            </Typography>
            <Typography variant="body1" paragraph>
              Thank you for your application. You will be redirected to your applications page shortly.
            </Typography>
            <CircularProgress />
          </Box>
        </ApplicationPaper>
      </Container>
    );
  }
  
  return (
    <Container maxWidth="md">
      <ApplicationPaper>
        {loading ? (
          <Box textAlign="center" py={4}>
            <CircularProgress />
          </Box>
        ) : error ? (
          <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>
        ) : (
          <>
            <Typography variant="h4" gutterBottom align="center">
              Job Application
            </Typography>
            
            <Stepper activeStep={activeStep} alternativeLabel sx={{ mb: 4 }}>
              {steps.map((label) => (
                <Step key={label}>
                  <StepLabel>{label}</StepLabel>
                </Step>
              ))}
            </Stepper>
            
            {renderStepContent(activeStep)}
            
            {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}
            
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mt: 3 }}>
              <Button
                disabled={activeStep === 0 || submitting}
                onClick={handleBack}
                variant="outlined"
              >
                Back
              </Button>
              
              {activeStep === steps.length - 1 ? (
                <Button
                  variant="contained"
                  color="primary"
                  onClick={handleSubmit}
                  disabled={submitting}
                >
                  {submitting ? <CircularProgress size={24} /> : 'Submit Application'}
                </Button>
              ) : (
                <Button
                  variant="contained"
                  color="primary"
                  onClick={handleNext}
                >
                  Next
                </Button>
              )}
            </Box>
          </>
        )}
      </ApplicationPaper>
    </Container>
  );
};

export default ApplicationForm; 