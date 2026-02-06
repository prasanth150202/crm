<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Lead Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 flex items-center justify-center h-screen">

    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Create Account</h1>
            <p class="text-gray-500 mt-2">Start managing your leads today</p>
        </div>

        <form id="registerForm" class="space-y-6">
            <div>
                <label for="org_name" class="block text-sm font-medium text-gray-700">Organization Name</label>
                <input type="text" id="org_name" name="org_name" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="Acme Corp">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input type="email" id="email" name="email" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="you@example.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" name="password" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="••••••••">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Choose a Plan</label>
                <div id="plan-options-container" class="mt-2 space-y-2">
                    <!-- Plan options will be loaded here -->
                    <p class="text-gray-500 text-sm">Loading plans...</p>
                </div>
            </div>

            <div>
                <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150">
                    Create Account
                </button>
            </div>
        </form>

        <div id="errorMessage" class="mt-4 text-center text-sm text-red-600 hidden"></div>

        <div class="mt-6 text-center text-sm text-gray-500">
            Already have an account? <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">Sign in</a>
        </div>
    </div>

    <script type="module" src="js/app.js?v=<?php echo time(); ?>"></script>
    <script type="module">
        const registerForm = document.getElementById('registerForm');
        const errorMessage = document.getElementById('errorMessage');
        const planOptionsContainer = document.getElementById('plan-options-container');

        async function fetchAndRenderPlans() {
            try {
                const response = await fetch('/leads2/api/plans/list.php');
                const result = await response.json();

                if (result.success && result.data.length > 0) {
                    planOptionsContainer.innerHTML = ''; // Clear loading text
                    result.data.forEach((plan, index) => {
                        const planId = plan.id;
                        const planName = plan.name;
                        const price = plan.base_price_monthly > 0 ? `₹${plan.base_price_monthly}/month` : 'Free';

                        const label = document.createElement('label');
                        label.className = 'flex items-center p-3 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50';
                        label.innerHTML = `
                            <input type="radio" name="plan" value="${planId}" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" ${index === 0 ? 'checked' : ''}>
                            <div class="ml-3 text-sm">
                                <span class="font-medium text-gray-900">${planName}</span>
                                <span class="text-gray-500 ml-2">${price}</span>
                            </div>
                        `;
                        planOptionsContainer.appendChild(label);
                    });
                } else {
                    planOptionsContainer.innerHTML = '<p class="text-red-500 text-sm">Could not load plans. Please try again later.</p>';
                }
            } catch (error) {
                planOptionsContainer.innerHTML = '<p class="text-red-500 text-sm">An error occurred while fetching plans.</p>';
            }
        }

        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMessage.classList.add('hidden');

            const orgName = document.getElementById('org_name').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const selectedPlan = document.querySelector('input[name="plan"]:checked');

            if (!selectedPlan) {
                errorMessage.textContent = 'Please select a plan.';
                errorMessage.classList.remove('hidden');
                return;
            }
            const planId = selectedPlan.value;

            try {
                // Assuming App.register will be updated to accept planId
                const result = await App.register(email, password, orgName, planId); 
                if (result.success) {
                    alert('Registration successful! Please sign in.');
                    window.location.href = 'login.php';
                } else {
                    errorMessage.textContent = result.error || 'Registration failed';
                    errorMessage.classList.remove('hidden');
                }
            } catch (error) {
                errorMessage.textContent = 'An error occurred. Please try again.';
                errorMessage.classList.remove('hidden');
            }
        });

        // Fetch and render plans when the page loads
        fetchAndRenderPlans();
    </script>
</body>

</html>