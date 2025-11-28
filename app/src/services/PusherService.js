import Pusher from 'pusher-js'
import { apiService } from './ApiService'

class PusherService {
  constructor() {
    this.pusher = null
    this.channel = null
    this.handlers = []
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
      if (user.role === 'admin') {
        const adminChannel = this.pusher.subscribe('admin')
        adminChannel.bind('whatsapp_message', (data) => {
          this.handlers.forEach(h => h(data))
        })
      }
      return true
    } catch (e) {
      return false
    }
  }

  onMessage(handler) {
    this.handlers.push(handler)
  }
}

export const pusherService = new PusherService()
