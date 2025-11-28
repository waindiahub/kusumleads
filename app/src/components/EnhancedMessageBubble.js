import React, { useState } from 'react'
import {
  Box,
  Paper,
  Typography,
  IconButton,
  Menu,
  MenuItem,
  Chip,
  Dialog,
  CircularProgress
} from '@mui/material'
import {
  MoreVert,
  Download,
  Reply,
  Copy,
  Delete,
  PlayArrow,
  Pause
} from '@mui/icons-material'

const formatTime = (timestamp) => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const getStatusColor = (status) => {
  const colors = {
    sent: '#4caf50',
    delivered: '#2196f3',
    read: '#1976d2',
    failed: '#f44336',
    pending: '#ff9800'
  }
  return colors[status] || '#666'
}

const getStatusIcon = (status) => {
  return {
    sent: '✓',
    delivered: '✓✓',
    read: '✓✓',
    failed: '✗',
    pending: '...'
  }[status] || ''
}

export default function EnhancedMessageBubble({ message, onReply, onDelete, isOutgoing }) {
  const [anchorEl, setAnchorEl] = useState(null)
  const [mediaDialogOpen, setMediaDialogOpen] = useState(false)
  const [isPlaying, setIsPlaying] = useState(false)
  const audioRef = React.useRef(null)

  const handleMenuOpen = (e) => setAnchorEl(e.currentTarget)
  const handleMenuClose = () => setAnchorEl(null)

  const handleCopy = () => {
    if (message.body) {
      navigator.clipboard.writeText(message.body)
      handleMenuClose()
    }
  }

  const handleDownload = () => {
    if (message.media_url) {
      const link = document.createElement('a')
      link.href = message.media_url
      link.download = message.filename || 'attachment'
      link.click()
    }
    handleMenuClose()
  }

  const messageType = (message.type || '').toLowerCase()
  const mediaUrl = message.media_url
  const hasMedia = mediaUrl && messageType !== 'text'

  return (
    <>
      <Box sx={{
        display: 'flex',
        justifyContent: isOutgoing ? 'flex-end' : 'flex-start',
        mb: 1.5,
        alignItems: 'flex-end',
        gap: 1,
        px: isOutgoing ? 2 : 0,
        group: '&:hover'
      }}>
        <Box sx={{ display: 'flex', alignItems: 'flex-end', gap: 0.5 }}>
          <Paper elevation={0} sx={{
            maxWidth: { xs: '85%', sm: '70%' },
            p: 1.5,
            bgcolor: isOutgoing ? '#d1f4cc' : '#ffffff',
            borderRadius: 2.5,
            borderTopRightRadius: isOutgoing ? 4 : 20,
            borderTopLeftRadius: isOutgoing ? 20 : 4,
            boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
            border: isOutgoing ? 'none' : '1px solid rgba(0,0,0,0.05)',
            position: 'relative'
          }}>
            {/* Reply indicator */}
            {message.replied_to_id && (
              <Box sx={{
                pl: 1,
                py: 0.5,
                mb: 1,
                borderLeft: '3px solid #0e7c7b',
                bgcolor: 'rgba(14,124,123,0.05)',
                borderRadius: 1
              }}>
                <Typography variant="caption" color="textSecondary">
                  Replied to...
                </Typography>
              </Box>
            )}

            {/* Media rendering */}
            {hasMedia && messageType === 'image' && (
              <Box sx={{ mb: 1, cursor: 'pointer' }} onClick={() => setMediaDialogOpen(true)}>
                <img
                  src={mediaUrl}
                  alt="Message"
                  style={{
                    maxWidth: '100%',
                    borderRadius: 8,
                    maxHeight: '300px'
                  }}
                />
              </Box>
            )}

            {hasMedia && messageType === 'video' && (
              <Box sx={{ mb: 1 }}>
                <video
                  controls
                  src={mediaUrl}
                  style={{
                    maxWidth: '100%',
                    borderRadius: 8,
                    maxHeight: '300px'
                  }}
                />
              </Box>
            )}

            {hasMedia && messageType === 'audio' && (
              <Box sx={{ mb: 1, p: 1, bgcolor: 'rgba(0,0,0,0.05)', borderRadius: 1 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <IconButton
                    size="small"
                    onClick={() => {
                      if (isPlaying) {
                        audioRef.current?.pause()
                      } else {
                        audioRef.current?.play()
                      }
                      setIsPlaying(!isPlaying)
                    }}
                  >
                    {isPlaying ? <Pause /> : <PlayArrow />}
                  </IconButton>
                  <audio
                    ref={audioRef}
                    src={mediaUrl}
                    onEnded={() => setIsPlaying(false)}
                  />
                  <Typography variant="caption">Audio message</Typography>
                </Box>
              </Box>
            )}

            {hasMedia && !['image', 'video', 'audio', 'sticker'].includes(messageType) && (
              <Box sx={{
                mb: 1,
                p: 1,
                bgcolor: '#f5f5f5',
                borderRadius: 1,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between'
              }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flex: 1 }}>
                  <Box sx={{
                    width: 40,
                    height: 40,
                    bgcolor: '#e0e0e0',
                    borderRadius: 1,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center'
                  }}>
                    📄
                  </Box>
                  <Box sx={{ flex: 1, minWidth: 0 }}>
                    <Typography variant="caption" sx={{ wordBreak: 'break-all' }}>
                      {message.filename || 'Document'}
                    </Typography>
                  </Box>
                </Box>
                <IconButton size="small" onClick={handleDownload}>
                  <Download fontSize="small" />
                </IconButton>
              </Box>
            )}

            {/* Text content */}
            {(message.body || messageType === 'text') && (
              <Typography variant="body2" sx={{
                whiteSpace: 'pre-wrap',
                wordBreak: 'break-word',
                mb: message.body ? 0.5 : 0
              }}>
                {message.body || messageType}
              </Typography>
            )}

            {/* Time and status */}
            <Box sx={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'flex-end',
              gap: 0.5,
              mt: 0.5
            }}>
              <Typography variant="caption" sx={{ color: 'rgba(0,0,0,0.5)' }}>
                {formatTime(message.created_at || message.timestamp)}
              </Typography>
              {isOutgoing && (
                <Typography variant="caption" sx={{
                  color: getStatusColor(message.status || 'sent'),
                  fontWeight: 'bold'
                }}>
                  {getStatusIcon(message.status || 'sent')}
                </Typography>
              )}
            </Box>

            {/* Menu button */}
            <IconButton
              size="small"
              onClick={handleMenuOpen}
              sx={{
                position: 'absolute',
                top: -8,
                right: isOutgoing ? -8 : 'auto',
                left: isOutgoing ? 'auto' : -8,
                opacity: 0,
                '&:hover': { opacity: 1 }
              }}
            >
              <MoreVert fontSize="small" />
            </IconButton>
          </Paper>

          {/* Menu */}
          <Menu anchorEl={anchorEl} open={!!anchorEl} onClose={handleMenuClose}>
            <MenuItem onClick={() => { onReply?.(message); handleMenuClose() }}>
              <Reply fontSize="small" sx={{ mr: 1 }} /> Reply
            </MenuItem>
            {message.body && (
              <MenuItem onClick={handleCopy}>
                <Copy fontSize="small" sx={{ mr: 1 }} /> Copy
              </MenuItem>
            )}
            {mediaUrl && (
              <MenuItem onClick={handleDownload}>
                <Download fontSize="small" sx={{ mr: 1 }} /> Download
              </MenuItem>
            )}
            {isOutgoing && (
              <MenuItem onClick={() => { onDelete?.(message); handleMenuClose() }}>
                <Delete fontSize="small" sx={{ mr: 1 }} /> Delete
              </MenuItem>
            )}
          </Menu>
        </Box>
      </Box>

      {/* Image viewer dialog */}
      <Dialog
        open={mediaDialogOpen}
        onClose={() => setMediaDialogOpen(false)}
        maxWidth="md"
        PaperProps={{ sx: { bgcolor: 'transparent', boxShadow: 'none' } }}
      >
        <img src={mediaUrl} alt="Full view" style={{ maxWidth: '100vw', maxHeight: '100vh' }} />
      </Dialog>
    </>
  )
}
