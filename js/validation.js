// Enhanced validation functions
class FormValidator {
    constructor(formId) {
        this.form = document.getElementById(formId);
        this.fields = {};
        this.init();
    }
    
    init() {
        if (!this.form) return;
        
        // Add real-time validation
        this.form.addEventListener('submit', (e) => this.validateForm(e));
        
        // Add input event listeners for real-time validation
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }
    
    validateForm(e) {
        e.preventDefault();
        
        let isValid = true;
        const inputs = this.form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        if (isValid) {
            this.form.submit();
        } else {
            this.showNotification('Please fix the errors before submitting.', 'error');
        }
        
        return isValid;
    }
    
    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        const name = field.name;
        
        // Clear previous errors
        this.clearFieldError(field);
        
        // Required field validation
        if (field.required && !value) {
            return this.showFieldError(field, 'This field is required');
        }
        
        // Email validation
        if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                return this.showFieldError(field, 'Please enter a valid email address');
            }
        }
        
        // Phone validation
        if (name === 'phone' && value) {
            const phoneRegex = /^[0-9]{10}$/;
            if (!phoneRegex.test(value)) {
                return this.showFieldError(field, 'Please enter a valid 10-digit phone number');
            }
        }
        
        // Password validation
        if (type === 'password' && value) {
            if (value.length < 6) {
                return this.showFieldError(field, 'Password must be at least 6 characters long');
            }
            
            // Confirm password validation
            if (name === 'confirm_password') {
                const password = this.form.querySelector('input[name="password"]');
                if (password && password.value !== value) {
                    return this.showFieldError(field, 'Passwords do not match');
                }
            }
        }
        
        // Amount validation
        if ((name === 'amount' || name === 'base_amount') && value) {
            if (parseFloat(value) <= 0) {
                return this.showFieldError(field, 'Amount must be greater than 0');
            }
        }
        
        // Date validation
        if (type === 'date' && value) {
            const selectedDate = new Date(value);
            const today = new Date();
            
            if (name === 'due_date' && selectedDate < today) {
                return this.showFieldError(field, 'Due date cannot be in the past');
            }
        }
        
        return true;
    }
    
    showFieldError(field, message) {
        field.style.borderColor = '#e74c3c';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.color = '#e74c3c';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
        
        return false;
    }
    
    clearFieldError(field) {
        field.style.borderColor = '#ddd';
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    showNotification(message, type = 'info') {
        // Use the existing notification function from script.js
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else {
            alert(message);
        }
    }
}

// Payment validation
class PaymentValidator {
    static validatePaymentAmount(amount) {
        if (!amount || amount <= 0) {
            throw new Error('Invalid payment amount');
        }
        return true;
    }
    
    static validateBillStatus(billStatus) {
        if (billStatus !== 'pending') {
            throw new Error('This bill has already been paid');
        }
        return true;
    }
}

// Initialize validators when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validators
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        if (form.id) {
            new FormValidator(form.id);
        }
    });
    
    // Add specific validation for payment forms
    const paymentForms = document.querySelectorAll('form[action*="payment"]');
    paymentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const amountInput = form.querySelector('input[name="amount"]');
            if (amountInput) {
                try {
                    PaymentValidator.validatePaymentAmount(parseFloat(amountInput.value));
                } catch (error) {
                    e.preventDefault();
                    showNotification(error.message, 'error');
                }
            }
        });
    });
});

// Export for use in other files
window.FormValidator = FormValidator;
window.PaymentValidator = PaymentValidator;