// Add to existing settings page JavaScript
// This code should be added to the settings module or main app.js

// Add Feature Permissions button to Settings page (Admin only)
function initializeFeatureKnobsButton() {
    // Check if user is Admin or Owner
    if (window.AppData && (window.AppData.user.role === 'admin' || window.AppData.user.role === 'owner')) {
        // Import the feature knobs module
        import('./modules/feature_knobs.js').then(module => {
            window.FeatureKnobs = module.FeatureKnobs;
        });
    }
}

// Call on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFeatureKnobsButton);
} else {
    initializeFeatureKnobsButton();
}

// Add this function to your App object or settings module
function openFeatureKnobPanel() {
    if (window.FeatureKnobs) {
        window.FeatureKnobs.openModal();
    } else {
        console.error('Feature Knobs module not loaded');
    }
}

// Make it globally available
window.openFeatureKnobPanel = openFeatureKnobPanel;
