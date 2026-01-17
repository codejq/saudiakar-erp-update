<?php
// Messages inbox page
$sc_title = "صندوق الرسائل";
$sc_id = "72";
$canscroll = 1;

include_once("../../nocash.hnt");
include("../../header.hnt");
require_once("setup.php");
require_once("ApiKey.php");
// API Configuration
$API_BASE_URL = 'https://api.saudiakar.net';

// Read API key
$api_key = getApiKey();
 
// Get current user ID
$user_query = "SELECT id FROM users WHERE status = 'ACTIVE' LIMIT 1";
$user_result = mysql_query($user_query, $offers_link);
$current_user_id = 0;
if ($user_result && mysql_num_rows($user_result) > 0) {
    $user_row = mysql_fetch_assoc($user_result);
    $current_user_id = intval($user_row['id']);
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="bi bi-envelope me-2"></i>
                    صندوق الرسائل
                </h2>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right me-2"></i>
                    رجوع للقائمة
                </a>
            </div>
        </div>
    </div>

    <!-- Messages Container -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-inbox me-2"></i>
                            الرسائل الواردة
                        </h5>
                        <span class="badge bg-light text-primary" id="totalMessages">0 رسالة</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Loading State -->
                    <div id="loadingState" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="mt-3 text-muted">جاري تحميل الرسائل...</p>
                    </div>

                    <!-- Messages List -->
                    <div id="messagesList" style="display: none;">
                        <!-- Messages will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="replyModalLabel">الرد على الرسالة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Original Message Display -->
                <div class="card mb-3 bg-light">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">الرسالة الأصلية:</h6>
                        <p class="card-text" id="originalMessage"></p>
                        <small class="text-muted">من: <span id="originalSender"></span></small>
                    </div>
                </div>

                <!-- Reply Form -->
                <form id="replyForm">
                    <input type="hidden" id="replyToSenderId" value="">
                    <input type="hidden" id="replyToOfferId" value="">
                    <input type="hidden" id="originalMessageId" value="">

                    <div class="mb-3">
                        <label for="replySubject" class="form-label">الموضوع</label>
                        <input type="text" class="form-control" id="replySubject" required>
                    </div>

                    <div class="mb-3">
                        <label for="replyMessage" class="form-label">الرسالة</label>
                        <textarea class="form-control" id="replyMessage" rows="6" required></textarea>
                    </div>

                    <div id="replyAlert" class="alert" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="sendReplyBtn" onclick="sendReply()">
                    <i class="bi bi-send me-2"></i>
                    إرسال الرد
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast notification container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="notificationToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
// API Configuration
const API_BASE_URL = '<?php echo $API_BASE_URL; ?>';
const API_KEY = '<?php echo $api_key; ?>';
const CURRENT_USER_ID = <?php echo $current_user_id; ?>;

let messagesData = [];
let replyModal = null;

/**
 * Show toast notification
 */
const showToast = (message, type = 'success') => {
    const toastEl = document.getElementById('notificationToast');
    const toastBody = document.getElementById('toastMessage');

    toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning', 'text-bg-info');
    const typeClass = type === 'error' ? 'text-bg-danger' : `text-bg-${type}`;
    toastEl.classList.add(typeClass);

    toastBody.textContent = message;

    const toast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: 3000
    });
    toast.show();
};

/**
 * Show alert in reply modal
 */
const showReplyAlert = (message, type) => {
    const alertDiv = document.getElementById('replyAlert');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.display = 'block';

    setTimeout(() => {
        alertDiv.style.display = 'none';
    }, 5000);
};

/**
 * Fetch messages from backend
 */
const fetchMessages = async () => {
    try {
        const response = await fetch('offers_backend.php?action=get_messages');
        const result = await response.json();

        if (result.success) {
            messagesData = result.data;
            renderMessages(result.data);
        } else {
            showToast(result.message, 'error');
            renderEmptyState();
        }
    } catch (error) {
        console.error('Error fetching messages:', error);
        showToast('حدث خطأ أثناء تحميل الرسائل', 'error');
        renderEmptyState();
    } finally {
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('messagesList').style.display = 'block';
    }
};

/**
 * Render messages list
 */
const renderMessages = (messages) => {
    const messagesList = document.getElementById('messagesList');
    const totalMessages = document.getElementById('totalMessages');

    totalMessages.textContent = `${messages.length} رسالة`;

    if (messages.length === 0) {
        renderEmptyState();
        return;
    }

    const messagesHtml = messages.map(msg => {
        const isUnread = msg.status === 'SENT';
        const statusBadge = isUnread
            ? '<span class="badge bg-danger">جديدة</span>'
            : '<span class="badge bg-secondary">مقروءة</span>';

        const offerLink = msg.offer_id
            ? `<a href="offer_details.php?id=${msg.offer_id}" class="text-decoration-none"><i class="bi bi-house-door me-1"></i>${msg.offer_title}</a>`
            : '';

        const messageDate = new Date(msg.created_at).toLocaleString('ar-SA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        return `
            <div class="message-item border-bottom p-3 ${isUnread ? 'bg-light' : ''}" data-message-id="${msg.id}">
                <div class="row">
                    <div class="col-md-8">
                        <div class="d-flex align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    ${statusBadge}
                                    <strong class="ms-2">${msg.subject || 'بدون موضوع'}</strong>
                                </h6>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-person me-1"></i>من: ${msg.sender_name}
                                    ${msg.sender_phone ? `<i class="bi bi-telephone ms-3 me-1"></i>${msg.sender_phone}` : ''}
                                </p>
                                ${offerLink ? `<p class="text-muted mb-1">${offerLink}</p>` : ''}
                                <p class="message-text mt-2">${msg.message}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <small class="text-muted d-block mb-2">
                            <i class="bi bi-clock me-1"></i>${messageDate}
                        </small>
                        <button class="btn btn-sm btn-primary me-1" onclick="openReplyModal(${msg.id})">
                            <i class="bi bi-reply me-1"></i>رد
                        </button>
                        ${isUnread ? `<button class="btn btn-sm btn-outline-secondary" onclick="markAsRead(${msg.id})">
                            <i class="bi bi-check2 me-1"></i>تعليم كمقروءة
                        </button>` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    messagesList.innerHTML = messagesHtml;
};

/**
 * Render empty state
 */
const renderEmptyState = () => {
    const messagesList = document.getElementById('messagesList');
    messagesList.innerHTML = `
        <div class="text-center py-5">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h5 class="mt-3">لا توجد رسائل</h5>
            <p class="text-muted">لم يتم استلام أي رسائل بعد</p>
        </div>
    `;
};

/**
 * Mark message as read
 */
const markAsRead = async (messageId) => {
    try {
        const formData = new FormData();
        formData.append('action', 'mark_as_read');
        formData.append('message_id', messageId);

        const response = await fetch('offers_backend.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast('تم تعليم الرسالة كمقروءة', 'success');
            // Refresh messages
            fetchMessages();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Error marking message as read:', error);
        showToast('حدث خطأ أثناء تحديث حالة الرسالة', 'error');
    }
};

/**
 * Open reply modal
 */
const openReplyModal = (messageId) => {
    const message = messagesData.find(m => m.id === messageId);
    if (!message) return;

    // Populate modal with message data
    document.getElementById('originalMessage').textContent = message.message;
    document.getElementById('originalSender').textContent = message.sender_name;
    document.getElementById('replyToSenderId').value = message.sender_id;
    document.getElementById('replyToOfferId').value = message.offer_id || '';
    document.getElementById('originalMessageId').value = message.id;
    document.getElementById('replySubject').value = 'رد: ' + (message.subject || 'بدون موضوع');
    document.getElementById('replyMessage').value = '';
    document.getElementById('replyAlert').style.display = 'none';

    // Show modal
    replyModal.show();

    // Mark as read if unread
    if (message.status === 'SENT') {
        markAsRead(messageId);
    }
};

/**
 * Send reply via API
 */
const sendReply = async () => {
    const senderId = parseInt(document.getElementById('replyToSenderId').value);
    const offerId = document.getElementById('replyToOfferId').value;
    const subject = document.getElementById('replySubject').value.trim();
    const message = document.getElementById('replyMessage').value.trim();
    const sendBtn = document.getElementById('sendReplyBtn');

    if (!message) {
        showReplyAlert('الرجاء كتابة رسالة', 'warning');
        return;
    }

    // Disable button
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري الإرسال...';

    try {
        const requestBody = {
            receiver_id: senderId,
            subject: subject,
            message: message
        };

        if (offerId) {
            requestBody.offer_id = parseInt(offerId);
        }

        const response = await fetch(`${API_BASE_URL}/messages`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            showToast('تم إرسال الرد بنجاح!', 'success');
            replyModal.hide();
            // Clear form
            document.getElementById('replyForm').reset();
        } else {
            throw new Error(result.message || 'فشل إرسال الرسالة');
        }
    } catch (error) {
        console.error('Error sending reply:', error);
        showReplyAlert('حدث خطأ أثناء إرسال الرسالة: ' + error.message, 'danger');
    } finally {
        // Re-enable button
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send me-2"></i>إرسال الرد';
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Initialize reply modal
    replyModal = new bootstrap.Modal(document.getElementById('replyModal'));

    // Fetch messages
    fetchMessages();

    // Refresh messages every 30 seconds
    setInterval(fetchMessages, 30000);
});
</script>

<style>
.message-item {
    transition: background-color 0.2s;
}

.message-item:hover {
    background-color: #f8f9fa;
}

.message-text {
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>

<?php
include("../../footer.hnt");
?>
