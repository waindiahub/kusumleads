import React, { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  AttachFile,
  CameraAlt,
  EmojiEmotions,
  KeyboardArrowLeft,
  MoreVert,
  PictureAsPdf,
  Send,
  Share,
  ShieldMoon
} from '@mui/icons-material'
import {
  Avatar,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  Drawer,
  IconButton,
  Paper,
  Switch,
  TextField,
  Typography
} from '@mui/material'
import { apiService } from '../services/ApiService'
import WhatsAppTemplateSelector from '../components/WhatsAppTemplateSelector'

const quickReplies = [
  'Attendance IN',
  'Attendance OUT',
  'Fees Reminder',
  'Share Location',
  'Send Homework'
]

const infoSections = [
  { label: 'Payments', icon: '💵' },
  { label: 'Campaigns', icon: '📣' },
  { label: 'Attributes', icon: '🧾' },
  { label: 'Customer Journey', icon: '⏱️' }
]

const formatDate = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const MessageBubble = ({ message }) => {
  const isOutgoing = message.direction === 'outgoing'
  const mediaUrl = message.media_url
  const type = (message.type || '').toLowerCase()
  return (
    <Box
      sx={{
        display: 'flex',
        justifyContent: isOutgoing ? 'flex-end' : 'flex-start',
        mb: 1.5,
        pl: isOutgoing ? 6 : 0,
        pr: isOutgoing ? 0 : 6
      }}
    >
      <Paper
        elevation={0}
        sx={{
          maxWidth: '85%',
          p: 1.5,
          bgcolor: isOutgoing ? '#d1f4cc' : '#ffffff',
          borderRadius: 3,
          borderTopRightRadius: isOutgoing ? 0 : 20,
          borderTopLeftRadius: isOutgoing ? 20 : 0,
          boxShadow: '0 4px 15px rgba(0,0,0,0.05)'
        }}
      >
        {mediaUrl && type === 'image' && (
          <Box sx={{ mb: 0.5 }}>
            <img src={mediaUrl} alt="Attachment" style={{ maxWidth: '100%', borderRadius: 8 }} />
          </Box>
        )}
        {mediaUrl && type === 'video' && (
          <Box sx={{ mb: 0.5 }}>
            <video controls src={mediaUrl} style={{ maxWidth: '100%', borderRadius: 8 }} />
          </Box>
        )}
        {mediaUrl && type === 'audio' && (
          <Box sx={{ mb: 0.5 }}>
            <audio controls src={mediaUrl} />
          </Box>
        )}
        {mediaUrl && !['image','video','audio','sticker'].includes(type) && (
          <Box sx={{ mb: 0.5, display: 'inline-flex', alignItems: 'center', gap: 0.5 }}>
            <PictureAsPdf fontSize="small" />
            <a href={mediaUrl} target="_blank" rel="noreferrer">Download attachment</a>
          </Box>
        )}
        {(message.body || message.type) && (
          <Typography variant="body2" sx={{ whiteSpace: 'pre-wrap' }}>
            {message.body || message.type}
          </Typography>
        )}
        <Typography variant="caption" color="text.secondary" sx={{ mt: 0.5, display: 'block', textAlign: 'right' }}>
          {formatDate(message.created_at || message.timestamp)}
        </Typography>
      </Paper>
    </Box>
  )
}

const ContactProfileDrawer = ({ open, onClose, contact }) => {
  const [optedIn, setOptedIn] = useState(true)
  const [blocked, setBlocked] = useState(false)
  const [tags, setTags] = useState(['Payments', 'Campaigns'])

  const addNewTag = () => {
    const label = prompt('Enter tag name')
    if (label && !tags.includes(label)) {
      setTags([...tags, label])
    }
  }

  return (
    <Drawer
      anchor="bottom"
      open={open}
      onClose={onClose}
      PaperProps={{
        sx: {
          borderTopLeftRadius: 32,
          borderTopRightRadius: 32,
          p: 3,
          minHeight: '80vh'
        }
      }}
    >
      <Box sx={{ textAlign: 'center' }}>
        <Box sx={{ width: 60, height: 4, bgcolor: 'grey.300', borderRadius: 999, mx: 'auto', mb: 2 }} />
        <Avatar sx={{ width: 72, height: 72, mx: 'auto', mb: 1, bgcolor: '#fde68a', color: '#b45309', fontSize: 28 }}>
          {(contact?.displayName || contact?.phone || 'U').charAt(0).toUpperCase()}
        </Avatar>
        <Typography variant="h6">{contact?.displayName || 'Unknown'}</Typography>
        <Typography variant="body2" color="text.secondary">
          {contact?.phone || '—'}
        </Typography>
      </Box>

      <Box sx={{ mt: 3 }}>
        <Typography variant="subtitle2" color="text.secondary">
          Account overview
        </Typography>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mt: 1 }}>
          <Box>
            <Typography variant="body2" color="text.secondary">
              WA Conversation
            </Typography>
            <Typography variant="subtitle2">Inactive</Typography>
          </Box>
          <Box>
            <Typography variant="body2" color="text.secondary">
              MAU Status
            </Typography>
            <Typography variant="subtitle2" color="#0e7c7b">
              Active
            </Typography>
          </Box>
        </Box>
      </Box>

      <Divider sx={{ my: 3 }} />

      <Box>
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
          <Box>
            <Typography variant="subtitle1">Opted In</Typography>
            <Typography variant="body2" color="text.secondary">
              Marks user as opted-out of future campaigns.
            </Typography>
          </Box>
          <Switch checked={optedIn} onChange={(e) => setOptedIn(e.target.checked)} color="success" />
        </Box>
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
          <Box>
            <Typography variant="subtitle1" color="error">
              Block Incoming Messages
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Blocks user from messaging.
            </Typography>
          </Box>
          <Switch checked={blocked} onChange={(e) => setBlocked(e.target.checked)} color="error" />
        </Box>
      </Box>

      <Divider sx={{ my: 3 }} />

      <Box>
        <Typography variant="subtitle1" sx={{ mb: 1 }}>
          Tags
        </Typography>
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 2 }}>
          {tags.map((tag) => (
            <Chip
              key={tag}
              label={tag}
              onDelete={() => setTags(tags.filter((t) => t !== tag))}
              sx={{ bgcolor: '#e0f2f1', color: '#0e7c7b' }}
            />
          ))}
          <Chip
            label="+ Add Tag"
            onClick={addNewTag}
            sx={{ border: '1px dashed rgba(0,0,0,0.2)', bgcolor: 'transparent' }}
          />
        </Box>
      </Box>

      <Divider sx={{ my: 3 }} />

      {infoSections.map((section) => (
        <Box
          key={section.label}
          sx={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            py: 1.5,
            borderBottom: '1px solid rgba(0,0,0,0.05)'
          }}
        >
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
            <span style={{ fontSize: 20 }}>{section.icon}</span>
            <Typography>{section.label}</Typography>
          </Box>
          <KeyboardArrowLeft sx={{ transform: 'rotate(180deg)' }} />
        </Box>
      ))}
    </Drawer>
  )
}

export default function ChatDetailScreen() {
  const { id } = useParams()
  const [messages, setMessages] = useState([])
  const [text, setText] = useState('')
  const [mediaUrl, setMediaUrl] = useState('')
  const [loading, setLoading] = useState(true)
  const [selectorOpen, setSelectorOpen] = useState(false)
  const [profileOpen, setProfileOpen] = useState(false)
  const [sessionOpen, setSessionOpen] = useState(true)
  const [uploading, setUploading] = useState(false)
  const listRef = useRef(null)

  useEffect(() => {
    loadMessages()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  const loadMessages = async () => {
    setLoading(true)
    try {
      const res = await apiService.getWhatsAppMessages(id)
      if (res.success && Array.isArray(res.data)) {
        setMessages(res.data)
      } else {
        setMessages([])
      }
      const head = (res.data || [])[0] || {}
      const phone = head.recipient_phone || head.sender_phone
      if (phone) {
        try {
          const s = await apiService.get('/whatsapp/session/check', { phone })
          setSessionOpen(!!(s.data && s.data.session_open))
        } catch {}
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (listRef.current) {
      listRef.current.scrollTop = listRef.current.scrollHeight
    }
  }, [messages])

  const conversationMeta = useMemo(() => {
    const head = messages[0] || {}
    const displayName =
      head.contact_name ||
      head.sender_name ||
      head.recipient_name ||
      head.profile_name ||
      head.recipient_phone ||
      head.sender_phone ||
      `Conversation #${id}`
    const phone = head.recipient_phone || head.sender_phone
    return {
      displayName,
      phone,
      status: head.intervention_status || 'Intervened',
      lastActive: head.timestamp || head.created_at
    }
  }, [messages, id])

  const sendText = async () => {
    const conv = messages[0]
    const to = conv?.recipient_phone || conv?.sender_phone
    if (!to || !text.trim()) return
    if (!sessionOpen) return
    const res = await apiService.sendWhatsAppMessage({ to, type: 'text', text: text.trim() })
    if (res.success) {
      setText('')
      loadMessages()
    }
  }

  const sendMedia = async () => {
    const conv = messages[0]
    const to = conv?.recipient_phone || conv?.sender_phone
    if (!to || !mediaUrl.trim()) return
    const res = await apiService.sendWhatsAppMessage({
      to,
      type: 'document',
      media_url: mediaUrl.trim()
    })
    if (res.success) {
      setMediaUrl('')
      loadMessages()
    }
  }

  const handleFileUpload = async (file) => {
    const conv = messages[0]
    const to = conv?.recipient_phone || conv?.sender_phone
    if (!to || !file) return
    if (!sessionOpen) return
    setUploading(true)
    try {
      const presign = await apiService.post('/r2/presign-put', {
        content_type: file.type || 'application/octet-stream',
        suggested_name: file.name || 'upload.bin'
      })
      const putUrl = presign.data?.url
      const headers = presign.data?.headers || {}
      await fetch(putUrl, { method: 'PUT', headers, body: file })
      const publicUrlRes = await apiService.get('/r2/public-url', { key: presign.data?.key })
      const url = publicUrlRes.data?.url || presign.data?.key
      const type = (file.type || '').startsWith('image/') ? 'image' : (file.type || '').startsWith('video/') ? 'video' : (file.type || '').startsWith('audio/') ? 'audio' : 'document'
      const sendRes = await apiService.sendWhatsAppMessage({ to, type, media_url: url })
      if (sendRes.success) loadMessages()
    } catch (err) {
      // silently fail; UI will remain
    } finally {
      setUploading(false)
    }
  }

  const sendTemplate = async ({ templateName, languageCode, components }) => {
    const conv = messages[0]
    const to = conv?.recipient_phone || conv?.sender_phone
    if (!to) return
    const res = await apiService.sendWhatsAppMessage({
      to,
      type: 'template',
      template_name: templateName,
      language_code: languageCode,
      components
    })
    if (res.success) {
      loadMessages()
    }
  }

  if (loading) {
    return (
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '70vh' }}>
        <CircularProgress />
      </Box>
    )
  }

  return (
    <Box sx={{ minHeight: '100vh', bgcolor: '#e5ddd5', pb: 10 }}>
      <Box
        sx={{
          position: 'sticky',
          top: 0,
          zIndex: 3,
          bgcolor: '#fefefe',
          px: 2,
          py: 1.5,
          boxShadow: '0 10px 30px rgba(0,0,0,0.08)'
        }}
      >
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
          <IconButton component={Link} to="/chats">
            <KeyboardArrowLeft />
          </IconButton>
          <Avatar sx={{ bgcolor: '#d9fdd3', color: '#256029', fontWeight: 600 }}>
            {conversationMeta.displayName.charAt(0).toUpperCase()}
          </Avatar>
          <Box sx={{ flexGrow: 1 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
              {conversationMeta.displayName}
            </Typography>
            <Typography variant="caption" color="text.secondary">
              {conversationMeta.phone || 'Phone unavailable'}
            </Typography>
          </Box>
          <Chip
            label={conversationMeta.status}
            sx={{ bgcolor: '#e0f2f1', color: '#0e7c7b', fontWeight: 600 }}
          />
          <IconButton onClick={() => setProfileOpen(true)}>
            <MoreVert />
          </IconButton>
        </Box>
        <Box sx={{ mt: 1, display: 'flex', gap: 1 }}>
          <Button variant="outlined" size="small" startIcon={<Share fontSize="small" />}>
            Transfer
          </Button>
          <Button variant="contained" size="small" startIcon={<ShieldMoon fontSize="small" />} color="success">
            Resolve
          </Button>
        </Box>
      </Box>

      <Box
        ref={listRef}
        sx={{
          height: 'calc(100vh - 270px)',
          overflowY: 'auto',
          px: 2,
          py: 2,
          backgroundImage: 'url(https://www.gstatic.com/allo/stickers/V3/watermark.png)',
          backgroundBlendMode: 'soft-light'
        }}
      >
        <Typography variant="caption" color="text.secondary" align="center" sx={{ display: 'block', mb: 2 }}>
          User intervened by you • Last active {formatDate(conversationMeta.lastActive)}
        </Typography>
        {messages.map((message) => (
          <MessageBubble key={message.id} message={message} />
        ))}
      </Box>

      <Box
        sx={{
          position: 'sticky',
          bottom: 56,
          px: 2
        }}
      >
        <Paper
          elevation={0}
          sx={{
            borderRadius: 3,
            p: 1.5,
            mb: 1,
            bgcolor: '#fefefe'
          }}
        >
          <Box sx={{ display: 'flex', gap: 1, overflowX: 'auto' }}>
            {quickReplies.map((label) => (
              <Chip
                key={label}
                label={label}
                variant="outlined"
                color="success"
                onClick={() => setText(label)}
              />
            ))}
            <Chip label="Templates" color="success" onClick={() => setSelectorOpen(true)} />
          </Box>
        </Paper>

        <Paper
          elevation={0}
          sx={{
            borderRadius: 4,
            p: 1,
            display: 'flex',
            alignItems: 'center',
            gap: 1,
            bgcolor: '#fefefe',
            boxShadow: '0 20px 40px rgba(0,0,0,0.08)'
          }}
        >
          <IconButton>
            <EmojiEmotions />
          </IconButton>
          <TextField
            fullWidth
            placeholder="Message"
            multiline
            maxRows={3}
            value={text}
            onChange={(e) => setText(e.target.value)}
            variant="standard"
            InputProps={{ disableUnderline: true }}
          />
          <IconButton component="label" disabled={!sessionOpen || uploading}>
            <AttachFile />
            <input hidden type="file" onChange={(e) => e.target.files && e.target.files[0] && handleFileUpload(e.target.files[0])} />
          </IconButton>
          <IconButton>
            <CameraAlt />
          </IconButton>
          <IconButton color="success" onClick={sendText} disabled={!sessionOpen}>
            <Send />
          </IconButton>
        </Paper>

        <Paper
          elevation={0}
          sx={{
            borderRadius: 3,
            p: 1,
            mt: 1,
            display: 'flex',
            alignItems: 'center',
            gap: 1,
            bgcolor: '#fefefe'
          }}
        >
          <TextField
            fullWidth
            size="small"
            placeholder="Paste media/document URL"
            value={mediaUrl}
            onChange={(e) => setMediaUrl(e.target.value)}
          />
          <Button variant="outlined" startIcon={<PictureAsPdf />} onClick={sendMedia} disabled={!sessionOpen}>
            Send Doc
          </Button>
        </Paper>
      </Box>

      <WhatsAppTemplateSelector
        open={selectorOpen}
        onClose={() => setSelectorOpen(false)}
        onSendTemplate={sendTemplate}
      />

      <ContactProfileDrawer
        open={profileOpen}
        onClose={() => setProfileOpen(false)}
        contact={{
          displayName: conversationMeta.displayName,
          phone: conversationMeta.phone
        }}
      />
    </Box>
  )
}
