import React, { useEffect, useState } from 'react';
import { Box, Typography } from '@mui/material';
import './SplashScreen.css';

const SplashScreen = ({ onComplete }) => {
  const [show, setShow] = useState(true);

  useEffect(() => {
    const timer = setTimeout(() => {
      setShow(false);
      if (onComplete) onComplete();
    }, 3000);

    return () => clearTimeout(timer);
  }, [onComplete]);

  if (!show) return null;

  return (
    <Box className="splash-screen">
      <Box className="splash-content">
        <img 
          src="/assets/tkthao219-bubududu.gif" 
          alt="Loading..." 
          className="splash-gif"
        />
        <Typography variant="h5" className="splash-text">
          कaam kr lo bhai nhi maare jaoge 😄💪
        </Typography>
      </Box>
    </Box>
  );
};

export default SplashScreen;