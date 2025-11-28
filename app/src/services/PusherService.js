import Pusher from 'pusher-js'
import { apiService } from './ApiService'

class PusherService {
  constructor() {
    this.pusher = null
    this.channel = null
    this.handlers = []
    this.callHandlers = []
  }

  async init(user) {
    try {
      const cfg = await apiService.get('/agents/pusher_config')
      if (!cfg.success) return false
      const { key, cluster, agent_id } = cfg.data
      this.pusher = new Pusher(key, { cluster, forceTLS: true })
      const channelName = `agent-${agent_id}`
      this.channel = this.pusher.subscribe(channelName)
      
      this.channel.bind('whatsapp_message', (data) => {
        this.handlers.forEach(h => h(data))
      })
      
      this.channel.bind('whatsapp_call_update', (data) => {
        this.callHandlers.forEach(h => h(data))
      })
      
      // Legacy support
      this.channel.bind('whatsapp_call', (data) => {
        this.callHandlers.forEach(h => h(data))
      })
      
      if (user.role === 'admin') {
        const adminChannel = this.pusher.subscribe('admin')
        adminChannel.bind('whatsapp_message', (data) => {
          this.handlers.forEach(h => h(data))
        })
        adminChannel.bind('whatsapp_call_update', (data) => {
          this.callHandlers.forEach(h => h(data))
        })
        adminChannel.bind('whatsapp_call', (data) => {
          this.callHandlers.forEach(h => h(data))
        })
      }
      return true
    } catch (e) {
      console.error('Pusher initialization failed:', e)
      return false
    }
  }

  onMessage(handler) {
    this.handlers.push(handler)
    return () => {
      this.handlers = this.handlers.filter(h => h !== handler)
    }
  }

  onCallUpdate(handler) {
    this.callHandlers.push(handler)
    return () => {
      this.callHandlers = this.callHandlers.filter(h => h !== handler)
    }
  }

  // Legacy support
  onCall(handler) {
    return this.onCallUpdate(handler)
  }
}

export const pusherService = new PusherService()
