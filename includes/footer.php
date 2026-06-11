        </main>
    </div>
</div>

<?php include __DIR__ . '/modals.php'; ?>

<!-- Scripts -->
<script type="module" src="js/app.js?v=<?php echo defined('APP_VERSION') ? APP_VERSION : '1'; ?>"></script>
<script type="module">
    // Init Icons
    lucide.createIcons();

    // Make openCreateModal available globally immediately
    window.openCreateModal = async function() {
        try {
            // Wait for App to be available
            let attempts = 0;
            while ((!window.App || typeof window.App.openCreateModal !== 'function') && attempts < 50) {
                await new Promise(resolve => setTimeout(resolve, 100));
                attempts++;
            }
            
            if (!window.App || typeof window.App.openCreateModal !== 'function') {
                console.error('App.openCreateModal is not available after waiting');
                alert('Error: Application not fully loaded. Please refresh the page.');
                return;
            }
            await window.App.openCreateModal();
        } catch (error) {
            console.error('Error opening create modal:', error);
            alert('Error opening form: ' + error.message);
        }
    };

    (async () => {

    // WAIT UNTIL APP IS FULLY INITIALIZED
    let attempts = 0;
    while (!window.AppReady && attempts < 50) {
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }

    if (!window.App || typeof App.getUser !== 'function') {
        console.error("App not ready after waiting");
        return;
    }

    const user = App.getUser();

    // Bind Form Submits
    const createLeadForm = document.getElementById('createLeadForm');
    if (createLeadForm) {
        createLeadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            App.saveLead(e);
        });
    }
    
    const chartBuilderForm = document.getElementById('chartBuilderForm');
    if (chartBuilderForm) {
        chartBuilderForm.addEventListener('submit', (e) => {
            e.preventDefault();
            App.saveChartFromBuilder(e);
        });
    }
    
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', (e) => {
            e.preventDefault();
            App.saveUser(e);
        });
    }
    
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            App.changePassword(e);
        });
    }

    })(); // Close async IIFE
</script>
</body>
</html>
