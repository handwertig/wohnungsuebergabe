// Protocol Save Handler
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Protocol Save] Script loaded');
    
    // Find protocol edit form
    const form = document.getElementById('protocol-edit-form');
    
    if (!form) {
        console.log('[Protocol Save] No protocol-edit-form found');
        return;
    }
    
    console.log('[Protocol Save] Form found:', form);
    console.log('[Protocol Save] Form action:', form.action);
    console.log('[Protocol Save] Form method:', form.method);
    
    // Handle form submission
    form.addEventListener('submit', function(e) {
        console.log('[Protocol Save] Form submit event triggered!');
        console.log('[Protocol Save] Event:', e);
        
        // Get form data for debugging
        const formData = new FormData(this);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        console.log('[Protocol Save] Form data:', data);
        
        // Visual feedback
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Speichert...';
            submitBtn.disabled = true;
            
            // Reset after timeout (fallback)
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;
                    console.warn('[Protocol Save] Submit taking too long, reset button');
                }
            }, 10000);
        }
        
        // Allow form to submit normally
        console.log('[Protocol Save] Allowing form submission...');
        return true;
    });
    
    // Debug: Check all submit buttons
    const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    console.log('[Protocol Save] Found submit buttons:', submitButtons.length);
    
    submitButtons.forEach((btn, index) => {
        console.log(`[Protocol Save] Button ${index}:`, btn);
        
        btn.addEventListener('click', function(e) {
            console.log(`[Protocol Save] Submit button ${index} clicked!`);
            console.log('[Protocol Save] Click event:', e);
        });
    });
    
    // Add global debug function
    window.debugProtocolForm = function() {
        console.log('[Protocol Save] === DEBUG INFO ===');
        console.log('Form exists:', !!form);
        console.log('Form action:', form?.action);
        console.log('Form method:', form?.method);
        console.log('Submit buttons:', submitButtons.length);
        
        // Check for any preventDefault
        const events = form?._events || {};
        console.log('Form events:', events);
        
        // Test submit
        console.log('Testing submit...');
        if (form) {
            console.log('Calling form.submit()...');
            // form.submit(); // Uncomment to actually test
        }
    };
    
    console.log('[Protocol Save] Setup complete. Use window.debugProtocolForm() for debug info.');
});

// Manual submit function for testing
window.manualSubmitProtocol = function() {
    const form = document.getElementById('protocol-edit-form');
    if (form) {
        console.log('[Protocol Save] Manual submit triggered');
        form.submit();
    } else {
        console.error('[Protocol Save] Form not found for manual submit');
    }
};
