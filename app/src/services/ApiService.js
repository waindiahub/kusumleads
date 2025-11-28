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
}

export const apiService = new ApiService();
