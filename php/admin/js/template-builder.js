const builderState = {
    interactiveButtons: [],
    actionMode: 'none',
    buttonId: 0,
    placeholderValues: {},
    cards: [],
    cardId: 0
};

const BUILDER_LIMITS = {
    quick_reply: 10,
    url: 2,
    phone: 1,
    copy_code: 1
};

let currentCardUploadId = null;

document.addEventListener('DOMContentLoaded', () => {
    registerBuilderListeners();
    resetBuilderForm();
    updateBuilderPreview();
    if (window.__metaError) {
        showBuilderError(window.__metaError);
    }
});

function registerBuilderListeners() {
    ['templateMessage', 'footerText', 'headerText'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            if (id === 'templateMessage') {
                document.getElementById('bodyCounter').textContent = `${document.getElementById(id).value.length} / 1024`;
            }
            updatePlaceholderValues();
            updateBuilderPreview();
        });
    });
    document.getElementById('templateCategory')?.addEventListener('change', updateBuilderPreview);
    document.getElementById('templateLanguage')?.addEventListener('change', updateBuilderPreview);
    document.getElementById('templateType')?.addEventListener('change', handleTemplateTypeChange);
    document.querySelectorAll('input[name="actionMode"]').forEach(radio => {
        radio.addEventListener('change', handleActionModeChange);
    });
    document.getElementById('uploadHeaderMediaBtn')?.addEventListener('click', () => {
        document.getElementById('headerMediaFile').click();
    });
    document.getElementById('headerMediaFile')?.addEventListener('change', handleHeaderMediaUpload);
    document.getElementById('addCarouselCardBtn')?.addEventListener('click', addCarouselCard);
    document.getElementById('carouselMediaInput')?.addEventListener('change', handleCarouselMediaUpload);
}

function resetBuilderForm() {
    document.getElementById('templateCategory').value = 'UTILITY';
    document.getElementById('templateLanguage').value = 'en_US';
    document.getElementById('templateName').value = '';
    document.getElementById('templateType').value = 'TEXT';
    document.getElementById('headerText').value = '';
    document.getElementById('headerMediaUrl').value = '';
    document.getElementById('templateMessage').value = '';
    document.getElementById('footerText').value = '';
    document.getElementById('bodyCounter').textContent = '0 / 1024';
    builderState.interactiveButtons = [];
    builderState.actionMode = 'none';
    builderState.buttonId = 0;
    builderState.placeholderValues = {};
    builderState.cards = [];
    builderState.cardId = 0;
    document.querySelector('input[name="actionMode"][value="none"]').checked = true;
    renderInteractiveList();
    handleTemplateTypeChange();
    updatePlaceholderValues();
    renderCarouselCards();
}

function handleTemplateTypeChange() {
    const type = document.getElementById('templateType').value;
    const headerTextWrapper = document.getElementById('headerTextWrapper');
    const headerMediaWrapper = document.getElementById('headerMediaWrapper');
    const carouselBuilder = document.getElementById('carouselBuilder');
    if (type === 'TEXT') {
        headerTextWrapper.style.display = 'block';
        headerMediaWrapper.style.display = 'none';
        carouselBuilder.style.display = 'none';
    } else if (type === 'CAROUSEL') {
        headerTextWrapper.style.display = 'none';
        headerMediaWrapper.style.display = 'none';
        carouselBuilder.style.display = 'block';
        if (!builderState.cards.length) {
            addCarouselCard();
        } else {
            renderCarouselCards();
        }
    } else {
        headerTextWrapper.style.display = 'none';
        headerMediaWrapper.style.display = 'block';
        carouselBuilder.style.display = 'none';
    }
    updateBuilderPreview();
}

function handleActionModeChange(event) {
    builderState.actionMode = event.target.value;
}

function addInteractiveAction(type) {
    if (!ensureActionModeAllows(type)) {
        return;
    }
    const currentCount = builderState.interactiveButtons.filter(btn => btn.type === type).length;
    if (currentCount >= (BUILDER_LIMITS[type] || 0)) {
        showBuilderError(`Limit reached for ${type.replace('_', ' ')}`);
        return;
    }
    builderState.buttonId += 1;
    builderState.interactiveButtons.push({
        id: builderState.buttonId,
        type,
        text: '',
        value: ''
    });
    renderInteractiveList();
    updateBuilderPreview();
}

function ensureActionModeAllows(type) {
    if (builderState.actionMode === 'none') {
        showBuilderError('Select an Interactive Action mode first');
        return false;
    }
    if (builderState.actionMode === 'quick' && type !== 'quick_reply') {
        showBuilderError('Quick Reply mode only allows quick replies');
        return false;
    }
    if (builderState.actionMode === 'cta' && type === 'quick_reply') {
        showBuilderError('Call-to-Action mode cannot include quick replies');
        return false;
    }
    return true;
}

function renderInteractiveList() {
    const container = document.getElementById('interactiveList');
    if (!container) return;
    if (!builderState.interactiveButtons.length) {
        container.innerHTML = '<p class="interactive-empty">No interactive actions selected yet.</p>';
    } else {
        container.innerHTML = builderState.interactiveButtons.map(renderInteractiveItem).join('');
    }
    updateInteractiveCounts();
}

function renderInteractiveItem(button) {
    const label = {
        quick_reply: 'Quick Reply',
        url: 'URL Button',
        phone: 'Phone Button',
        copy_code: 'Copy Code'
    }[button.type] || 'Button';

    const valuePlaceholder = {
        quick_reply: 'Payload (optional)',
        url: 'https://example.com',
        phone: '+919876543210',
        copy_code: 'PROMO2025'
    }[button.type] || '';

    const valueLabel = {
        quick_reply: 'Payload',
        url: 'URL',
        phone: 'Phone Number',
        copy_code: 'Code'
    }[button.type] || 'Value';

    return `
        <div class="interactive-item">
            <div class="interactive-item-header">
                <span>${label}</span>
                <button type="button" class="btn-remove" onclick="removeInteractiveAction(${button.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="interactive-item-body">
                <input type="text" class="form-input" placeholder="Button text" value="${escapeBuilderAttr(button.text)}"
                    oninput="handleInteractiveInput(${button.id}, 'text', this.value)" maxlength="20">
                <input type="text" class="form-input" placeholder="${valuePlaceholder}" value="${escapeBuilderAttr(button.value)}"
                    oninput="handleInteractiveInput(${button.id}, 'value', this.value)">
                <small>${valueLabel}</small>
            </div>
        </div>
    `;
}

function handleInteractiveInput(id, field, value) {
    const button = builderState.interactiveButtons.find(btn => btn.id === id);
    if (!button) return;
    button[field] = value;
    updateBuilderPreview();
}

function removeInteractiveAction(id) {
    builderState.interactiveButtons = builderState.interactiveButtons.filter(btn => btn.id !== id);
    renderInteractiveList();
    updateBuilderPreview();
}

function updateInteractiveCounts() {
    document.getElementById('quickReplyCount').textContent = `${countButtons('quick_reply')}/${BUILDER_LIMITS.quick_reply}`;
    document.getElementById('urlCount').textContent = `${countButtons('url')}/${BUILDER_LIMITS.url}`;
    document.getElementById('phoneCount').textContent = `${countButtons('phone')}/${BUILDER_LIMITS.phone}`;
    document.getElementById('copyCount').textContent = `${countButtons('copy_code')}/${BUILDER_LIMITS.copy_code}`;
}

function countButtons(type) {
    return builderState.interactiveButtons.filter(btn => btn.type === type).length;
}

function updatePlaceholderValues() {
    const fields = [
        document.getElementById('headerText')?.value || '',
        document.getElementById('templateMessage')?.value || '',
        document.getElementById('footerText')?.value || ''
    ];
    const matches = new Set();
    fields.forEach(text => {
        (text.match(/\{\{\s*([^}]+)\s*\}\}/g) || []).forEach(token => {
            matches.add(token.replace('{{', '').replace('}}', '').trim());
        });
    });
    const keys = Array.from(matches);
    Object.keys(builderState.placeholderValues).forEach(key => {
        if (!keys.includes(key)) {
            delete builderState.placeholderValues[key];
        }
    });
    renderPlaceholderValues(keys);
}

function renderPlaceholderValues(keys) {
    const container = document.getElementById('placeholderValues');
    if (!container) return;
    if (!keys.length) {
        container.innerHTML = '<div class="text-muted small">Start typing {{placeholders}} in your template body.</div>';
        return;
    }
    container.innerHTML = keys.map(key => {
        const value = builderState.placeholderValues[key] || '';
        return `
            <div class="placeholder-row">
                <label>{{${escapeBuilderHtml(key)}}}</label>
                <input type="text" class="form-input" value="${escapeBuilderAttr(value)}"
                    oninput="handlePlaceholderInput('${escapeBuilderAttr(key)}', this.value)" placeholder="Sample value for Meta">
            </div>
        `;
    }).join('');
}

function handlePlaceholderInput(key, value) {
    builderState.placeholderValues[key] = value;
}

function addCarouselCard() {
    if (builderState.cards.length >= 10) {
        alert('Carousel supports up to 10 cards.');
        return;
    }
    builderState.cardId += 1;
    builderState.cards.push({
        id: builderState.cardId,
        title: '',
        body: '',
        media_url: '',
        button_text: '',
        button_url: ''
    });
    renderCarouselCards();
}

function removeCarouselCard(id) {
    builderState.cards = builderState.cards.filter(card => card.id !== id);
    renderCarouselCards();
}

function handleCardInput(id, field, value) {
    const card = builderState.cards.find(c => c.id === id);
    if (!card) return;
    card[field] = value;
}

function renderCarouselCards() {
    const list = document.getElementById('carouselCardList');
    if (!list) return;
    if (!builderState.cards.length) {
        list.innerHTML = '<p class="text-muted">Add cards to start building your carousel.</p>';
        return;
    }
    list.innerHTML = builderState.cards.map((card, index) => `
        <div class="carousel-card">
            <div class="carousel-card-header">
                <strong>Card ${index + 1}</strong>
                <button type="button" class="btn btn-sm btn-link text-danger" onclick="removeCarouselCard(${card.id})">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            <input type="text" class="form-input mb-2" placeholder="Card title" value="${escapeBuilderAttr(card.title)}"
                oninput="handleCardInput(${card.id}, 'title', this.value)">
            <textarea class="form-input textarea mb-2" rows="2" placeholder="Card description" oninput="handleCardInput(${card.id}, 'body', this.value)">${escapeBuilderAttr(card.body)}</textarea>
            <div class="input-group mb-2">
                <input type="text" class="form-input" placeholder="Image URL" value="${escapeBuilderAttr(card.media_url || '')}" oninput="handleCardInput(${card.id}, 'media_url', this.value)">
                <button type="button" class="btn btn-outline-primary" onclick="uploadCardMedia(${card.id})">
                    <i class="fas fa-cloud-upload-alt"></i>
                </button>
            </div>
            <div class="input-group">
                <input type="text" class="form-input" placeholder="Button text (optional)" value="${escapeBuilderAttr(card.button_text || '')}" oninput="handleCardInput(${card.id}, 'button_text', this.value)">
                <input type="text" class="form-input" placeholder="Button URL" value="${escapeBuilderAttr(card.button_url || '')}" oninput="handleCardInput(${card.id}, 'button_url', this.value)">
            </div>
        </div>
    `).join('');
}

function uploadCardMedia(cardId) {
    currentCardUploadId = cardId;
    document.getElementById('carouselMediaInput')?.click();
}

async function handleCarouselMediaUpload(event) {
    const file = event.target.files?.[0];
    if (!file || !currentCardUploadId) return;
    try {
        const data = await uploadTemplateMedia(file);
        handleCardInput(currentCardUploadId, 'media_url', data.url);
        renderCarouselCards();
    } catch (error) {
        alert(error.message || 'Failed to upload media for card');
    } finally {
        event.target.value = '';
        currentCardUploadId = null;
    }
}

function insertVariable(fieldId, variable) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    const start = field.selectionStart;
    const end = field.selectionEnd;
    const text = field.value;
    field.value = text.slice(0, start) + variable + text.slice(end);
    field.focus();
    field.setSelectionRange(start + variable.length, start + variable.length);
    if (fieldId === 'templateMessage') {
        document.getElementById('bodyCounter').textContent = `${field.value.length} / 1024`;
    }
    updateBuilderPreview();
}

function updateBuilderPreview() {
    const category = document.getElementById('templateCategory').value;
    const language = document.getElementById('templateLanguage').value;
    const templateType = document.getElementById('templateType').value;
    const headerText = document.getElementById('headerText').value;
    const message = document.getElementById('templateMessage').value;
    const footer = document.getElementById('footerText').value;

    document.getElementById('previewStatus').textContent = `${category} · ${language}`;
    document.getElementById('previewType').textContent = `${templateType} TEMPLATE`;

    const headerEl = document.getElementById('previewHeader');
    const bodyEl = document.getElementById('previewBody');
    const footerEl = document.getElementById('previewFooter');
    const buttonsEl = document.getElementById('previewButtons');

    if (templateType === 'TEXT' && headerText) {
        headerEl.style.display = 'block';
        headerEl.textContent = headerText;
    } else if (templateType === 'CAROUSEL') {
        headerEl.style.display = 'block';
        headerEl.innerHTML = `<i class="fas fa-images me-2"></i>${builderState.cards.length || 0} carousel card(s)`;
    } else if (templateType !== 'TEXT') {
        headerEl.style.display = 'block';
        headerEl.innerHTML = `<i class="fas fa-${getMediaIcon(templateType.toLowerCase())} me-2"></i>${templateType} HEADER`;
    } else {
        headerEl.style.display = 'none';
        headerEl.textContent = '';
    }

    bodyEl.textContent = message || 'Start typing to see the preview...';

    if (footer) {
        footerEl.style.display = 'block';
        footerEl.textContent = footer;
    } else {
        footerEl.style.display = 'none';
        footerEl.textContent = '';
    }

    buttonsEl.innerHTML = builderState.interactiveButtons
        .filter(btn => btn.text)
        .map(btn => `<span class="preview-button-chip">${escapeBuilderHtml(btn.text)}</span>`)
        .join('');
}

async function saveTemplate() {
    const name = document.getElementById('templateName').value.trim();
    const message = document.getElementById('templateMessage').value.trim();
    const templateType = document.getElementById('templateType').value;
    const headerText = document.getElementById('headerText').value.trim();
    const headerMediaUrl = document.getElementById('headerMediaUrl').value.trim();
    const footerText = document.getElementById('footerText').value.trim();

    if (!name || !message) {
        showBuilderError('Template name and body are required');
        return;
    }
    if (!/^[a-z0-9_]+$/.test(name)) {
        showBuilderError('Template name must be lowercase with underscores only');
        return;
    }
    // Allow carousel templates via cards payload
    if (['IMAGE', 'VIDEO', 'DOCUMENT'].includes(templateType) && !headerMediaUrl) {
        showBuilderError('Provide a publicly accessible media URL for media templates');
        return;
    }

    const buttons = builderState.interactiveButtons.map(({ type, text, value }) => ({ type, text, value }));
    const payload = {
        name,
        message,
        category: document.getElementById('templateCategory').value,
        language: document.getElementById('templateLanguage').value,
        template_type: templateType,
        header_text: templateType === 'TEXT' ? headerText : '',
        header_media_url: ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(templateType) ? headerMediaUrl : '',
        footer_text: footerText,
        buttons,
        placeholders: builderState.placeholderValues
    };
    if (templateType === 'CAROUSEL') {
        if (!builderState.cards.length) {
            showBuilderError('Add at least one card for carousel templates');
            return;
        }
        payload.cards = builderState.cards;
    }

    setBuilderLoading(true);
    try {
        const response = await fetch('whatsapp_templates.php?ajax=1&source=meta', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', data: payload })
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Meta API rejected the template');
        }
        showBuilderSuccess('Template submitted to Meta for approval');
        resetBuilderForm();
    } catch (error) {
        console.error('Error creating template:', error);
        showBuilderError(error.message || 'Failed to create template');
    } finally {
        setBuilderLoading(false);
    }
}

function setBuilderLoading(isLoading) {
    const button = document.querySelector('.builder-actions .btn-primary');
    if (!button) return;
    button.disabled = isLoading;
    button.innerHTML = isLoading
        ? '<span class="spinner-border spinner-border-sm"></span> Submitting...'
        : '<i class="fas fa-save me-2"></i>Submit to Meta';
}

function getMediaIcon(type) {
    const icons = {
        image: 'image',
        video: 'video',
        document: 'file',
        pdf: 'file-pdf',
        text: 'font'
    };
    return icons[type] || 'file';
}

function escapeBuilderHtml(text) {
    if (text === null || text === undefined) return '';
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function escapeBuilderAttr(text) {
    return escapeBuilderHtml(text || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showBuilderSuccess(message) {
    alert(message);
}

function showBuilderError(message) {
    alert(message);
}

async function handleHeaderMediaUpload(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const btn = document.getElementById('uploadHeaderMediaBtn');
    if (!btn) return;
    const original = btn.innerHTML;
    try {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        const data = await uploadTemplateMedia(file);
        document.getElementById('headerMediaUrl').value = data.url;
        const hint = document.getElementById('headerMediaHint');
        hint.textContent = `Uploaded ${file.name} (${data.media_type || file.type})`;
        updateBuilderPreview();
    } catch (error) {
        showBuilderError(error.message || 'Failed to upload media');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt me-1"></i>Upload';
        event.target.value = '';
    }
}

async function uploadTemplateMedia(file) {
    const data = new FormData();
    data.append('action', 'upload_media');
    data.append('file', file);
    const response = await fetch('whatsapp_api.php', {
        method: 'POST',
        body: data
    });
    const result = await response.json();
    if (!result.success) {
        throw new Error(result.message || 'Upload failed');
    }
    return result.data;
}

