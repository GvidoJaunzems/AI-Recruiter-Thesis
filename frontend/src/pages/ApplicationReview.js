import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    Container, Paper, Typography, Box, CircularProgress, Alert, Grid, Button, Chip, Divider, Link,
    Tooltip, List, ListItem, ListItemIcon, ListItemText
} from '@mui/material';
import { styled } from '@mui/material/styles';
import axios from 'axios';
import { API_URL } from '../config';
import { useAuth } from '../contexts/AuthContext';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutline';
import RemoveCircleOutlineIcon from '@mui/icons-material/RemoveCircleOutline';
import HelpOutlineIcon from '@mui/icons-material/HelpOutline';

const ReviewPaper = styled(Paper)(({ theme }) => ({
    padding: theme.spacing(4),
    marginTop: theme.spacing(3),
    marginBottom: theme.spacing(3),
}));

const DetailSection = styled(Box)(({ theme }) => ({
    marginBottom: theme.spacing(3),
}));

const ApplicationReview = () => {
    const { applicationId } = useParams();
    const navigate = useNavigate();
    const { getAuthHeader, currentUser } = useAuth();
    const [application, setApplication] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [updatingStatus, setUpdatingStatus] = useState(false);
    const [updateError, setUpdateError] = useState('');
    const [isRecalculatingScore, setIsRecalculatingScore] = useState(false);

    const educationLevelMap = [
        { value: 'high_school', label: "High School Diploma" },
        { value: 'associates', label: "Associate Degree" },
        { value: 'bachelors', label: "Bachelor's Degree" },
        { value: 'masters', label: "Master's Degree" },
        { value: 'phd', label: "Ph.D. or Doctorate" },
        { value: 'other', label: "Other" }
    ];

    const getEducationLabel = (value) => {
        return educationLevelMap.find(option => option.value === value)?.label || value || 'N/A';
    };

    const fetchApplicationDetails = useCallback(async (showLoadingIndicator = true, forceRecalculate = false) => {
        if (!applicationId) {
            setError('Application ID is missing.');
            if (showLoadingIndicator) setLoading(false);
            return;
        }
        if (showLoadingIndicator) setLoading(true);
        setError('');
        try {
            const authHeaders = getAuthHeader();
            if (!authHeaders) {
                throw new Error('User not authenticated.');
            }
            
            let apiUrl = `${API_URL}/applications.php?id=${applicationId}`;
            if (forceRecalculate) {
                apiUrl += '&recalculate=true';
                console.log("[Recalculate] Requesting AI recalculation from URL:", apiUrl);
            } else {
                console.log("[Fetch] Fetching details from URL:", apiUrl);
            }
            
            const response = await axios.get(apiUrl, {
                headers: authHeaders
            });
            
            console.log("[Fetch/Recalculate] Received application details:", response.data);
            if (response.data && !response.data.error) {
                const factors = Array.isArray(response.data.ai_key_factors) ? response.data.ai_key_factors : [];
                
                const updatedApplication = {
                    ...response.data,
                    ai_score: response.data.ai_score !== null ? parseFloat(response.data.ai_score) : null,
                    ai_key_factors: factors
                };
                console.log("[Fetch/Recalculate] Setting application state:", updatedApplication);
                setApplication(updatedApplication);
            } else {
                const errorMsg = response.data?.error || 'Failed to load application details.'
                console.error("[Fetch/Recalculate] Error received from backend:", errorMsg);
                setError(errorMsg);
                if (forceRecalculate) {
                     setApplication(prev => ({
                          ...prev, 
                          ai_score: null, 
                          ai_analysis: prev?.ai_analysis || 'Recalculation failed.',
                          ai_score_explanation: '',
                          ai_key_factors: []
                      }));
                }
            }
        } catch (err) {
            console.error('Error during fetch/recalculate:', err);
            setError(err.response?.data?.error || err.message || 'An error occurred.');
             if (forceRecalculate) {
                 setApplication(prev => ({
                      ...prev,
                      ai_score: null,
                      ai_analysis: prev?.ai_analysis || 'Recalculation failed due to network/client error.',
                      ai_score_explanation: '',
                      ai_key_factors: []
                  }));
            }
        } finally {
            if (showLoadingIndicator) setLoading(false);
        }
    }, [applicationId, getAuthHeader]);

    useEffect(() => {
        fetchApplicationDetails();
    }, [fetchApplicationDetails]);

    const handleStatusUpdate = async (newStatus) => {
        setUpdatingStatus(true);
        setUpdateError('');
        try {
             const authHeaders = getAuthHeader();
             if (!authHeaders) {
                 throw new Error('Authentication details are missing.');
             }
            if (currentUser?.role !== 'admin') {
                throw new Error('You do not have permission to perform this action.');
            }

            const response = await axios.put(`${API_URL}/applications.php?id=${applicationId}`,
                { status: newStatus },
                { headers: authHeaders }
            );

            if (response.data && response.data.message) {
                setApplication(prev => ({ ...prev, status: newStatus }));
                console.log(`Application ${applicationId} status updated to ${newStatus}`);
            } else {
                 throw new Error(response.data?.error || 'Failed to update status.');
            }
        } catch (err) {
             console.error('Error updating application status:', err);
             setUpdateError(err.response?.data?.error || err.message || 'An error occurred while updating status.');
        } finally {
             setUpdatingStatus(false);
        }
    };

    const handleRecalculateScore = async () => {
        console.log("[Recalculate] Button clicked. Current application state:", application);
        setIsRecalculatingScore(true);
        setError('');
        setApplication(prev => ({ 
            ...prev, 
            ai_score: null,
            ai_analysis: 'Recalculating... please wait.',
            ai_score_explanation: '',
            ai_key_factors: []
        })); 
        
        await fetchApplicationDetails(false, true); 
        
        setIsRecalculatingScore(false);
        console.log("[Recalculate] Recalculation fetch finished.");
    };

    if (loading) {
        return (
            <Container maxWidth="md">
                <Box display="flex" justifyContent="center" my={5}><CircularProgress /></Box>
            </Container>
        );
    }

    if (error) {
        return (
            <Container maxWidth="md">
                <Alert severity="error" sx={{ my: 3 }}>{error}</Alert>
                 <Button variant="outlined" onClick={() => navigate(-1)}>Go Back</Button>
            </Container>
        );
    }

    if (!application) {
         return (
            <Container maxWidth="md">
                <Alert severity="warning" sx={{ my: 3 }}>Application data could not be loaded.</Alert>
                 <Button variant="outlined" onClick={() => navigate(-1)}>Go Back</Button>
            </Container>
        );
    }

    const renderBool = (value) => (value ? 'Yes' : 'No');

    const formattedAiScore = application.ai_score !== null ? parseFloat(application.ai_score).toFixed(2) : 'N/A';

    const renderKeyFactor = (factor, index) => {
        let icon = <HelpOutlineIcon color="disabled" sx={{ mr: 1 }} />;
        let text = factor;
        if (factor.startsWith('+')) {
            icon = <CheckCircleOutlineIcon color="success" sx={{ mr: 1 }} />;
            text = factor.substring(1).trim();
        } else if (factor.startsWith('-')) {
            icon = <RemoveCircleOutlineIcon color="error" sx={{ mr: 1 }} />;
            text = factor.substring(1).trim();
        }
        return (
            <ListItem key={index} disablePadding>
                <ListItemIcon sx={{ minWidth: 'auto' }}>{icon}</ListItemIcon>
                <ListItemText primary={text} />
            </ListItem>
        );
    };

    return (
        <Container maxWidth="lg">
            <ReviewPaper>
                 <Box display="flex" justifyContent="space-between" alignItems="center" mb={2} flexWrap="wrap">
                    <Typography variant="h4" component="h2" gutterBottom sx={{ mr: 2 }}>
                        Review: {application.applicant_name} for {application.job_title}
                    </Typography>
                     <Box sx={{ display: 'flex', gap: 1, alignItems: 'center'}}>
                        <Chip label={`Status: ${application.status}`} color={
                            application.status === 'accepted' ? 'success' :
                            application.status === 'rejected' ? 'error' :
                            application.status === 'reviewed' ? 'info' : 'default'
                        } />
                         <Tooltip title={application.ai_analysis || 'No AI justification available'} placement="top">
                            <Chip 
                                icon={<InfoOutlinedIcon fontSize="small" />}
                                label={`AI Score: ${formattedAiScore}`}
                                variant="outlined"
                                color={application.ai_score === null ? "default" : "primary"}
                                sx={{ cursor: 'help' }}
                             />
                        </Tooltip>
                    </Box>
                </Box>

                <Divider sx={{ my: 2 }} />

                 {currentUser?.role === 'admin' && (
                     <Box sx={{ my: 3, display: 'flex', gap: 2, flexWrap: 'wrap' }}>
                        <Button
                            variant="contained"
                            color="success"
                            disabled={updatingStatus || application.status === 'accepted'}
                            onClick={() => handleStatusUpdate('accepted')}
                        >
                            {updatingStatus ? <CircularProgress size={24} /> : 'Accept'}
                        </Button>
                         <Button
                            variant="contained"
                            color="warning"
                            disabled={updatingStatus || application.status === 'reviewed'}
                            onClick={() => handleStatusUpdate('reviewed')}
                         >
                             {updatingStatus ? <CircularProgress size={24} /> : 'Mark as Reviewed'}
                         </Button>
                        <Button
                            variant="contained"
                            color="error"
                            disabled={updatingStatus || application.status === 'rejected'}
                            onClick={() => handleStatusUpdate('rejected')}
                        >
                            {updatingStatus ? <CircularProgress size={24} /> : 'Reject'}
                        </Button>
                         <Button
                            variant="outlined"
                            color="secondary"
                            disabled={isRecalculatingScore}
                            onClick={handleRecalculateScore}
                            startIcon={isRecalculatingScore ? <CircularProgress size={20} /> : null}
                        >
                            {application.ai_score === null && !isRecalculatingScore ? 'Calculate AI Score' : 'Update AI Score'}
                        </Button>
                    </Box>
                )}
                 {updateError && <Alert severity="error" sx={{ mb: 2 }}>{updateError}</Alert>}

                {application.ai_score_explanation && (
                    <Alert severity="info" icon={<InfoOutlinedIcon />} sx={{ mb: 3 }}>
                        <Typography variant="subtitle2" component="strong">AI Score Explanation:</Typography>
                        <Typography variant="body2">{application.ai_score_explanation}</Typography>
                    </Alert>
                )}

                {application.ai_key_factors && application.ai_key_factors.length > 0 && (
                    <DetailSection>
                        <Typography variant="h6" gutterBottom>AI Key Factors</Typography>
                        <List dense>
                            {application.ai_key_factors.map(renderKeyFactor)}
                        </List>
                    </DetailSection>
                )}

                <Grid container spacing={4}>
                    <Grid item xs={12} md={6}>
                        <DetailSection>
                            <Typography variant="h6" gutterBottom>Applicant Information</Typography>
                            <Typography variant="body1"><strong>Name:</strong> {application.applicant_name}</Typography>
                            <Typography variant="body1"><strong>Email:</strong> {application.applicant_email}</Typography>
                            <Typography variant="body1"><strong>Submitted:</strong> {new Date(application.created_at).toLocaleString()}</Typography>
                        </DetailSection>

                         <DetailSection>
                             <Typography variant="h6" gutterBottom>Documents</Typography>
                             <Typography variant="body1">
                                 <strong>Resume:</strong>{' '}
                                 {application.resume_url ? (
                                     <Link href={application.resume_url} target="_blank" rel="noopener noreferrer">
                                         View/Download Resume
                                     </Link>
                                 ) : 'Not provided'}
                             </Typography>
                             <Typography variant="body1" mt={1}><strong>Cover Letter:</strong></Typography>
                             <Typography variant="body2" sx={{ whiteSpace: 'pre-wrap', maxHeight: '200px', overflowY: 'auto', border: '1px solid #eee', p: 1, background: '#f9f9f9' }}>
                                 {application.cover_letter || 'Not provided'}
                             </Typography>
                         </DetailSection>

                         <DetailSection>
                            <Typography variant="h6" gutterBottom>Personal & Work Info</Typography>
                            <Typography variant="body1"><strong>Current Employer:</strong> {application.current_employer || 'N/A'}</Typography>
                            <Typography variant="body1"><strong>Current Job Title:</strong> {application.current_job_title || 'N/A'}</Typography>
                            <Typography variant="body1"><strong>Years of Experience:</strong> {application.years_of_experience !== null ? application.years_of_experience : 'N/A'}</Typography>
                         </DetailSection>

                        <DetailSection>
                            <Typography variant="h6" gutterBottom>Work Experience Details</Typography>
                            <Typography variant="subtitle1" component="strong">Work Experience:</Typography>
                            {Array.isArray(application.work_experience) && application.work_experience.length > 0 ? (
                                application.work_experience.map((exp, index) => (
                                    <Box key={exp.id || index} sx={{ border: '1px solid #ccc', p: 2, mt: 1, borderRadius: '4px' }}>
                                        <Typography variant="body2"><strong>Job Title:</strong> {exp.job_title || 'N/A'}</Typography>
                                        <Typography variant="body2"><strong>Company:</strong> {exp.company || 'N/A'}</Typography>
                                        <Typography variant="body2"><strong>Dates:</strong> {exp.start_date || 'N/A'} - {exp.end_date || 'Present'}</Typography>
                                        <Typography variant="body2" sx={{ mt: 1 }}><strong>Description:</strong> {exp.description || 'N/A'}</Typography>
                                    </Box>
                                ))
                            ) : (
                                <Typography variant="body2">N/A</Typography>
                            )}
                        </DetailSection>

                    </Grid>

                    <Grid item xs={12} md={6}>
                        <DetailSection>
                            <Typography variant="h6" gutterBottom>Education</Typography>
                            <Typography variant="subtitle1" component="strong">Highest Level of Education:</Typography>
                            <Typography variant="body2">{getEducationLabel(application.highest_education)}</Typography>
                            <Typography variant="subtitle1" component="strong" mt={1}>Education History:</Typography>
                            {Array.isArray(application.education_details) && application.education_details.length > 0 ? (
                                application.education_details.map((edu, index) => (
                                    <Box key={edu.id || index} sx={{ border: '1px solid #ccc', p: 2, mt: 1, borderRadius: '4px' }}>
                                        <Typography variant="body2"><strong>Institution:</strong> {edu.institution || 'N/A'}</Typography>
                                        <Typography variant="body2"><strong>Degree:</strong> {edu.degree || 'N/A'}</Typography>
                                        <Typography variant="body2"><strong>Field of Study:</strong> {edu.field_of_study || 'N/A'}</Typography>
                                        <Typography variant="body2"><strong>Graduation Date:</strong> {edu.graduation_date || 'N/A'}</Typography>
                                    </Box>
                                ))
                            ) : (
                                <Typography variant="body2">N/A</Typography>
                            )}
                        </DetailSection>

                         <DetailSection>
                            <Typography variant="h6" gutterBottom>Skills & Qualifications</Typography>
                            <Typography variant="body1"><strong>Skills:</strong> {application.skills || 'N/A'}</Typography>
                            <Typography variant="body1"><strong>Certifications:</strong> {application.certifications || 'N/A'}</Typography>
                            <Typography variant="body1"><strong>Languages:</strong> {application.languages || 'N/A'}</Typography>
                         </DetailSection>

                        <DetailSection>
                             <Typography variant="h6" gutterBottom>Additional Information</Typography>
                            <Typography variant="body1"><strong>Referral Source:</strong> {application.referral_source || 'N/A'}</Typography>
                            <Typography variant="body1"><strong>Willing to Relocate:</strong> {renderBool(application.willing_to_relocate)}</Typography>
                            <Typography variant="body1"><strong>Available Start Date:</strong> {application.available_start_date || 'N/A'}</Typography>
                             <Typography variant="body1"><strong>Salary Expectations:</strong> {application.salary_expectations || 'N/A'}</Typography>
                         </DetailSection>

                         <DetailSection>
                             <Typography variant="h6" gutterBottom>Legal Information</Typography>
                            <Typography variant="body1"><strong>Authorized to Work:</strong> {renderBool(application.legally_authorized_to_work)}</Typography>
                            <Typography variant="body1"><strong>Requires Sponsorship:</strong> {renderBool(application.require_sponsorship)}</Typography>
                         </DetailSection>

                        <DetailSection>
                            <Typography variant="h6" gutterBottom>Diversity Information (Optional)</Typography>
                             <Typography variant="body1"><strong>Gender:</strong> {application.gender || 'N/A'}</Typography>
                            <Typography variant="body1"><strong>Ethnicity:</strong> {application.ethnicity || 'N/A'}</Typography>
                            <Typography variant="body1"><strong>Veteran Status:</strong> {application.veteran_status || 'N/A'}</Typography>
                             <Typography variant="body1"><strong>Disability Status:</strong> {application.disability_status || 'N/A'}</Typography>
                        </DetailSection>
                    </Grid>
                </Grid>

                 <Divider sx={{ my: 3 }} />
                 <Button variant="outlined" onClick={() => navigate('/admin/applications')}>
                     Back to Applications List
                </Button>

            </ReviewPaper>
        </Container>
    );
};

export default ApplicationReview; 