<?php
/**
 * Login Page
 */
require_once 'config/security.php';

Security::secureSession();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - CRM.Zingbot.io</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .left-panel {
            background: linear-gradient(145deg, #1E3A5F 0%, #1a3356 40%, #142a47 100%);
        }
        .feature-chip {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(4px);
        }
        input:focus { outline: none; }
    </style>
</head>
<body class="h-screen flex overflow-hidden bg-slate-50">

    <!-- Left Panel — Branding -->
    <div class="hidden lg:flex lg:w-1/2 left-panel flex-col justify-between p-12 relative overflow-hidden">
        <!-- Decorative circles -->
        <div class="absolute top-0 right-0 w-72 h-72 rounded-full opacity-10" style="background: radial-gradient(circle, #2563EB, transparent); transform: translate(30%, -30%)"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 rounded-full opacity-10" style="background: radial-gradient(circle, #06B6D4, transparent); transform: translate(-30%, 30%)"></div>
        <div class="absolute top-1/2 left-1/2 w-48 h-48 rounded-full opacity-5" style="background: #2563EB; transform: translate(-50%, -60%)"></div>

        <!-- Logo -->
        <div class="flex items-center gap-3 relative z-10">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: #2563EB">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <span class="text-white font-bold text-lg tracking-tight">CRM.Zingbot.io</span>
        </div>

        <!-- Main copy -->
        <div class="relative z-10">
            <h2 class="text-4xl font-extrabold text-white leading-tight mb-4">
                Welcome back.<br>
                <span style="color: #93C5FD">Your leads are waiting.</span>
            </h2>
            <p class="text-blue-200 text-base leading-relaxed mb-8 max-w-sm">
                Sign in to manage your pipeline, track conversations, and close more deals — all in one place.
            </p>

            <!-- Feature chips -->
            <div class="flex flex-col gap-3">
                <div class="feature-chip flex items-center gap-3 px-4 py-3 rounded-xl w-fit">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(37,99,235,0.4)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <span class="text-white text-sm font-medium">Smart Lead Management</span>
                </div>
                <div class="feature-chip flex items-center gap-3 px-4 py-3 rounded-xl w-fit">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(6,182,212,0.3)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-cyan-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="text-white text-sm font-medium">WhatsApp Automation</span>
                </div>
                <div class="feature-chip flex items-center gap-3 px-4 py-3 rounded-xl w-fit">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(16,185,129,0.3)">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <span class="text-white text-sm font-medium">Real-time Analytics</span>
                </div>
            </div>
        </div>

        <!-- Footer note -->
        <p class="text-blue-300 text-xs relative z-10">© 2025 CRM.Zingbot.io · All rights reserved</p>
    </div>

    <!-- Right Panel — Form -->
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 bg-white lg:px-12">
        <!-- Mobile logo -->
        <div class="flex items-center gap-2 mb-8 lg:hidden">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: #2563EB">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <span class="font-bold text-lg text-slate-900">CRM.Zingbot.io</span>
        </div>

        <div class="w-full max-w-sm">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-slate-900 mb-1">Sign in to your account</h1>
                <p class="text-sm text-slate-500">Enter your credentials to continue</p>
            </div>

            <!-- Error Banner -->
            <div id="errorMessage" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl border" style="background: #FEF2F2; border-color: #FECACA;">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 mt-0.5" style="color: #EF4444" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium" style="color: #991B1B" id="errorText">An error occurred.</p>
            </div>

            <form id="loginForm" class="space-y-5">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                        class="block w-full px-3.5 py-2.5 text-sm border rounded-xl shadow-sm transition-colors"
                        style="border-color: #E2E8F0; color: #0F172A; background: #FAFAFA"
                        onfocus="this.style.borderColor='#2563EB'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'; this.style.background='#fff'"
                        onblur="this.style.borderColor='#E2E8F0'; this.style.boxShadow='none'; this.style.background='#FAFAFA'"
                        placeholder="you@company.com">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            class="block w-full px-3.5 py-2.5 text-sm border rounded-xl shadow-sm transition-colors pr-10"
                            style="border-color: #E2E8F0; color: #0F172A; background: #FAFAFA"
                            onfocus="this.style.borderColor='#2563EB'; this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'; this.style.background='#fff'"
                            onblur="this.style.borderColor='#E2E8F0'; this.style.boxShadow='none'; this.style.background='#FAFAFA'"
                            placeholder="••••••••">
                        <button type="button" id="togglePassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember + Forgot -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input id="remember-me" name="remember-me" type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 transition-colors"
                            style="accent-color: #2563EB">
                        <span class="text-sm text-slate-600">Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="text-sm font-medium transition-colors" style="color: #2563EB"
                        onmouseover="this.style.color='#1D4ED8'" onmouseout="this.style.color='#2563EB'">
                        Forgot password?
                    </a>
                </div>

                <!-- Submit -->
                <button type="submit" id="submitBtn"
                    class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-sm font-semibold text-white rounded-xl shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                    style="background: #2563EB; focus-ring-color: #2563EB"
                    onmouseover="this.style.background='#1D4ED8'"
                    onmouseout="this.style.background='#2563EB'">
                    <span id="btnText">Sign in</span>
                    <svg id="btnSpinner" class="hidden animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>
            </form>

            <!-- Sign up link -->
            <p class="mt-6 text-center text-sm text-slate-500">
                Don't have an account?
                <a href="register.php" class="font-semibold transition-colors" style="color: #2563EB"
                    onmouseover="this.style.color='#1D4ED8'" onmouseout="this.style.color='#2563EB'">
                    Start free trial
                </a>
            </p>
        </div>
    </div>

    <script type="module" src="js/app.js?v=<?php echo defined('APP_VERSION') ? APP_VERSION : '1'; ?>"></script>
    <script type="module">
        const loginForm = document.getElementById('loginForm');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        // URL error param
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'payment_required') {
            errorText.textContent = 'Subscription payment pending. Please complete your registration to access the dashboard.';
            errorMessage.classList.remove('hidden');
        }

        // Password toggle
        document.getElementById('togglePassword').addEventListener('click', () => {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />`;
            } else {
                pwd.type = 'password';
                icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
            }
        });

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMessage.classList.add('hidden');
            submitBtn.disabled = true;
            btnText.textContent = 'Signing in...';
            btnSpinner.classList.remove('hidden');
            submitBtn.style.background = '#1D4ED8';

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            try {
                const result = await App.login(email, password);
                if (result.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    errorText.textContent = result.error || 'Login failed. Please check your credentials.';
                    errorMessage.classList.remove('hidden');
                    submitBtn.disabled = false;
                    btnText.textContent = 'Sign in';
                    btnSpinner.classList.add('hidden');
                    submitBtn.style.background = '#2563EB';
                }
            } catch (error) {
                errorText.textContent = 'An error occurred. Please try again.';
                errorMessage.classList.remove('hidden');
                submitBtn.disabled = false;
                btnText.textContent = 'Sign in';
                btnSpinner.classList.add('hidden');
                submitBtn.style.background = '#2563EB';
            }
        });
    </script>
</body>
</html>
