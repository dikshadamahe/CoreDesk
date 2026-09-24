/**
 * CoreDesk — Web Helpdesk & Support Ticketing System
 * Pure Vanilla JavaScript Client Logic (Zero external dependencies/frameworks)
 */

document.addEventListener('DOMContentLoaded', () => {
    initQuickStatusHandlers();
    initReplyForm();
    initSearchFilter();
    initLiveMetrics();
});

/**
 * Helper: Display floating toast alerts
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : 'success'}`;
    toast.style.cssText = 'min-width:280px; box-shadow:0 8px 24px rgba(0,0,0,0.15); animation:fadeIn 0.3s ease;';
    toast.innerHTML = `<strong>${type === 'error' ? 'Notice:' : 'Success:'}</strong> ${escapeHtml(message)}`;
    
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s ease';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

/**
 * XSS escaping utility
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * AJAX Ticket Status Updater (Inline from tables or detail headers)
 */
function initQuickStatusHandlers() {
    const statusSelects = document.querySelectorAll('.js-status-select');
    statusSelects.forEach(select => {
        select.addEventListener('change', async (e) => {
            const ticketId = select.dataset.ticketId;
            const newStatus = select.value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            select.disabled = true;
            try {
                const response = await fetch('api/update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ticket_id: parseInt(ticketId, 10),
                        status: newStatus,
                        new_status: newStatus,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    showToast(`Ticket status updated to ${newStatus}`);
                    // Update badge color in DOM if applicable
                    const badge = document.querySelector(`.js-status-badge-${ticketId}`);
                    if (badge) {
                        badge.className = `badge badge-${newStatus.toLowerCase().replace('-', '')} js-status-badge-${ticketId}`;
                        badge.textContent = newStatus;
                    }
                    refreshMetrics();
                } else {
                    showToast(data.error || 'Failed to update ticket status', 'error');
                }
            } catch (err) {
                console.error('Status update failed:', err);
                showToast('Network error while updating ticket status', 'error');
            } finally {
                select.disabled = false;
            }
        });
    });
}

/**
 * Ticket Reply Form Handler (Asynchronous Fetch submission)
 */
function initReplyForm() {
    const replyForm = document.getElementById('ticket-reply-form');
    if (!replyForm) return;

    replyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = replyForm.querySelector('button[type="submit"]');
        const messageInput = document.getElementById('reply-message');
        const internalCheckbox = document.getElementById('is-internal-note');
        const ticketIdInput = document.getElementById('ticket-id');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const message = messageInput.value.trim();
        if (!message) {
            showToast('Please type a reply message.', 'error');
            return;
        }

        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Posting...';

        try {
            const payload = {
                ticket_id: parseInt(ticketIdInput.value, 10),
                message: message,
                is_internal_note: internalCheckbox ? (internalCheckbox.checked ? 1 : 0) : 0,
                csrf_token: csrfToken
            };

            const res = await fetch('api/add_reply.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast('Reply published successfully');
                messageInput.value = '';
                if (internalCheckbox) internalCheckbox.checked = false;

                // Append new reply directly into DOM thread
                appendReplyToThread(data.reply);
            } else {
                showToast(data.error || 'Could not post reply', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Network error while posting reply', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

function appendReplyToThread(reply) {
    const threadContainer = document.getElementById('ticket-replies-list');
    if (!threadContainer) return;

    const div = document.createElement('div');
    const isInternal = reply.is_internal_note == 1;
    div.className = `card reply-card ${isInternal ? 'internal-note' : ''}`;
    div.style.marginBottom = '16px';
    div.innerHTML = `
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <strong>${escapeHtml(reply.user_name || 'You')}</strong>
                <span class="badge badge-neutral" style="font-size:11px;">${escapeHtml(reply.user_role || 'Agent')}</span>
                ${isInternal ? '<span class="badge badge-warning" style="font-size:11px;">🔒 Internal Note</span>' : ''}
            </div>
            <span class="text-muted" style="font-size:12px;">Just now</span>
        </div>
        <div class="card-body">
            <p style="white-space:pre-wrap; margin:0;">${escapeHtml(reply.message)}</p>
        </div>
    `;
    threadContainer.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Real-time Client-Side Search Filtering on Table
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
 * Fetch Live Dashboard Turnaround Metrics
 */
function initLiveMetrics() {
    const metricsContainer = document.getElementById('dashboard-metrics');
    if (!metricsContainer) return;
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
        console.warn('Metrics polling error', e);
    }
}
