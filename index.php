<?php
require_once 'config/security.php';
Security::secureSession();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
$pageTitle = 'Home | Crm.Zingbot.io | CRM & WhatsApp Marketing';
include_once 'includes/landing-header.php';
?>

<!-- Hero -->
<section class="relative pt-36 pb-24 overflow-hidden">
    <div class="absolute top-20 -left-20 w-64 h-64 organic-shape -z-10 animate-pulse opacity-40" style="background: #EFF6FF"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 organic-shape -z-10 blur-3xl opacity-30" style="background: #DBEAFE"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <!-- Left: Copy -->
            <div class="text-left">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold uppercase tracking-wider mb-8" style="background: #EFF6FF; color: #2563EB">
                    <span class="flex h-2 w-2 rounded-full" style="background: #2563EB"></span>
                    Now with Creative Agency Flows
                </div>
                <h1 class="text-5xl md:text-6xl font-extrabold tracking-tight text-slate-900 leading-[1.1] mb-8">
                    The CRM that feels <span style="color: #2563EB" class="italic">human</span>.
                </h1>
                <p class="text-lg md:text-xl text-slate-600 mb-10 leading-relaxed max-w-xl">
                    Scale your creative agency or startup with a CRM built for collaboration. Automate your WhatsApp marketing without losing the personal touch.
                </p>
                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <a href="register.php"
                        class="w-full sm:w-auto text-white text-base font-bold px-10 py-4 rounded-2xl transition-all shadow-xl flex items-center justify-center gap-2 group"
                        style="background: #2563EB; box-shadow: 0 10px 30px rgba(37,99,235,0.25)"
                        onmouseover="this.style.background='#1D4ED8'"
                        onmouseout="this.style.background='#2563EB'">
                        Start 14-Day Free Trial
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
                <div class="mt-8 flex items-center gap-3 text-slate-400">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full border-2 border-white" style="background: #DBEAFE"></div>
                        <div class="w-8 h-8 rounded-full border-2 border-white" style="background: #CFFAFE"></div>
                        <div class="w-8 h-8 rounded-full border-2 border-white" style="background: #EFF6FF"></div>
                    </div>
                    <span class="text-sm font-medium">Loved by 2,000+ modern teams</span>
                </div>
            </div>

            <!-- Right: Visual -->
            <div class="relative">
                <div class="relative z-10 bg-white p-4 rounded-[2.5rem] shadow-2xl border transform lg:rotate-1" style="border-color: #EFF6FF; box-shadow: 0 25px 60px rgba(37,99,235,0.12)">
                    <div class="aspect-square rounded-[2rem] overflow-hidden relative group" style="background: #EFF6FF">
                        <img alt="CRM Dashboard Preview" class="w-full h-full object-cover rounded-[2rem]"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuAWqPyQqLOkTVlk31qNJLnAzZjTT-hDen91NFJNQwls6LWwPeHsgRiSpPhdUM29D5gPOC7uEoBDpCw3z2I810PvsM_yJF9d07qzT5aJhi1XWVRgeqfpP9QzsqM-KKagJCwfoLrCIFEN9Jq0Jr-A3w-MBRp2ZktrAK_WWR6KvKP6oTS0N8r-BAIA5zo0OwF3sRFKgFC61wx4weq3RXdr-qJsEqEyFUChZ_1uX9MEw0qZho7JVyWBE2UeYH0YID29LrznXENetPGk2sr_"/>
                        <!-- Floating notification card -->
                        <div class="absolute top-8 left-8 bg-white p-4 rounded-2xl shadow-xl border border-slate-100 max-w-[180px]" style="animation: float 4s ease-in-out infinite">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-2 h-2 rounded-full" style="background: #10B981"></div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">New Lead</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-700">"Hey! Interested in your services."</p>
                        </div>
                        <!-- Floating automation badge -->
                        <div class="absolute bottom-12 right-8 p-4 rounded-2xl shadow-xl max-w-[160px] text-white" style="background: #2563EB">
                            <div class="flex items-center gap-2 mb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <span class="text-[10px] font-bold uppercase">Automated</span>
                            </div>
                            <p class="text-xs font-medium">Follow-up sent via WhatsApp</p>
                        </div>
                    </div>
                </div>
                <div class="absolute -top-10 -right-10 w-36 h-36 organic-shape -z-10" style="background: #EFF6FF; opacity: 0.6"></div>
                <div class="absolute -bottom-10 -left-10 w-56 h-56 organic-shape -z-10" style="background: #DBEAFE; opacity: 0.4"></div>
            </div>
        </div>
    </div>
</section>

<style>
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
}
</style>

<!-- Features -->
<section class="py-28" id="features">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-20">
            <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-5 leading-tight">Everything you need, nothing you don't.</h2>
            <p class="text-slate-500 text-xl">Powerful tools designed for humans, not robots. Make every interaction count.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="p-9 bg-white border border-slate-100 rounded-2xl hover:shadow-xl hover:border-blue-100 transition-all group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 opacity-40 organic-shape translate-x-10 -translate-y-10" style="background: #EFF6FF"></div>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-7 group-hover:scale-110 transition-transform" style="background: #EFF6FF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #2563EB" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-3">Lead Capture</h3>
                <p class="text-slate-500 leading-relaxed text-sm">Turn social engagement into customers. Sync leads instantly from where your audience lives.</p>
            </div>
            <!-- Feature 2 -->
            <div class="p-9 bg-white border border-slate-100 rounded-2xl hover:shadow-xl hover:border-cyan-100 transition-all group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 opacity-40 organic-shape translate-x-10 -translate-y-10" style="background: #ECFEFF"></div>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-7 group-hover:scale-110 transition-transform" style="background: #ECFEFF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #06B6D4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-3">WhatsApp Magic</h3>
                <p class="text-slate-500 leading-relaxed text-sm">Personalized broadcasts that actually get read. Reach your clients on their favorite channel.</p>
            </div>
            <!-- Feature 3 -->
            <div class="p-9 bg-white border border-slate-100 rounded-2xl hover:shadow-xl hover:border-blue-100 transition-all group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 opacity-40 organic-shape translate-x-10 -translate-y-10" style="background: #EFF6FF"></div>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-7 group-hover:scale-110 transition-transform" style="background: #EFF6FF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="color: #2563EB" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-3">Friendly Bots</h3>
                <p class="text-slate-500 leading-relaxed text-sm">Build chatbots that sound like you. Qualify leads and book calls while you sleep.</p>
            </div>
        </div>
    </div>
</section>

<!-- Workflow -->
<section class="py-28 border-y border-slate-100" style="background: #F8FAFC" id="workflow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-20">
            <h2 class="text-4xl md:text-5xl font-extrabold text-slate-900 mb-5">How it works</h2>
            <p class="text-slate-500 text-lg">Simple steps to superpower your business.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-12">
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-6" style="background: #2563EB">
                    <span class="text-xl font-black text-white">1</span>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-900">Say Hello</h4>
                <p class="text-slate-500 text-sm px-4">Automatic lead capture from every corner of the web — forms, social, webhooks.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-6" style="background: #06B6D4">
                    <span class="text-xl font-black text-white">2</span>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-900">Spark Joy</h4>
                <p class="text-slate-500 text-sm px-4">Instant, personalized engagement that keeps leads warm and conversations flowing.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-6" style="background: #10B981">
                    <span class="text-xl font-black text-white">3</span>
                </div>
                <h4 class="text-xl font-bold mb-3 text-slate-900">Grow Together</h4>
                <p class="text-slate-500 text-sm px-4">Close deals and build lasting client relationships with zero friction.</p>
            </div>
        </div>
    </div>
</section>

<!-- Live Activity Ticker -->
<section class="py-10 bg-white border-b border-slate-100 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center gap-4 overflow-hidden whitespace-nowrap">
            <div class="flex items-center gap-2 px-4 py-2 rounded-full shadow-sm border" style="background: #EFF6FF; border-color: #DBEAFE">
                <span class="w-2 h-2 rounded-full" style="background: #10B981"></span>
                <span class="text-xs font-bold" style="color: #1E3A5F">LIVE: <span id="leads-count">24,582</span> Leads managed today</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-sm border border-slate-100">
                <span class="text-xs font-medium text-slate-400 italic">"Just converted a lead to won!" — Agency in NYC</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-sm border border-slate-100">
                <span class="text-xs font-medium text-slate-400 italic">"Sent 500 WhatsApp broadcasts" — Startup in London</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-sm border border-slate-100">
                <span class="text-xs font-medium text-slate-400 italic">"New trial started" — Design Studio in Berlin</span>
            </div>
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="py-28" id="pricing">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-5">Simple, fair pricing</h2>
            <p class="text-slate-500 text-lg mb-10">Transparent plans that scale with your team.</p>
            <div class="flex items-center justify-center gap-5 bg-white w-fit mx-auto px-7 py-3 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Monthly</span>
                <button id="billing-toggle" class="w-14 h-8 rounded-full relative p-0.5 transition-all border" style="background: #E2E8F0; border-color: #CBD5E1">
                    <div id="toggle-circle" class="w-7 h-7 bg-white rounded-full absolute shadow-sm transition-all" style="top: 2px; left: 2px;"></div>
                </button>
                <span class="text-sm font-semibold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    Yearly
                    <span class="px-2 py-0.5 text-[10px] rounded-lg font-bold" style="background: #EFF6FF; color: #2563EB">-20%</span>
                </span>
            </div>
        </div>

        <div id="plans-container" class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <div class="col-span-full text-center py-20">
                <div class="inline-block animate-spin rounded-full h-10 w-10 border-b-2" style="border-color: #2563EB"></div>
                <p class="mt-4 text-slate-500 font-medium">Loading plans...</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-24 px-4">
    <div class="max-w-5xl mx-auto rounded-3xl p-12 md:p-20 relative overflow-hidden text-center" style="background: #1E3A5F">
        <div class="absolute -top-24 -left-24 w-64 h-64 organic-shape opacity-20" style="background: #2563EB"></div>
        <div class="absolute -bottom-24 -right-24 w-64 h-64 organic-shape opacity-10" style="background: #06B6D4"></div>
        <div class="relative z-10">
            <h2 class="text-3xl md:text-5xl font-black text-white mb-6 leading-tight">
                Ready to grow your <span style="color: #60A5FA">sales pipeline?</span>
            </h2>
            <p class="text-blue-200 text-lg mb-10 max-w-xl mx-auto">Join thousands of teams using Crm.Zingbot.io to build better connections and close more deals.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="register.php"
                    class="w-full sm:w-auto px-10 py-4 font-bold text-base rounded-2xl transition-all active:scale-95 text-center text-white shadow-lg"
                    style="background: #2563EB; box-shadow: 0 8px 24px rgba(37,99,235,0.4)"
                    onmouseover="this.style.background='#1D4ED8'"
                    onmouseout="this.style.background='#2563EB'">
                    Start Your Free Trial
                </a>
            </div>
            <p class="mt-6 text-blue-300 text-sm">No credit card · Instant setup · 24/7 support</p>
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
            if (isYearly) {
                billingToggle.style.background = '#2563EB';
                toggleCircle.style.left = 'calc(100% - 30px)';
            } else {
                billingToggle.style.background = '#E2E8F0';
                toggleCircle.style.left = '2px';
            }
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
                const price = isCustomPricing ? 'Custom' : priceValue;
                const period = isCustomPricing ? '' : (isYearly ? '/yr' : '/mo');
                const isFeatured = plan.name.toLowerCase() === 'growth' || plan.name.toLowerCase() === 'pro';

                const card = document.createElement('div');
                card.className = `relative flex flex-col rounded-2xl p-8 transition-all`;
                card.style.cssText = isFeatured
                    ? 'background: #1E3A5F; border: 2px solid #2563EB; box-shadow: 0 20px 50px rgba(37,99,235,0.25); transform: scale(1.03); z-index: 10;'
                    : 'background: #fff; border: 1px solid #E2E8F0;';

                if (isFeatured) {
                    const badge = document.createElement('div');
                    badge.className = 'absolute -top-4 left-1/2 -translate-x-1/2 text-white text-[10px] font-black uppercase tracking-widest px-5 py-1.5 rounded-full shadow-lg';
                    badge.style.background = '#2563EB';
                    badge.textContent = 'Most Popular';
                    card.appendChild(badge);
                }

                let featuresHtml = '';
                if (Array.isArray(plan.features)) {
                    plan.features.forEach(feat => {
                        featuresHtml += `
                            <li class="flex items-center gap-2.5 text-sm ${isFeatured ? 'text-blue-100' : 'text-slate-600'}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" style="color: ${isFeatured ? '#60A5FA' : '#2563EB'}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                ${feat}
                            </li>`;
                    });
                }
                featuresHtml += `
                    <li class="flex items-center gap-2.5 text-sm ${isFeatured ? 'text-blue-100' : 'text-slate-600'}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" style="color: ${isFeatured ? '#60A5FA' : '#2563EB'}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        ${plan.included_users} Included Users
                    </li>`;

                const mainContent = document.createElement('div');
                mainContent.className = 'flex flex-col flex-1';
                mainContent.innerHTML = `
                    <h3 class="text-xl font-bold mb-1 ${isFeatured ? 'text-white' : 'text-slate-900'}">${plan.name}</h3>
                    <p class="text-sm mb-7 ${isFeatured ? 'text-blue-200' : 'text-slate-500'}">${plan.description || 'Flexible plan for growing teams'}</p>
                    <div class="flex items-baseline gap-1 mb-8">
                        <span class="text-4xl font-black ${isFeatured ? 'text-white' : 'text-slate-900'}">${plan.currency === 'INR' ? '₹' : '$'}${price}</span>
                        <span class="${isFeatured ? 'text-blue-300' : 'text-slate-400'} font-medium text-sm">${period}</span>
                    </div>
                    <ul class="space-y-3 mb-8 flex-grow">
                        ${featuresHtml}
                    </ul>
                    <a href="${isCustomPricing ? 'form.php?plan=enterprise' : 'register.php?plan_id=' + plan.id + '&billing_interval=' + (isYearly ? 'yearly' : 'monthly')}"
                       class="w-full py-3 px-4 rounded-xl font-semibold transition-all text-center text-sm"
                       style="${isFeatured
                           ? 'background: #2563EB; color: #fff; box-shadow: 0 4px 15px rgba(37,99,235,0.4)'
                           : 'background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE'}"
                       onmouseover="this.style.opacity='0.9'"
                       onmouseout="this.style.opacity='1'">
                        ${isCustomPricing ? 'Contact Us' : (isFeatured ? 'Start Free Trial' : 'Choose ' + plan.name)}
                    </a>
                `;
                card.appendChild(mainContent);
                plansContainer.appendChild(card);
            });
        }

        fetchPlans();
    });
</script>

<?php include_once 'includes/landing-footer.php'; ?>
