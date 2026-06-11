<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - CRM.Zingbot.io</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        input:focus { outline: none; }
        .plan-card input[type="radio"]:checked ~ .plan-card-inner {
            border-color: #2563EB;
            background: #EFF6FF;
        }
    </style>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body class="min-h-screen flex items-center justify-center py-10 px-4" style="background: #F8FAFC">

    <div class="w-full max-w-lg">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 mb-6">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: #2563EB">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="font-bold text-xl text-slate-900">CRM.Zingbot.io</span>
            </a>
            <h1 class="text-2xl font-bold text-slate-900 mb-1">Create your account</h1>
            <p class="text-sm text-slate-500">Start your 14-day free trial. No credit card required.</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-sm border p-8" style="border-color: #E2E8F0">

            <!-- Error Banner -->
            <div id="errorMessage" class="hidden mb-6 flex items-start gap-3 p-4 rounded-xl border" style="background: #FEF2F2; border-color: #FECACA">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" style="color: #EF4444" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium" style="color: #991B1B" id="errorText">An error occurred.</p>
            </div>

            <form id="registerForm" class="space-y-5">
                <!-- Section: Account Info -->
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider mb-4" style="color: #64748B">Account Information</p>
                    <div class="space-y-4">
                        <div>
                            <label for="org_name" class="block text-sm font-medium text-slate-700 mb-1.5">Organization Name</label>
                            <input type="text" id="org_name" name="org_name" required autocomplete="organization"
                                class="block w-full px-3.5 py-2.5 text-sm border rounded-xl shadow-sm transition-colors"
                                style="border-color: #E2E8F0; color: #0F172A; background: #FAFAFA"
                                onfocus="this.style.borderColor='#2563EB'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'; this.style.background='#fff'"
                                onblur="this.style.borderColor='#E2E8F0'; this.style.boxShadow='none'; this.style.background='#FAFAFA'"
                                placeholder="Acme Corp">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Work Email</label>
                            <input type="email" id="email" name="email" required autocomplete="email"
                                class="block w-full px-3.5 py-2.5 text-sm border rounded-xl shadow-sm transition-colors"
                                style="border-color: #E2E8F0; color: #0F172A; background: #FAFAFA"
                                onfocus="this.style.borderColor='#2563EB'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'; this.style.background='#fff'"
                                onblur="this.style.borderColor='#E2E8F0'; this.style.boxShadow='none'; this.style.background='#FAFAFA'"
                                placeholder="you@company.com">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                            <input type="password" id="password" name="password" required autocomplete="new-password"
                                class="block w-full px-3.5 py-2.5 text-sm border rounded-xl shadow-sm transition-colors"
                                style="border-color: #E2E8F0; color: #0F172A; background: #FAFAFA"
                                onfocus="this.style.borderColor='#2563EB'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'; this.style.background='#fff'"
                                onblur="this.style.borderColor='#E2E8F0'; this.style.boxShadow='none'; this.style.background='#FAFAFA'"
                                placeholder="Min. 8 characters">
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="border-t" style="border-color: #F1F5F9"></div>

                <!-- Section: Plan -->
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider mb-4" style="color: #64748B">Choose a Plan</p>
                    <div id="plan-options-container" class="space-y-3">
                        <div class="flex items-center gap-3 p-3 rounded-xl border animate-pulse" style="border-color: #E2E8F0">
                            <div class="w-4 h-4 rounded-full bg-slate-200"></div>
                            <div class="h-4 bg-slate-200 rounded w-32"></div>
                            <div class="h-4 bg-slate-100 rounded w-16 ml-auto"></div>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl border animate-pulse" style="border-color: #E2E8F0">
                            <div class="w-4 h-4 rounded-full bg-slate-200"></div>
                            <div class="h-4 bg-slate-200 rounded w-24"></div>
                            <div class="h-4 bg-slate-100 rounded w-16 ml-auto"></div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" id="submitBtn"
                    class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-sm font-semibold text-white rounded-xl shadow-sm transition-colors focus:outline-none disabled:opacity-60 disabled:cursor-not-allowed"
                    style="background: #2563EB"
                    onmouseover="if(!this.disabled) this.style.background='#1D4ED8'"
                    onmouseout="if(!this.disabled) this.style.background='#2563EB'">
                    <span id="btnText">Create Account</span>
                    <svg id="btnLoader" class="hidden animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>
            </form>

            <p class="mt-5 text-center text-xs text-slate-400">
                By creating an account, you agree to our
                <a href="terms.php" class="underline hover:text-slate-600">Terms</a> and
                <a href="privacy.php" class="underline hover:text-slate-600">Privacy Policy</a>.
            </p>
        </div>

        <p class="mt-5 text-center text-sm text-slate-500">
            Already have an account?
            <a href="login.php" class="font-semibold transition-colors" style="color: #2563EB"
                onmouseover="this.style.color='#1D4ED8'" onmouseout="this.style.color='#2563EB'">Sign in</a>
        </p>
    </div>

    <script type="module" src="js/app.js?v=<?php echo defined('APP_VERSION') ? APP_VERSION : '1'; ?>"></script>
    <script type="module">
        const registerForm = document.getElementById('registerForm');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const planOptionsContainer = document.getElementById('plan-options-container');

        async function fetchAndRenderPlans() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const preSelectedPlanId = urlParams.get('plan_id');
                const billingInterval = urlParams.get('billing_interval') || 'monthly';

                const response = await fetch('api/plans/list.php');
                const result = await response.json();

                if (result.success && result.data.length > 0) {
                    planOptionsContainer.innerHTML = '';
                    const selectablePlans = result.data.filter(plan => {
                        const isEnterprise = plan.name.toLowerCase().includes('enterprise');
                        const priceValue = billingInterval === 'yearly' ? plan.base_price_yearly : plan.base_price_monthly;
                        return !(isEnterprise && priceValue == 0);
                    });

                    selectablePlans.forEach((plan, index) => {
                        const planId = plan.id;
                        const planName = plan.name;
                        const isYearly = billingInterval === 'yearly';
                        const priceValue = isYearly ? plan.base_price_yearly : plan.base_price_monthly;
                        const price = priceValue > 0 ? `₹${priceValue}/${isYearly ? 'yr' : 'mo'}` : 'Free';
                        const isChecked = preSelectedPlanId
                            ? (planId.toString() === preSelectedPlanId)
                            : (index === 0);

                        const label = document.createElement('label');
                        label.className = 'flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-all';
                        label.style.cssText = `border-color: ${isChecked ? '#2563EB' : '#E2E8F0'}; background: ${isChecked ? '#EFF6FF' : '#fff'}`;
                        label.innerHTML = `
                            <input type="radio" name="plan" value="${planId}" class="h-4 w-4 border-slate-300 focus:ring-blue-500" style="accent-color: #2563EB" ${isChecked ? 'checked' : ''}>
                            <div class="flex-1 min-w-0">
                                <span class="text-sm font-semibold text-slate-900">${planName}</span>
                            </div>
                            <span class="text-sm font-medium" style="color: #2563EB">${price}</span>
                        `;
                        label.querySelector('input').addEventListener('change', () => {
                            planOptionsContainer.querySelectorAll('label').forEach(l => {
                                l.style.borderColor = '#E2E8F0';
                                l.style.background = '#fff';
                            });
                            label.style.borderColor = '#2563EB';
                            label.style.background = '#EFF6FF';
                        });
                        planOptionsContainer.appendChild(label);
                    });
                } else {
                    planOptionsContainer.innerHTML = '<p class="text-sm" style="color: #EF4444">Could not load plans. Please refresh and try again.</p>';
                }
            } catch (error) {
                planOptionsContainer.innerHTML = '<p class="text-sm" style="color: #EF4444">An error occurred while fetching plans.</p>';
            }
        }

        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMessage.classList.add('hidden');

            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');

            const orgName = document.getElementById('org_name').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const selectedPlan = document.querySelector('input[name="plan"]:checked');

            if (!selectedPlan) {
                errorText.textContent = 'Please select a plan to continue.';
                errorMessage.classList.remove('hidden');
                return;
            }

            submitBtn.disabled = true;
            btnText.textContent = 'Creating Account...';
            btnLoader.classList.remove('hidden');

            const planId = selectedPlan.value;

            try {
                const urlParams = new URLSearchParams(window.location.search);
                const billingInterval = urlParams.get('billing_interval') || 'monthly';

                const result = await App.register(email, password, orgName, planId, billingInterval);
                if (result.success || result.message === "Registration successful") {
                    if (result.razorpay_checkout_required) {
                        const options = {
                            "key": result.razorpay_key,
                            "subscription_id": result.razorpay_subscription_id,
                            "name": "CRM.Zingbot.io",
                            "description": "Subscription Payment",
                            "handler": function (response) {
                                alert("Payment successful! Your account is now active.");
                                window.location.href = 'login.php';
                            },
                            "prefill": { "name": orgName, "email": email },
                            "theme": { "color": "#2563EB" },
                            "modal": {
                                "ondismiss": function() {
                                    submitBtn.disabled = false;
                                    btnText.textContent = 'Create Account';
                                    btnLoader.classList.add('hidden');
                                    errorText.textContent = 'Account created but inactive. Please complete payment to activate.';
                                    errorMessage.classList.remove('hidden');
                                }
                            }
                        };
                        const rzp = new Razorpay(options);
                        rzp.open();
                    } else {
                        alert('Registration successful! Please sign in.');
                        window.location.href = 'login.php';
                    }
                } else {
                    errorText.textContent = result.error || 'Registration failed. Please try again.';
                    errorMessage.classList.remove('hidden');
                    submitBtn.disabled = false;
                    btnText.textContent = 'Create Account';
                    btnLoader.classList.add('hidden');
                }
            } catch (error) {
                errorText.textContent = 'An error occurred. Please try again.';
                errorMessage.classList.remove('hidden');
                submitBtn.disabled = false;
                btnText.textContent = 'Create Account';
                btnLoader.classList.add('hidden');
            }
        });

        fetchAndRenderPlans();
    </script>
</body>
</html>
