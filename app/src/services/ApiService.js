import axios from 'axios';

const API_BASE_URL = 'https://sandybrown-gull-863456.hostingersite.com';

class ApiService {
  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      timeout: 10000,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    // Request interceptor
    this.client.interceptors.request.use(
      (config) => {
        if (this.authToken) {
          config.headers.Authorization = `Bearer ${this.authToken}`;
        }
        return config;
      },
      (error) => {
        return Promise.reject(error);
      }
    );

    // Response interceptor
    this.client.interceptors.response.use(
      (response) => {
        return response.data;
      },
      (error) => {
        if (error.response?.status === 401) {
          // Handle unauthorized access
          this.authToken = null;
          // Redirect to login or show error
        }
        return Promise.reject(error);
      }
    );
  }

  setAuthToken(token) {
    this.authToken = token;
  }

  getFullUrl(url, params = {}) {
    const baseURL = this.client.defaults.baseURL || API_BASE_URL;
    let fullUrl = `${baseURL}${url}`;
    
    if (params && Object.keys(params).length > 0) {
      const queryString = new URLSearchParams(params).toString();
      fullUrl += `?${queryString}`;
    }
    
    return fullUrl;
  }

  async get(url, params = {}) {
    try {
      const fullUrl = this.getFullUrl(url, params);
      console.log(`API GET Request URL: ${fullUrl}`);
      const response = await this.client.get(url, { params });
      console.log(`API GET Response ${fullUrl}:`, response);
      return response;
    } catch (error) {
      throw this.handleError(error);
    }
  }

  async post(url, data = {}) {
    try {
      const fullUrl = this.getFullUrl(url);
      console.log(`API POST Request URL: ${fullUrl}`, data);
      const response = await this.client.post(url, data);
      console.log(`API POST Response ${fullUrl}:`, response);
      return response;
    } catch (error) {
      console.error(`API POST Error ${this.getFullUrl(url)}:`, error);
      throw this.handleError(error);
    }
  }

  async put(url, data = {}) {
    try {
      const fullUrl = this.getFullUrl(url);
      console.log(`API PUT Request URL: ${fullUrl}`, data);
      const response = await this.client.put(url, data);
      console.log(`API PUT Response ${fullUrl}:`, response);
      return response;
    } catch (error) {
      throw this.handleError(error);
    }
  }

  async delete(url) {
    try {
      const fullUrl = this.getFullUrl(url);
      console.log(`API DELETE Request URL: ${fullUrl}`);
      const response = await this.client.delete(url);
      console.log(`API DELETE Response ${fullUrl}:`, response);
      return response;
    } catch (error) {
      throw this.handleError(error);
    }
  }

  handleError(error) {
    console.error('Full error object:', error);
    
    // Get full URL for error messages
    let fullUrl = 'unknown';
    if (error.config) {
      const baseURL = error.config.baseURL || this.client.defaults.baseURL || API_BASE_URL;
      const url = error.config.url || '';
      const params = error.config.params || {};
      
      fullUrl = `${baseURL}${url}`;
      if (params && Object.keys(params).length > 0) {
        const queryString = new URLSearchParams(params).toString();
        fullUrl += `?${queryString}`;
      }
    }
    
    if (error.response) {
      // Server responded with error status
      console.error(`Server error response - Request URL: ${fullUrl}`, error.response);
      return {
        success: false,
        message: error.response.data?.message || `Server error: ${error.response.status}`,
        status: error.response.status,
        requestUrl: fullUrl,
        details: error.response.data
      };
    } else if (error.request) {
      // Network error
      console.error(`Network error - Request URL: ${fullUrl}`, error.request);
      return {
        success: false,
        message: `Network error. Request URL: ${fullUrl}`,
        status: 0,
        requestUrl: fullUrl,
        details: error.message
      };
    } else {
      // Other error
      console.error(`Other error - Request URL: ${fullUrl}`, error.message);
      return {
        success: false,
        message: `Error: ${error.message}. Request URL: ${fullUrl}`,
        status: 0,
        requestUrl: fullUrl
      };
    }
  }

  // Lead-specific methods
  async getLeads(params = {}) {
    return this.get('/leads', params);
  }

  async getLeadDetail(leadId) {
    return this.get(`/leads/${leadId}`);
  }

  async submitLeadResponse(leadId, responseData) {
    return this.post(`/leads/${leadId}/response`, responseData);
  }

  // Agent methods
  async updateDeviceToken(agentId, deviceToken) {
    return this.post(`/agents/${agentId}/device_token`, { device_token: deviceToken });
  }

  async updatePlayerId(agentId, playerId) {
    return this.post(`/agents/${agentId}/player_id`, { player_id: playerId });
  }

  // Dashboard methods
  async getDashboardStats() {
    return this.get('/reports/dashboard');
  }

  // WhatsApp Templates
  async getWhatsAppTemplates() {
    return this.get('/whatsapp/templates');
  }

  // WhatsApp Cloud
  async getWhatsAppConversations() {
    return this.get('/whatsapp/conversations');
  }

  async getWhatsAppMessages(conversationId) {
    return this.get(`/whatsapp/conversations/${conversationId}/messages`);
  }

  async sendWhatsAppMessage(payload) {
    return this.post('/whatsapp/send', payload);
  }

  // R2 Presign helpers
  async presignPut(contentType, suggestedName) {
    return this.post('/r2/presign-put', { content_type: contentType, suggested_name: suggestedName });
  }

  async publicUrl(key) {
    return this.get('/r2/public-url', { key });
  }

  // WhatsApp Advanced API Methods
  async sendInteractiveMessage(to, type, payload) {
    return this.post('/whatsapp/send', { to, type: 'interactive', interactive: payload });
  }

  async sendListMessage(to, header, body, footer, options) {
    return this.post('/whatsapp/send', {
      to,
      type: 'interactive',
      interactive: {
        type: 'list',
        header: { type: 'text', text: header },
        body: { text: body },
        footer: { text: footer },
        action: { button: 'Select', sections: [{ rows: options }] }
      }
    });
  }

  async sendButtonMessage(to, bodyText, buttons) {
    return this.post('/whatsapp/send', {
      to,
      type: 'interactive',
      interactive: {
        type: 'button',
        body: { text: bodyText },
        action: { buttons: buttons.map((b, i) => ({ type: 'reply', reply: { id: String(i), title: b } })) }
      }
    });
  }

  async sendProductMessage(to, catalogId, productId, bodyText = '') {
    return this.post('/whatsapp/send', {
      to,
      type: 'interactive',
      interactive: {
        type: 'product',
        product: { catalog_id: catalogId, product_retailer_id: productId },
        ...(bodyText && { body: { text: bodyText } })
      }
    });
  }

  async sendLocationMessage(to, latitude, longitude, name, address) {
    return this.post('/whatsapp/send', {
      to,
      type: 'location',
      location: { latitude, longitude, name, address }
    });
  }

  async getConversationMetrics(fromDate = null, toDate = null) {
    return this.get('/whatsapp/metrics', { from: fromDate, to: toDate });
  }

  async markMessageAsRead(conversationId, messageId) {
    return this.post(`/whatsapp/conversations/${conversationId}/messages/${messageId}/read`, {});
  }

  // WhatsApp Calling API Methods
  async initiateCall(to, sdpOffer, phoneNumberId = null, bizOpaqueCallbackData = null) {
    return this.post('/whatsapp/calls/initiate', {
      to,
      sdp_offer: sdpOffer,
      phone_number_id: phoneNumberId,
      biz_opaque_callback_data: bizOpaqueCallbackData
    });
  }

  async preAcceptCall(callId, sdpAnswer, phoneNumberId = null) {
    return this.post('/whatsapp/calls/pre_accept', {
      call_id: callId,
      sdp_answer: sdpAnswer,
      phone_number_id: phoneNumberId
    });
  }

  async acceptCall(callId, sdpAnswer, phoneNumberId = null, bizOpaqueCallbackData = null) {
    return this.post('/whatsapp/calls/accept', {
      call_id: callId,
      sdp_answer: sdpAnswer,
      phone_number_id: phoneNumberId,
      biz_opaque_callback_data: bizOpaqueCallbackData
    });
  }

  async rejectCall(callId, phoneNumberId = null) {
    return this.post('/whatsapp/calls/reject', {
      call_id: callId,
      phone_number_id: phoneNumberId
    });
  }

  async terminateCall(callId, phoneNumberId = null) {
    return this.post('/whatsapp/calls/terminate', {
      call_id: callId,
      phone_number_id: phoneNumberId
    });
  }

  async getCall(callId) {
    return this.get(`/whatsapp/calls/${callId}`);
  }

  async getCalls() {
    return this.get('/whatsapp/calls');
  }

  async getCallPermissions(userWaId, phoneNumberId = null) {
    return this.get('/whatsapp/call_permissions', {
      user_wa_id: userWaId,
      phone_number_id: phoneNumberId
    });
  }

  async sendCallPermissionRequest(to, messageBody, phoneNumberId = null) {
    return this.post('/whatsapp/call_permissions/request', {
      to,
      message_body: messageBody,
      phone_number_id: phoneNumberId
    });
  }

  async sendMediaCarouselMessage(to, bodyText, cards, phoneNumberId = null) {
    return this.post('/whatsapp/send', {
      to,
      type: 'interactive',
      interactive: {
        type: 'carousel',
        body: { text: bodyText },
        action: { cards }
      },
      phone_number_id: phoneNumberId
    });
  }

  async sendTypingIndicator(to, messageId, phoneNumberId = null) {
    return this.post('/whatsapp/typing_indicator', {
      to,
      message_id: messageId,
      phone_number_id: phoneNumberId
    });
  }

  async sendContextualReply(to, messageBody, contextMessageId, phoneNumberId = null) {
    return this.post('/whatsapp/contextual_reply', {
      to,
      message_body: messageBody,
      context_message_id: contextMessageId,
      phone_number_id: phoneNumberId
    });
  }

  async sendAddressMessage(to, address, phoneNumberId = null) {
    return this.post('/whatsapp/message/address', {
      to,
      address,
      phone_number_id: phoneNumberId
    });
  }

  async sendAudioMessage(to, audioUrl, phoneNumberId = null) {
    return this.post('/whatsapp/message/audio', {
      to,
      audio_url: audioUrl,
      phone_number_id: phoneNumberId
    });
  }

  async sendContactsMessage(to, contacts, phoneNumberId = null) {
    return this.post('/whatsapp/message/contacts', {
      to,
      contacts,
      phone_number_id: phoneNumberId
    });
  }

  async sendStickerMessage(to, stickerUrl, phoneNumberId = null) {
    return this.post('/whatsapp/message/sticker', {
      to,
      sticker_url: stickerUrl,
      phone_number_id: phoneNumberId
    });
  }

  async sendReactionMessage(to, messageId, emoji, phoneNumberId = null) {
    return this.post('/whatsapp/message/reaction', {
      to,
      message_id: messageId,
      emoji,
      phone_number_id: phoneNumberId
    });
  }

  async getLinkPreview(url) {
    return this.get('/whatsapp/link/preview', { url });
  }

  async uploadMedia(filePath, mimeType, phoneNumberId = null) {
    return this.post('/whatsapp/media/upload', {
      file_path: filePath,
      mime_type: mimeType,
      phone_number_id: phoneNumberId
    });
  }

  async getMediaUrl(mediaId, phoneNumberId = null) {
    return this.get(`/whatsapp/media/${mediaId}/url`, {
      phone_number_id: phoneNumberId
    });
  }

  async deleteMedia(mediaId, phoneNumberId = null) {
    return this.delete(`/whatsapp/media/${mediaId}`, {
      phone_number_id: phoneNumberId
    });
  }
}

export const apiService = new ApiService();
