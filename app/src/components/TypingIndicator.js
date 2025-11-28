import React from 'react'
import { Box, Typography } from '@mui/material'

export default function TypingIndicator({ name = 'User' }) {
  return (
    <Box sx={{
      display: 'flex',
      alignItems: 'center',
      gap: 0.5,
      py: 1,
      px: 1.5
    }}>
      <Typography variant="body2" color="textSecondary">
        {name} is typing
      </Typography>
      <Box sx={{ display: 'flex', gap: 0.3, ml: 0.5 }}>
        {[0, 1, 2].map((i) => (
          <Box
            key={i}
            sx={{
              width: 6,
              height: 6,
              borderRadius: '50%',
              bgcolor: '#0e7c7b',
              animation: `bounce 1.4s infinite`,
              animationDelay: `${i * 0.2}s`,
              '@keyframes bounce': {
                '0%, 60%, 100%': { opacity: 0.7, transform: 'translateY(0)' },
                '30%': { opacity: 1, transform: 'translateY(-8px)' }
              }
            }}
          />
        ))}
      </Box>
    </Box>
  )
}
