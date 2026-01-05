// Enhanced validation system
class EnhancedValidator {
    constructor() {
        this.rules = {
            phone: /^[0-9]{10}$/,
            email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
            customer_code: /^CUST[A-Z0-9]{8,12}$/,
            amount: /^\d+(\.\d{1,2})?$/,
            verification_code: /^[0-9]{6}$/
        };
    }

    validateField(field, value) {
        const type = field.type;
        const name = field.name;
        
        // Required field validation
        if (field.required && !value.trim()) {
            return { isValid: false, message: 'This field is required' };
        }

        // Type-specific validation
        switch (name) {
            case 'phone':
                if (value && !this.rules.phone.test(value)) {
                    return { isValid: false, message: 'Please enter a valid 10-digit phone number' };
                }
                break;
                
            case 'email':
                if (value && !this.rules.email.test(value)) {
                    return { isValid: false, message: 'Please enter a valid email address' };
                }
                break;
                
            case 'customer_code':
                if (value && !this.rules.customer_code.test(value)) {
                    return { isValid: false, message: 'Please enter a valid customer code' };
                }
                break;
                
            case 'amount':
            case 'base_amount':
                if (value && (!this.rules.amount.test(value) || parseFloat(value) <= 0)) {
                    return { isValid: false, message: 'Please enter a valid amount greater than 0' };
                }
                break;
                
            case 'verification_code':
                if (value && !this.rules.verification_code.test(value)) {
                    return { isValid: false, message: 'Please enter a valid 6-digit verification code' };
                }
                break;
                
            case 'password':
                if (value && value.length < 6) {
                    return { isValid: false, message: 'Password must be at least 6 characters long' };
                }
                break;
                
            case 'confirm_password':
                const password = field.form.querySelector('input[name="password"]');
                if (password && value !== password.value) {
                    return { isValid: false, message: 'Passwords do not match' };
                }
                break;
        }

        return { isValid: true };
    }

    validateForm(form) {
        let isValid = true;
        const fields = form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            if (!this.validateField(field, field.value).isValid) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    // Real-time validation
    enableRealTimeValidation(form) {
        const fields = form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            field.addEventListener('blur', () => {
                this.validateAndShowError(field);
            });
            
            field.addEventListener('input', () => {
                this.clearFieldError(field);
            });
        });
    }

    validateAndShowError(field) {
        const result = this.validateField(field, field.value);
        
        if (!result.isValid) {
            this.showFieldError(field, result.message);
        } else {
            this.clearFieldError(field);
        }
        
        return result.isValid;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);
        
        field.style.borderColor = '#e74c3c';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.color = '#e74c3c';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
    }

    clearFieldError(field) {
        field.style.borderColor = '#ddd';
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    // ADD THIS METHOD INSIDE THE CLASS - Payment validation
    validatePayment(billId, amount, paymentMethod) {
        return new Promise((resolve, reject) => {
            // Basic validation
            if (!billId) {
                reject('Please select a bill to pay');
                return;
            }
            
            if (!amount || amount <= 0) {
                reject('Invalid payment amount');
                return;
            }
            
            if (!paymentMethod) {
                reject('Please select a payment method');
                return;
            }
            
            // Simulate API validation
            setTimeout(() => {
                // In real implementation, this would validate with your server
                const isValid = billId && amount > 0 && paymentMethod;
                if (isValid) {
                    resolve({
                        valid: true,
                        bill: {
                            id: billId,
                            amount: amount,
                            method: paymentMethod
                        }
                    });
                } else {
                    reject('Payment validation failed');
                }
            }, 500);
        });
    }
}

// Initialize enhanced validator
const enhancedValidator = new EnhancedValidator();

// Auto-initialize forms
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        enhancedValidator.enableRealTimeValidation(form);
        
        form.addEventListener('submit', function(e) {
            if (!enhancedValidator.validateForm(this)) {
                e.preventDefault();
                showNotification('Please fix the errors before submitting.', 'error');
            }
        });
    });
});

// Export for global use
window.EnhancedValidator = EnhancedValidator;
window.enhancedValidator = enhancedValidator;