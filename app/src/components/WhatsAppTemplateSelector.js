import React, { useState, useEffect, useMemo } from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  List,
  ListItem,
  ListItemText,
  ListItemButton,
  Typography,
  Box,
  Chip,
  IconButton,
  TextField,
  InputAdornment,
  CircularProgress
} from '@mui/material';
import { WhatsApp, Close, Image, VideoFile, PictureAsPdf, Search } from '@mui/icons-material';
import { apiService } from '../services/ApiService';

export default function WhatsAppTemplateSelector({ open, onClose, onSendTemplate }) {
  const [templates, setTemplates] = useState([]);
  const [selectedTemplate, setSelectedTemplate] = useState(null);
  const [loading, setLoading] = useState(false);
  const [query, setQuery] = useState('');
  const [vars, setVars] = useState({});

  useEffect(() => {
    if (open) {
      loadTemplates();
    } else {
      setSelectedTemplate(null);
      setQuery('');
    }
  }, [open]);

  const loadTemplates = async () => {
    setLoading(true);
    try {
      const response = await apiService.getWhatsAppTemplates();
      if (response.success) {
        setTemplates(response.data);
      }
    } catch (error) {
      console.error('Error loading templates:', error);
    } finally {
      setLoading(false);
    }
  };

  const formatMessage = (template) => template.message;

  const getButtons = (template) => {
    if (!template.buttons) return [];
    if (Array.isArray(template.buttons)) return template.buttons;
    try {
      const parsed = JSON.parse(template.buttons);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  };

  const getMediaIcon = (mediaType) => {
    switch (mediaType) {
      case 'image': return <Image fontSize="small" />;
      case 'video': return <VideoFile fontSize="small" />;
      case 'pdf': return <PictureAsPdf fontSize="small" />;
      default: return null;
    }
  };

  const sendTemplate = async (template) => {
    setLoading(true);
    try {
      if (onSendTemplate) {
        await onSendTemplate({ templateId: template.id, templateName: template.name, languageCode: template.language || 'en_US', components: [], variables: vars });
      }
      onClose();
    } catch (error) {
      console.error('Error sending template:', error);
    } finally {
      setLoading(false);
    }
  };

  const filteredTemplates = useMemo(() => {
    if (!query) return templates;
    return templates.filter((template) =>
      `${template.name} ${template.message}`.toLowerCase().includes(query.toLowerCase())
    );
  }, [templates, query]);

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <WhatsApp color="success" />
          <Typography variant="h6">WhatsApp Templates</Typography>
        </Box>
        <IconButton onClick={onClose} size="small">
          <Close />
        </IconButton>
      </DialogTitle>
      
      <DialogContent dividers>
        <TextField
          fullWidth
          size="small"
          placeholder="Search by template name"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <Search fontSize="small" />
              </InputAdornment>
            )
          }}
          sx={{ mb: 2 }}
        />

        {loading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
            <CircularProgress size={28} />
          </Box>
        ) : (
          <List>
            {filteredTemplates.map((template) => {
              const buttons = getButtons(template);
              const placeholders = extractPlaceholders(template);
              return (
              <ListItem key={template.id} disablePadding>
                <ListItemButton 
                  onClick={() => setSelectedTemplate(template)}
                  selected={selectedTemplate?.id === template.id}
                  sx={{ borderRadius: 1, mb: 1, alignItems: 'flex-start' }}
                >
                  <ListItemText
                    primary={
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Typography variant="subtitle2">{template.name}</Typography>
                        {template.category && (
                          <Chip label={template.category} size="small" variant="outlined" />
                        )}
                        {template.media_type !== 'none' && (
                          <Chip 
                            icon={getMediaIcon(template.media_type)}
                            label={template.media_type}
                            size="small"
                            variant="outlined"
                          />
                        )}
                      </Box>
                    }
                    secondary={
                      <div>
                        <Typography variant="body2" sx={{ mt: 0.5 }}>
                          {formatMessage(template)}
                        </Typography>
                        {buttons.length > 0 && (
                          <Box sx={{ mt: 1, display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                            {buttons.map((button, index) => (
                              <Chip 
                                key={index}
                                label={button}
                                size="small"
                                sx={{ mr: 0.5, mb: 0.5 }}
                              />
                            ))}
                          </Box>
                        )}
                        {selectedTemplate?.id === template.id && placeholders.length > 0 && (
                          <Box sx={{ mt: 1 }}>
                            {placeholders.map((ph) => (
                              <TextField
                                key={ph}
                                size="small"
                                label={`{{${ph}}}`}
                                value={vars[ph] || ''}
                                onChange={(e) => setVars({ ...vars, [ph]: e.target.value })}
                                sx={{ mr: 1, mb: 1 }}
                              />
                            ))}
                          </Box>
                        )}
                      </div>
                    }
                  />
                </ListItemButton>
              </ListItem>
            )})}
          </List>
        )}
        
        {!loading && filteredTemplates.length === 0 && (
          <Typography color="textSecondary" align="center" sx={{ py: 4 }}>
            No templates match your search
          </Typography>
        )}
      </DialogContent>
      
      <DialogActions>
        <Button onClick={onClose}>Cancel</Button>
        <Button 
          variant="contained"
          startIcon={<WhatsApp />}
          onClick={() => selectedTemplate && sendTemplate(selectedTemplate)}
          disabled={!selectedTemplate || loading}
        >
          Send Template
        </Button>
      </DialogActions>
    </Dialog>
  );
}

function extractPlaceholders(template) {
  const haystack = [template.header_text || '', template.message || '', template.footer_text || '']
  try {
    const buttons = Array.isArray(template.buttons) ? template.buttons : JSON.parse(template.buttons || '[]')
    buttons.forEach((b) => {
      if (b && typeof b === 'string') haystack.push(b)
      if (b && typeof b === 'object') { haystack.push(b.text || ''); haystack.push(b.value || '') }
    })
  } catch {}
  const set = new Set()
  haystack.forEach((t) => {
    (t.match(/\{\{\s*([^}]+)\s*\}\}/g) || []).forEach((m) => set.add(m.replace('{{','').replace('}}','').trim()))
  })
  return Array.from(set)
}
