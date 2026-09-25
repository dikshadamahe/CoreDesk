/**
 * CoreDesk — Enterprise Service Management Client Engine
 * Pure Vanilla JavaScript ES6+ (Zero external dependencies)
 * Features: Fetch API, Keyboard Shortcuts (Cmd+K), Real-time Filter, Toast Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    initKeyboardShortcuts();
    initQuickStatusHandlers();
    initReplyForm();
    initSearchFilter();
    initLiveMetrics();
});

/**
 * Enterprise Toast Notification Engine
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-alert toast-${type === 'error' ? 'error' : 'success'}`;
    const label = type === 'error' ? 'Notice:' : 'Success:';
    toast.innerHTML = `<span style="font-weight:700;">${label}</span> <span>${escapeHtml(message)}</span>`;
    
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/**
 * XSS escaping helper
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Global Keyboard Shortcut: Cmd/Ctrl + K focuses search bar
 */
function initKeyboardShortcuts() {
    window.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            const searchBar = document.getElementById('global-search-bar') || document.getElementById('table-search-input');
            if (searchBar) {
                searchBar.focus();
                searchBar.select();
            }
        }
    });

    const globalSearch = document.getElementById('global-search-bar');
    if (globalSearch) {
        globalSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const query = globalSearch.value.trim();
                window.location.href = `tickets.php?q=${encodeURIComponent(query)}`;
            }
        });
    }
}

/**
 * AJAX Ticket Status Transition Handler
 */
function initQuickStatusHandlers() {
    const statusSelects = document.querySelectorAll('.js-status-select');
    statusSelects.forEach(select => {
        select.addEventListener('change', async () => {
            const ticketId = select.dataset.ticketId;
            const newStatus = select.value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            select.disabled = true;
            try {
                const response = await fetch('api/update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ticket_id: parseInt(ticketId, 10),
                        status: newStatus,
                        new_status: newStatus,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    showToast(`Status updated to ${newStatus}`);
                    
                    // Update Lozenge in DOM if present
                    const lozenge = document.querySelector(`.js-status-badge-${ticketId}`);
                    if (lozenge) {
                        const cleanStatus = newStatus.toLowerCase().replace('-', '');
                        lozenge.className = `lozenge lozenge-${cleanStatus} js-status-badge-${ticketId}`;
                        lozenge.textContent = newStatus.toUpperCase();
                    }
                    refreshMetrics();
                } else {
                    showToast(data.error || 'Failed to update status', 'error');
                }
            } catch (err) {
                console.error('Status transition error:', err);
                showToast('Network error while updating incident status', 'error');
            } finally {
                select.disabled = false;
            }
        });
    });
}

/**
 * Asynchronous Ticket Reply & Internal Note Submission
 */
function initReplyForm() {
    const replyForm = document.getElementById('ticket-reply-form');
    if (!replyForm) return;

    replyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('reply-submit-btn') || replyForm.querySelector('button[type="submit"]');
        const messageInput = document.getElementById('reply-message');
        const internalCheckbox = document.getElementById('is-internal-note');
        const ticketIdInput = document.getElementById('ticket-id');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const message = messageInput.value.trim();
        if (!message) {
            showToast('Please type a response before submitting.', 'error');
            return;
        }

        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Posting...';

        try {
            const isInternal = internalCheckbox && internalCheckbox.checked ? 1 : 0;
            const payload = {
                ticket_id: parseInt(ticketIdInput.value, 10),
                message: message,
                is_internal_note: isInternal,
                csrf_token: csrfToken
            };

            const res = await fetch('api/add_reply.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(isInternal ? 'Private staff note saved' : 'Response dispatched to customer');
                messageInput.value = '';

                // Append newly authored reply directly to timeline
                appendReplyToThread(data.reply);
            } else {
                showToast(data.error || 'Could not post response', 'error');
            }
        } catch (err) {
            console.error('Reply dispatch failure:', err);
            showToast('Network error while posting response', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

function appendReplyToThread(reply) {
    const threadContainer = document.getElementById('ticket-replies-list');
    if (!threadContainer) return;

    const isInternal = (reply.is_internal_note == 1);
    const initial = (reply.user_name || 'You').charAt(0).toUpperCase();
    const avatarBg = reply.user_role === 'admin' ? '#0747A6' : (reply.user_role === 'agent' ? '#0052CC' : '#403294');

    const card = document.createElement('div');
    card.className = `timeline-message-card ${isInternal ? 'internal-note' : ''}`;
    card.innerHTML = `
        <div class="message-card-header">
            <div class="message-author-box">
                <div class="user-pill-avatar" style="background: ${avatarBg}; width: 26px; height: 26px; font-size: 11px;">
                    ${initial}
                </div>
                <div>
                    <strong style="font-size: 13px; color: var(--text-heading);">${escapeHtml(reply.user_name || 'You')}</strong>
                    <span class="lozenge lozenge-inprogress" style="font-size: 9px; padding: 1px 5px; margin-left: 4px;">
                        ${escapeHtml((reply.user_role || 'Agent').toUpperCase())}
                    </span>
                    ${isInternal ? '<span class="status-pill status-inprogress" style="font-size: 9.5px; padding: 1px 6px; margin-left: 4px;">Private Staff Note</span>' : ''}
                </div>
            </div>
            <span style="font-size: 11.5px; color: var(--text-muted);">Just now</span>
        </div>
        <div class="message-card-body">
            ${escapeHtml(reply.message)}
        </div>
    `;

    threadContainer.appendChild(card);
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Real-time Client-Side Search Filtering on Queue Table
 */
function initSearchFilter() {
    const searchInput = document.getElementById('table-search-input');
    if (!searchInput) return;

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.js-ticket-row');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
}

/**
 * Live KPI Polling
 */
function initLiveMetrics() {
    const openEl = document.getElementById('metric-open');
    if (!openEl) return;
    refreshMetrics();
}

async function refreshMetrics() {
    try {
        const res = await fetch('api/metrics.php');
        if (!res.ok) return;
        const metrics = await res.json();

        const openEl = document.getElementById('metric-open');
        const inProgressEl = document.getElementById('metric-inprogress');
        const resolvedEl = document.getElementById('metric-resolved');
        const criticalEl = document.getElementById('metric-critical');

        if (openEl) openEl.textContent = metrics.open_count ?? 0;
        if (inProgressEl) inProgressEl.textContent = metrics.inprogress_count ?? 0;
        if (resolvedEl) resolvedEl.textContent = metrics.resolved_count ?? 0;
        if (criticalEl) criticalEl.textContent = metrics.critical_count ?? 0;
    } catch (e) {
        console.warn('Metrics refresh failed', e);
    }
}
