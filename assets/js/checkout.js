class CheckoutHandler {
    constructor(orderId, expireMinutes = 15) {
        this.orderId = orderId;
        this.expireTime = Date.now() + (expireMinutes * 60 * 1000);
        this.pollInterval = null;
        this.pollCount = 0;
        this.maxPolls = Math.max(expireMinutes * 20, 20);
        this.init();
    }

    init() {
        this.startCountdown();
        this.startPolling();
    }

    startCountdown() {
        const timerEl = document.getElementById('countdown-timer');
        if (!timerEl) {
            return;
        }

        const updateTimer = () => {
            const remaining = this.expireTime - Date.now();

            if (remaining <= 0) {
                timerEl.textContent = 'Waktu habis';
                this.stopPolling();
                this.showExpired();
                return;
            }

            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);
            timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            setTimeout(updateTimer, 1000);
        };

        updateTimer();
    }

    startPolling() {
        const statusEl = document.getElementById('payment-status');

        const checkStatus = async () => {
            if (this.pollCount >= this.maxPolls) {
                this.stopPolling();
                return;
            }

            this.pollCount += 1;

            try {
                if (statusEl) {
                    statusEl.innerHTML = '<span class="spinner"></span><span>Memeriksa status pembayaran...</span>';
                }

                const response = await fetch(`api/check-status.php?order_id=${encodeURIComponent(this.orderId)}`);
                const data = await response.json();

                if (!response.ok || !data.success || !data.data) {
                    return;
                }

                if (data.data.is_paid) {
                    this.stopPolling();
                    this.redirectToSuccess();
                    return;
                }

                if (data.data.is_expired || ['expire', 'cancel', 'deny'].includes(data.data.status)) {
                    this.stopPolling();
                    if (data.data.status === 'cancel' || data.data.status === 'deny') {
                        this.showFailed(data.data.status);
                    } else {
                        this.showExpired();
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        };

        this.pollInterval = setInterval(checkStatus, 3000);
        setTimeout(checkStatus, 1000);
    }

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }

    redirectToSuccess() {
        window.location.href = `success.php?order_id=${encodeURIComponent(this.orderId)}`;
    }

    showExpired() {
        this.updateStatus('Pembayaran sudah kedaluwarsa.', 'var(--error)', 'rgba(211, 78, 72, 0.12)');
        this.fadeQr();
        this.showRetryButton();
    }

    showFailed(status) {
        const messages = {
            deny: 'Pembayaran ditolak.',
            cancel: 'Pembayaran dibatalkan.',
            expire: 'Pembayaran kedaluwarsa.'
        };

        this.updateStatus(messages[status] || 'Pembayaran gagal.', 'var(--error)', 'rgba(211, 78, 72, 0.12)');
        this.showRetryButton();
    }

    updateStatus(message, color, background) {
        const statusEl = document.getElementById('payment-status');
        if (!statusEl) {
            return;
        }

        statusEl.innerHTML = `<span>${message}</span>`;
        statusEl.style.color = color;
        statusEl.style.background = background;
    }

    fadeQr() {
        const qrContainer = document.querySelector('.qr-image-container');
        if (qrContainer) {
            qrContainer.style.opacity = '0.35';
        }
    }

    showRetryButton() {
        const parent = document.querySelector('.qr-card');
        if (!parent || document.getElementById('retry-btn')) {
            return;
        }

        const retry = document.createElement('a');
        retry.id = 'retry-btn';
        retry.href = './';
        retry.className = 'btn btn-secondary';
        retry.textContent = 'Kembali ke Landing Page';
        parent.appendChild(retry);
    }
}

function copyToClipboard(text, buttonEl) {
    navigator.clipboard.writeText(text).then(() => {
        const originalText = buttonEl.textContent;
        buttonEl.textContent = 'Kode berhasil disalin';

        setTimeout(() => {
            buttonEl.textContent = originalText;
        }, 1800);
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        buttonEl.textContent = 'Kode berhasil disalin';
    });
}
