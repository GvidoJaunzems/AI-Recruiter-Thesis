import React, { useState, useEffect, useMemo } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
    Container, Paper, Typography, Box, CircularProgress, Alert,
    Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Button,
    TableSortLabel,
    TextField,
    MenuItem,
    Grid,
    Slider
} from '@mui/material';
import { styled } from '@mui/material/styles';
import axios from 'axios';
import { API_URL } from '../config';
import { useAuth } from '../contexts/AuthContext';

const ApplicationsPaper = styled(Paper)(({ theme }) => ({
    padding: theme.spacing(3),
    marginTop: theme.spacing(3),
    marginBottom: theme.spacing(3),
}));

const FilterBox = styled(Box)(({ theme }) => ({
    padding: theme.spacing(2),
    marginBottom: theme.spacing(3),
    border: `1px solid ${theme.palette.divider}`,
    borderRadius: theme.shape.borderRadius,
}));

function descendingComparator(a, b, orderBy) {
  if (b[orderBy] < a[orderBy]) {
    return -1;
  }
  if (b[orderBy] > a[orderBy]) {
    return 1;
  }
  return 0;
}

function getComparator(order, orderBy) {
  return order === 'desc'
    ? (a, b) => descendingComparator(a, b, orderBy)
    : (a, b) => -descendingComparator(a, b, orderBy);
}

function stableSort(array, comparator) {
  const stabilizedThis = array.map((el, index) => [el, index]);
  stabilizedThis.sort((a, b) => {
    const order = comparator(a[0], b[0]);
    if (order !== 0) return order;
    return a[1] - b[1];
  });
  return stabilizedThis.map((el) => el[0]);
}

const headCells = [
  { id: 'id', numeric: true, disablePadding: false, label: 'ID' },
  { id: 'applicant_name', numeric: false, disablePadding: false, label: 'Applicant' },
  { id: 'job_title', numeric: false, disablePadding: false, label: 'Job Title' },
  { id: 'created_at', numeric: false, disablePadding: false, label: 'Submitted At' },
  { id: 'status', numeric: false, disablePadding: false, label: 'Status' },
  { id: 'ai_score', numeric: true, disablePadding: false, label: 'AI Score' },
  { id: 'actions', numeric: false, disablePadding: false, label: 'Actions', sortable: false },
];

function EnhancedTableHead(props) {
  const { order, orderBy, onRequestSort } = props;
  const createSortHandler = (property) => (event) => {
    onRequestSort(event, property);
  };

  return (
    <TableHead>
      <TableRow>
        {headCells.map((headCell) => (
          <TableCell
            key={headCell.id}
            align={headCell.numeric ? 'right' : 'left'}
            padding={headCell.disablePadding ? 'none' : 'normal'}
            sortDirection={orderBy === headCell.id ? order : false}
          >
            {headCell.sortable !== false ? (
                 <TableSortLabel
                    active={orderBy === headCell.id}
                    direction={orderBy === headCell.id ? order : 'asc'}
                    onClick={createSortHandler(headCell.id)}
                >
                {headCell.label}
                {orderBy === headCell.id ? (
                    <Box component="span" sx={{ border: 0, clip: 'rect(0 0 0 0)', height: 1, margin: -1, overflow: 'hidden', padding: 0, position: 'absolute', top: 20, width: 1 }}>
                    {order === 'desc' ? 'sorted descending' : 'sorted ascending'}
                    </Box>
                ) : null}
                </TableSortLabel>
            ) : (
                headCell.label
            )}
          </TableCell>
        ))}
      </TableRow>
    </TableHead>
  );
}

const AdminApplications = () => {
    const [applications, setApplications] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const { getAuthHeader } = useAuth();

    const [order, setOrder] = useState('desc');
    const [orderBy, setOrderBy] = useState('created_at');

    const [statusFilter, setStatusFilter] = useState('');
    const [scoreFilter, setScoreFilter] = useState([0, 100]);
    const [applicantNameFilter, setApplicantNameFilter] = useState('');
    const [jobTitleFilter, setJobTitleFilter] = useState('');

    const applicationStatuses = ['pending', 'reviewed', 'accepted', 'rejected'];

    useEffect(() => {
        const fetchApplications = async () => {
            setLoading(true);
            setError('');
            try {
                const authHeaders = getAuthHeader();
                if (!authHeaders) {
                    throw new Error('User not authenticated.');
                }
                const response = await axios.get(`${API_URL}/applications.php`, {
                    headers: authHeaders
                });

                if (response.data && response.data.applications) {
                    const processedApps = response.data.applications.map(app => ({
                        ...app,
                        ai_score: app.ai_score !== null ? parseFloat(app.ai_score) : null,
                    }));
                    setApplications(processedApps);
                } else {
                     if(response.data && response.data.error) {
                        setError(`Failed to load applications: ${response.data.error}`);
                     } else {
                        setError('Failed to load applications: Unexpected response format.');
                     }
                    setApplications([]);
                }
            } catch (err) {
                console.error('Error fetching applications:', err);
                 const errorMsg = err.response?.data?.error || err.message || 'An error occurred while fetching applications.';
                setError(errorMsg);
                setApplications([]);
            } finally {
                setLoading(false);
            }
        };

        fetchApplications();
    }, [getAuthHeader]);

    const handleRequestSort = (event, property) => {
        const isAsc = orderBy === property && order === 'asc';
        setOrder(isAsc ? 'desc' : 'asc');
        setOrderBy(property);
    };

    const filteredApplications = useMemo(() => {
        return applications.filter((app) => {
            const score = app.ai_score !== null ? app.ai_score : -1;
            const nameMatch = applicantNameFilter ? (app.applicant_name || '').toLowerCase().includes(applicantNameFilter.toLowerCase()) : true;
            const jobMatch = jobTitleFilter ? (app.job_title || '').toLowerCase().includes(jobTitleFilter.toLowerCase()) : true;
            const statusMatch = statusFilter ? app.status === statusFilter : true;
            const scoreMatch = score === -1 || (score >= scoreFilter[0] && score <= scoreFilter[1]);
            
            return nameMatch && jobMatch && statusMatch && scoreMatch;
        });
    }, [applications, statusFilter, scoreFilter, applicantNameFilter, jobTitleFilter]);

    const sortedApplications = useMemo(() => {
        return stableSort(filteredApplications, getComparator(order, orderBy));
    }, [filteredApplications, order, orderBy]);

    const handleScoreChange = (event, newValue) => {
        setScoreFilter(newValue);
    };

    return (
        <Container maxWidth="xl">
            <ApplicationsPaper>
                <Typography variant="h4" gutterBottom>
                    Review Applications
                </Typography>

                <FilterBox>
                    <Typography variant="h6" gutterBottom>Filters</Typography>
                    <Grid container spacing={2} alignItems="center">
                        <Grid item xs={12} sm={6} md={3}>
                             <TextField
                                select
                                label="Status"
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                fullWidth
                                variant="outlined"
                                size="small"
                            >
                                <MenuItem value=""><em>All Statuses</em></MenuItem>
                                {applicationStatuses.map((status) => (
                                    <MenuItem key={status} value={status}>
                                        {status.charAt(0).toUpperCase() + status.slice(1)}
                                    </MenuItem>
                                ))}
                            </TextField>
                        </Grid>
                         <Grid item xs={12} sm={6} md={3}>
                            <TextField
                                label="Applicant Name"
                                value={applicantNameFilter}
                                onChange={(e) => setApplicantNameFilter(e.target.value)}
                                fullWidth
                                variant="outlined"
                                size="small"
                            />
                        </Grid>
                         <Grid item xs={12} sm={6} md={3}>
                             <TextField
                                label="Job Title"
                                value={jobTitleFilter}
                                onChange={(e) => setJobTitleFilter(e.target.value)}
                                fullWidth
                                variant="outlined"
                                size="small"
                            />
                        </Grid>
                        <Grid item xs={12} sm={6} md={3}>
                             <Typography gutterBottom>AI Score Range</Typography>
                            <Slider
                                value={scoreFilter}
                                onChange={handleScoreChange}
                                valueLabelDisplay="auto"
                                min={0}
                                max={100}
                                marks={[{value: 0, label: '0'}, {value: 100, label: '100'}]}
                                disableSwap
                            />
                        </Grid>
                    </Grid>
                </FilterBox>

                {loading ? (
                    <Box display="flex" justifyContent="center" my={4}>
                        <CircularProgress />
                    </Box>
                ) : error ? (
                    <Alert severity="error" sx={{ my: 2 }}>{error}</Alert>
                ) : (
                    <TableContainer>
                        <Table sx={{ minWidth: 750 }} aria-label="applications table">
                             <EnhancedTableHead
                                order={order}
                                orderBy={orderBy}
                                onRequestSort={handleRequestSort}
                            />
                            <TableBody>
                                {sortedApplications.length > 0 ? sortedApplications.map((app) => (
                                    <TableRow
                                        hover
                                        key={app.id}
                                        sx={{ '&:last-child td, &:last-child th': { border: 0 } }}
                                    >
                                        <TableCell align="right">{app.id}</TableCell>
                                        <TableCell>{app.applicant_name || 'N/A'}</TableCell>
                                        <TableCell>{app.job_title || 'N/A'}</TableCell>
                                        <TableCell>{new Date(app.created_at).toLocaleString()}</TableCell>
                                        <TableCell>{app.status}</TableCell>
                                         <TableCell align="right">
                                            {app.ai_score !== null ? app.ai_score.toFixed(2) : 'N/A'}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                component={RouterLink}
                                                to={`/admin/applications/${app.id}`}
                                                variant="outlined"
                                                size="small"
                                            >
                                                View Details
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                )) : (
                                    <TableRow>
                                        <TableCell colSpan={headCells.length} align="center">
                                            No applications match the current filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </TableContainer>
                )}
            </ApplicationsPaper>
        </Container>
    );
};

export default AdminApplications; 