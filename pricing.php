<?php
$pageTitle = 'Pricing | Crm.Zingbot.io';
include_once 'includes/landing-header.php';
?>

<section class="pt-32 pb-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-5xl md:text-7xl font-black text-slate-900 mb-8 tracking-tight">
            Plans for every <span class="text-primary italic">stage</span>
        </h1>
        <p class="text-xl text-slate-500 max-w-2xl mx-auto font-medium">
            Join thousands of businesses who have scaled their operations with Crm.Zingbot.io. Transparent pricing, no hidden fees.
        </p>
    </div>
</section>

<section class="pb-32" id="pricing-details">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-center gap-6 mb-16 bg-slate-50 w-fit mx-auto px-8 py-3 rounded-2xl border border-slate-100">
            <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Monthly Billing</span>
            <button id="billing-toggle" class="w-16 h-9 bg-slate-200 rounded-full relative p-1 transition-all hover:bg-slate-300">
                <div id="toggle-circle" class="w-7 h-7 bg-white rounded-full absolute left-1 shadow-md transition-all"style="top:3px;"></div>
            </button>
            <span class="text-sm font-bold text-slate-900 uppercase tracking-widest flex items-center gap-2">
                Yearly Billing 
                <span class="px-2 py-0.5 bg-secondary/10 text-secondary text-[10px] rounded-md">-20%</span>
            </span>
        </div>

        <div id="plans-container" class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <!-- Dynamic loading -->
            <div class="col-span-full text-center py-20">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
                <p class="mt-4 text-slate-500 font-bold">Fetching latest plans...</p>
            </div>
        </div>
    </div>
</section>

<section class="py-24 bg-slate-50 border-y border-slate-100">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-12">Frequently Asked Questions</h2>
        <div class="grid gap-6 text-left">
            <div class="bg-white p-8 rounded-2xl border border-slate-100">
                <h4 class="font-bold text-lg mb-2">Can I switch plans later?</h4>
                <p class="text-slate-500">Yes! You can upgrade or downgrade your plan at any time from your dashboard settings.</p>
            </div>
            <div class="bg-white p-8 rounded-2xl border border-slate-100">
                <h4 class="font-bold text-lg mb-2">What is included in the free trial?</h4>
                <p class="text-slate-500">The 14-day free trial gives you full access to all features of your chosen plan. No credit card required.</p>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const plansContainer = document.getElementById('plans-container');
        const billingToggle = document.getElementById('billing-toggle');
        const toggleCircle = document.getElementById('toggle-circle');
        let isYearly = false;
        let allPlans = [];

        billingToggle.addEventListener('click', () => {
            isYearly = !isYearly;
            toggleCircle.classList.toggle('translate-x-0', !isYearly);
            toggleCircle.classList.toggle('translate-x-7', isYearly);
            renderPlans();
        });

        async function fetchPlans() {
            try {
                const response = await fetch('api/plans/list.php');
                const result = await response.json();
                if (result.success) {
                    allPlans = result.data;
                    renderPlans();
                } else {
                    plansContainer.innerHTML = '<p class="text-red-500 col-span-full text-center">Failed to load plans</p>';
                }
            } catch (error) {
                console.error('Error fetching plans:', error);
                plansContainer.innerHTML = '<p class="text-red-500 col-span-full text-center">Network error while fetching plans</p>';
            }
        }

        function renderPlans() {
            if (!allPlans.length) return;
            plansContainer.innerHTML = '';
            
            allPlans.forEach(plan => {
                const priceValue = isYearly ? plan.base_price_yearly : plan.base_price_monthly;
                const isEnterprise = plan.name.toLowerCase().includes('enterprise');
                const isCustomPricing = isEnterprise && priceValue == 0;
                
                const price = isCustomPricing ? 'Custom' : (plan.currency === 'INR' ? '' : '$') + priceValue;
                const period = isCustomPricing ? '' : (isYearly ? '/yr' : '/mo');
                const isFeatured = plan.name.toLowerCase() === 'growth' || plan.name.toLowerCase() === 'pro';
                

                const card = document.createElement('div');
                card.className = `p-10 bg-white border ${isFeatured ? 'border-2 border-primary shadow-2xl scale-105 z-10' : 'border-slate-100'} rounded-[2.5rem] flex flex-col relative transition-all hover:border-orange-200`;
                
                if (isFeatured) {
                    card.innerHTML += `<div class="absolute -top-5 left-1/2 -translate-x-1/2 bg-primary text-white text-[10px] font-black uppercase tracking-widest px-6 py-2 rounded-full shadow-lg shadow-orange-200">Best Value</div>`;
                }

                let featuresHtml = '';
                if (Array.isArray(plan.features)) {
                    plan.features.forEach(feat => {
                        featuresHtml += `
                            <li class="flex items-center gap-3 text-sm font-medium">
                                <span class="material-symbols-outlined ${isFeatured ? 'text-primary' : 'text-secondary'}">check_circle</span>
                                ${feat}
                            </li>
                        `;
                    });
                }

                card.innerHTML += `
                    <h3 class="text-xl font-bold mb-2">${plan.name}</h3>
                    <p class="text-slate-500 text-sm mb-8">${plan.description || 'Professional features for growing teams'}</p>
                    <div class="flex items-baseline gap-1 mb-8">
                        <span class="text-5xl font-black text-slate-900">${plan.currency === 'INR' ? '₹' : '$'}${price}</span>
                        <span class="text-slate-400 font-bold">${period}</span>
                    </div>
                    <ul class="space-y-4 mb-10 flex-grow">
                        ${featuresHtml}
                        <li class="flex items-center gap-3 text-sm font-medium">
                            <span class="material-symbols-outlined ${isFeatured ? 'text-primary' : 'text-secondary'}">check_circle</span>
                            ${plan.included_users} Included Users
                        </li>
                    </ul>
                    <a href="${isCustomPricing ? 'form.php?plan=enterprise' : 'register.php?plan_id=' + plan.id + '&billing_interval=' + (isYearly ? 'yearly' : 'monthly')}" class="w-full py-4 px-4 ${isFeatured ? 'bg-primary text-white hover:bg-orange-600 shadow-xl shadow-orange-200' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'} rounded-2xl font-bold transition-all text-center">
                        ${isCustomPricing ? 'Contact Us' : 'Start Trial'}
                    </a>
                `;
                plansContainer.appendChild(card);
            });
        }

        fetchPlans();
    });
</script>

<?php include_once 'includes/landing-footer.php'; ?>
