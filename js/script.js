// Password visibility toggle function
function togglePassword(passwordFieldId) {
    const passwordField = document.getElementById(passwordFieldId);
    const toggleButton = passwordField.nextElementSibling;
    const icon = toggleButton.querySelector('[data-lucide]');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        // Change to eye-off icon
        icon.setAttribute('data-lucide', 'eye-off');
        lucide.createIcons();
        toggleButton.setAttribute('aria-label', 'Hide password');
    } else {
        passwordField.type = 'password';
        // Change to eye icon
        icon.setAttribute('data-lucide', 'eye');
        lucide.createIcons();
        toggleButton.setAttribute('aria-label', 'Show password');
    }
}

// Fix password toggle button positioning
function fixPasswordTogglePosition() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        const input = button.previousElementSibling;
        if (input) {
            const inputHeight = input.offsetHeight;
            const buttonHeight = 24; // Fixed button height
            const topPosition = (inputHeight - buttonHeight) / 2;
            button.style.top = `calc(50% + ${topPosition}px)`;
        }
    });
}

// Auto-focus on first input field in forms
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('form input:not([type="hidden"])');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Initialize all password toggle buttons
    initializePasswordToggles();
    
    // Fix password toggle positioning
    fixPasswordTogglePosition();
    
    // Re-fix positioning after a short delay to ensure elements are rendered
    setTimeout(fixPasswordTogglePosition, 100);
    
    // Initialize form validations
    initializeFormValidations();
    
    // Initialize table sorting if available
    initializeTableSorting();
    
    // Initialize payment gateway selection
    initializePaymentGateway();
    
    // Initialize dashboard charts
    initializeDashboardCharts();
    
    // Initialize notifications
    initializeNotifications();
    
    // Fix positioning on window resize
    window.addEventListener('resize', fixPasswordTogglePosition);
});

// Initialize all password toggle buttons
function initializePasswordToggles() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.innerHTML = '<i data-lucide="eye" class="icon-sm"></i>';
        button.setAttribute('aria-label', 'Show password');
        // Initialize Lucide icons if available
        if (window.lucide) {
            lucide.createIcons();
        }
    });
}

// Form validation functions
function validatePhone(phone) {
    const phoneRegex = /^[0-9]{10}$/;
    return phoneRegex.test(phone);
}

function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePassword(password) {
    return password.length >= 6;
}

// Real-time form validation
function initializeFormValidations() {
    // Phone number validation
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !validatePhone(this.value)) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'Please enter a valid 10-digit phone number');
            } else {
                this.style.borderColor = '#ddd';
                clearFieldError(this);
            }
        });
    });
    
    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !validateEmail(this.value)) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'Please enter a valid email address');
            } else {
                this.style.borderColor = '#ddd';
                clearFieldError(this);
            }
        });
    });
    
    // Password validation
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !validatePassword(this.value)) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'Password must be at least 6 characters long');
            } else {
                this.style.borderColor = '#ddd';
                clearFieldError(this);
            }
        });
    });
}

// Show field error message
function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#e74c3c';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.innerHTML = message;
    
    field.parentNode.appendChild(errorDiv);
}

// Clear field error message
function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Table sorting functionality
function initializeTableSorting() {
    const sortableHeaders = document.querySelectorAll('th[data-sort]');
    sortableHeaders.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const columnIndex = Array.from(this.parentNode.children).indexOf(this);
            const isNumeric = this.dataset.sort === 'numeric';
            const currentOrder = this.dataset.order || 'asc';
            const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            
            // Reset all headers
            sortableHeaders.forEach(h => {
                h.dataset.order = '';
                h.innerHTML = h.innerHTML.replace(' ↑', '').replace(' ↓', '');
            });
            
            // Set current header
            this.dataset.order = newOrder;
            this.innerHTML += newOrder === 'asc' ? ' ↑' : ' ↓';
            
            sortTable(table, columnIndex, isNumeric, newOrder);
        });
    });
}

function sortTable(table, columnIndex, isNumeric, order) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let aValue = a.cells[columnIndex].textContent.trim();
        let bValue = b.cells[columnIndex].textContent.trim();
        
        if (isNumeric) {
            aValue = parseFloat(aValue.replace(/[^\d.-]/g, '')) || 0;
            bValue = parseFloat(bValue.replace(/[^\d.-]/g, '')) || 0;
        }
        
        if (order === 'asc') {
            return aValue > bValue ? 1 : -1;
        } else {
            return aValue < bValue ? 1 : -1;
        }
    });
    
    // Remove existing rows
    rows.forEach(row => row.remove());
    
    // Add sorted rows
    rows.forEach(row => tbody.appendChild(row));
}

// Payment gateway initialization
function initializePaymentGateway() {
    const gatewayOptions = document.querySelectorAll('input[name="payment_gateway"]');
    const gatewayDetails = document.getElementById('gateway-details');
    const payButton = document.getElementById('payButton');
    
    if (gatewayOptions && gatewayDetails) {
        gatewayOptions.forEach(gateway => {
            gateway.addEventListener('change', function() {
                const selectedGateway = this.value;
                let formHTML = '';
                
                switch(selectedGateway) {
                    case 'esewa':
                        formHTML = `
                            <h3>eSewa Payment</h3>
                            <div class="qr-container">
                                <p>Scan QR Code to pay with eSewa</p>
                                <div class="qr-code">
                                    <img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=esewa://pay?amt=100&pid=TXN123&scd=EPAYTEST&su=http://localhost/billing_system/payment_success.php" alt="eSewa QR Code">
                                </div>
                                <p>Or click Pay Now to redirect to eSewa</p>
                            </div>
                        `;
                        break;
                        
                    case 'khalti':
                        formHTML = `
                            <h3>Khalti Payment</h3>
                            <p>You will be redirected to Khalti payment page</p>
                            <div class="form-group">
                                <label>Mobile Number (for demo)</label>
                                <input type="text" class="form-control" name="khalti_mobile" placeholder="98XXXXXXXX" required>
                            </div>
                        `;
                        break;
                }
                
                gatewayDetails.innerHTML = formHTML;
                gatewayDetails.style.display = 'block';
                if (payButton) payButton.style.display = 'block';
            });
        });
    }
}

// Bill download simulation
function downloadBill(billId) {
    // Simulate download process
    showNotification('Preparing bill download...', 'info');
    
    setTimeout(() => {
        // In a real application, this would generate and download a PDF
        const billData = {
            id: billId,
            date: new Date().toLocaleDateString(),
            amount: '₹1,250.50',
            service: 'Electricity'
        };
        
        // Create a simple text file for demo
        const content = `
            BILL PAY PRO - INVOICE
            ======================
            Bill ID: #${billData.id}
            Date: ${billData.date}
            Service: ${billData.service}
            Amount: ${billData.amount}
            
            Thank you for your business!
        `;
        
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bill-${billId}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showNotification('Bill downloaded successfully!', 'success');
    }, 1000);
}

// Report download functionality
function downloadReport(reportType) {
    showNotification(`Generating ${reportType} report...`, 'info');
    
    setTimeout(() => {
        const reports = {
            'transaction': 'Transaction Report - All payments and billing history',
            'customer': 'Customer Report - Registered customers and their activities',
            'revenue': 'Revenue Report - Income and payment statistics',
            'service': 'Service Report - Service usage and billing patterns'
        };
        
        const content = `
            BILL PAY PRO - ${reportType.toUpperCase()} REPORT
            ====================================
            Generated on: ${new Date().toLocaleDateString()}
            
            ${reports[reportType]}
            
            Report Summary:
            - Total Records: 150
            - Date Range: Last 30 days
            - Generated By: System Administrator
            
            This is a demo report. In production, this would contain
            actual data from the database with proper formatting.
        `;
        
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${reportType}-report-${new Date().getTime()}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showNotification(`${reportType.charAt(0).toUpperCase() + reportType.slice(1)} report downloaded!`, 'success');
    }, 1500);
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `custom-notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease-out;
    `;
    
    // Set background color based on type
    const colors = {
        success: '#27ae60',
        error: '#e74c3c',
        warning: '#f39c12',
        info: '#3498db'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Confirmation dialog
function showConfirmation(message, callback) {
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;
    
    const dialog = document.createElement('div');
    dialog.style.cssText = `
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        max-width: 400px;
        width: 90%;
        text-align: center;
    `;
    
    dialog.innerHTML = `
        <h3 style="margin-bottom: 1rem; color: #2c3e50;">Confirmation</h3>
        <p style="margin-bottom: 2rem; color: #666;">${message}</p>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <button class="btn" style="background: #95a5a6;" onclick="this.closest('.confirmation-overlay').remove()">Cancel</button>
            <button class="btn" style="background: #e74c3c;" onclick="this.closest('.confirmation-overlay').remove(); callback()">Confirm</button>
        </div>
    `;
    
    overlay.className = 'confirmation-overlay';
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    
    // Store callback in global scope temporarily
    window.callback = callback;
}

// Form submission enhancement
function enhanceFormSubmission(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = 'Processing...';
                
                // Re-enable button after 3 seconds in case of error
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = submitButton.dataset.originalText || 'Submit';
                }, 3000);
            }
        });
    }
}

// Initialize all forms on page load
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        if (!form.id) {
            form.id = 'form-' + Math.random().toString(36).substr(2, 9);
        }
        enhanceFormSubmission(form.id);
        
        // Store original button text
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.dataset.originalText = submitButton.innerHTML;
        }
    });
});

// Utility function for formatting numbers as currency
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Utility function for date formatting
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    };
    return new Date(dateString).toLocaleDateString('en-IN', options);
}

// Auto-calculate due dates
function calculateDueDate(days = 30) {
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + days);
    return dueDate.toISOString().split('T')[0];
}

// Search functionality for tables
function initializeTableSearch() {
    const searchInputs = document.querySelectorAll('.table-search');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('.card').querySelector('table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });
}

// Dashboard charts initialization
function initializeDashboardCharts() {
    // Simple chart simulation for dashboard
    const chartContainers = document.querySelectorAll('.chart-container');
    if (chartContainers.length > 0) {
        chartContainers.forEach(container => {
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem; background: #f8f9fa; border-radius: 8px;">
                    <h4>Analytics Chart</h4>
                    <p>In a real application, this would show interactive charts</p>
                    <div style="height: 200px; background: linear-gradient(90deg, #3498db, #2ecc71); opacity: 0.7; border-radius: 4px; display: flex; align-items: end; justify-content: space-around; padding: 1rem;">
                        <div style="width: 30px; background: #e74c3c; height: 80%; border-radius: 2px;"></div>
                        <div style="width: 30px; background: #3498db; height: 60%; border-radius: 2px;"></div>
                        <div style="width: 30px; background: #2ecc71; height: 90%; border-radius: 2px;"></div>
                        <div style="width: 30px; background: #f39c12; height: 40%; border-radius: 2px;"></div>
                        <div style="width: 30px; background: #9b59b6; height: 70%; border-radius: 2px;"></div>
                    </div>
                </div>
            `;
        });
    }
}

// Notifications initialization
function initializeNotifications() {
    const notificationBells = document.querySelectorAll('.notification-bell');
    notificationBells.forEach(bell => {
        bell.addEventListener('click', function(e) {
            e.preventDefault();
            showNotification('You have new notifications!', 'info');
        });
    });
}

// Service management functions
function editService(serviceId) {
    showNotification(`Editing service #${serviceId}`, 'info');
    // In real application, this would open a modal with service details
}

function deleteService(serviceId) {
    showConfirmation('Are you sure you want to delete this service?', function() {
        showNotification('Service deleted successfully!', 'success');
    });
}

// User management functions
function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    showNotification(`User status changed to ${newStatus}`, 'success');
}

// Payment processing simulation
function processPayment(amount, gateway) {
    showNotification(`Processing payment of ${formatCurrency(amount)} via ${gateway}...`, 'info');
    
    setTimeout(() => {
        showNotification('Payment processed successfully!', 'success');
    }, 2000);
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTableSearch);
} else {
    initializeTableSearch();
}

// Export functions for global use
window.togglePassword = togglePassword;
window.downloadBill = downloadBill;
window.downloadReport = downloadReport;
window.showNotification = showNotification;
window.showConfirmation = showConfirmation;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.fixPasswordTogglePosition = fixPasswordTogglePosition;
window.editService = editService;
window.deleteService = deleteService;
window.toggleUserStatus = toggleUserStatus;
window.processPayment = processPayment;