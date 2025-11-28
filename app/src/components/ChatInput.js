import React, { useState, useRef } from 'react'
import {
  Box,
  Button,
  IconButton,
  TextField,
  CircularProgress,
  Paper,
  Tooltip,
  Menu,
  MenuItem
} from '@mui/material'
import {
  Send,
  AttachFile,
  EmojiEmotions,
  Mic,
  Close,
  Image as ImageIcon,
  Description,
  LocationOn,
  Person
} from '@mui/icons-material'
import { apiService } from '../services/ApiService'

export default function ChatInput({ onSendMessage, onSendMedia, conversationId, disabled = false }) {
  const [text, setText] = useState('')
  const [uploading, setUploading] = useState(false)
  const [mediaPreview, setMediaPreview] = useState(null)
  const [anchorEl, setAnchorEl] = useState(null)
  const fileInputRef = useRef(null)
  const mediaInputRef = useRef(null)

  const handleSendText = async () => {
    if (!text.trim()) return
    await onSendMessage({ type: 'text', text: text.trim() })
    setText('')
  }

  const handleMediaSelect = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return

    setUploading(true)
    try {
      const formData = new FormData()
      formData.append('file', file)

      const contentType = file.type || 'application/octet-stream'
      const presignRes = await apiService.presignPut(contentType, file.name)

      if (!presignRes.success) throw new Error(presignRes.message)

      const uploadRes = await fetch(presignRes.data.presigned_url, {
        method: 'PUT',
        body: file,
        headers: { 'Content-Type': contentType }
      })

      if (!uploadRes.ok) throw new Error('Upload failed')

      const publicUrlRes = await apiService.publicUrl(presignRes.data.key)
      if (!publicUrlRes.success) throw new Error('Failed to get public URL')

      const mediaType = contentType.startsWith('image/') ? 'image'
        : contentType.startsWith('video/') ? 'video'
        : contentType.startsWith('audio/') ? 'audio'
        : 'document'

      await onSendMedia({
        type: mediaType,
        url: publicUrlRes.data.url,
        caption: file.name
      })

      setMediaPreview(null)
    } catch (error) {
      console.error('Media upload failed:', error)
      alert('Failed to upload media: ' + error.message)
    } finally {
      setUploading(false)
      mediaInputRef.current.value = ''
    }
  }

  const handleMenuClick = (event) => {
    setAnchorEl(event.currentTarget)
  }

  const handleMenuClose = () => {
    setAnchorEl(null)
  }

  const attachmentOptions = [
    { label: 'Image', icon: <ImageIcon />, action: () => { mediaInputRef.current?.click() } },
    { label: 'Document', icon: <Description />, action: () => { mediaInputRef.current?.click() } },
    { label: 'Location', icon: <LocationOn />, action: () => { console.log('Location') } },
    { label: 'Contact', icon: <Person />, action: () => { console.log('Contact') } }
  ]

  return (
    <Box sx={{ p: 2, borderTop: '1px solid rgba(0,0,0,0.1)' }}>
      {mediaPreview && (
        <Paper sx={{ p: 1, mb: 1, bgcolor: '#f5f5f5', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
            <Box sx={{ width: 60, height: 60, bgcolor: '#e0e0e0', borderRadius: 1, overflow: 'hidden' }}>
              {mediaPreview.type.startsWith('image/') && (
                <img src={URL.createObjectURL(mediaPreview)} alt="preview" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
              )}
            </Box>
            <Box>
              <div style={{ fontWeight: 500 }}>{mediaPreview.name}</div>
              <div style={{ fontSize: '0.85rem', color: '#666' }}>{(mediaPreview.size / 1024).toFixed(0)} KB</div>
            </Box>
          </Box>
          <IconButton size="small" onClick={() => setMediaPreview(null)}>
            <Close />
          </IconButton>
        </Paper>
      )}

      <Box sx={{ display: 'flex', gap: 1, alignItems: 'flex-end' }}>
        <Tooltip title="Attach media">
          <div>
            <IconButton
              size="small"
              onClick={handleMenuClick}
              disabled={uploading || disabled}
              sx={{ color: '#0e7c7b' }}
            >
              <AttachFile />
            </IconButton>
          </div>
        </Tooltip>

        <Menu anchorEl={anchorEl} open={!!anchorEl} onClose={handleMenuClose}>
          {attachmentOptions.map((opt) => (
            <MenuItem key={opt.label} onClick={() => { opt.action(); handleMenuClose() }}>
              {opt.icon} {opt.label}
            </MenuItem>
          ))}
        </Menu>

        <TextField
          fullWidth
          multiline
          maxRows={4}
          placeholder="Type a message..."
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyPress={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault()
              handleSendText()
            }
          }}
          disabled={uploading || disabled}
          variant="outlined"
          size="small"
          sx={{
            '& .MuiOutlinedInput-root': {
              borderRadius: 2,
              backgroundColor: '#f9f9f9'
            }
          }}
        />

        <Tooltip title={uploading ? 'Uploading...' : 'Send message'}>
          <span>
            <IconButton
              onClick={handleSendText}
              disabled={!text.trim() || uploading || disabled}
              sx={{ color: '#0e7c7b', bgcolor: '#e0f2f1', '&:hover': { bgcolor: '#b2dfdb' } }}
            >
              {uploading ? <CircularProgress size={24} /> : <Send />}
            </IconButton>
          </span>
        </Tooltip>

        <input
          ref={mediaInputRef}
          type="file"
          hidden
          onChange={handleMediaSelect}
          accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx"
        />
      </Box>
    </Box>
  )
}
