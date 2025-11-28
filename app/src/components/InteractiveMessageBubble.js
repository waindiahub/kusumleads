import React, { useState } from 'react'
import { Box, Paper, Typography, Button, Dialog, List, ListItem, ListItemButton, Card, CardMedia, CardContent } from '@mui/material'
import { apiService } from '../services/ApiService'

export function ButtonMessageBubble({ message, onReply, isOutgoing }) {
  const [selectedButton, setSelectedButton] = useState(null)
  const interactive = message.interactive || {}
  const buttons = interactive.action?.buttons || []

  const handleButtonClick = async (button) => {
    setSelectedButton(button.id)
    if (onReply) {
      onReply(`Button clicked: ${button.reply?.title || button.id}`)
    }
    try {
      await apiService.sendMessage(message.conversation_id, {
        type: 'interactive',
        interactive: {
          type: 'button_reply',
          button_reply: { id: button.id, title: button.reply?.title }
        }
      })
    } catch (error) {
      console.error('Error sending button response:', error)
    }
  }

  return (
    <Paper elevation={0} sx={{
      maxWidth: '85%',
      p: 2,
      bgcolor: isOutgoing ? '#d1f4cc' : '#ffffff',
      borderRadius: 2.5,
      borderTopRightRadius: isOutgoing ? 4 : 20,
      borderTopLeftRadius: isOutgoing ? 20 : 4,
      boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
    }}>
      {interactive.body?.text && (
        <Typography variant="body2" sx={{ mb: 1.5 }}>
          {interactive.body.text}
        </Typography>
      )}
      <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
        {buttons.map((button) => (
          <Button
            key={button.id}
            variant="outlined"
            fullWidth
            onClick={() => handleButtonClick(button)}
            disabled={selectedButton !== null && selectedButton !== button.id}
            sx={{
              borderColor: '#0e7c7b',
              color: '#0e7c7b',
              '&:hover': { bgcolor: 'rgba(14,124,123,0.05)' }
            }}
          >
            {button.reply?.title || button.id}
          </Button>
        ))}
      </Box>
    </Paper>
  )
}

export function ListMessageBubble({ message, onReply, isOutgoing }) {
  const [openList, setOpenList] = useState(false)
  const interactive = message.interactive || {}
  const sections = interactive.action?.sections || []
  const allRows = sections.flatMap(s => s.rows || [])

  const handleRowSelect = async (row) => {
    setOpenList(false)
    if (onReply) {
      onReply(`Selected: ${row.title}`)
    }
    try {
      await apiService.sendMessage(message.conversation_id, {
        type: 'interactive',
        interactive: {
          type: 'list_reply',
          list_reply: { id: row.id, title: row.title }
        }
      })
    } catch (error) {
      console.error('Error sending list response:', error)
    }
  }

  return (
    <>
      <Paper elevation={0} sx={{
        maxWidth: '85%',
        p: 2,
        bgcolor: isOutgoing ? '#d1f4cc' : '#ffffff',
        borderRadius: 2.5,
        borderTopRightRadius: isOutgoing ? 4 : 20,
        borderTopLeftRadius: isOutgoing ? 20 : 4,
        boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
        cursor: 'pointer'
      }} onClick={() => setOpenList(true)}>
        {interactive.body?.text && (
          <Typography variant="body2" sx={{ mb: 1 }}>
            {interactive.body.text}
          </Typography>
        )}
        <Button variant="outlined" fullWidth sx={{
          borderColor: '#0e7c7b',
          color: '#0e7c7b',
          '&:hover': { bgcolor: 'rgba(14,124,123,0.05)' }
        }}>
          {interactive.action?.button || 'View Options'} ▼
        </Button>
      </Paper>
      <Dialog open={openList} onClose={() => setOpenList(false)} maxWidth="xs" fullWidth>
        <List>
          {sections.map((section) => (
            <Box key={section.title}>
              {section.title && (
                <Typography variant="caption" sx={{ px: 2, py: 1, display: 'block', fontWeight: 600, color: '#0e7c7b' }}>
                  {section.title}
                </Typography>
              )}
              {(section.rows || []).map((row) => (
                <ListItemButton
                  key={row.id}
                  onClick={() => handleRowSelect(row)}
                  sx={{ px: 2, py: 1.5 }}
                >
                  <Box>
                    <Typography variant="body2" sx={{ fontWeight: 500 }}>
                      {row.title}
                    </Typography>
                    {row.description && (
                      <Typography variant="caption" color="textSecondary">
                        {row.description}
                      </Typography>
                    )}
                  </Box>
                </ListItemButton>
              ))}
            </Box>
          ))}
        </List>
      </Dialog>
    </>
  )
}

export function ProductMessageBubble({ message, isOutgoing }) {
  const interactive = message.interactive || {}
  const products = interactive.action?.products || []

  return (
    <Paper elevation={0} sx={{
      maxWidth: '85%',
      p: 2,
      bgcolor: isOutgoing ? '#d1f4cc' : '#ffffff',
      borderRadius: 2.5,
      borderTopRightRadius: isOutgoing ? 4 : 20,
      borderTopLeftRadius: isOutgoing ? 20 : 4,
      boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
    }}>
      {interactive.body?.text && (
        <Typography variant="body2" sx={{ mb: 1.5, fontWeight: 500 }}>
          {interactive.body.text}
        </Typography>
      )}
      <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
        {products.map((product) => (
          <Card key={product.product_retailer_id} sx={{ display: 'flex' }}>
            {product.image_url && (
              <CardMedia
                component="img"
                sx={{ width: 100, height: 100, objectFit: 'cover' }}
                image={product.image_url}
                alt={product.title}
              />
            )}
            <CardContent sx={{ flex: 1, p: 1.5 }}>
              <Typography variant="subtitle2">{product.title}</Typography>
              {product.description && (
                <Typography variant="caption" color="textSecondary">{product.description}</Typography>
              )}
              {product.price && (
                <Typography variant="body2" sx={{ fontWeight: 600, color: '#0e7c7b', mt: 0.5 }}>
                  {product.currency} {product.price}
                </Typography>
              )}
            </CardContent>
          </Card>
        ))}
      </Box>
    </Paper>
  )
}

export function MediaCarouselBubble({ message, isOutgoing }) {
  const [currentCard, setCurrentCard] = useState(0)
  const interactive = message.interactive || {}
  const cards = interactive.action?.cards || []

  if (cards.length === 0) return null

  const card = cards[currentCard]

  return (
    <Paper elevation={0} sx={{
      maxWidth: '85%',
      p: 2,
      bgcolor: isOutgoing ? '#d1f4cc' : '#ffffff',
      borderRadius: 2.5,
      borderTopRightRadius: isOutgoing ? 4 : 20,
      borderTopLeftRadius: isOutgoing ? 20 : 4,
      boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
    }}>
      {interactive.body?.text && (
        <Typography variant="body2" sx={{ mb: 1, fontWeight: 500 }}>
          {interactive.body.text}
        </Typography>
      )}
      <Box sx={{
        mb: 1.5,
        borderRadius: 1.5,
        overflow: 'hidden',
        bgcolor: '#f5f5f5',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: 200,
        position: 'relative'
      }}>
        {card.header?.type === 'image' && card.header.image?.link && (
          <img src={card.header.image.link} alt="Card" style={{ maxWidth: '100%', maxHeight: '200px', objectFit: 'cover' }} />
        )}
        {card.header?.type === 'video' && card.header.video?.link && (
          <video src={card.header.video.link} style={{ maxWidth: '100%', maxHeight: '200px', objectFit: 'cover' }} controls />
        )}
      </Box>
      {card.body?.text && (
        <Typography variant="body2" sx={{ mb: 1 }}>
          {card.body.text}
        </Typography>
      )}
      {card.action?.parameters?.url && (
        <Button
          variant="contained"
          fullWidth
          href={card.action.parameters.url}
          target="_blank"
          sx={{ bgcolor: '#0e7c7b', mb: 1, '&:hover': { bgcolor: '#0d6b6a' } }}
        >
          {card.action.parameters.display_text || 'Learn More'}
        </Button>
      )}
      {cards.length > 1 && (
        <Box sx={{ display: 'flex', gap: 1, justifyContent: 'space-between', alignItems: 'center' }}>
          <Button size="small" onClick={() => setCurrentCard(Math.max(0, currentCard - 1))} disabled={currentCard === 0}>
            ← Prev
          </Button>
          <Typography variant="caption">{currentCard + 1} / {cards.length}</Typography>
          <Button size="small" onClick={() => setCurrentCard(Math.min(cards.length - 1, currentCard + 1))} disabled={currentCard === cards.length - 1}>
            Next →
          </Button>
        </Box>
      )}
    </Paper>
  )
}
