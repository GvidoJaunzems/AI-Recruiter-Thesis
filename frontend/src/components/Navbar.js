import React, { useState } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import {
  AppBar, Toolbar, Typography, Button, Box, IconButton, Menu, MenuItem,
  Avatar, Drawer, List, ListItem, ListItemText, Divider, ListItemIcon
} from '@mui/material';
import { styled } from '@mui/material/styles';
import MenuIcon from '@mui/icons-material/Menu';
import AccountCircleIcon from '@mui/icons-material/AccountCircle';
import WorkIcon from '@mui/icons-material/Work';
import HomeIcon from '@mui/icons-material/Home';
import ExitToAppIcon from '@mui/icons-material/ExitToApp';
import PersonIcon from '@mui/icons-material/Person';
import AdminPanelSettingsIcon from '@mui/icons-material/AdminPanelSettings';
import AssignmentIcon from '@mui/icons-material/Assignment';
import { useAuth } from '../contexts/AuthContext';

// Styled components
const StyledAppBar = styled(AppBar)(({ theme }) => ({
  backgroundColor: theme.palette.primary.main,
}));

const LogoText = styled(Typography)(({ theme }) => ({
  fontWeight: 700,
  letterSpacing: 1,
  cursor: 'pointer',
  display: 'flex',
  alignItems: 'center',
}));

const NavButton = styled(Button)(({ theme }) => ({
  color: 'white',
  marginLeft: theme.spacing(2),
}));

const Navbar = () => {
  const { currentUser, logout, isAdmin } = useAuth();
  const navigate = useNavigate();
  
  // Mobile menu state
  const [mobileOpen, setMobileOpen] = useState(false);
  
  // User menu state
  const [anchorEl, setAnchorEl] = useState(null);
  const open = Boolean(anchorEl);
  
  // Page links
  const pages = [
    { title: 'Home', path: '/', icon: <HomeIcon /> },
    { title: 'Jobs', path: '/jobs', icon: <WorkIcon /> },
  ];
  
  const privatePages = [
    { title: 'Profile', path: '/profile', icon: <PersonIcon /> },
    { title: 'My Applications', path: '/my-applications', icon: <AssignmentIcon /> },
  ];
  
  const adminPages = [
    { title: 'Admin Dashboard', path: '/admin', icon: <AdminPanelSettingsIcon /> },
  ];
  
  // Toggle mobile menu
  const handleDrawerToggle = () => {
    setMobileOpen(!mobileOpen);
  };
  
  // User menu handlers
  const handleMenu = (event) => {
    setAnchorEl(event.currentTarget);
  };
  
  const handleClose = () => {
    setAnchorEl(null);
  };
  
  // Handle logout
  const handleLogout = () => {
    logout();
    handleClose();
    navigate('/');
  };
  
  // Mobile drawer content
  const drawer = (
    <Box sx={{ width: 250 }} role="presentation" onClick={handleDrawerToggle}>
      <Box sx={{ p: 2 }}>
        <Typography variant="h6" component="div">
          Recruiter App
        </Typography>
      </Box>
      <Divider />
      <List>
        {pages.map((page) => (
          <ListItem 
            button 
            key={page.title} 
            component={RouterLink} 
            to={page.path}
            sx={{ py: 1 }}
          >
            <ListItemIcon>
              {page.icon}
            </ListItemIcon>
            <ListItemText primary={page.title} />
          </ListItem>
        ))}
      </List>
      <Divider />
      {currentUser ? (
        <>
          <List>
            {privatePages.map((page) => (
              <ListItem 
                button 
                key={page.title} 
                component={RouterLink} 
                to={page.path}
                sx={{ py: 1 }}
              >
                <ListItemIcon>
                  {page.icon}
                </ListItemIcon>
                <ListItemText primary={page.title} />
              </ListItem>
            ))}
            {isAdmin && adminPages.map((page) => (
              <ListItem 
                button 
                key={page.title} 
                component={RouterLink} 
                to={page.path}
                sx={{ py: 1 }}
              >
                <ListItemIcon>
                  {page.icon}
                </ListItemIcon>
                <ListItemText primary={page.title} />
              </ListItem>
            ))}
          </List>
          <Divider />
          <List>
            <ListItem button onClick={handleLogout} sx={{ py: 1 }}>
              <ListItemIcon>
                <ExitToAppIcon />
              </ListItemIcon>
              <ListItemText primary="Logout" />
            </ListItem>
          </List>
        </>
      ) : (
        <List>
          <ListItem button component={RouterLink} to="/login" sx={{ py: 1 }}>
            <ListItemIcon>
              <AccountCircleIcon />
            </ListItemIcon>
            <ListItemText primary="Login" />
          </ListItem>
          <ListItem button component={RouterLink} to="/register" sx={{ py: 1 }}>
            <ListItemIcon>
              <PersonIcon />
            </ListItemIcon>
            <ListItemText primary="Register" />
          </ListItem>
        </List>
      )}
    </Box>
  );
  
  return (
    <>
      <StyledAppBar position="static">
        <Toolbar>
          <IconButton
            color="inherit"
            aria-label="open drawer"
            edge="start"
            onClick={handleDrawerToggle}
            sx={{ mr: 2, display: { sm: 'none' } }}
          >
            <MenuIcon />
          </IconButton>
          
          <LogoText 
            variant="h6" 
            component="div" 
            sx={{ flexGrow: 1 }}
            onClick={() => navigate('/')}
          >
            RECRUITER
          </LogoText>
          
          {/* Desktop Navigation */}
          <Box sx={{ display: { xs: 'none', sm: 'flex' } }}>
            {pages.map((page) => (
              <NavButton
                key={page.title}
                component={RouterLink}
                to={page.path}
              >
                {page.title}
              </NavButton>
            ))}
          </Box>
          
          {currentUser ? (
            <Box sx={{ display: 'flex', alignItems: 'center' }}>
              {isAdmin && (
                <NavButton
                  component={RouterLink}
                  to="/admin"
                  sx={{ display: { xs: 'none', sm: 'block' } }}
                >
                  Admin
                </NavButton>
              )}
              
              <IconButton
                aria-label="account of current user"
                aria-controls="menu-appbar"
                aria-haspopup="true"
                onClick={handleMenu}
                color="inherit"
              >
                <Avatar sx={{ width: 32, height: 32, bgcolor: 'secondary.main' }}>
                  {currentUser.name ? currentUser.name.charAt(0).toUpperCase() : 'U'}
                </Avatar>
              </IconButton>
              <Menu
                id="menu-appbar"
                anchorEl={anchorEl}
                anchorOrigin={{
                  vertical: 'bottom',
                  horizontal: 'right',
                }}
                keepMounted
                transformOrigin={{
                  vertical: 'top',
                  horizontal: 'right',
                }}
                open={open}
                onClose={handleClose}
              >
                <MenuItem component={RouterLink} to="/profile" onClick={handleClose}>
                  Profile
                </MenuItem>
                <MenuItem component={RouterLink} to="/my-applications" onClick={handleClose}>
                  My Applications
                </MenuItem>
                <Divider />
                <MenuItem onClick={handleLogout}>Logout</MenuItem>
              </Menu>
            </Box>
          ) : (
            <Box>
              <NavButton component={RouterLink} to="/login">
                Login
              </NavButton>
              <NavButton component={RouterLink} to="/register">
                Register
              </NavButton>
            </Box>
          )}
        </Toolbar>
      </StyledAppBar>
      
      {/* Mobile Navigation Drawer */}
      <Drawer
        variant="temporary"
        open={mobileOpen}
        onClose={handleDrawerToggle}
        ModalProps={{
          keepMounted: true, // Better mobile performance
        }}
        sx={{
          display: { xs: 'block', sm: 'none' },
          '& .MuiDrawer-paper': { boxSizing: 'border-box', width: 250 },
        }}
      >
        {drawer}
      </Drawer>
    </>
  );
};

export default Navbar; 