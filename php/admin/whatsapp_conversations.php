<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pusherKey = @getSetting('pusher_key');
$pusherCluster = @getSetting('pusher_cluster');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Chat - WhatsApp Conversations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
  <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
  <link href="css/chats.css" rel="stylesheet">
  <style>
    /* Override any conflicting styles */
    .chat-main-container {
      margin-left: 240px !important;
      width: calc(100% - 240px) !important;
    }
    .container-fluid.p-0 {
      padding: 0 !important;
      margin: 0 !important;
    }
    .row.g-0 {
      margin: 0 !important;
      width: 100%;
    }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid p-0">
  <div class="row g-0">
    <?php include 'sidebar.php'; ?>
    <main class="col-md-10 ms-sm-auto chat-main-container">
      <div class="chat-container">
        <!-- Left Panel: Conversation List -->
        <div class="chat-left-panel">
          <div class="chat-header-tabs">
            <div class="live-chat-title">
              <i class="fas fa-comments me-2"></i>
              <span>Live Chat Intervention</span>
            </div>
            <div class="user-avatars">
              <div class="user-avatar" title="Active users"></div>
              <div class="user-avatar" title="Active users"></div>
              <div class="user-avatar" title="Active users"></div>
              <div class="user-avatar" title="Active users"></div>
            </div>
          </div>
          
          <!-- Search Bar -->
          <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" id="searchConversations" placeholder="Search conversations...">
            <button class="search-clear" id="clearSearch" style="display:none;">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div class="tab-nav">
            <button class="tab-btn active" data-tab="active" id="tabActive">
              <span>Active</span>
              <span class="tab-count" id="activeCount">0</span>
            </button>
            <button class="tab-btn" data-tab="requesting" id="tabRequesting">
              <span>Requesting</span>
              <span class="tab-count badge-alert" id="requestingCount">0</span>
            </button>
            <button class="tab-btn tab-intervened" data-tab="intervened" id="tabIntervened">
              <span>Intervened</span>
              <span class="tab-count" id="intervenedCount">0</span>
            </button>
          </div>

          <div class="conversation-list" id="conversationList">
            <!-- Loading skeleton -->
            <div class="loading-skeleton">
              <div class="skeleton-item"></div>
              <div class="skeleton-item"></div>
              <div class="skeleton-item"></div>
              <div class="skeleton-item"></div>
              <div class="skeleton-item"></div>
            </div>
          </div>
        </div>

        <!-- Center Panel: Chat Messages -->
        <div class="chat-center-panel">
          <div class="chat-center-header">
            <button class="btn-menu-toggle" id="menuToggle" onclick="toggleConversationList()">
              <i class="fas fa-bars"></i>
            </button>
            <div class="chat-header-info">
              <div class="chat-contact-name" id="chatTitle">Select a conversation</div>
              <div class="chat-status-badge" id="chatStatus"></div>
            </div>
            <div class="chat-header-actions">
              <button class="btn-intervene" id="interveneBtn" style="display:none;">
                <i class="fas fa-user-plus me-2"></i>Intervene
              </button>
              <button class="btn-resolve" id="resolveBtn" style="display:none;">
                <i class="fas fa-check me-2"></i>Resolve
              </button>
              <button class="btn-profile-toggle" id="profileToggle" onclick="toggleProfile()">
                <i class="fas fa-info-circle"></i>
              </button>
            </div>
          </div>

          <div class="chat-messages-container" id="messagePane">
            <div class="empty-chat-state">
              <i class="far fa-comments"></i>
              <p>Select a conversation to start chatting</p>
              <small>Choose from your active conversations on the left</small>
            </div>
          </div>
          
          <!-- Typing Indicator -->
          <div class="typing-indicator" id="typingIndicator" style="display:none;">
            <div class="typing-dots">
              <span></span>
              <span></span>
              <span></span>
            </div>
            <span class="typing-text">Customer is typing...</span>
          </div>

          <div class="intervention-notice" id="interventionNotice" style="display:none;">
            <div class="intervention-content">
              <i class="fas fa-user-plus"></i>
              <span>User intervened</span>
            </div>
          </div>

          <div class="requesting-notice" id="requestingNotice" style="display:none;">
            <div class="requesting-content">
              <i class="fas fa-hand-paper"></i>
              <span>Requesting Intervention</span>
              <button class="btn-intervene-small" id="interveneBottomBtn">
                <i class="fas fa-user-plus me-1"></i>Intervene
              </button>
            </div>
          </div>

          <div class="session-window-alert" id="sessionAlert" style="display:none;">
            <i class="fas fa-clock me-2"></i>
            <span>24-hour WhatsApp session expired. Send a template or wait for a new customer reply.</span>
          </div>

          <div class="chat-input-container">
            <!-- Quick Action Buttons -->
            <div class="quick-actions">
              <button class="quick-action-btn" id="showMediaBtn" title="Send Media">
                <i class="fas fa-image"></i>
                <span>Media</span>
              </button>
              <button class="quick-action-btn" id="showTemplateBtn" title="Send Template">
                <i class="fas fa-file-alt"></i>
                <span>Template</span>
              </button>
              <button class="quick-action-btn" id="showEmojiBtn" title="Emoji">
                <i class="far fa-smile"></i>
                <span>Emoji</span>
              </button>
            </div>
            
            <!-- Input Row -->
            <div class="input-row">
              <button class="btn-attach" id="attachFileBtn" title="Attach file">
                <i class="fas fa-paperclip"></i>
              </button>
              <input type="file" id="fileInput" style="display:none" accept="image/*,video/*,application/pdf" />
              <div class="input-wrapper">
                <textarea class="chat-input" id="msgInput" placeholder="Type a message..." rows="1"></textarea>
                <div class="input-actions">
                  <button class="format-btn-inline" title="Bold" onclick="formatText('bold')">
                    <i class="fas fa-bold"></i>
                  </button>
                  <button class="format-btn-inline" title="Italic" onclick="formatText('italic')">
                    <i class="fas fa-italic"></i>
                  </button>
                </div>
              </div>
              <button class="btn-send" id="sendBtn" disabled>
                <i class="fas fa-paper-plane"></i>
              </button>
            </div>
          </div>
          
          <!-- Media Upload Modal -->
          <div class="input-modal" id="mediaModal" style="display:none;">
            <div class="modal-header-custom">
              <h4><i class="fas fa-image me-2"></i>Send Media</h4>
              <button class="btn-close-modal" onclick="closeModal('mediaModal')">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="modal-body-custom">
              <div class="media-upload-area" id="mediaUploadArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Click or drag to upload</p>
                <input type="file" id="mediaFileInput" accept="image/*,video/*" />
              </div>
              <div class="media-preview" id="mediaPreview" style="display:none;"></div>
              <textarea class="form-control mt-3" id="mediaCaption" placeholder="Add a caption (optional)" rows="2"></textarea>
            </div>
            <div class="modal-footer-custom">
              <button class="btn btn-secondary" onclick="closeModal('mediaModal')">Cancel</button>
              <button class="btn btn-primary" onclick="sendMedia()">
                <i class="fas fa-paper-plane me-2"></i>Send Media
              </button>
            </div>
          </div>
          
          <!-- Template Selector Modal -->
          <div class="input-modal" id="templateModal" style="display:none;">
            <div class="modal-header-custom">
              <h4><i class="fas fa-file-alt me-2"></i>Select Template</h4>
              <button class="btn-close-modal" onclick="closeModal('templateModal')">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="modal-body-custom template-modal-body">
              <div class="template-list-panel">
                <input type="text" class="form-control mb-3" id="templateSearch" placeholder="Search templates..." />
                <div class="template-list" id="templateList"></div>
              </div>
              <div class="template-preview-panel">
                <div class="template-preview-empty" id="templateEmptyState">
                  <p>Select a template to see details and variables.</p>
                </div>
                <div class="template-preview-stack" id="templatePreviewStack" style="display:none;">
                  <div class="template-preview-header">
                    <div>
                      <h5 id="templatePreviewName"></h5>
                      <small id="templatePreviewMeta"></small>
                    </div>
                    <span class="status-chip" id="templateStatusBadge"></span>
                  </div>
                  <div class="template-preview-body" id="templatePreviewBody"></div>
                  <div class="template-preview-footer" id="templatePreviewFooter"></div>
                  <div class="template-variable-list" id="templateVariableList"></div>
                  <div class="text-danger small mb-2" id="templateError"></div>
                  <button class="btn btn-primary w-100" id="sendTemplateBtn" disabled>
                    <i class="fas fa-paper-plane me-2"></i>Send Template
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Emoji Picker Modal -->
          <div class="input-modal emoji-picker-modal" id="emojiModal" style="display:none;">
            <div class="emoji-grid" id="emojiGrid">
              <!-- Emojis will be generated here -->
            </div>
          </div>
        </div>

        <!-- Right Panel: Chat Profile -->
        <div class="chat-right-panel">
          <div class="profile-header">
            <h3>Chat Profile</h3>
          </div>

          <div class="profile-content">
            <div class="profile-avatar-section">
              <div class="profile-avatar" id="profileAvatar">?</div>
              <div class="profile-name" id="profileName">Select a contact</div>
            </div>

            <div class="profile-status-card">
              <div class="status-badge-large" id="profileStatusBadge">Active</div>
              <div class="status-grid">
                <div class="status-item">
                  <span class="status-label">Last Active</span>
                  <span class="status-value" id="profileLastActive">-</span>
                </div>
                <div class="status-item">
                  <span class="status-label">Template Messages</span>
                  <span class="status-value" id="profileTemplateCount">0</span>
                </div>
                <div class="status-item">
                  <span class="status-label">Session Messages</span>
                  <span class="status-value" id="profileMessageCount">0</span>
                </div>
                <div class="status-item">
                  <span class="status-label">Unresolved Queries</span>
                  <span class="status-value" id="profileUnresolved">0</span>
                </div>
                <div class="status-item">
                  <span class="status-label">Source</span>
                  <span class="status-value" id="profileSource">-</span>
                </div>
                <div class="status-item">
                  <span class="status-label">Lead State</span>
                  <span class="status-value" id="profileLeadState">-</span>
                </div>
                <div class="status-item">
                  <span class="status-label">First Message</span>
                  <span class="status-value" id="profileFirstMsg">-</span>
                </div>
              </div>
            </div>

            <div class="profile-assignment-card">
              <label class="form-label">Assigned Agent</label>
              <div class="assignment-controls">
                <select class="form-select form-select-sm" id="agentSelect">
                  <option value="">Unassigned</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" id="transferAgentBtn">
                  <i class="fas fa-user-check me-1"></i>Update
                </button>
              </div>
            </div>

            <!-- Campaigns Section -->
            <div class="profile-accordion">
              <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                  <span>Campaigns</span>
                  <i class="fas fa-chevron-down"></i>
                </button>
                <div class="accordion-content">
                  <div id="profileCampaigns" class="text-muted">No campaigns</div>
                </div>
              </div>

              <!-- Tags Section -->
              <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                  <span>Tags</span>
                  <i class="fas fa-chevron-up"></i>
                </button>
                <div class="accordion-content show">
                  <div id="tagList" class="tags-display mb-3"></div>
                  <div class="tag-selector-wrapper">
                    <select class="tag-selector" id="tagDropdown">
                      <option value="">Select & add a tag</option>
                    </select>
                    <button class="btn-add-tag" id="addTagBtn">
                      <i class="fas fa-plus"></i>
                      <span>Add</span>
                    </button>
                  </div>
                  <button class="btn-create-tag" id="showCreateTagBtn">
                    <i class="fas fa-plus-circle me-2"></i>
                    Create & Add Tag
                  </button>
                  <div class="create-tag-form" id="createTagForm" style="display:none;">
                    <input type="text" class="form-control mb-2" id="newTagName" placeholder="Enter new tag name">
                    <div class="d-flex gap-2">
                      <button class="btn btn-sm btn-success" id="saveNewTagBtn">Create</button>
                      <button class="btn btn-sm btn-secondary" id="cancelNewTagBtn">Cancel</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Notes Section -->
              <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                  <span>Notes</span>
                  <i class="fas fa-chevron-up"></i>
                </button>
                <div class="accordion-content show">
                  <div id="notesList" class="notes-list">
                    <div class="text-muted small">No notes yet</div>
                  </div>
                  <textarea class="form-control mb-2" id="noteInput" rows="2" maxlength="240" placeholder="Add an internal note..."></textarea>
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" id="notePrivate" checked>
                      <label class="form-check-label small" for="notePrivate">Private note</label>
                    </div>
                    <span class="text-muted small" id="noteCounter">0 / 240</span>
                  </div>
                  <button class="btn btn-sm btn-primary w-100" id="addNoteBtn">
                    <i class="fas fa-plus me-1"></i>Add Note
                  </button>
                </div>
              </div>

              <!-- Customer Journey Section -->
              <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                  <span>Customer Journey</span>
                  <i class="fas fa-chevron-down"></i>
                </button>
                <div class="accordion-content">
                  <div id="journeyList" class="journey-timeline"></div>
                </div>
              </div>

              <!-- Attributes Section -->
              <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                  <span>Attributes</span>
                  <i class="fas fa-chevron-down"></i>
                </button>
                <div class="accordion-content">
                  <button class="btn-edit-attributes mb-3" id="editAttributesBtn">
                    <i class="fas fa-edit me-2"></i>Edit
                  </button>
                  <div class="attributes-list">
                    <div class="attribute-row">
                      <span class="attr-label">Cities</span>
                      <span class="attr-value" id="attrCityDisplay">-</span>
                    </div>
                    <div class="attribute-row">
                      <span class="attr-label">Email</span>
                      <span class="attr-value" id="attrEmailDisplay">-</span>
                    </div>
                    <div class="attribute-row">
                      <span class="attr-label">Organisation</span>
                      <span class="attr-value" id="attrOrgDisplay">-</span>
                    </div>
                    <div class="attribute-row">
                      <span class="attr-label">Date</span>
                      <span class="attr-value" id="attrDateDisplay">-</span>
                    </div>
                    <div class="attribute-row">
                      <span class="attr-label">Type</span>
                      <span class="attr-value" id="attrTypeDisplay">-</span>
                    </div>
                    <div class="attribute-row">
                      <span class="attr-label">Industry</span>
                      <span class="attr-value" id="attrIndustryDisplay">-</span>
                    </div>
                  </div>
                  <div class="attributes-edit-form" id="attributesEditForm" style="display:none;">
                    <div class="mb-2">
                      <label class="form-label">Contact Name</label>
                      <input type="text" class="form-control" id="contactName" placeholder="Contact name">
                    </div>
                    <div class="mb-2">
                      <label class="form-label">Email</label>
                      <input type="email" class="form-control" id="attrEmail" placeholder="Email">
                    </div>
                    <div class="mb-2">
                      <label class="form-label">City</label>
                      <input type="text" class="form-control" id="attrCity" placeholder="City">
                    </div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-sm btn-primary" id="saveAttrBtn">Save</button>
                      <button class="btn btn-sm btn-secondary" id="cancelAttrBtn">Cancel</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script src="https://js.pusher.com/8.4/pusher.min.js"></script>
<script>
let conversations = []
let current = null
let currentTab = 'active'
let allTags = ['Warm Lead', 'Hot Lead', 'Cold Lead', 'Payment Done', 'Daily User', 'Newone', 'Follow Up']
let pendingMediaFile = null
let mediaPreviewObjectUrl = null
let agentsCache = []
let templatesCache = []
let selectedTemplate = null
let templateVariables = {}
let sessionWindowOpen = true

function escapeHtml(value) {
  if (value === null || value === undefined) return ''
  return value
    .toString()
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function toggleAccordion(btn) {
  const content = btn.nextElementSibling
  const icon = btn.querySelector('i')
  const isOpen = content.classList.contains('show')
  
  if (isOpen) {
    content.classList.remove('show')
    icon.classList.remove('fa-chevron-up')
    icon.classList.add('fa-chevron-down')
  } else {
    content.classList.add('show')
    icon.classList.remove('fa-chevron-down')
    icon.classList.add('fa-chevron-up')
  }
}

async function loadConversations() {
  let conversations = [];
  try {
    const r = await fetch('whatsapp_api.php?action=list_conversations');
    if (!r.ok) throw new Error('Network error');
    const text = await r.text();
    if (!text) throw new Error('Empty response');
    const j = JSON.parse(text);
    conversations = j.data || [];
  } catch (err) {
    conversations = [];
  }
  renderList(conversations);
}

async function loadAgentsList(force = false) {
  if (agentsCache.length && !force) {
    populateAgentDropdown()
    return agentsCache
  }
  try {
    const res = await fetch('whatsapp_api.php?action=list_agents')
    const json = await res.json()
    agentsCache = json.data || []
    populateAgentDropdown()
  } catch (err) {
    console.error('Failed to load agents', err)
  }
  return agentsCache
}

function populateAgentDropdown() {
  const select = document.getElementById('agentSelect')
  if (!select) return
  const selected = current?.assigned_agent_id ? String(current.assigned_agent_id) : ''
  select.innerHTML = '<option value="">Unassigned</option>' + agentsCache.map(agent => `
    <option value="${agent.id}">${escapeHtml(agent.name)}</option>
  `).join('')
  if (selected) {
    select.value = selected
  }
}

async function transferAgent() {
  if (!current) return
  const select = document.getElementById('agentSelect')
  const agentId = select.value
  if (!agentId) {
    alert('Please select an agent')
    return
  }
  const fd = new FormData()
  fd.append('action', 'reassign_agent')
  fd.append('conversation_id', current.id)
  fd.append('agent_id', agentId)
  const res = await fetch('whatsapp_api.php', { method: 'POST', body: fd })
  const json = await res.json()
  if (!json.success) {
    alert(json.message || 'Unable to transfer agent')
    return
  }
  current.assigned_agent_id = parseInt(agentId, 10)
  populateAgentDropdown()
  loadConversations()
}

function renderList(items) {
  const list = document.getElementById('conversationList')
  list.innerHTML = ''
  
  const filtered = items.filter(c=> {
    if (currentTab === 'active') return c.intervened == 0 && (!c.requesting || c.requesting == 0)
    if (currentTab === 'requesting') return (c.requesting||0) == 1
    if (currentTab === 'intervened') return c.intervened == 1
    return false
  })
  
  // Update tab counts
  const activeCnt = items.filter(c=> c.intervened == 0 && (!c.requesting || c.requesting == 0)).length
  const requestingCnt = items.filter(c=> (c.requesting||0) == 1).length
  const intervenedCnt = items.filter(c=> c.intervened == 1).length
  document.getElementById('activeCount').textContent = activeCnt
  document.getElementById('requestingCount').textContent = requestingCnt
  document.getElementById('intervenedCount').textContent = intervenedCnt
  
  filtered.forEach(c=>{
    const item = document.createElement('div')
    item.className = 'conversation-item'
    item.dataset.convId = c.id
    const searchable = (c.contact_name || c.lead_name || c.phone_number || '').toLowerCase()
    item.dataset.name = searchable
    if (current && current.id === c.id) item.classList.add('active')
    if ((c.session_open ?? 1) === 0) item.classList.add('session-closed')
    
    const initial = (c.contact_name||c.phone_number||'')[0]?.toUpperCase()||'?'
    const unread = c.unread_count > 0 ? `<span class="unread-count">${c.unread_count}</span>` : ''
    const preview = c.last_message_preview
      || (c.last_message_media ? `[${(c.last_message_type || 'media').toUpperCase()} attachment]` : (c.agent_name ? `Agent: ${c.agent_name}` : 'No messages yet'))
    const time = formatTime(c.last_message_at)
    const statusIndicator = c.requesting == 1 ? '<span class="status-dot requesting"></span>' : ''
    
    item.innerHTML = `
      ${statusIndicator}
      <div class="conv-avatar">${initial}</div>
      <div class="conv-details">
        <div class="conv-header">
          <div class="conv-name">${c.contact_name||c.phone_number||''}</div>
          <div class="conv-time">${time}</div>
        </div>
        <div class="conv-preview">${preview}</div>
      </div>
      ${unread}
    `
    item.onclick = ()=>openConversation(c)
    list.appendChild(item)
  })
  
  if (list.children.length === 0) {
    const emptyIcon = currentTab === 'active' ? 'fa-inbox' : currentTab === 'requesting' ? 'fa-hand-paper' : 'fa-check-circle'
    const emptyText = currentTab === 'active' ? 'No active conversations' : currentTab === 'requesting' ? 'No requests pending' : 'No intervened chats'
    list.innerHTML = `<div class="empty-state">
      <i class="fas ${emptyIcon}"></i>
      <p>${emptyText}</p>
    </div>`
  }
}

function formatTime(timestamp) {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m`
  if (hours < 24) return `${hours}h`
  if (days < 7) return `${days}d`
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

async function openConversation(c) {
  current = c
  
  // Update active item in UI
  setTimeout(() => {
    document.querySelectorAll('.conversation-item').forEach(item => {
      if (item.dataset.convId == c.id) {
        item.classList.add('active')
      } else {
        item.classList.remove('active')
      }
    })
  }, 100)
  
  document.getElementById('chatTitle').textContent = c.contact_name || c.phone_number || ''
  
  const r = await fetch(`whatsapp_api.php?action=get_messages&conversation_id=${c.id}`)
  const j = await r.json()
  renderMessages(j.data||[])
  renderProfile(j.data||[])
  await loadAgentsList()
  populateAgentDropdown()
  loadNotes()
  updateNoteCounter()
  loadJourney()
  loadTags()
  updateState()
}

function renderMessages(items) {
  const pane = document.getElementById('messagePane')
  pane.innerHTML = ''
  
  if (items.length === 0) {
    pane.innerHTML = `<div class="empty-chat-state">
      <i class="far fa-comments"></i>
      <p>No messages yet</p>
      <small>Start the conversation by sending a message</small>
    </div>`
    return
  }
  
  let lastDate = null
  items.forEach((m, idx)=>{
    const msgDate = new Date(m.timestamp).toDateString()
    
    // Add date separator
    if (msgDate !== lastDate) {
      const separator = document.createElement('div')
      separator.className = 'date-separator'
      separator.innerHTML = `<span>${formatDateSeparator(m.timestamp)}</span>`
      pane.appendChild(separator)
      lastDate = msgDate
    }
    
    const bubble = document.createElement('div')
    bubble.className = `message-bubble ${m.direction}`
    
    const initial = m.direction === 'incoming' 
      ? (current.contact_name||current.phone_number||'')[0]?.toUpperCase()||'?'
      : (current.agent_name||'A')[0]?.toUpperCase()||'A'
    
    const messageText = formatMessageText(m.body||m.type||'')
    const timeFormatted = new Date(m.timestamp).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
    const statusIcon = m.direction === 'outgoing' ? getMessageStatus(m.status) : ''

    let mediaBlock = ''
    if (m.media_url) {
      if (m.type === 'image') {
        mediaBlock = `<div class="msg-media"><img src="${escapeHtml(m.media_url)}" alt="Attachment"></div>`
      } else if (m.type === 'video') {
        mediaBlock = `<div class="msg-media"><video controls src="${escapeHtml(m.media_url)}"></video></div>`
      } else if (m.type === 'audio') {
        mediaBlock = `<div class="msg-media audio"><audio controls src="${escapeHtml(m.media_url)}"></audio></div>`
      } else {
        mediaBlock = `<div class="msg-media doc"><a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener"><i class="fas fa-file-alt me-1"></i>Download attachment</a></div>`
      }
    }

    const textBlock = messageText ? `<div class="msg-text">${messageText}</div>` : ''
    
    bubble.innerHTML = `
      <div class="msg-avatar">${initial}</div>
      <div class="msg-content">
        ${mediaBlock}
        ${textBlock || (!mediaBlock ? '<div class="msg-text muted">No message content</div>' : '')}
        <div class="msg-meta">
          <span>${timeFormatted}</span>
          ${statusIcon}
        </div>
      </div>
    `
    pane.appendChild(bubble)
  })
  
  pane.scrollTop = pane.scrollHeight
}

function formatDateSeparator(timestamp) {
  const date = new Date(timestamp)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)
  
  if (date.toDateString() === today.toDateString()) return 'Today'
  if (date.toDateString() === yesterday.toDateString()) return 'Yesterday'
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatMessageText(text) {
  return text
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
    .replace(/_([^_]+)_/g, '<em>$1</em>')
    .replace(/~([^~]+)~/g, '<del>$1</del>')
    .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>')
}

function resetMediaModal() {
  pendingMediaFile = null
  if (mediaPreviewObjectUrl) {
    URL.revokeObjectURL(mediaPreviewObjectUrl)
    mediaPreviewObjectUrl = null
  }
  const preview = document.getElementById('mediaPreview')
  if (preview) {
    preview.innerHTML = ''
    preview.style.display = 'none'
  }
  const caption = document.getElementById('mediaCaption')
  if (caption) caption.value = ''
  const mediaFileInput = document.getElementById('mediaFileInput')
  if (mediaFileInput) mediaFileInput.value = ''
  const sendBtn = document.querySelector('#mediaModal .btn.btn-primary')
  if (sendBtn) {
    sendBtn.disabled = false
    sendBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Media'
  }
}

function handleMediaSelection(file) {
  if (!file) return
  pendingMediaFile = file
  if (mediaPreviewObjectUrl) {
    URL.revokeObjectURL(mediaPreviewObjectUrl)
    mediaPreviewObjectUrl = null
  }
  const preview = document.getElementById('mediaPreview')
  if (!preview) return
  mediaPreviewObjectUrl = URL.createObjectURL(file)
  preview.style.display = 'block'
  const mime = file.type || ''
  if (mime.startsWith('image/')) {
    preview.innerHTML = `<img src="${mediaPreviewObjectUrl}" alt="Preview" class="media-preview-img">`
  } else if (mime.startsWith('video/')) {
    preview.innerHTML = `<video controls class="media-preview-video"><source src="${mediaPreviewObjectUrl}" type="${escapeHtml(mime)}">Your browser does not support video playback.</video>`
  } else {
    preview.innerHTML = `<div class="media-preview-doc"><i class="fas fa-file-alt me-2"></i>${escapeHtml(file.name || 'Attachment')}</div>`
  }
}

async function uploadMediaFile(file) {
  const data = new FormData()
  data.append('action', 'upload_media')
  data.append('file', file)
  const response = await fetch('whatsapp_api.php', {
    method: 'POST',
    body: data
  })
  const result = await response.json()
  if (!result.success) {
    throw new Error(result.message || 'Unable to upload media')
  }
  return result.data
}

function getMessageStatus(status) {
  switch(status) {
    case 'sent': return '<i class="fas fa-check msg-status"></i>'
    case 'delivered': return '<i class="fas fa-check-double msg-status"></i>'
    case 'read': return '<i class="fas fa-check-double msg-status-read"></i>'
    default: return '<i class="fas fa-clock msg-status"></i>'
  }
}

function renderProfile(items) {
  if (!current) return
  
  const avatar = document.getElementById('profileAvatar')
  const initial = (current.contact_name||current.phone_number||'?')[0]?.toUpperCase()||'?'
  avatar.textContent = initial
  
  const displayName = current.contact_name || current.lead_name || current.phone_number || ''
  document.getElementById('profileName').textContent = displayName
  document.getElementById('profileStatusBadge').textContent = current.intervened == 1 ? 'Intervened' : 'Active'
  document.getElementById('profileStatusBadge').className = 'status-badge-large ' + (current.intervened == 1 ? 'intervened' : 'active')
  document.getElementById('profileLastActive').textContent = formatDateTime(current.last_message_at)
  
  const templates = items.filter(m=>m.type==='template').length
  document.getElementById('profileTemplateCount').textContent = templates
  document.getElementById('profileMessageCount').textContent = items.length
  document.getElementById('profileUnresolved').textContent = current.unresolved_count || '0'
  document.getElementById('profileSource').textContent = current.platform || current.sheet_source || '-'
  document.getElementById('profileLeadState').textContent = current.lead_status || '-'
  document.getElementById('profileFirstMsg').textContent = formatDateTime(current.first_message_at || current.lead_created_at)
  
  // Update attributes
  document.getElementById('attrCityDisplay').textContent = current.lead_city || current.city || '-'
  document.getElementById('attrEmailDisplay').textContent = current.lead_email || current.email || '-'
  document.getElementById('attrOrgDisplay').textContent = current.campaign_name || current.organisation || '-'
  document.getElementById('attrDateDisplay').textContent = formatDateTime(current.lead_created_at)
  document.getElementById('attrTypeDisplay').textContent = current.form_name || current.platform || '-'
  document.getElementById('attrIndustryDisplay').textContent = current.sheet_source || '-'
}

function updateState() {
  if (!current) return
  
  const isIntervened = current.intervened == 1
  const isRequesting = (current.requesting || 0) == 1
  setSessionWindowState((current.session_open ?? 1) === 1)
  
  document.getElementById('chatStatus').textContent = isIntervened ? 'Intervened' : 'Active'
  document.getElementById('chatStatus').className = 'chat-status-badge ' + (isIntervened ? 'intervened' : 'active')
  document.getElementById('interveneBtn').style.display = isIntervened ? 'none' : 'inline-flex'
  document.getElementById('resolveBtn').style.display = isIntervened ? 'inline-flex' : 'none'
  document.getElementById('interventionNotice').style.display = isIntervened ? 'flex' : 'none'
  document.getElementById('requestingNotice').style.display = isRequesting && !isIntervened ? 'flex' : 'none'
}

function setSessionWindowState(isOpen) {
  sessionWindowOpen = !!isOpen
  const alert = document.getElementById('sessionAlert')
  if (alert) alert.style.display = sessionWindowOpen ? 'none' : 'flex'
  msgInput.disabled = !sessionWindowOpen
  msgInput.placeholder = sessionWindowOpen ? 'Type a message...' : '24-hour window closed. Send a template.'
  document.getElementById('showMediaBtn').disabled = !sessionWindowOpen
  document.getElementById('attachFileBtn').disabled = !sessionWindowOpen
  sendBtn.disabled = !sessionWindowOpen || !msgInput.value.trim()
}

async function intervene() {
  if (!current) return
  const fd = new FormData()
  fd.append('action','intervene')
  fd.append('conversation_id', current.id)
  await fetch('whatsapp_api.php', { method:'POST', body: fd })
  current.intervened = 1
  current.requesting = 0
  updateState()
  loadConversations()
}

async function resolve() {
  if (!current) return
  const fd = new FormData()
  fd.append('action','resolve')
  fd.append('conversation_id', current.id)
  await fetch('whatsapp_api.php', { method:'POST', body: fd })
  current.intervened = 0
  updateState()
  loadConversations()
}

async function loadJourney() {
  if (!current) return
  const r = await fetch(`whatsapp_api.php?action=get_journey&conversation_id=${current.id}`)
  const j = await r.json()
  const el = document.getElementById('journeyList')
  el.innerHTML = ''
  
  ;(j.data||[]).forEach((ev, idx)=>{
    const item = document.createElement('div')
    item.className = 'journey-item'
    
    let icon = '<i class="far fa-circle"></i>'
    let label = ev.type
    
    if (ev.type.includes('tag')) {
      icon = '<i class="fas fa-tag"></i>'
      label = ev.type.includes('added') ? 'Warm Lead added' : 'Tag removed'
    } else if (ev.type.includes('campaign')) {
      icon = '<i class="fas fa-paper-plane"></i>'
    } else if (ev.type.includes('intervened')) {
      icon = '<i class="fas fa-user-plus"></i>'
    } else if (ev.type.includes('closed')) {
      icon = '<i class="fas fa-times-circle"></i>'
    }
    
    item.innerHTML = `
      <div class="journey-icon ${idx === 0 ? 'active' : ''}">${icon}</div>
      <div class="journey-content">
        <div class="journey-label">${label}</div>
        <div class="journey-meta">${ev.added_by || ''}</div>
        <div class="journey-time">${ev.at || ''}</div>
      </div>
    `
    el.appendChild(item)
  })
  
  if (el.children.length === 0) {
    el.innerHTML = '<div class="text-muted">No journey events</div>'
  }
}

async function loadNotes() {
  if (!current) return
  const r = await fetch(`whatsapp_api.php?action=list_notes&conversation_id=${current.id}`)
  const j = await r.json()
  renderNotes(j.data || [])
}

function renderNotes(notes) {
  const container = document.getElementById('notesList')
  if (!container) return
  if (!notes.length) {
    container.innerHTML = '<div class="text-muted small">No notes yet</div>'
    return
  }
  container.innerHTML = notes.map(note => `
    <div class="note-entry">
      <div class="note-meta">
        <strong>${escapeHtml(note.author_name || 'Agent')}</strong>
        <span>${formatDateTime(note.created_at)}</span>
      </div>
      <p>${escapeHtml(note.note_text)}</p>
      ${note.is_private ? '<span class="badge bg-secondary">Private</span>' : ''}
    </div>
  `).join('')
}

async function addNote() {
  if (!current) return
  const input = document.getElementById('noteInput')
  const text = input.value.trim()
  if (!text) return
  const isPrivate = document.getElementById('notePrivate').checked ? 1 : 0
  const fd = new FormData()
  fd.append('action', 'add_note')
  fd.append('conversation_id', current.id)
  fd.append('note_text', text)
  fd.append('is_private', isPrivate)
  const res = await fetch('whatsapp_api.php', { method: 'POST', body: fd })
  const json = await res.json()
  if (!json.success) {
    alert(json.message || 'Unable to save note')
    return
  }
  input.value = ''
  updateNoteCounter()
  loadNotes()
}

function updateNoteCounter() {
  const input = document.getElementById('noteInput')
  const counter = document.getElementById('noteCounter')
  if (!input || !counter) return
  counter.textContent = `${input.value.length} / 240`
}

async function loadTags() {
  if (!current) return
  const r = await fetch(`whatsapp_api.php?action=get_tags&conversation_id=${current.id}`)
  const j = await r.json()
  const el = document.getElementById('tagList')
  el.innerHTML = ''
  
  ;(j.data||[]).forEach(t=>{
    const tag = document.createElement('span')
    tag.className = 'tag-pill'
    tag.innerHTML = `${t.tag} <i class="fas fa-times"></i>`
    tag.querySelector('i').onclick = () => removeTag(t.tag)
    el.appendChild(tag)
  })
  
  // Update dropdown
  const dropdown = document.getElementById('tagDropdown')
  dropdown.innerHTML = '<option value="">Select & add a tag</option>'
  allTags.forEach(tag => {
    const option = document.createElement('option')
    option.value = tag
    option.textContent = tag
    dropdown.appendChild(option)
  })
}

async function addTag() {
  if (!current) return
  const tag = document.getElementById('tagDropdown').value
  if (!tag) return
  
  const fd = new FormData()
  fd.append('action','add_tag')
  fd.append('conversation_id', current.id)
  fd.append('tag', tag)
  await fetch('whatsapp_api.php', { method:'POST', body: fd })
  loadTags()
  loadJourney()
}

async function removeTag(tag) {
  if (!current) return
  const fd = new FormData()
  fd.append('action','remove_tag')
  fd.append('conversation_id', current.id)
  fd.append('tag', tag)
  await fetch('whatsapp_api.php', { method:'POST', body: fd })
  loadTags()
  loadJourney()
}

async function sendText() {
  if (!current) return
  const text = document.getElementById('msgInput').value
  if (!text) return
  if (!sessionWindowOpen) {
    alert('24-hour window closed. Please send an approved template.')
    return
  }
  
  const fd = new FormData()
  fd.append('action','send_text')
  fd.append('to', current.phone_number)
  fd.append('text', text)
  await fetch('whatsapp_api.php', { method:'POST', body: fd })
  document.getElementById('msgInput').value = ''
  openConversation(current)
}

async function saveAttributes() {
  if (!current || !current.lead_id) return
  const email = document.getElementById('attrEmail').value
  const city = document.getElementById('attrCity').value
  const name = document.getElementById('contactName').value
  
  const fd = new FormData()
  fd.append('action','update_lead_attr')
  fd.append('lead_id', current.lead_id)
  fd.append('email', email)
  fd.append('city', city)
  if (name) {
    fd.append('action','update_contact')
    fd.append('conversation_id', current.id)
    fd.append('contact_name', name)
  }
  await fetch('whatsapp_api.php', { method:'POST', body: fd })
  
  document.getElementById('attributesEditForm').style.display = 'none'
  openConversation(current)
}

// Search functionality
document.getElementById('searchConversations').oninput = (e) => {
  const query = e.target.value.toLowerCase()
  const items = document.querySelectorAll('.conversation-item')
  let visibleCount = 0
  
  items.forEach(item => {
    const name = item.dataset.name || ''
    if (name.includes(query)) {
      item.style.display = 'flex'
      visibleCount++
    } else {
      item.style.display = 'none'
    }
  })
  
  document.getElementById('clearSearch').style.display = query ? 'flex' : 'none'
  
  if (visibleCount === 0 && query) {
    document.getElementById('conversationList').innerHTML = `
      <div class="empty-state">
        <i class="fas fa-search"></i>
        <p>No results found</p>
        <small>Try a different search term</small>
      </div>`
  }
}

document.getElementById('clearSearch').onclick = () => {
  document.getElementById('searchConversations').value = ''
  document.getElementById('clearSearch').style.display = 'none'
  renderList(conversations)
}

// Input functionality
const msgInput = document.getElementById('msgInput')
const sendBtn = document.getElementById('sendBtn')

// Auto-resize textarea
msgInput.oninput = () => {
  sendBtn.disabled = !sessionWindowOpen || !msgInput.value.trim()
  msgInput.style.height = 'auto'
  msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px'
}

// Event Listeners
sendBtn.onclick = sendText
msgInput.onkeypress = (e) => { 
  if(e.key==='Enter' && !e.shiftKey) {
    e.preventDefault()
    if(sessionWindowOpen && msgInput.value.trim()) sendText()
  }
}

// File attachment
document.getElementById('attachFileBtn').onclick = () => {
  document.getElementById('fileInput').click()
}

const quickFileInput = document.getElementById('fileInput')
quickFileInput.onchange = (e) => {
  const file = e.target.files[0]
  if (!file) return
  handleMediaSelection(file)
  document.getElementById('mediaModal').style.display = 'block'
}

const modalFileInput = document.getElementById('mediaFileInput')
modalFileInput.onchange = (e) => {
  const file = e.target.files[0]
  if (!file) return
  handleMediaSelection(file)
}
document.getElementById('mediaUploadArea').onclick = () => {
  document.getElementById('mediaFileInput').click()
}

// Media Modal
document.getElementById('showMediaBtn').onclick = () => {
  resetMediaModal()
  document.getElementById('mediaModal').style.display = 'block'
}

// Template Modal
document.getElementById('showTemplateBtn').onclick = async () => {
  resetTemplateModal()
  document.getElementById('templateModal').style.display = 'block'
  await loadTemplatesList()
}

// Emoji Modal
document.getElementById('showEmojiBtn').onclick = () => {
  const modal = document.getElementById('emojiModal')
  modal.style.display = modal.style.display === 'none' ? 'block' : 'none'
  if (modal.style.display === 'block') {
    generateEmojiPicker()
  }
}

// Close modal function
function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none'
  if (modalId === 'mediaModal') {
    resetMediaModal()
  }
  if (modalId === 'templateModal') {
    resetTemplateModal()
  }
}

// Send Media
function sendMedia() {
  if (!current) {
    alert('Select a conversation first.')
    return
  }
  if (!sessionWindowOpen) {
    alert('24-hour window closed. Send a template instead.')
    return
  }
  if (!pendingMediaFile) {
    alert('Please select a media file.')
    return
  }
  const caption = document.getElementById('mediaCaption').value
  const sendButton = document.querySelector('#mediaModal .btn.btn-primary')
  const originalLabel = sendButton.innerHTML
  sendButton.disabled = true
  sendButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...'

  uploadMediaFile(pendingMediaFile)
    .then(async upload => {
      const fd = new FormData()
      fd.append('action', 'send_media')
      fd.append('to', current.phone_number)
      fd.append('type', upload.media_type || 'image')
      fd.append('media_url', upload.url)
      fd.append('caption', caption || '')
      fd.append('filename', upload.filename || pendingMediaFile.name || '')
      const res = await fetch('whatsapp_api.php', { method: 'POST', body: fd })
      const json = await res.json()
      if (!json.success) {
        throw new Error(json.message || 'Unable to send media')
      }
      closeModal('mediaModal')
      openConversation(current)
    })
    .catch(err => {
      alert(err.message || 'Upload failed')
    })
    .finally(() => {
      sendButton.disabled = false
      sendButton.innerHTML = originalLabel
    })
}

function resetTemplateModal() {
  selectedTemplate = null
  templateVariables = {}
  document.getElementById('templatePreviewStack').style.display = 'none'
  document.getElementById('templateEmptyState').style.display = 'flex'
  document.getElementById('templateError').textContent = ''
  document.getElementById('sendTemplateBtn').disabled = true
}

async function loadTemplatesList(force = false) {
  if (templatesCache.length && !force) {
    renderTemplateList(document.getElementById('templateSearch').value || '')
    return
  }
  const list = document.getElementById('templateList')
  list.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div>'
  try {
    const r = await fetch('whatsapp_templates.php?ajax=1&source=local')
    const j = await r.json()
    if (j.success) {
      templatesCache = j.data || []
      renderTemplateList(document.getElementById('templateSearch').value || '')
    } else {
      list.innerHTML = `<div class="text-danger text-center p-3">${escapeHtml(j.message || 'No templates available')}</div>`
    }
  } catch (e) {
    console.error(e)
    list.innerHTML = '<div class="text-danger text-center p-3">Error loading templates</div>'
  }
}

function renderTemplateList(query = '') {
  const list = document.getElementById('templateList')
  if (!list) return
  const term = query.toLowerCase()
  const filtered = templatesCache.filter(t => {
    return t.name.toLowerCase().includes(term) || (t.message || '').toLowerCase().includes(term)
  })
  if (!filtered.length) {
    list.innerHTML = '<div class="text-muted text-center p-3">No templates found</div>'
    return
  }
  list.innerHTML = filtered.map(t => `
    <div class="template-item ${selectedTemplate && selectedTemplate.id === t.id ? 'selected' : ''}" data-id="${t.id}">
      <div class="template-item-header">
        <div>
          <strong>${escapeHtml(t.name)}</strong>
          <small>${escapeHtml(t.category || 'Utility')} • ${escapeHtml(t.language || 'en_US')}</small>
        </div>
        <span class="status-chip">${escapeHtml(t.status || 'approved')}</span>
      </div>
      <p>${escapeHtml((t.message || '').substring(0, 90))}${(t.message || '').length > 90 ? '…' : ''}</p>
    </div>
  `).join('')
  list.querySelectorAll('.template-item').forEach(item => {
    item.addEventListener('click', () => selectTemplate(parseInt(item.dataset.id, 10)))
  })
}

function extractPlaceholdersFromText(text) {
  if (!text) return []
  const matches = text.match(/\{\{\s*([^}]+)\s*\}\}/g) || []
  return matches.map(token => token.replace('{{', '').replace('}}', '').trim())
}

function collectTemplatePlaceholders(template) {
  const placeholders = new Set()
  ;[template.header_text, template.message, template.footer_text].forEach(block => {
    extractPlaceholdersFromText(block).forEach(ph => placeholders.add(ph))
  })
  try {
    const buttons = typeof template.buttons === 'string' ? JSON.parse(template.buttons || '[]') : (template.buttons || [])
    buttons.forEach(btn => {
      extractPlaceholdersFromText(btn.text).forEach(ph => placeholders.add(ph))
      extractPlaceholdersFromText(btn.value).forEach(ph => placeholders.add(ph))
    })
  } catch (err) {
    // ignore parse errors
  }
  return Array.from(placeholders)
}

function highlightPlaceholders(text) {
  if (!text) return ''
  const safe = escapeHtml(text)
  return safe.replace(/\{\{\s*([^}]+)\s*\}\}/g, '<span class="placeholder-chip">{{$1}}</span>')
}

function autofillVariable(key) {
  if (!current) return ''
  const normalized = key.toLowerCase()
  if (normalized.includes('name')) return current.contact_name || current.lead_name || ''
  if (normalized.includes('city')) return current.lead_city || current.city || ''
  if (normalized.includes('email')) return current.lead_email || current.email || ''
  if (normalized.includes('phone')) return current.phone_number || ''
  if (normalized.includes('score')) return current.lead_score || ''
  if (normalized.includes('campaign')) return current.campaign_name || ''
  if (normalized.includes('form')) return current.form_name || ''
  return ''
}

function renderTemplatePreview(template) {
  const stack = document.getElementById('templatePreviewStack')
  const emptyState = document.getElementById('templateEmptyState')
  const nameEl = document.getElementById('templatePreviewName')
  const metaEl = document.getElementById('templatePreviewMeta')
  const statusEl = document.getElementById('templateStatusBadge')
  const bodyEl = document.getElementById('templatePreviewBody')
  const footerEl = document.getElementById('templatePreviewFooter')
  const varList = document.getElementById('templateVariableList')
  const errorEl = document.getElementById('templateError')

  if (!template) {
    stack.style.display = 'none'
    emptyState.style.display = 'flex'
    document.getElementById('sendTemplateBtn').disabled = true
    return
  }

  emptyState.style.display = 'none'
  stack.style.display = 'flex'
  nameEl.textContent = template.name
  metaEl.textContent = `${template.category || 'Utility'} • ${template.language || 'en_US'}`
  statusEl.textContent = (template.status || 'approved').toUpperCase()

  const headerBlock = template.header_text ? `<p class="preview-header-text">${highlightPlaceholders(template.header_text)}</p>` : ''
  const bodyBlock = `<p class="preview-body-text">${highlightPlaceholders(template.message || '')}</p>`
  const footerBlock = template.footer_text ? `<p class="preview-footer-text">${highlightPlaceholders(template.footer_text)}</p>` : ''

  bodyEl.innerHTML = headerBlock + bodyBlock
  footerEl.innerHTML = footerBlock

  const placeholders = collectTemplatePlaceholders(template)
  if (placeholders.length === 0) {
    varList.innerHTML = '<div class="text-muted small">No variables required</div>'
  } else {
    varList.innerHTML = placeholders.map(ph => {
      if (!(ph in templateVariables) || templateVariables[ph] === '') {
        const stored = template.placeholders ? tryParseJSON(template.placeholders)[ph] : ''
        templateVariables[ph] = stored || autofillVariable(ph)
      }
      return `
        <div class="template-var-row">
          <label>{{${escapeHtml(ph)}}}</label>
          <input type="text" data-placeholder="${escapeHtml(ph)}" class="form-control form-control-sm" value="${escapeHtml(templateVariables[ph] || '')}">
        </div>
      `
    }).join('')
    varList.querySelectorAll('input').forEach(input => {
      input.addEventListener('input', e => {
        templateVariables[e.target.dataset.placeholder] = e.target.value
      })
    })
  }

  errorEl.textContent = ''
  document.getElementById('sendTemplateBtn').disabled = false
}

function tryParseJSON(value) {
  try {
    return JSON.parse(value || '{}')
  } catch {
    return {}
  }
}

function selectTemplate(templateId) {
  const template = templatesCache.find(t => Number(t.id) === Number(templateId))
  if (!template) return
  selectedTemplate = template
  highlightTemplateItem(templateId)
  templateVariables = {}
  renderTemplatePreview(template)
}

function highlightTemplateItem(templateId) {
  document.querySelectorAll('.template-item').forEach(item => {
    item.classList.toggle('selected', Number(item.dataset.id) === Number(templateId))
  })
}

async function sendSelectedTemplate() {
  if (!current) return
  if (!selectedTemplate) {
    alert('Select a template to send.')
    return
  }
  const btn = document.getElementById('sendTemplateBtn')
  const errorEl = document.getElementById('templateError')
  btn.disabled = true
  const original = btn.innerHTML
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...'
  try {
    const fd = new FormData()
    fd.append('action', 'send_template')
    fd.append('to', current.phone_number)
    fd.append('template_id', selectedTemplate.id)
    fd.append('language_code', selectedTemplate.language || 'en_US')
    fd.append('variables', JSON.stringify(templateVariables || {}))
    const res = await fetch('whatsapp_api.php', { method: 'POST', body: fd })
    const json = await res.json()
    if (!json.success) {
      errorEl.textContent = json.message || 'Failed to send template'
      return
    }
    closeModal('templateModal')
    openConversation(current)
  } catch (err) {
    errorEl.textContent = err.message || 'Failed to send template'
  } finally {
    btn.disabled = false
    btn.innerHTML = original
  }
}

// Generate Emoji Picker
function generateEmojiPicker() {
  const grid = document.getElementById('emojiGrid')
  if (grid.children.length > 0) return // Already generated
  
  const emojis = [
    '😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙',
    '😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥',
    '😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','😵','🤯','🤠','🥳','😎','🤓','🧐',
    '👍','👎','👌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','🤝','💪',
    '❤️','🧡','💛','💚','💙','💜','🖤','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','☮️','✝️','☪️',
    '🔥','✨','💫','⭐','🌟','💥','💢','💯','✅','❌','⭕','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤'
  ]
  
  emojis.forEach(emoji => {
    const span = document.createElement('span')
    span.className = 'emoji-item'
    span.textContent = emoji
    span.onclick = () => {
      msgInput.value += emoji
      msgInput.focus()
      closeModal('emojiModal')
    }
    grid.appendChild(span)
  })
}
document.getElementById('interveneBtn').onclick = intervene
document.getElementById('interveneBottomBtn').onclick = intervene
document.getElementById('resolveBtn').onclick = resolve
document.getElementById('addTagBtn').onclick = addTag

document.getElementById('showCreateTagBtn').onclick = () => {
  document.getElementById('createTagForm').style.display = 'block'
}
document.getElementById('cancelNewTagBtn').onclick = () => {
  document.getElementById('createTagForm').style.display = 'none'
  document.getElementById('newTagName').value = ''
}
document.getElementById('saveNewTagBtn').onclick = async () => {
  const newTag = document.getElementById('newTagName').value
  if (newTag && !allTags.includes(newTag)) {
    allTags.push(newTag)
  }
  document.getElementById('createTagForm').style.display = 'none'
  document.getElementById('newTagName').value = ''
  loadTags()
}

document.getElementById('editAttributesBtn').onclick = () => {
  document.getElementById('attributesEditForm').style.display = 'block'
}
document.getElementById('cancelAttrBtn').onclick = () => {
  document.getElementById('attributesEditForm').style.display = 'none'
}
document.getElementById('saveAttrBtn').onclick = saveAttributes
document.getElementById('addNoteBtn').onclick = addNote
document.getElementById('noteInput').addEventListener('input', updateNoteCounter)
document.getElementById('transferAgentBtn').onclick = transferAgent
document.getElementById('templateSearch').addEventListener('input', e => renderTemplateList(e.target.value))
document.getElementById('sendTemplateBtn').onclick = sendSelectedTemplate

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.onclick = () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'))
    btn.classList.add('active')
    currentTab = btn.getAttribute('data-tab')
    renderList(conversations)
  }
})

// Helper function for text formatting
function formatText(type) {
  const input = document.getElementById('msgInput')
  const start = input.selectionStart
  const end = input.selectionEnd
  const text = input.value
  const selectedText = text.substring(start, end)
  
  if (!selectedText) return
  
  let formatted = selectedText
  if (type === 'bold') formatted = `*${selectedText}*`
  if (type === 'italic') formatted = `_${selectedText}_`
  if (type === 'strikethrough') formatted = `~${selectedText}~`
  
  input.value = text.substring(0, start) + formatted + text.substring(end)
  input.focus()
  input.setSelectionRange(start, start + formatted.length)
}

// Toggle mobile conversation list
function toggleConversationList() {
  const leftPanel = document.querySelector('.chat-left-panel')
  const centerPanel = document.querySelector('.chat-center-panel')
  leftPanel.classList.toggle('show-mobile')
  centerPanel.classList.toggle('hide-mobile')
}

// Toggle mobile profile panel
function toggleProfile() {
  const rightPanel = document.querySelector('.chat-right-panel')
  rightPanel.classList.toggle('show-mobile')
}

// Notification sound
function playNotificationSound() {
  const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuB0PP')
  audio.play().catch(() => {}) // Ignore if autoplay is blocked
}

// Initialize
loadConversations()
loadAgentsList()
setInterval(loadConversations, 30000) // Refresh every 30 seconds

// Show notification for new messages
let lastMessageCount = 0
setInterval(() => {
  if (current && conversations.find(c => c.id === current.id)) {
    const conv = conversations.find(c => c.id === current.id)
    if (conv.unread_count > lastMessageCount) {
      playNotificationSound()
      // Visual notification
      if (document.hidden) {
        document.title = `(${conv.unread_count}) New Messages - WhatsApp`
      }
    }
    lastMessageCount = conv.unread_count
  }
}, 5000)

// Reset title when page is visible
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    document.title = 'Live Chat - WhatsApp Conversations'
  }
})

<?php if ($pusherKey && $pusherCluster): ?>
const p = new Pusher('<?php echo $pusherKey ?>', { cluster: '<?php echo $pusherCluster ?>' })
const ch = p.subscribe('admin')
ch.bind('whatsapp_message', data => {
  if (current && data.conversation_id === current.id) {
    openConversation(current)
  }
  loadConversations()
})
<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
