<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/jwt_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$agentStmt = $db->query("SELECT id, name FROM users WHERE role = 'agent' AND active = 1 ORDER BY name");
$agents = $agentStmt->fetchAll();

$templateStmt = $db->query("SELECT id, name, language FROM whatsapp_templates WHERE is_active = 1 ORDER BY name");
$templates = $templateStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Flow Builder</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
  <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/flow-builder.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/7e9f1f9f3c.js" crossorigin="anonymous"></script>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <?php include 'sidebar.php'; ?>
    <main class="col-md-10 ms-sm-auto px-md-4">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Automations</p>
                    <h1>WhatsApp Flow Builder</h1>
                    <p class="text-muted mb-0">Design AiSensy-style journeys with drag & drop nodes, routing, and WhatsApp actions.</p>
        </div>
                <div class="page-heading__actions">
                    <button class="ghost-button" id="newFlowBtn">
                        <i class="fas fa-circle-plus me-1"></i> New Flow
                    </button>
                    <button class="btn btn-outline-primary" id="cloneFlowBtn" disabled>
                        <i class="fas fa-copy me-1"></i> Duplicate
                    </button>
                    <button class="btn btn-primary" id="saveFlowBtn">
                        <i class="fas fa-save me-1"></i> Save Flow
                    </button>
              </div>
            </div>

            <div class="flow-builder-shell">
                <aside class="flow-card flow-library">
                    <div class="flow-library__header">
                        <h5 class="mb-0">Flows</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="flowActiveToggle" checked>
                            <label class="form-check-label small" for="flowActiveToggle">Active</label>
                        </div>
                    </div>
                    <input type="text" class="form-control mb-3" id="flowNameInput" placeholder="Flow name">
                    <div class="flow-library__list" id="flowList"></div>

                    <div class="flow-node-library">
                        <h5>Node Library</h5>
                        <div id="paletteContainer"></div>
                    </div>
                </aside>

                <section class="flow-card flow-canvas">
                    <div class="flow-canvas__toolbar">
                        <div class="text-muted small">
                            <i class="fas fa-arrows-up-down-left-right me-1"></i>
                            Drag cards to arrange steps. Use inspector to wire configs & next steps.
                        </div>
                        <div class="d-flex gap-2">
                            <button class="ghost-button" id="autoLayoutBtn">
                                <i class="fas fa-wand-magic-sparkles me-1"></i> Auto layout
                            </button>
                            <button class="ghost-button" id="centerCanvasBtn">
                                <i class="fas fa-crosshairs me-1"></i> Center
                            </button>
          </div>
        </div>
                    <div class="flow-canvas__viewport" id="flowCanvas">
                        <svg class="flow-connections" id="flowConnections"></svg>
                        <div class="flow-nodes" id="flowNodes"></div>
                    </div>
                </section>

                <aside class="flow-card flow-inspector">
                    <div id="inspectorPanel" class="inspector-placeholder">
                        <i class="fas fa-hand-pointer"></i>
                        <p>Select a node to configure details</p>
                    </div>
                </aside>
      </div>
    </main>
  </div>
</div>

<div class="flow-toast" id="flowToast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.__agents = <?= json_encode($agents ?? []) ?>;
window.__templates = <?= json_encode($templates ?? []) ?>;
window.__jwt = <?= json_encode(generateJWT(['user_id' => $_SESSION['user']['id'], 'email' => $_SESSION['user']['email'], 'role' => 'admin'])); ?>;
</script>
<script>
const FLOW_API_BASE = '../includes/whatsapp.php';
const JWT_TOKEN = (window.__jwt || '') || (sessionStorage.getItem('jwt') || '');
const NODE_LIBRARY = [
    {
        category: 'Triggers',
        items: [
            { type: 'keyword_trigger', label: 'Keyword Trigger', icon: 'fa-bolt', desc: 'Start when incoming message matches a keyword', defaults: { keywords: [] } },
            { type: 'user_input', label: 'Wait for Reply', icon: 'fa-inbox', desc: 'Pause until user responds and store input', defaults: { key: 'user_input' } }
        ]
    },
    {
        category: 'WhatsApp Actions',
        items: [
            { type: 'send_text', label: 'Send Text', icon: 'fa-message', desc: 'Reply with a personalized WhatsApp text', defaults: { text: 'Hello {{name}} 👋' } },
            { type: 'send_template', label: 'Send Template', icon: 'fa-whatsapp', desc: 'Fire an approved template', defaults: { template_id: null, language: 'en_US' } },
            { type: 'assign_agent', label: 'Assign Agent', icon: 'fa-user-tie', desc: 'Route conversation to an agent', defaults: { agent_id: null } },
            { type: 'tag_add', label: 'Add Tag', icon: 'fa-tag', desc: 'Label conversation for segmentation', defaults: { tag: '' } },
            { type: 'tag_remove', label: 'Remove Tag', icon: 'fa-tag', desc: 'Remove an existing tag', defaults: { tag: '' } }
        ]
    },
    {
        category: 'Automation',
        items: [
            { type: 'delay', label: 'Delay', icon: 'fa-clock', desc: 'Pause before next node', defaults: { minutes: 10 } },
            { type: 'condition', label: 'Condition', icon: 'fa-code-branch', desc: 'Only continue when criteria matches', defaults: { left: '{{text}}', op: 'eq', right: 'yes' } },
            { type: 'save_variable', label: 'Save Variable', icon: 'fa-database', desc: 'Store a value for later nodes', defaults: { key: 'source', value: '{{text}}' } },
            { type: 'api_call', label: 'API Call', icon: 'fa-plug', desc: 'Hit external webhook and use response', defaults: { method: 'POST', url: '', headers: [], body: '' } }
        ]
    }
];

const state = {
    flowList: [],
    currentFlowId: null,
    nodes: [],
    edges: [],
    selectedNodeId: null,
    connectingFrom: null
};

const els = {
    flowList: document.getElementById('flowList'),
    palette: document.getElementById('paletteContainer'),
    flowNodes: document.getElementById('flowNodes'),
    flowConnections: document.getElementById('flowConnections'),
    inspector: document.getElementById('inspectorPanel'),
    flowNameInput: document.getElementById('flowNameInput'),
    flowActiveToggle: document.getElementById('flowActiveToggle'),
    toast: document.getElementById('flowToast'),
    cloneBtn: document.getElementById('cloneFlowBtn')
};

const encodeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const formatFieldValue = value => {
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        try {
            return JSON.stringify(value);
        } catch {
            return '';
        }
    }
    return value ?? '';
};

function showToast(msg, variant = 'success') {
    els.toast.textContent = msg;
    els.toast.style.borderColor = variant === 'success' ? 'var(--brand-500)' : '#dc2626';
    els.toast.style.color = variant === 'success' ? 'var(--text-primary)' : '#dc2626';
    els.toast.classList.add('show');
    setTimeout(() => els.toast.classList.remove('show'), 2500);
}

function api(path, method = 'GET', body) {
    if (!JWT_TOKEN) {
        showToast('Missing JWT. Please login again.', 'error');
        return Promise.reject(new Error('Missing JWT'));
    }
    return fetch(`${FLOW_API_BASE}${path}`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${JWT_TOKEN}`
        },
        body: body ? JSON.stringify(body) : undefined
    }).then(res => res.json());
}

function initPalette() {
    els.palette.innerHTML = NODE_LIBRARY.map(group => `
        <div class="flow-node-group">
            <h6>${group.category}</h6>
            ${group.items.map(item => `
                <div class="palette-node" data-type="${item.type}">
                    <i class="fas ${item.icon} text-muted"></i>
                    <div>
                        <strong>${item.label}</strong>
                        <p class="text-muted small mb-0">${item.desc}</p>
                    </div>
                </div>
            `).join('')}
        </div>
    `).join('');

    els.palette.querySelectorAll('.palette-node').forEach(card => {
        card.addEventListener('click', () => addNode(card.dataset.type));
    });
}

function addNode(type) {
    const nodeDef = NODE_LIBRARY.flatMap(group => group.items).find(n => n.type === type);
    if (!nodeDef) return;
    const canvas = document.getElementById('flowCanvas');
    const node = {
        id: `node-${Date.now()}`,
        type,
        label: nodeDef.label,
        x: 80 + Math.random() * Math.max(100, canvas.clientWidth - 260),
        y: 80 + Math.random() * Math.max(100, canvas.clientHeight - 220),
        config: JSON.parse(JSON.stringify(nodeDef.defaults || {}))
    };
    state.nodes.push(node);
    state.selectedNodeId = node.id;
    renderNodes();
    renderInspector();
}

function renderFlowList() {
    if (!state.flowList.length) {
        els.flowList.innerHTML = '<p class="text-muted small mb-0">No flows yet.</p>';
        return;
    }
    els.flowList.innerHTML = state.flowList.map(flow => `
        <div class="flow-list-item ${flow.id == state.currentFlowId ? 'active' : ''}" data-id="${flow.id}">
            <div>
                <strong>${encodeHtml(flow.name)}</strong>
                <p class="mb-0 text-muted small">${new Date(flow.created_at).toLocaleDateString()}</p>
            </div>
            <span class="badge ${flow.active ? 'bg-success' : 'bg-secondary'}">${flow.active ? 'Live' : 'Paused'}</span>
        </div>
    `).join('');
    els.flowList.querySelectorAll('.flow-list-item').forEach(item => {
        item.addEventListener('click', () => loadFlow(item.dataset.id));
    });
}

function renderNodes() {
    els.flowNodes.innerHTML = '';
    state.nodes.forEach(node => {
        const el = document.createElement('div');
        const isSelected = state.selectedNodeId === node.id;
        const isConnecting = state.connectingFrom === node.id;
        el.className = `flow-node ${isSelected ? 'selected' : ''} ${isConnecting ? 'connecting' : ''}`;
        el.style.left = `${node.x}px`;
        el.style.top = `${node.y}px`;
        el.dataset.nodeId = node.id;
        el.innerHTML = `
        <button class="node-connector" title="Connect" onclick="startConnection('${node.id}'); event.stopPropagation();">
            <i class="fas fa-link"></i>
        </button>
        <div class="flow-node__type">${encodeHtml(node.type.replace('_', ' '))}</div>
        <div class="flow-node__title">${encodeHtml(node.label || 'Node')}</div>
            <div class="flow-node__meta">${Object.keys(node.config || {}).length} setting(s)</div>
        `;
        el.addEventListener('mousedown', e => startDrag(e, node.id));
        el.addEventListener('click', e => {
            e.stopPropagation();
            if (state.connectingFrom && state.connectingFrom !== node.id) {
                state.edges = state.edges.filter(edge => edge.from !== state.connectingFrom);
                state.edges.push({ id: `edge-${Date.now()}`, from: state.connectingFrom, to: node.id });
                state.connectingFrom = null;
                drawConnections();
                renderNodes();
                renderInspector();
                return;
            }
            state.selectedNodeId = node.id;
            renderNodes();
            renderInspector();
        });
        els.flowNodes.appendChild(el);
    });
    drawConnections();
}

function startConnection(nodeId) {
    if (state.connectingFrom === nodeId) {
        state.connectingFrom = null;
    } else {
        state.connectingFrom = nodeId;
    }
    renderNodes();
}

function startDrag(e, nodeId) {
    e.preventDefault();
    const node = state.nodes.find(n => n.id === nodeId);
    if (!node) return;
    const canvasRect = document.getElementById('flowCanvas').getBoundingClientRect();
    const offsetX = e.clientX - canvasRect.left - node.x;
    const offsetY = e.clientY - canvasRect.top - node.y;

    function onMove(ev) {
        node.x = ev.clientX - canvasRect.left - offsetX;
        node.y = ev.clientY - canvasRect.top - offsetY;
        renderNodes();
    }

    function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    }

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}

function drawConnections() {
    const svg = els.flowConnections;
    const canvas = document.getElementById('flowCanvas');
    svg.setAttribute('viewBox', `0 0 ${canvas.clientWidth} ${canvas.clientHeight}`);
    svg.innerHTML = '';
    state.edges.forEach(edge => {
        const from = state.nodes.find(n => n.id === edge.from);
        const to = state.nodes.find(n => n.id === edge.to);
        if (!from || !to) return;
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const startX = from.x + 115;
        const startY = from.y + 32;
        const endX = to.x + 115;
        const endY = to.y;
        const curve = `M${startX},${startY} C${startX},${(startY + endY) / 2} ${endX},${(startY + endY) / 2} ${endX},${endY - 8}`;
        path.setAttribute('d', curve);
        path.setAttribute('stroke', 'rgba(18,160,135,0.6)');
        path.setAttribute('stroke-width', 2);
        path.setAttribute('fill', 'none');
        svg.appendChild(path);
    });
}

function renderInspector() {
    const node = state.nodes.find(n => n.id === state.selectedNodeId);
    if (!node) {
        els.inspector.innerHTML = `
            <div class="inspector-placeholder">
                <i class="fas fa-hand-pointer"></i>
                <p>Select a node to configure details</p>
            </div>`;
        return;
    }
    const fields = getNodeFields(node.type);
    const downstream = state.nodes.filter(n => n.id !== node.id);
    const existingEdge = state.edges.find(edge => edge.from === node.id);
    els.inspector.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">${encodeHtml(node.label || 'Node details')}</h5>
            <button class="btn btn-sm btn-outline-danger" id="deleteNodeBtn">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="mb-3">
            <label class="form-label">Node label</label>
            <input type="text" class="form-control" id="nodeLabelInput" value="${node.label || ''}">
        </div>
        ${fields.map(field => renderField(node, field)).join('')}
        <div class="mb-3">
            <label class="form-label">Next step</label>
            <select class="form-select" id="nextNodeSelect">
                <option value="">End flow</option>
                ${downstream.map(opt => `<option value="${opt.id}" ${existingEdge && existingEdge.to === opt.id ? 'selected' : ''}>${encodeHtml(opt.label || opt.type)}</option>`).join('')}
            </select>
        </div>
    `;
    document.getElementById('deleteNodeBtn').onclick = () => {
        state.nodes = state.nodes.filter(n => n.id !== node.id);
        state.edges = state.edges.filter(edge => edge.from !== node.id && edge.to !== node.id);
        state.selectedNodeId = null;
        renderNodes();
        renderInspector();
    };
    document.getElementById('nodeLabelInput').addEventListener('input', e => {
        node.label = e.target.value;
        renderNodes();
    });
    document.getElementById('nextNodeSelect').addEventListener('change', e => {
        state.edges = state.edges.filter(edge => edge.from !== node.id);
        if (e.target.value) {
            state.edges.push({ id: `edge-${Date.now()}`, from: node.id, to: e.target.value });
        }
        drawConnections();
    });
    fields.forEach(field => {
        const input = els.inspector.querySelector(`[data-field="${field.key}"]`);
        if (!input) return;
        input.addEventListener('input', e => handleConfigChange(node, field, e.target.value));
        if (field.type === 'select') {
            input.addEventListener('change', e => handleConfigChange(node, field, e.target.value));
        }
    });
}

function renderField(node, field) {
    const rawValue = node.config[field.key] ?? field.default ?? '';
    const safeValue = encodeHtml(formatFieldValue(rawValue));
    if (field.type === 'textarea') {
        return `
            <div class="mb-3">
                <label class="form-label">${field.label}</label>
                <textarea class="form-control" rows="3" data-field="${field.key}">${safeValue}</textarea>
                ${field.hint ? `<small class="text-muted">${field.hint}</small>` : ''}
            </div>
        `;
    }
    if (field.type === 'select') {
        return `
            <div class="mb-3">
                <label class="form-label">${field.label}</label>
                <select class="form-select" data-field="${field.key}">
                    ${field.options.map(opt => `<option value="${opt.value}" ${opt.value == rawValue ? 'selected' : ''}>${encodeHtml(opt.label)}</option>`).join('')}
                </select>
                ${field.hint ? `<small class="text-muted">${field.hint}</small>` : ''}
            </div>
        `;
    }
    return `
        <div class="mb-3">
            <label class="form-label">${field.label}</label>
            <input type="${field.type || 'text'}" class="form-control" data-field="${field.key}" value="${safeValue}" ${field.placeholder ? `placeholder="${field.placeholder}"` : ''}>
            ${field.hint ? `<small class="text-muted">${field.hint}</small>` : ''}
        </div>
    `;
}

function handleConfigChange(node, field, rawValue) {
    let value = rawValue;
    if (field.type === 'number') value = Number(rawValue);
    if (field.parser) {
        try {
            value = field.parser(rawValue, node.config[field.key]);
        } catch (err) {
            return;
        }
    }
    node.config[field.key] = value;
}

function getNodeFields(type) {
    switch (type) {
        case 'keyword_trigger':
            return [{ key: 'keywords', label: 'Keywords', type: 'text', hint: 'Comma separated', parser: val => val.split(',').map(v => v.trim()).filter(Boolean) }];
        case 'send_text':
            return [{ key: 'text', label: 'Message', type: 'textarea', hint: 'Supports {{variables}}' }];
        case 'send_template':
            return [
                { key: 'template_id', label: 'Template', type: 'select', options: window.__templates.map(t => ({ value: t.id, label: `${t.name} (${t.language})` })) },
                { key: 'language', label: 'Language code', type: 'text', default: 'en_US' }
            ];
        case 'assign_agent':
            return [{ key: 'agent_id', label: 'Agent', type: 'select', options: [{ value: '', label: 'Select' }].concat(window.__agents.map(a => ({ value: a.id, label: a.name }))) }];
        case 'delay':
            return [{ key: 'minutes', label: 'Minutes', type: 'number', default: 10 }];
        case 'condition':
            return [
                { key: 'left', label: 'Left value', type: 'text' },
                { key: 'op', label: 'Operator', type: 'select', options: [{ value: 'eq', label: 'Equals' }, { value: 'neq', label: 'Not equals' }] },
                { key: 'right', label: 'Right value', type: 'text' }
            ];
        case 'user_input':
            return [{ key: 'key', label: 'Save input as', type: 'text', placeholder: 'variable name' }];
        case 'tag_add':
        case 'tag_remove':
            return [{ key: 'tag', label: 'Tag value', type: 'text' }];
        case 'save_variable':
            return [
                { key: 'key', label: 'Variable name', type: 'text' },
                { key: 'value', label: 'Value', type: 'text', hint: 'Use {{text}} to reference' }
            ];
        case 'api_call':
            return [
                { key: 'method', label: 'Method', type: 'select', options: [{ value: 'GET', label: 'GET' }, { value: 'POST', label: 'POST' }] },
                { key: 'url', label: 'URL', type: 'text' },
                { key: 'headers', label: 'Headers (JSON array)', type: 'textarea', parser: (val, prev) => {
                    if (!val.trim()) return [];
                    try {
                        const parsed = JSON.parse(val);
                        return Array.isArray(parsed) ? parsed : (prev || []);
                    } catch (err) {
                        return prev || [];
                    }
                } },
                { key: 'body', label: 'Body (JSON)', type: 'textarea' },
                { key: 'save_key', label: 'Save response as', type: 'text' }
            ];
        default:
            return [];
    }
}

function serializeDefinition() {
    return {
        nodes: state.nodes.map(node => ({
            id: node.id,
            type: node.type,
            label: node.label,
            x: node.x,
            y: node.y,
            config: node.config
        })),
        edges: state.edges
    };
}

function loadDefinition(definition) {
    state.nodes = (definition.nodes || []).map((node, index) => ({
        id: node.id || `node-${Date.now()}-${index}`,
        type: node.type,
        label: node.label || node.type,
        x: node.x ?? (80 + index * 240),
        y: node.y ?? (80 + index * 80),
        config: node.config || {}
    }));
    state.edges = definition.edges || [];
    state.selectedNodeId = state.nodes[0]?.id || null;
    renderNodes();
    renderInspector();
}

async function loadFlowList() {
    try {
        const res = await api('/whatsapp/flows');
        if (!res.success) throw new Error(res.message || 'Failed to load flows');
        state.flowList = res.data || [];
        renderFlowList();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function loadFlow(id) {
    try {
        const res = await api(`/whatsapp/flows/${id}`);
        if (!res.success) throw new Error(res.message || 'Unable to load flow');
        const flow = res.data;
        state.currentFlowId = flow.id;
        els.flowNameInput.value = flow.name || '';
        els.flowActiveToggle.checked = !!flow.active;
        els.cloneBtn.disabled = false;
        const definition = JSON.parse(flow.definition_json || '{}');
        loadDefinition(definition);
        renderFlowList();
        showToast(`Loaded "${flow.name}"`);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function saveFlow() {
    const payload = {
        name: els.flowNameInput.value.trim() || 'Untitled Flow',
        definition: serializeDefinition(),
        active: els.flowActiveToggle.checked ? 1 : 0
    };
    try {
        if (state.currentFlowId) {
            await api(`/whatsapp/flows/${state.currentFlowId}`, 'PUT', payload);
            showToast('Flow updated');
        } else {
            const res = await api('/whatsapp/flows', 'POST', payload);
            state.currentFlowId = res.data?.id;
            showToast('Flow created');
        }
        await loadFlowList();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function resetBuilder() {
    state.currentFlowId = null;
    state.nodes = [];
    state.edges = [];
    state.selectedNodeId = null;
    els.flowNameInput.value = '';
    els.flowActiveToggle.checked = true;
    els.cloneBtn.disabled = true;
    renderNodes();
    renderInspector();
}

function cloneFlow() {
    if (!state.currentFlowId) return;
    state.currentFlowId = null;
    els.flowNameInput.value += ' (Copy)';
    els.flowActiveToggle.checked = true;
    els.cloneBtn.disabled = true;
    showToast('Cloned flow draft. Save to persist.');
}

function autoLayout() {
    const stepX = 260;
    const stepY = 140;
    state.nodes.forEach((node, idx) => {
        node.x = 80 + (idx % 3) * stepX;
        node.y = 80 + Math.floor(idx / 3) * stepY;
    });
    renderNodes();
}

function centerCanvas() {
    document.getElementById('flowCanvas').scrollTo({ top: 0, left: 0, behavior: 'smooth' });
}

document.getElementById('saveFlowBtn').addEventListener('click', saveFlow);
document.getElementById('newFlowBtn').addEventListener('click', resetBuilder);
document.getElementById('autoLayoutBtn').addEventListener('click', autoLayout);
document.getElementById('centerCanvasBtn').addEventListener('click', centerCanvas);
els.cloneBtn.addEventListener('click', cloneFlow);
els.flowNodes.addEventListener('click', () => {
    state.selectedNodeId = null;
    state.connectingFrom = null;
    renderNodes();
    renderInspector();
});
document.getElementById('flowCanvas').addEventListener('click', () => {
    state.selectedNodeId = null;
    state.connectingFrom = null;
    renderNodes();
    renderInspector();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && state.connectingFrom) {
        state.connectingFrom = null;
        renderNodes();
    }
});

initPalette();
renderNodes();
loadFlowList();
</script>
</body>
</html>
