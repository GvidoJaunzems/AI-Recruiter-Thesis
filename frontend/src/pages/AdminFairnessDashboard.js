import React, { useState, useEffect, useCallback } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
    Container, Paper, Typography, Box, CircularProgress, Alert, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Link, Divider, Button, Snackbar
} from '@mui/material';
import { styled } from '@mui/material/styles';
import axios from 'axios';
import { API_URL } from '../config';
import { useAuth } from '../contexts/AuthContext';
import PlayCircleOutlineIcon from '@mui/icons-material/PlayCircleOutline';

const DashboardPaper = styled(Paper)(({ theme }) => ({
    padding: theme.spacing(3),
    marginTop: theme.spacing(3),
    marginBottom: theme.spacing(3),
}));

const AdminFairnessDashboard = () => {
    const [metrics, setMetrics] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const { getAuthHeader } = useAuth();
    
    const [isCalculating, setIsCalculating] = useState(false);
    const [triggerStatus, setTriggerStatus] = useState({ open: false, message: '', severity: 'info' });

    const fetchMetrics = useCallback(async () => {
        setLoading(true);
        setError('');
        try {
            const headers = getAuthHeader();
            if (!headers) throw new Error('Authentication required.');
            
            const url = `${API_URL}/admin_fairness_metrics.php`;
            console.log(`Fetching fairness metrics from: ${url}`);
            const response = await axios.get(url, { headers });

            if (response.data && !response.data.error && Array.isArray(response.data)) {
                console.log("Metrics received:", response.data);
                setMetrics(response.data);
            } else {
                throw new Error(response.data?.error || 'Failed to load metrics or unexpected format.');
            }
        } catch (err) {
            console.error("Error fetching fairness metrics:", err);
            setError(err.response?.data?.error || err.message || 'Could not load fairness metrics.');
        } finally {
            setLoading(false);
        }
    }, [getAuthHeader]);

    useEffect(() => {
        fetchMetrics();
    }, [fetchMetrics]);

    const handleTriggerCalculation = async () => {
        setIsCalculating(true);
        setTriggerStatus({ open: false, message: '', severity: 'info' });
        try {
             const headers = getAuthHeader();
             if (!headers) throw new Error('Authentication required.');
             
             const url = `${API_URL}/admin_trigger_metric_calculation.php`;
             console.log(`Triggering metric calculation via: ${url}`);
             const response = await axios.post(url, {}, { headers }); 

             console.log("Trigger Response Status:", response.status);
             console.log("Trigger Response Data:", response.data);

             if (response.status === 200 && response.data && response.data.message) {
                 setTriggerStatus({ open: true, message: response.data.message, severity: 'success' });
                 console.log("Metric calculation completed synchronously.");
                 console.log("Script Output:", response.data.output);
                 fetchMetrics();
             } else {
                 console.error("Trigger response condition failed. Status:", response.status, "Data:", response.data);
                 throw new Error(response.data?.error || `Unexpected response status: ${response.status}`);
             }
        } catch (err) {
            console.error("Error triggering metric calculation:", err);
            console.error("Error object in catch:", err);
            const errorMsg = err.response?.data?.error || err.message || 'Could not trigger calculation.';
            setTriggerStatus({ open: true, message: `Error: ${errorMsg}`, severity: 'error' });
        } finally {
            setIsCalculating(false);
        }
    };
    
    const handleCloseSnackbar = (event, reason) => {
        if (reason === 'clickaway') {
            return;
        }
        setTriggerStatus(prev => ({ ...prev, open: false }));
    };

    const formatValue = (metricName, value) => {
        if (value === null || value === undefined) return 'N/A';
        const numericValue = parseFloat(value);
        if (isNaN(numericValue)) return 'Invalid Data';

        if (metricName.includes('rate')) {
            return `${(numericValue * 100).toFixed(1)}%`;
        } else if (metricName.includes('score')) {
            return numericValue.toFixed(2);
        }
        // Default for counts or other numeric values
        return numericValue.toLocaleString(); 
    };
    
    // Helper to render a metric group table
    const renderMetricTable = (metricGroup) => {
        if (!metricGroup || !metricGroup.data || metricGroup.data.length === 0) {
            return <Typography sx={{ mt: 1, fontStyle: 'italic' }}>No data available for this metric yet.</Typography>;
        }

        const hasJobColumn = metricGroup.data.some(dp => dp.job_id !== null);
        const hasStratumColumn = metricGroup.data.some(dp => dp.stratum !== null);

        return (
            <TableContainer component={Paper} variant="outlined" sx={{ mt: 1 }}>
                <Table size="small">
                    <TableHead>
                        <TableRow sx={{ backgroundColor: 'action.hover' }}>
                            {hasJobColumn && <TableCell>Job Title</TableCell>}
                            {hasStratumColumn && <TableCell>Group / Band</TableCell>}
                            <TableCell align="right">Value</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {metricGroup.data.map((dataPoint, dpIndex) => (
                            <TableRow key={dpIndex} sx={{ '&:last-child td, &:last-child th': { border: 0 } }}>
                                {hasJobColumn && (
                                    <TableCell>{dataPoint.job_title || `Job ID: ${dataPoint.job_id}`}</TableCell>
                                )}
                                {hasStratumColumn && (
                                    <TableCell>{dataPoint.stratum}</TableCell>
                                )}
                                <TableCell align="right">
                                    {formatValue(metricGroup.metric_name, dataPoint.value)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>
        );
    };

    return (
        <Container maxWidth="lg">
            <DashboardPaper>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2, flexWrap: 'wrap', gap: 1 }}>
                    <Typography variant="h4" component="h2" gutterBottom>
                        AI Fairness & Performance Metrics
                    </Typography>
                    <Button
                        variant="contained"
                        color="primary"
                        onClick={handleTriggerCalculation}
                        disabled={isCalculating}
                        startIcon={isCalculating ? <CircularProgress size={20} color="inherit" /> : <PlayCircleOutlineIcon />}
                    >
                        {isCalculating ? 'Calculating...' : 'Run Metric Calculation'}
                    </Button>
                </Box>
                <Typography paragraph color="text.secondary">
                    This dashboard shows metrics calculated periodically from the AI audit log to help monitor performance and potential biases.
                    These metrics are indicators and require human interpretation. Click the button above to update the metrics (runs in background).
                </Typography>

                {loading && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', my: 5 }}>
                        <CircularProgress />
                    </Box>
                )}
                {error && (
                    <Alert severity="error" sx={{ my: 3 }}>{error}</Alert>
                )}
                {!loading && !error && metrics.length === 0 && (
                    <Alert severity="info" sx={{ my: 3 }}>
                        No fairness metrics have been calculated yet. Ensure the calculation script has run.
                    </Alert>
                )}

                {/* Metrics Display - Use helper function */}
                {!loading && !error && metrics.length > 0 && (
                     <Typography variant="h5" sx={{ mb: 2, mt: 3 }}>Metric Results</Typography>
                )}
                {!loading && !error && metrics.map((metricGroup, index) => (
                    <Box key={index} sx={{ mb: 4 }}>
                        <Typography variant="h6" gutterBottom>
                            {/* Improved title formatting */}
                            {metricGroup.metric_name
                                .replace(/_/g, ' ') // Replace underscores first
                                .replace(/\b\w/g, l => l.toUpperCase())} 
                             {/* Add explicit warning for inferred metrics */}
                            {(metricGroup.metric_name.includes('_inferred_')) && 
                                <Typography variant="caption" color="text.secondary" sx={{ ml: 1 }}>(AI-Inferred - Use with Caution)</Typography> 
                            }
                        </Typography>
                        <Typography variant="caption" display="block" sx={{ mb: 1 }}>
                            Last Calculated: {metricGroup.last_calculated ? new Date(metricGroup.last_calculated).toLocaleString() : 'N/A'}
                        </Typography>
                        
                        {renderMetricTable(metricGroup)}
                        
                    </Box>
                ))}
                
                 <Divider sx={{ my: 4 }} />
                 <Link component={RouterLink} to="/admin/dashboard"> 
                     &laquo; Back to Admin Dashboard
                 </Link>

            </DashboardPaper>
             <Snackbar 
                open={triggerStatus.open} 
                autoHideDuration={6000} 
                onClose={handleCloseSnackbar}
                anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
            >
                <Alert onClose={handleCloseSnackbar} severity={triggerStatus.severity} sx={{ width: '100%' }}>
                    {triggerStatus.message}
                </Alert>
            </Snackbar>
        </Container>
    );
};

export default AdminFairnessDashboard; 