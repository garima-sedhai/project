/**
 * Simple AJAX Logout Confirmation Handler
 * Clean modal with no glitchy animations
 */

// Create and style the logout modal
function createLogoutModal() {
    // Check if modal already exists
    if (document.getElementById('logoutModal')) {
        return;
    }
    
    // Create simple modal HTML
    const modalHTML = `
    <div id="logoutModal" class="logout-modal" style="display: none;">
        <div class="logout-modal-overlay"></div>
        <div class="logout-modal-content">
            <div class="logout-modal-header">
                <h3>Confirm Logout</h3>
            </div>
            <div class="logout-modal-body">
                <p>Are you sure you want to logout?</p>
                <p class="logout-modal-subtext" style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                    You will be redirected to admin login page
                </p>
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

// NUCLEAR OPTION: Completely clear history and force fresh page
function performAjaxLogout() {
    showLoading();
    
    // Clear ALL browser data
    if (typeof(Storage) !== "undefined") {
        sessionStorage.clear();
        localStorage.clear();
    }
    
    // Clear cookies
    document.cookie.split(";").forEach(function(c) {
        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
    });
    
    // Send AJAX request to logout.php
    fetch('logout.php', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        },
        cache: 'no-store'
    })
    .then(response => {
        return response.json();
    })
    .then(data => {
        removeLoading();
        
        if (data.success) {
            // COMPLETE HISTORY CLEAR: Open login.php in a completely new context
            const loginUrl = 'login.php';
            
            // Method 1: Use window.open which creates a completely new browsing context
            const newWindow = window.open(loginUrl, '_self');
            
            // Method 2: Force a hard redirect that clears everything
            setTimeout(() => {
                // This completely resets the page
                document.body.innerHTML = '';
                window.location.href = loginUrl;
                window.location.reload(true); // Force reload from server, not cache
            }, 100);
        } else {
            // Fallback
            window.location.href = 'login.php';
            window.location.reload(true);
        }
    })
    .catch(error => {
        console.error('Logout AJAX error:', error);
        removeLoading();
        
        // Hard redirect on error
        window.location.href = 'login.php';
        window.location.reload(true);
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

// Initialize logout functionality
document.addEventListener('DOMContentLoaded', function() {
    // Find all logout links
    const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
    
    // Add click handler to each logout link
    logoutLinks.forEach(link => {
        link.addEventListener('click', showLogoutModal);
    });
});