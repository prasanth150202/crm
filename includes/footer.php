        </main>
    </div>
</div>

<?php include __DIR__ . '/modals.php'; ?>

<!-- Scripts -->
<script type="module" src="js/app.js?v=<?php echo time(); ?>"></script>
<script type="module" src="js/modules/import.js"></script>
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
        // App is assumed to be globally available via modules
        // Check Auth
        const user = App.getUser();
        if (user) {
            const userNameEl = document.getElementById('userName');
            const userInitialsEl = document.getElementById('userInitials');
            
            if (userNameEl) {
                // If name is just 'User' (fallback) or missing, show email as primary
                userNameEl.textContent = (user.name && user.name !== 'User') ? user.name : user.email;
            }
            if (userInitialsEl) {
                const displayName = (user.name && user.name !== 'User') ? user.name : user.email;
                userInitialsEl.textContent = displayName.charAt(0).toUpperCase();
            }
            const userEmailEl = document.getElementById('userEmail');
            if (userEmailEl) {
                // Only show email here if it's not already used as the primary name
                userEmailEl.textContent = (user.name && user.name !== 'User') ? user.email : '';
            }

            // Hide Settings link for non-admin users
            const settingsLink = document.getElementById('settingsLink');
            if (settingsLink && user.role !== 'owner' && user.role !== 'admin') {
                settingsLink.style.display = 'none';
            }

            // Show Organizations link for super admins only
            const organizationsLink = document.getElementById('organizationsLink');
            if (organizationsLink && user.is_super_admin) {
                organizationsLink.style.display = 'block';
            }

            // Load organization selector for super admin
            if (typeof App.loadOrgSelector === 'function') {
                App.loadOrgSelector();
            }

            // Rehydrate custom fields
            if (typeof App.refreshCustomFieldsFromServer === 'function') {
                await App.refreshCustomFieldsFromServer();
            }
        }

    })();

    // Bind Form Submits
    const createLeadForm = document.getElementById('createLeadForm');
    if (createLeadForm) createLeadForm.addEventListener('submit', (e) => App.saveLead(e));
    
    const chartBuilderForm = document.getElementById('chartBuilderForm');
    if (chartBuilderForm) chartBuilderForm.addEventListener('submit', (e) => App.saveChartFromBuilder(e));
    
    const userForm = document.getElementById('userForm');
    if (userForm) userForm.addEventListener('submit', (e) => App.saveUser(e));
    
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) changePasswordForm.addEventListener('submit', (e) => App.changePassword(e));

</script>
</body>
</html>
