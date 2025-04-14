import React from 'react';
import {
    Container, Paper, Typography, Box, Link
} from '@mui/material';
import { styled } from '@mui/material/styles';
import { Link as RouterLink } from 'react-router-dom';

const InfoPaper = styled(Paper)(({ theme }) => ({
    padding: theme.spacing(4),
    marginTop: theme.spacing(3),
    marginBottom: theme.spacing(3),
}));

const TransparencyInfo = () => {

    return (
        <Container maxWidth="md">
            <InfoPaper>
                <Typography variant="h4" component="h2" gutterBottom>
                    AI Usage & Transparency
                </Typography>

                <Typography variant="h6" gutterBottom sx={{ mt: 3 }}>
                    How AI is Used in Application Review
                </Typography>
                <Typography paragraph>
                    This platform utilizes Artificial Intelligence (AI), specifically Google's Gemini models,
                    to assist recruiters during the application review process. The AI provides the following
                    features to help streamline evaluation:
                </Typography>
                <ul>
                    <li>
                        <Typography component="li">
                            <strong>AI Score:</strong> The AI analyzes the applicant's resume, cover letter, and other submitted information
                            against the job description requirements to generate a relevance score (0-100). This score is intended
                            as a preliminary indicator of potential fit.
                        </Typography>
                    </li>
                     <li>
                        <Typography component="li">
                            <strong>AI Score Explanation:</strong> Alongside the score, the AI provides a textual explanation detailing the primary reasons
                            for the assigned score, highlighting key matches or mismatches found.
                        </Typography>
                    </li>
                     <li>
                        <Typography component="li">
                            <strong>AI Key Factors:</strong> The system identifies and displays the top factors (positive or negative) from the application
                            that most significantly influenced the AI's assessment.
                        </Typography>
                    </li>
                    <li>
                        <Typography component="li">
                            <strong>AI Data Extraction:</strong> The AI attempts to extract structured information (like skills, years of experience, education level)
                            from the application documents to enable more efficient filtering and review.
                        </Typography>
                    </li>
                </ul>

                 <Typography variant="h6" gutterBottom sx={{ mt: 3 }}>
                    Important Considerations & Limitations
                </Typography>
                 <Typography paragraph>
                    It is crucial to understand that the AI features are designed as decision-support tools, not decision-makers.
                    Human oversight by recruiters and hiring managers remains essential throughout the hiring process.
                </Typography>
                 <ul>
                     <li>
                        <Typography component="li">
                            AI models, while powerful, are not perfect. They may misinterpret information, overlook nuances, or exhibit unforeseen biases despite efforts to mitigate them.
                        </Typography>
                    </li>
                     <li>
                        <Typography component="li">
                            The AI score and analysis should be considered as one data point among many. Recruiters evaluate the complete application, including all submitted materials and potential interview performance.
                        </Typography>
                    </li>
                      <li>
                        <Typography component="li">
                           Final hiring decisions are always made by humans.
                        </Typography>
                    </li>
                 </ul>

                  <Typography variant="h6" gutterBottom sx={{ mt: 3 }}>
                    Commitment to Fairness
                </Typography>
                 <Typography paragraph>
                     We are committed to promoting fairness and reducing bias in our hiring process. We continuously monitor the performance of our AI tools
                     and strive to implement best practices in explainable and ethical AI.
                     {/* TODO (Phase 3): Add link to fairness monitoring dashboard/details */}
                 </Typography>
                 
                 <Typography paragraph sx={{ mt: 3 }}>
                     If you have questions or concerns about how AI is used on this platform, please contact the system administrator.
                 </Typography>
                 
                 <Box sx={{ mt: 4 }}>
                     <Link component={RouterLink} to="/admin/dashboard"> {/* Or appropriate dashboard route */}
                         &laquo; Back to Dashboard
                     </Link>
                 </Box>

            </InfoPaper>
        </Container>
    );
};

export default TransparencyInfo; 