// Enhanced payment validation
class PaymentValidator {
    static validateBillPayment(billId, amount) {
        return new Promise((resolve, reject) => {
            fetch(`../api/validate_bill.php?bill_id=${billId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.valid && parseFloat(data.bill.amount) === parseFloat(amount)) {
                        resolve(data);
                    } else {
                        reject('Bill validation failed or amount mismatch');
                    }
                })
                .catch(error => reject('Validation service unavailable'));
        });
    }

    static simulatePayment(transactionId, amount, gateway) {
        return new Promise((resolve) => {
            // Simulate API call delay
            setTimeout(() => {
                resolve({
                    success: true,
                    transaction_id: transactionId,
                    amount: amount,
                    gateway: gateway,
                    timestamp: new Date().toISOString()
                });
            }, 2000);
        });
    }

    static generatePaymentReceipt(paymentData) {
        return {
            transactionId: paymentData.transaction_id,
            amount: paymentData.amount,
            gateway: paymentData.gateway,
            customer: localStorage.getItem('customerName') || 'Demo Customer',
            date: new Date().toLocaleDateString(),
            time: new Date().toLocaleTimeString(),
            status: 'completed'
        };
    }
}

// QR Code Scanner Simulation
class QRScanner {
    static simulateScan(qrData) {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({
                    scanned: true,
                    data: qrData,
                    timestamp: new Date().toISOString()
                });
            }, 1500);
        });
    }

    static generateQRCode(elementId, data, size = 200) {
        const qrUrl = `https://chart.googleapis.com/chart?chs=${size}x${size}&cht=qr&chl=${encodeURIComponent(data)}&choe=UTF-8`;
        const img = document.getElementById(elementId);
        if (img) {
            img.src = qrUrl;
            img.alt = 'QR Code for Payment';
        }
    }
}

// Payment Status Manager
class PaymentStatus {
    constructor() {
        this.statuses = {
            pending: '<i data-lucide="clock" class="icon-sm"></i> Pending',
            processing: '<i data-lucide="refresh-cw" class="icon-sm"></i> Processing',
            completed: '<i data-lucide="check-circle" class="icon-sm"></i> Completed',
            failed: '<i data-lucide="x-circle" class="icon-sm"></i> Failed'
        };
    }

    updateStatus(elementId, status) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = this.statuses[status] || status;
            element.className = `payment-status payment-status-${status}`;
            // Re-initialize Lucide icons
            if (window.lucide) {
                lucide.createIcons();
            }
        }
    }

    showProgress(steps, currentStep) {
        const progressHtml = steps.map((step, index) => `
            <div class="progress-step ${index < currentStep ? 'completed' : ''} ${index === currentStep ? 'current' : ''}">
                <div class="step-number">${index + 1}</div>
                <div class="step-label">${step}</div>
            </div>
        `).join('');
        
        return `
            <div class="payment-progress">
                ${progressHtml}
            </div>
        `;
    }
}

// Initialize payment system
document.addEventListener('DOMContentLoaded', function() {
    // Auto-validate bills on payment page
    const billSelects = document.querySelectorAll('select[name="bill_id"], input[name="bill_id"]');
    billSelects.forEach(select => {
        select.addEventListener('change', function() {
            const billId = this.value;
            if (billId) {
                PaymentValidator.validateBillPayment(billId, this.dataset.amount)
                    .then(data => {
                        showNotification('Bill validated successfully', 'success');
                    })
                    .catch(error => {
                        showNotification(error, 'error');
                    });
            }
        });
    });

    // Enhance QR code payments
    const qrPaymentButtons = document.querySelectorAll('.qr-payment-btn');
    qrPaymentButtons.forEach(button => {
        button.addEventListener('click', function() {
            const transactionId = this.dataset.transactionId;
            const amount = this.dataset.amount;
            
            QRScanner.simulateScan(`BILLPAY:${transactionId}:${amount}`)
                .then(scanResult => {
                    if (scanResult.scanned) {
                        showNotification('QR Code scanned successfully!', 'success');
                    }
                });
        });
    });
});

// Export for global use
window.PaymentValidator = PaymentValidator;
window.QRScanner = QRScanner;
window.PaymentStatus = PaymentStatus;