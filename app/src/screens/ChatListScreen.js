import React, { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Avatar,
  Badge,
  Box,
  Button,
  Chip,
  CircularProgress,
  IconButton,
  InputAdornment,
  Paper,
  TextField,
  Typography
} from '@mui/material'
import {
  ArrowBack,
  KeyboardArrowRight,
  Refresh,
  Search,
  Tune
} from '@mui/icons-material'
import { apiService } from '../services/ApiService'

const TABS = [
  { id: 'active', label: 'Active' },
  { id: 'requesting', label: 'Requesting' },
  { id: 'intervened', label: 'Intervened' }
]

const statusDisplay = {
  active: { label: 'Active', color: '#12a09f' },
  requesting: { label: 'Requesting', color: '#f39c12' },
  intervened: { label: 'Intervened', color: '#0e7c7b' }
}

const normalizeStatus = (conversation) => {
  const raw =
    conversation?.intervention_status ||
    conversation?.status ||
    conversation?.queue_status ||
    'active'

  if (raw.toLowerCase().includes('request')) return 'requesting'
  if (raw.toLowerCase().includes('interven')) return 'intervened'
  return 'active'
}

const getInitials = (name = '') => {
  if (!name) return 'U'
  const parts = name.split(' ').filter(Boolean)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return `${parts[0][0]}${parts[1][0]}`.toUpperCase()
}

const formatTime = (value) => {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

export default function ChatListScreen() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState('intervened')
  const [search, setSearch] = useState('')
  const [error, setError] = useState(null)

  useEffect(() => {
    loadConversations()
  }, [])

  const loadConversations = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await apiService.getWhatsAppConversations()
      if (res.success && Array.isArray(res.data)) {
        setItems(res.data)
      } else {
        setItems([])
        if (res.message) {
          setError(res.message)
        }
      }
    } catch (err) {
      setItems([])
      setError(err?.message || err?.details?.message || 'Unable to load conversations')
    } finally {
      setLoading(false)
    }
  }

  const normalizedItems = useMemo(() => {
    return items.map((conv) => {
      const status = normalizeStatus(conv)
      return {
        ...conv,
        status,
        displayName:
          conv.contact_name ||
          conv.name ||
          conv.business_name ||
          conv.profile_name ||
          conv.phone_number,
        lastMessage:
          conv.last_message ||
          conv.last_message_text ||
          conv.last_message_body ||
          conv.preview_text ||
          '',
        lastTime:
          conv.last_message_at ||
          conv.updated_at ||
          conv.last_interaction_at ||
          conv.created_at
      }
    })
  }, [items])

  const counts = useMemo(() => {
    return normalizedItems.reduce(
      (acc, item) => {
        acc[item.status] = (acc[item.status] || 0) + 1
        return acc
      },
      { active: 0, requesting: 0, intervened: 0 }
    )
  }, [normalizedItems])

  const filtered = useMemo(() => {
    return normalizedItems.filter((item) => {
      const matchesTab = item.status === activeTab
      const haystack = `${item.displayName} ${item.phone_number} ${item.lastMessage}`.toLowerCase()
      const matchesSearch = haystack.includes(search.trim().toLowerCase())
      return matchesTab && matchesSearch
    })
  }, [normalizedItems, activeTab, search])

  const renderConversationCard = (conversation) => {
    const statusMeta = statusDisplay[conversation.status] || statusDisplay.active
    return (
      <Paper
        key={conversation.id}
        component={Link}
        to={`/chats/${conversation.id}`}
        elevation={0}
        sx={{
          textDecoration: 'none',
          color: 'inherit',
          p: 2,
          borderRadius: 3,
          mb: 2,
          bgcolor: '#ffffff',
          boxShadow: '0px 15px 40px rgba(15, 118, 110, 0.08)',
          border: '1px solid rgba(14, 124, 123, 0.05)',
          display: 'block'
        }}
      >
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
          <Badge
            color="error"
            badgeContent={conversation.unread_count}
            overlap="circular"
            invisible={!conversation.unread_count}
          >
            <Avatar
              sx={{
                bgcolor: '#fde68a',
                color: '#b45309',
                width: 48,
                height: 48,
                fontWeight: 600
              }}
            >
              {getInitials(conversation.displayName)}
            </Avatar>
          </Badge>
          <Box sx={{ flexGrow: 1 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
              {conversation.displayName}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {conversation.phone_number}
            </Typography>
          </Box>
          <Box sx={{ textAlign: 'right' }}>
            <Typography variant="caption" color="text.secondary">
              {formatTime(conversation.lastTime)}
            </Typography>
            <Chip
              label={statusMeta.label}
              size="small"
              sx={{
                mt: 1,
                bgcolor: `${statusMeta.color}15`,
                color: statusMeta.color,
                fontWeight: 600
              }}
            />
          </Box>
        </Box>
        <Box sx={{ mt: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
          <Typography
            variant="body2"
            color="text.secondary"
            sx={{ flexGrow: 1 }}
            noWrap
          >
            {conversation.lastMessage || 'No messages yet'}
          </Typography>
          <KeyboardArrowRight color="action" />
        </Box>
      </Paper>
    )
  }

  return (
    <Box
      sx={{
        minHeight: '100vh',
        bgcolor: '#f0f2f5',
        pb: 10
      }}
    >
      <Box
        sx={{
          position: 'sticky',
          top: 0,
          zIndex: 2,
          bgcolor: '#f0f2f5',
          px: 2,
          pt: 2
        }}
      >
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <IconButton component={Link} to="/dashboard">
            <ArrowBack />
          </IconButton>
          <Box sx={{ flexGrow: 1 }}>
            <Typography variant="h5" sx={{ fontWeight: 700 }}>
              Live Chat
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Intervene with parents instantly
            </Typography>
          </Box>
          <IconButton onClick={loadConversations}>
            <Refresh />
          </IconButton>
        </Box>

        <TextField
          fullWidth
          size="small"
          placeholder="Search by name or number"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          sx={{
            mt: 2,
            '& .MuiOutlinedInput-root': {
              borderRadius: 3,
              bgcolor: '#ffffff'
            }
          }}
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <Search />
              </InputAdornment>
            ),
            endAdornment: (
              <InputAdornment position="end">
                <IconButton size="small">
                  <Tune fontSize="small" />
                </IconButton>
              </InputAdornment>
            )
          }}
        />

        <Box sx={{ display: 'flex', gap: 1, mt: 2, pb: 2, overflowX: 'auto' }}>
          {TABS.map((tab) => {
            const isActive = tab.id === activeTab
            return (
              <Chip
                key={tab.id}
                label={`${tab.label} (${counts[tab.id] || 0})`}
                clickable
                onClick={() => setActiveTab(tab.id)}
                sx={{
                  px: 2,
                  py: 0.5,
                  borderRadius: 999,
                  fontWeight: 600,
                  border: isActive ? 'none' : '1px solid rgba(0,0,0,0.12)',
                  bgcolor: isActive ? '#0e7c7b' : '#ffffff',
                  color: isActive ? '#ffffff' : '#0e7c7b'
                }}
              />
            )
          })}
        </Box>
      </Box>

      <Box sx={{ px: 2 }}>
        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', mt: 8 }}>
            <CircularProgress />
          </Box>
        ) : error ? (
          <Paper
            elevation={0}
            sx={{
              textAlign: 'center',
              p: 4,
              borderRadius: 3,
              bgcolor: '#ffffff',
              color: 'text.secondary'
            }}
          >
            <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
              Unable to load chats
            </Typography>
            <Typography variant="body2" sx={{ mt: 1 }}>
              {error}
            </Typography>
            <Button variant="contained" sx={{ mt: 2 }} onClick={loadConversations}>
              Retry
            </Button>
          </Paper>
        ) : filtered.length === 0 ? (
          <Paper
            elevation={0}
            sx={{
              textAlign: 'center',
              p: 4,
              borderRadius: 3,
              bgcolor: '#ffffff',
              color: 'text.secondary'
            }}
          >
            <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
              No conversations here yet
            </Typography>
            <Typography variant="body2" sx={{ mt: 1 }}>
              New chats will appear once someone messages you in this category.
            </Typography>
          </Paper>
        ) : (
          filtered.map(renderConversationCard)
        )}
      </Box>
    </Box>
  )
}
