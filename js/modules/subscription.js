/**
 * Subscription Module
 * Handles subscription status, feature checking, and upgrade prompts
 */

const Subscription = {
    currentPlan: null,
    features: [],
    userCount: 0,
    userLimit: null,
    status: null,

    /**
     * Initialize subscription module
     */
    async init() {
        try {
            await this.loadSubscriptionStatus();
            this.renderPlanBadge();
        } catch (error) {
            console.error('Failed to initialize subscription module:', error);
        }
    },

    /**
     * Load current subscription status from API
     */
    async loadSubscriptionStatus() {
        try {
            // Ensure App.api is available
            if (!window.App || typeof window.App.api !== 'function') {
                console.warn('App.api not yet available, skipping subscription load');
                return;
            }

            const response = await App.api('/subscriptions/get_plan_details.php');

            if (response.success && response.data) {
                this.currentPlan = response.data.plan;
                this.features = response.data.features || [];
                this.userCount = response.data.user_count || 0;
                this.userLimit = response.data.user_limit;
                this.status = response.data.subscription_status;

                // Store in App for global access
                App.subscription = {
                    plan: this.currentPlan,
                    features: this.features,
                    userCount: this.userCount,
                    userLimit: this.userLimit,
                    status: this.status
                };

                // Check for lockout
                this.checkLockout();
            }
        } catch (error) {
            console.error('Failed to load subscription status:', error);
        }
    },

    /**
     * Check if the account should be locked out
     */
    checkLockout() {
        const inactiveStatuses = ['past_due', 'cancelled', 'halted', 'expired', 'none'];
        if (inactiveStatuses.includes(this.status)) {
            this.showLockoutOverlay();
        }
    },

    /**
     * Show a global lockout overlay
     */
    showLockoutOverlay() {
        if (document.getElementById('subscriptionLockoutOverlay')) return;

        let message = 'Your subscription is currently inactive.';
        if (this.status === 'past_due') message = 'Your payment is overdue. Please update your billing details.';
        if (this.status === 'none') message = 'No active subscription found. Please choose a plan to continue.';

        const projectRoot = (window.App && window.App.config && window.App.config.projectRoot) || '';
        const pricingUrl = `${projectRoot}/public/pricing.html`;

        const overlayHTML = `
            <div id="subscriptionLockoutOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-95 z-[9999] flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-8 text-center">
                    <div class="mb-6 flex justify-center">
                        <div class="h-20 w-20 bg-red-100 rounded-full flex items-center justify-center">
                            <i data-lucide="shield-alert" class="h-10 w-10 text-red-600"></i>
                        </div>
                    </div>
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-4">Account Restricted</h2>
                    <p class="text-gray-600 text-lg mb-8 leading-relaxed">
                        ${message} 
                        Access to your CRM data is restricted until your subscription is reactivated.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="${pricingUrl}" class="px-8 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-colors shadow-lg">
                            Reactivate Now
                        </a>
                        <button onclick="App.logout()" class="px-8 py-3 border border-gray-300 text-gray-700 rounded-xl font-bold hover:bg-gray-50 transition-colors">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', overlayHTML);
        if (window.lucide) lucide.createIcons();

        // Disable body scroll
        document.body.style.overflow = 'hidden';
    },

    /**
     * Check if a feature is available in current plan
     */
    hasFeature(featureKey) {
        return this.features.includes(featureKey);
    },

    /**
     * Render plan badge in header
     */
    renderPlanBadge() {
        if (!this.currentPlan) return;

        const headerActions = document.querySelector('#dashboardActions') || document.querySelector('#leadActions');
        if (!headerActions) return;

        // Determine badge color based on plan
        let badgeColor = 'bg-blue-100 text-blue-800';
        if (this.currentPlan.name.includes('Professional')) {
            badgeColor = 'bg-purple-100 text-purple-800';
        } else if (this.currentPlan.name.includes('Enterprise')) {
            badgeColor = 'bg-yellow-100 text-yellow-800';
        }

        const badgeHTML = `
            <div class="flex items-center space-x-2 mr-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${badgeColor} cursor-pointer" 
                      onclick="Subscription.showPlanDetails()">
                    <i data-lucide="crown" class="h-4 w-4 mr-1"></i>
                    ${this.currentPlan.name.replace(' Plan', '')}
                </span>
                ${this.userLimit !== null ? `
                    <span class="text-sm text-gray-600">
                        ${this.userCount}/${this.userLimit} users
                    </span>
                ` : ''}
            </div>
        `;

        headerActions.insertAdjacentHTML('afterbegin', badgeHTML);
        if (window.lucide) lucide.createIcons();
    },

    /**
     * Show upgrade prompt modal
     */
    showUpgradePrompt(featureName, requiredPlan = 'Professional or Enterprise') {
        const modalHTML = `
            <div id="upgradeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">Upgrade Required</h3>
                            <button onclick="Subscription.closeUpgradeModal()" class="text-gray-400 hover:text-gray-600">
                                <i data-lucide="x" class="h-6 w-6"></i>
                            </button>
                        </div>
                        
                        <div class="mb-6">
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i data-lucide="lock" class="h-5 w-5 text-blue-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">
                                            <strong>${featureName}</strong> is not available on your current plan.
                                        </p>
                                        <p class="text-sm text-blue-700 mt-1">
                                            Upgrade to <strong>${requiredPlan}</strong> to unlock this feature.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ${this.getPlanComparisonHTML()}

                        <div class="mt-6 flex justify-end space-x-3">
                            <button onclick="Subscription.closeUpgradeModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Maybe Later
                            </button>
                            ${requiredPlan.includes('Enterprise') ? `
                                <button onclick="Subscription.contactSales()" 
                                        class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                                    Contact Sales
                                </button>
                            ` : `
                                <button onclick="Subscription.upgradeNow()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Upgrade Now
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        lucide.createIcons();
    },

    /**
     * Get plan comparison table HTML
     */
    getPlanComparisonHTML() {
        return `
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Feature</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Basic</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Professional</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">Users</td>
                            <td class="px-6 py-4 text-sm text-center">Up to 3</td>
                            <td class="px-6 py-4 text-sm text-center">Up to 5</td>
                            <td class="px-6 py-4 text-sm text-center">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">Custom Fields</td>
                            <td class="px-6 py-4 text-sm text-center">❌</td>
                            <td class="px-6 py-4 text-sm text-center">✅</td>
                            <td class="px-6 py-4 text-sm text-center">✅</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">Advanced Reports</td>
                            <td class="px-6 py-4 text-sm text-center">❌</td>
                            <td class="px-6 py-4 text-sm text-center">✅</td>
                            <td class="px-6 py-4 text-sm text-center">✅</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">Automations</td>
                            <td class="px-6 py-4 text-sm text-center">❌</td>
                            <td class="px-6 py-4 text-sm text-center">✅ (No Webhooks)</td>
                            <td class="px-6 py-4 text-sm text-center">✅ Full Suite</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">External Webhooks</td>
                            <td class="px-6 py-4 text-sm text-center">❌</td>
                            <td class="px-6 py-4 text-sm text-center">❌</td>
                            <td class="px-6 py-4 text-sm text-center">✅</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">API Access</td>
                            <td class="px-6 py-4 text-sm text-center">❌</td>
                            <td class="px-6 py-4 text-sm text-center">❌</td>
                            <td class="px-6 py-4 text-sm text-center">✅</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">Price</td>
                            <td class="px-6 py-4 text-sm text-center font-semibold">₹1,999/mo</td>
                            <td class="px-6 py-4 text-sm text-center font-semibold">₹4,999/mo</td>
                            <td class="px-6 py-4 text-sm text-center font-semibold">Custom</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    },

    /**
     * Show plan details modal
     */
    showPlanDetails() {
        if (!this.currentPlan) return;

        const modalHTML = `
            <div id="planDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">Your Current Plan</h3>
                            <button onclick="Subscription.closePlanDetailsModal()" class="text-gray-400 hover:text-gray-600">
                                <i data-lucide="x" class="h-6 w-6"></i>
                            </button>
                        </div>
                        
                        <div class="mb-6">
                            <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white mb-4">
                                <h4 class="text-2xl font-bold">${this.currentPlan.name}</h4>
                                <p class="text-blue-100 mt-2">${this.currentPlan.description || ''}</p>
                                ${this.currentPlan.base_price_monthly > 0 ? `
                                    <p class="text-3xl font-bold mt-4">₹${this.currentPlan.base_price_monthly}/month</p>
                                ` : '<p class="text-2xl font-bold mt-4">Custom Pricing</p>'}
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                    <span class="text-gray-700">Users</span>
                                    <span class="font-semibold">${this.userLimit !== null ? `${this.userCount}/${this.userLimit}` : 'Unlimited'}</span>
                                </div>
                                ${this.currentPlan.price_per_additional_user_monthly > 0 ? `
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                        <span class="text-gray-700">Additional User</span>
                                        <span class="font-semibold">₹${this.currentPlan.price_per_additional_user_monthly}/month</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            ${!this.currentPlan.name.includes('Enterprise') ? `
                                <button onclick="Subscription.showUpgradePrompt('Premium Features', 'Professional or Enterprise')" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Upgrade Plan
                                </button>
                            ` : ''}
                            <button onclick="Subscription.closePlanDetailsModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        lucide.createIcons();
    },

    /**
     * Close upgrade modal
     */
    closeUpgradeModal() {
        const modal = document.getElementById('upgradeModal');
        if (modal) modal.remove();
    },

    /**
     * Close plan details modal
     */
    closePlanDetailsModal() {
        const modal = document.getElementById('planDetailsModal');
        if (modal) modal.remove();
    },

    /**
     * Navigate to upgrade page
     */
    upgradeNow() {
        // TODO: Implement Razorpay integration
        App.showNotification('Upgrade functionality coming soon!', 'info');
        this.closeUpgradeModal();
    },

    /**
     * Contact sales for Enterprise plan
     */
    contactSales() {
        // TODO: Implement contact sales form
        App.showNotification('Please contact sales@yourcompany.com for Enterprise pricing', 'info');
        this.closeUpgradeModal();
    },

    /**
     * Check feature access and show upgrade prompt if needed
     */
    checkFeatureAccess(featureKey, featureName, callback) {
        if (this.hasFeature(featureKey)) {
            if (callback) callback();
            return true;
        } else {
            let requiredPlan = 'Professional or Enterprise';
            if (featureKey === 'external_webhooks' || featureKey === 'api_access') {
                requiredPlan = 'Enterprise';
            }
            this.showUpgradePrompt(featureName, requiredPlan);
            return false;
        }
    }
};

// Initialize after App is loaded
if (document.readyState === 'loading') {
    window.addEventListener('load', () => {
        // Wait a bit for App to be fully initialized
        setTimeout(() => Subscription.init(), 100);
    });
} else {
    // DOM already loaded, wait for App
    setTimeout(() => Subscription.init(), 100);
}

// Make available globally
window.Subscription = Subscription;
