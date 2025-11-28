const listState = {
    templates: Array.isArray(window.__metaTemplates) ? window.__metaTemplates : []
};

document.addEventListener('DOMContentLoaded', () => {
    if (listState.templates.length) {
        renderTemplates(listState.templates);
    }
    loadTemplates();
    initializeSearchAndFilters();
    document.getElementById('templatesGrid')?.addEventListener('click', handleTemplateCardClick);
    if (window.__metaError) {
        showError(window.__metaError);
    }
});

async function loadTemplates() {
    try {
        const response = await fetch('whatsapp_templates.php?ajax=1&source=meta');
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch templates from Meta');
        }
        listState.templates = data.data || [];
        renderTemplates(listState.templates);
    } catch (error) {
        console.error('Error loading templates:', error);
        showError(error.message || 'Unable to fetch templates from Meta');
        renderTemplates([]);
    }
}

function renderTemplates(list) {
    const grid = document.getElementById('templatesGrid');
    const emptyState = document.getElementById('emptyState');
    if (!grid || !emptyState) return;

    if (!list.length) {
        grid.innerHTML = '';
        grid.style.display = 'none';
        emptyState.style.display = 'flex';
        return;
    }

    emptyState.style.display = 'none';
    grid.style.display = 'grid';
    grid.innerHTML = list.map(buildTemplateCard).join('');
}

function handleTemplateCardClick(event) {
    const trashButton = event.target.closest('.template-delete');
    if (!trashButton) return;
    event.preventDefault();
    deleteTemplate(trashButton.dataset.templateName, trashButton.dataset.templateLanguage);
}

function buildTemplateCard(template) {
    const preview = getTemplatePreviewParts(template);
    const buttonsCount = preview.buttons.length;
    const statusLabel = (template.status || 'PENDING').toUpperCase();
    const statusClass = statusLabel.toLowerCase();
    const quality = template.quality ? `<span class="badge quality">${template.quality}</span>` : '';
    const rejection = template.rejection_reason ? `<div class="rejection-note">${escapeHtml(template.rejection_reason)}</div>` : '';
    const templateNameAttr = escapeAttribute(template.name);
    const templateLangAttr = escapeAttribute(template.language || 'en_US');
    const categoryLabel = (template.category || 'UTILITY').toUpperCase();
    const languageLabel = (template.language || 'en_US').toUpperCase();

    return `
        <article class="template-card" data-name="${templateNameAttr}">
            <header class="template-card-head">
                <div>
                    <p class="template-card-title">${escapeHtml(template.name)}</p>
                    <div class="badge-group">
                        <span class="badge status-${statusClass}">${statusLabel}</span>
                        ${quality}
                    </div>
                </div>
                <div class="template-card-actions">
                    <span class="template-type-chip">${escapeHtml(template.type || categoryLabel)}</span>
                    <button type="button" class="ghost-button template-delete" data-template-name="${templateNameAttr}" data-template-language="${templateLangAttr}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </header>

            <section class="template-card-preview">
                <div class="preview-phone">
                    <div class="preview-phone-header">
                        <span class="preview-dot"></span>
                        WhatsApp Preview
                    </div>
                    <div class="preview-bubble">
                        ${preview.header ? `<p class="preview-header-line">${preview.header}</p>` : ''}
                        <p class="preview-body-text">${preview.body}</p>
                        ${preview.footer ? `<p class="preview-footer-line">${preview.footer}</p>` : ''}
                        ${buttonsCount ? `
                            <div class="preview-button-group">
                                ${preview.buttons.map(btn => `<span class="preview-button-chip">${btn}</span>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            </section>

            <footer class="template-card-meta">
                <span><i class="fas fa-tag"></i>${categoryLabel}</span>
                <span><i class="fas fa-language"></i>${languageLabel}</span>
                <span><i class="fas fa-hand-pointer"></i>${buttonsCount || 0} Action${buttonsCount === 1 ? '' : 's'}</span>
                <span><i class="fas fa-clock"></i>${formatTimestamp(template.last_updated)}</span>
            </footer>
            ${rejection}
        </article>
    `;
}

function getTemplatePreviewParts(template) {
    const components = template?.components;
    const componentsArray = Array.isArray(components)
        ? components
        : Array.isArray(components?.raw)
            ? components.raw
            : null;

    if (componentsArray) {
        const headerComponent = componentsArray.find(c => (c.type || '').toUpperCase() === 'HEADER');
        const bodyComponent = componentsArray.find(c => (c.type || '').toUpperCase() === 'BODY');
        const footerComponent = componentsArray.find(c => (c.type || '').toUpperCase() === 'FOOTER');
        const buttonComponent = componentsArray.find(c => (c.type || '').toUpperCase() === 'BUTTONS');
        const headerHtml = headerComponent?.text
            ? escapeHtml(headerComponent.text)
            : headerComponent?.format && headerComponent.format !== 'TEXT'
                ? `<i class="fas fa-${getMediaIcon(headerComponent.format.toLowerCase())} me-1"></i>${headerComponent.format} header`
                : '';
        return {
            header: headerHtml,
            body: escapeHtml(bodyComponent?.text || ''),
            footer: escapeHtml(footerComponent?.text || ''),
            buttons: (buttonComponent?.buttons || []).map(btn => escapeHtml(btn.text || btn.type))
        };
    }
    if (components && typeof components === 'object') {
        const headerComponent = components.header;
        const bodyComponent = components.body;
        const footerComponent = components.footer;
        const buttonComponent = components.buttons;
        const headerHtml = headerComponent?.text
            ? escapeHtml(headerComponent.text)
            : headerComponent?.type && headerComponent.type !== 'none'
                ? `<i class="fas fa-${getMediaIcon(headerComponent.type)} me-1"></i>${headerComponent.type.toUpperCase()} header`
                : '';
        return {
            header: headerHtml,
            body: escapeHtml(bodyComponent?.text || ''),
            footer: escapeHtml(footerComponent?.text || ''),
            buttons: (buttonComponent || []).map(btn => escapeHtml(btn.text || btn.type))
        };
    }

    const fallbackButtons = parseButtons(template.buttons);
    let headerText = '';
    if (template.header_text) {
        headerText = escapeHtml(template.header_text);
    } else if (template.header_media_type && template.header_media_type !== 'none') {
        headerText = `<i class="fas fa-${getMediaIcon(template.header_media_type)} me-1"></i>${template.header_media_type.toUpperCase()} header`;
    }

    return {
        header: headerText,
        body: escapeHtml(template.message || ''),
        footer: escapeHtml(template.footer_text || ''),
        buttons: fallbackButtons.map(btn => escapeHtml(btn.text || btn.type || 'Action'))
    };
}

function parseButtons(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw;
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}

function formatTimestamp(timestamp) {
    if (!timestamp) return 'Not synced';
    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) return 'Not synced';
    return `${date.toLocaleDateString()} · ${date.toLocaleTimeString()}`;
}

function initializeSearchAndFilters() {
    document.getElementById('searchInput')?.addEventListener('input', filterTemplates);
    document.getElementById('statusFilter')?.addEventListener('change', filterTemplates);
    document.getElementById('categoryFilter')?.addEventListener('change', filterTemplates);
}

function filterTemplates() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();

    const filtered = listState.templates.filter(template => {
        const bodyText = template?.components?.body?.text || template.message || '';
        const matchesSearch =
            !searchTerm ||
            template.name.toLowerCase().includes(searchTerm) ||
            bodyText.toLowerCase().includes(searchTerm);

        const matchesStatus = !statusFilter || (template.status || '').toLowerCase() === statusFilter;
        const matchesCategory = !categoryFilter || (template.category || '').toLowerCase() === categoryFilter;

        return matchesSearch && matchesStatus && matchesCategory;
    });

    renderTemplates(filtered);
}

async function deleteTemplate(name, language) {
    if (!confirm('Delete this template from Meta?')) return;
    try {
        const response = await fetch('whatsapp_templates.php?ajax=1&source=meta', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', name, language })
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Meta API error');
        }
        showSuccess('Template deleted successfully');
        await loadTemplates();
    } catch (error) {
        console.error('Delete error:', error);
        showError(error.message || 'Failed to delete template');
    }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function escapeAttribute(text) {
    return escapeHtml(text || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function getMediaIcon(type) {
    const icons = {
        image: 'image',
        video: 'video',
        document: 'file',
        pdf: 'file-pdf',
        audio: 'music',
        text: 'font'
    };
    return icons[type] || 'file';
}

function showSuccess(message) {
    alert(message);
}

function showError(message) {
    alert(message);
}

