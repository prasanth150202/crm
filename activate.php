<?php require_once __DIR__ . '/config/env.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Account - Lead Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .password-requirement {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .password-requirement.met {
            color: #10b981;
        }
        .password-requirement.unmet {
            color: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen py-8">

    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
        <div id="loadingState" class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Validating invitation...</p>
        </div>

        <div id="invalidState" class="hidden text-center">
            <div class="text-red-600 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Invalid Invitation</h2>
            <p id="errorMessage" class="text-gray-600 mb-6"></p>
            <a href="login.php" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Go to Login
            </a>
        </div>

        <div id="activationForm" class="hidden">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Activate Your Account</h1>
                <p class="text-gray-500 mt-2">Welcome to <span id="orgName" class="font-semibold"></span></p>
            </div>

            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-gray-700">
                    <strong>Email:</strong> <span id="inviteEmail"></span><br>
                    <strong>Role:</strong> <span id="inviteRole" class="capitalize"></span>
                </p>
            </div>

            <form id="activateForm" class="space-y-6">
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder="John Doe">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder="••••••••">
                    <div id="passwordRequirements" class="mt-2 space-y-1">
                        <div class="password-requirement unmet" data-requirement="length">
                            <span class="requirement-icon">✗</span> At least 8 characters
                        </div>
                        <div class="password-requirement unmet" data-requirement="uppercase">
                            <span class="requirement-icon">✗</span> One uppercase letter
                        </div>
                        <div class="password-requirement unmet" data-requirement="lowercase">
                            <span class="requirement-icon">✗</span> One lowercase letter
                        </div>
                        <div class="password-requirement unmet" data-requirement="number">
                            <span class="requirement-icon">✗</span> One number
                        </div>
                        <div class="password-requirement unmet" data-requirement="special">
                            <span class="requirement-icon">✗</span> One special character
                        </div>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        placeholder="••••••••">
                    <p id="passwordMatchError" class="hidden mt-1 text-sm text-red-600">Passwords do not match</p>
                </div>

                <div>
                    <button type="submit" id="submitBtn"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150">
                        Activate Account
                    </button>
                </div>
            </form>

            <div id="formError" class="mt-4 text-center text-sm text-red-600 hidden"></div>
        </div>

        <div id="successState" class="hidden text-center">
            <div class="text-green-600 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Account Activated!</h2>
            <p class="text-gray-600 mb-6">Your account has been successfully activated. You can now log in.</p>
            <a href="login.php" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Go to Login
            </a>
        </div>
    </div>

    <script>
        const APP_URL = "<?php echo htmlspecialchars(Env::get('APP_URL'), ENT_QUOTES); ?>";
        console.log('DEBUG APP_URL:', APP_URL);
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        let invitationData = null;

        // Validate token on page load
        async function validateInvitation() {
            if (!token) {
                showInvalidState('No invitation token provided');
                return;
            }

            try {
                const response = await fetch(`${APP_URL}/api/invitations/validate.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token })
                });

                const data = await response.json();

                if (data.success) {
                    invitationData = data.data;
                    showActivationForm();
                } else {
                    showInvalidState(data.error || 'Invalid invitation token');
                }
            } catch (error) {
                console.error('Validation error:', error);
                showInvalidState('Failed to validate invitation. Please try again.');
            }
        }

        function showInvalidState(message) {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('invalidState').classList.remove('hidden');
            document.getElementById('errorMessage').textContent = message;
        }

        function showActivationForm() {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('activationForm').classList.remove('hidden');
            document.getElementById('orgName').textContent = invitationData.org_name;
            document.getElementById('inviteEmail').textContent = invitationData.email;
            document.getElementById('inviteRole').textContent = invitationData.role;
        }

        function showSuccessState() {
            document.getElementById('activationForm').classList.add('hidden');
            document.getElementById('successState').classList.remove('hidden');
        }

        // Password validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatchError = document.getElementById('passwordMatchError');

        passwordInput?.addEventListener('input', function() {
            const password = this.value;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            Object.keys(requirements).forEach(req => {
                const element = document.querySelector(`[data-requirement="${req}"]`);
                if (requirements[req]) {
                    element.classList.remove('unmet');
                    element.classList.add('met');
                    element.querySelector('.requirement-icon').textContent = '✓';
                } else {
                    element.classList.remove('met');
                    element.classList.add('unmet');
                    element.querySelector('.requirement-icon').textContent = '✗';
                }
            });
        });

        confirmPasswordInput?.addEventListener('input', function() {
            if (this.value && this.value !== passwordInput.value) {
                passwordMatchError.classList.remove('hidden');
            } else {
                passwordMatchError.classList.add('hidden');
            }
        });

        // Form submission
        document.getElementById('activateForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const fullName = document.getElementById('full_name').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const formError = document.getElementById('formError');
            const submitBtn = document.getElementById('submitBtn');

            formError.classList.add('hidden');

            if (password !== confirmPassword) {
                formError.textContent = 'Passwords do not match';
                formError.classList.remove('hidden');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Activating...';

            try {
                const response = await fetch(`${APP_URL}/api/invitations/activate.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token,
                        password,
                        full_name: fullName
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showSuccessState();
                } else {
                    if (data.password_errors && data.password_errors.length > 0) {
                        formError.textContent = data.password_errors.join('. ');
                    } else {
                        formError.textContent = data.error || 'Activation failed';
                    }
                    formError.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Activate Account';
                }
            } catch (error) {
                console.error('Activation error:', error);
                formError.textContent = 'An error occurred. Please try again.';
                formError.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Activate Account';
            }
        });

        // Initialize
        validateInvitation();
    </script>
</body>
</html>
