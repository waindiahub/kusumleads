import React, { useEffect, useState } from 'react'
import { Box, Paper, Typography, Link, CircularProgress } from '@mui/material'
import { OpenInNew } from '@mui/icons-material'

export default function LinkPreview({ url }) {
  const [preview, setPreview] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const extractLinkPreview = async () => {
      try {
        const response = await fetch(url)
        const html = await response.text()
        const parser = new DOMParser()
        const doc = parser.parseFromString(html, 'text/html')

        const getMetaTag = (property) => {
          const tag = doc.querySelector(`meta[property="${property}"], meta[name="${property}"]`)
          return tag?.getAttribute('content')
        }

        setPreview({
          title: getMetaTag('og:title') || doc.title || url,
          description: getMetaTag('og:description'),
          image: getMetaTag('og:image'),
          url: getMetaTag('og:url') || url
        })
      } catch (error) {
        console.error('Error fetching link preview:', error)
        setPreview({ title: url, url })
      } finally {
        setLoading(false)
      }
    }

    extractLinkPreview()
  }, [url])

  if (loading) return <CircularProgress size={24} />

  return (
    <Paper
      component={Link}
      href={url}
      target="_blank"
      rel="noopener noreferrer"
      elevation={0}
      sx={{
        display: 'flex',
        gap: 1,
        p: 1.5,
        bgcolor: 'rgba(14, 124, 123, 0.05)',
        border: '1px solid rgba(14, 124, 123, 0.2)',
        borderRadius: 1.5,
        cursor: 'pointer',
        transition: 'all 0.2s',
        textDecoration: 'none',
        '&:hover': {
          bgcolor: 'rgba(14, 124, 123, 0.1)',
          boxShadow: '0 2px 8px rgba(14, 124, 123, 0.15)'
        }
      }}
    >
      {preview?.image && (
        <Box
          component="img"
          src={preview.image}
          sx={{ width: 80, height: 80, objectFit: 'cover', borderRadius: 1 }}
          onError={(e) => { e.target.style.display = 'none' }}
        />
      )}
      <Box sx={{ flex: 1, minWidth: 0 }}>
        <Typography variant="subtitle2" sx={{ fontWeight: 600, color: '#0e7c7b', display: 'flex', alignItems: 'center', gap: 0.5 }}>
          {preview?.title}
          <OpenInNew sx={{ fontSize: 14 }} />
        </Typography>
        {preview?.description && (
          <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mt: 0.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {preview.description}
          </Typography>
        )}
        <Typography variant="caption" sx={{ display: 'block', mt: 0.5, color: '#0e7c7b', opacity: 0.7 }}>
          {new URL(preview?.url || url).hostname}
        </Typography>
      </Box>
    </Paper>
  )
}
