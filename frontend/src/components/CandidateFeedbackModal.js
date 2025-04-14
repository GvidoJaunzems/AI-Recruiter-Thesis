import React, { useState, useEffect } from 'react';
import {
    Dialog, DialogTitle, DialogContent, DialogActions, Button, Typography, Box, CircularProgress, Alert, List, ListItem, ListItemText, Divider
} from '@mui/material';
import axios from 'axios';
import { API_URL } from '../config'; // Assuming API_URL is defined here
import { useAuth } from '../contexts/AuthContext'; // Assuming AuthContext provides token/headers

const CandidateFeedbackModal = ({ open, onClose, applicationId }) => {
    const [feedback, setFeedback] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const { getAuthHeader } = useAuth(); // Get headers for authenticated request

    useEffect(() => {
        if (open && applicationId) {
            const fetchFeedback = async () => {
                setLoading(true);
                setError('');
                setFeedback(null);
                try {
                    const headers = getAuthHeader();
                    if (!headers) {
                        throw new Error('Authentication required.');
                    }
                    const url = `${API_URL}/application_feedback.php?id=${applicationId}`;
                    console.log(`Fetching feedback from: ${url}`);
                    const response = await axios.get(url, { headers });

                    if (response.data && !response.data.error) {
                        console.log("Feedback received:", response.data);
                        setFeedback(response.data);
                    } else {
                        throw new Error(response.data?.error || 'Failed to load feedback.');
                    }
                } catch (err) {
                    console.error("Error fetching feedback:", err);
                    setError(err.response?.data?.error || err.message || 'Could not load feedback at this time.');
                } finally {
                    setLoading(false);
                }
            };

            fetchFeedback();
        }
    }, [open, applicationId, getAuthHeader]);

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>Application Feedback</DialogTitle>
            <DialogContent dividers>
                {loading && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', my: 3 }}>
                        <CircularProgress />
                    </Box>
                )}
                {error && (
                    <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>
                )}
                {feedback && (
                    <Box>
                        <Typography variant="h6" gutterBottom>AI Assessment Summary:</Typography>
                        <Typography variant="body1" paragraph sx={{ fontStyle: 'italic' }}>
                            {feedback.ai_candidate_explanation || 'No explanation available.'}
                        </Typography>
                        
                        {feedback.ai_counterfactuals && feedback.ai_counterfactuals.length > 0 && (
                            <>
                                <Divider sx={{ my: 2 }} />
                                <Typography variant="h6" gutterBottom>Suggestions for Future Applications:</Typography>
                                <List dense>
                                    {feedback.ai_counterfactuals.map((suggestion, index) => (
                                        <ListItem key={index}>
                                            <ListItemText primary={`• ${suggestion}`} />
                                        </ListItem>
                                    ))}
                                </List>
                                <Typography variant="caption" display="block" sx={{ mt: 1 }}>
                                    Note: These are AI-generated suggestions based on the specific job description and may not apply universally.
                                </Typography>
                            </>
                        )}
                    </Box>
                )}
                 {!loading && !error && !feedback && (
                    <Typography>Feedback details are being loaded or are unavailable.</Typography>
                 )}
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Close</Button>
            </DialogActions>
        </Dialog>
    );
};

export default CandidateFeedbackModal; 