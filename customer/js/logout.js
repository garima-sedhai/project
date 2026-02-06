/**
 * Simple AJAX Logout Confirmation Handler for Customers
 * Clean modal with teal theme matching admin interface
 */

// Create and style the logout modal
function createLogoutModal() {
    // Check if modal already exists
    if (document.getElementById('logoutModal')) {
        return;
    }
    
    // Create simple modal HTML with teal theme
    const modalHTML = `
    <div id="logoutModal" class="logout-modal" style="display: none;">
        <div class="logout-modal-overlay"></div>
        <div class="logout-modal-content">
            <div class="logout-modal-header">
                <h3>Confirm Logout</h3>
            </div>
            <div class="logout-modal-body">
                <p>Are you sure you want to logout?</p>
                <p class="logout-modal-subtext">You will be redirected to login page</p>
            </div>
            <div class="logout-modal-footer">
                <button class="logout-modal-btn cancel-btn">Cancel</button>
                <button class="logout-modal-btn confirm-btn">Logout</button>
            </div>
        </div>
    </div>
    `;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add simple CSS
    addModalStyles();
    
    // Setup events
    setupModalEvents();
}

// Add simple CSS styles
function addModalStyles() {
    const style = document.createElement('style');
    style.textContent = `
    .logout-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
        font-family: inherit;
    }
    
    .logout-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
    }
    
    .logout-modal-content {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        border: 1px solid #eaeaea;
        width: 350px;
        overflow: hidden;
    }
    
    .logout-modal-header {
        background: linear-gradient(135deg, #075B5E 0%, #0A6F73 100%);
        color: white;
        padding: 15px 20px;
    }
    
    .logout-modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .logout-modal-body {
        padding: 20px;
        text-align: center;
    }
    
    .logout-modal-body p {
        margin: 0;
        font-size: 1rem;
        color: #333;
    }
    
    .logout-modal-body .logout-modal-subtext {
        margin-top: 10px;
        font-size: 0.9rem;
        color: #666;
    }
    
    .logout-modal-footer {
        padding: 15px 20px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        border-top: 1px solid #eee;
        background: #f8f9fa;
    }
    
    .logout-modal-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: background-color 0.2s;
        font-weight: 500;
    }
    
    .logout-modal-btn.cancel-btn {
        background: white;
        color: #333;
        border: 1px solid #ddd;
    }
    
    .logout-modal-btn.cancel-btn:hover {
        background: #f8f9fa;
    }
    
    .logout-modal-btn.confirm-btn {
        background: #075B5E;
        color: white;
    }
    
    .logout-modal-btn.confirm-btn:hover {
        background: #064b4e;
    }
    
    .logout-modal-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    /* Simple loading */
    .logout-loading {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        text-align: center;
        z-index: 10000;
        border: 1px solid #eaeaea;
    }
    
    .logout-loading-spinner {
        width: 30px;
        height: 30px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #075B5E;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 10px;
    }
    
    .logout-loading-text {
        color: #333;
        font-size: 0.9rem;
        margin: 0;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    `;
    
    document.head.appendChild(style);
}

// Setup modal event listeners
function setupModalEvents() {
    const modal = document.getElementById('logoutModal');
    const overlay = modal.querySelector('.logout-modal-overlay');
    const cancelBtn = modal.querySelector('.cancel-btn');
    const confirmBtn = modal.querySelector('.confirm-btn');
    
    // Close modal function
    function closeModal() {
        modal.style.display = 'none';
    }
    
    // Close on overlay click
    overlay.addEventListener('click', closeModal);
    
    // Close on cancel button
    cancelBtn.addEventListener('click', closeModal);
    
    // Handle logout on confirm
    confirmBtn.addEventListener('click', function() {
        // Add loading effect to button
        confirmBtn.innerHTML = 'Logging out...';
        confirmBtn.disabled = true;
        
        closeModal();
        performAjaxLogout();
    });
    
    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            closeModal();
        }
    });
}

// Show loading
function showLoading() {
    const loadingHTML = `
    <div class="logout-loading">
        <div class="logout-loading-spinner"></div>
        <p class="logout-loading-text">Logging out...</p>
    </div>
    `;
    document.body.insertAdjacentHTML('beforeend', loadingHTML);
}

// Remove loading
function removeLoading() {
    const loading = document.querySelector('.logout-loading');
    if (loading) {
        loading.remove();
    }
}

// Get clean login URL
function getCleanLoginUrl() {
    const currentUrl = window.location.href;
    const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/') + 1);
    return baseUrl + 'login.php';
}

// Simple AJAX logout - Allows back button to index.php
function performAjaxLogout() {
    showLoading();
    
    // Store in session that we're coming from logout
    // This will be used by login.php to allow back navigation
    if (typeof(Storage) !== "undefined") {
        sessionStorage.setItem('logout_redirect', 'allow_back');
    }
    
    // Get clean login URL
    const loginUrl = getCleanLoginUrl();
    
    // Send AJAX request to logout.php
    fetch('logout.php', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        return response.json();
    })
    .then(data => {
        removeLoading();
        
        if (data.success) {
            // Use replace to go to login page but allow history
            window.location.href = loginUrl;
        } else {
            // Fallback
            window.location.href = loginUrl;
        }
    })
    .catch(error => {
        console.error('Logout AJAX error:', error);
        removeLoading();
        window.location.href = loginUrl;
    });
}

// Show logout modal
function showLogoutModal(event) {
    event.preventDefault();
    event.stopPropagation();
    
    // Create modal if it doesn't exist
    if (!document.getElementById('logoutModal')) {
        createLogoutModal();
    }
    
    // Show modal
    const modal = document.getElementById('logoutModal');
    modal.style.display = 'block';
}

// Simple initialization - attach to all logout links
function initializeLogout() {
    // Find all logout links in customer area
    const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
    
    if (logoutLinks.length === 0) {
        console.warn('No logout links found');
        return;
    }
    
    console.log(`Found ${logoutLinks.length} logout links`);
    
    // Add click handler to each logout link
    logoutLinks.forEach(link => {
        // Remove any existing click handlers to prevent duplicates
        const newLink = link.cloneNode(true);
        link.parentNode.replaceChild(newLink, link);
        
        // Add new event listener
        newLink.addEventListener('click', showLogoutModal);
        
        // Add visual feedback
        newLink.style.cursor = 'pointer';
        newLink.style.transition = 'opacity 0.2s';
        
        newLink.addEventListener('mouseenter', function() {
            this.style.opacity = '0.8';
        });
        
        newLink.addEventListener('mouseleave', function() {
            this.style.opacity = '1';
        });
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLogout);
} else {
    initializeLogout();
}

// Export for debugging
console.log('Customer logout.js loaded successfully');