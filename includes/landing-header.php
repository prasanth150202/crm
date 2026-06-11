<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo $pageTitle ?? 'Crm.Zingbot.io | CRM & WhatsApp Marketing'; ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2563EB",
                        "primary-dark": "#1D4ED8",
                        "primary-soft": "#EFF6FF",
                        "accent": "#06B6D4",
                        "accent-soft": "#ECFEFF",
                        "sidebar": "#1E3A5F",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg": "1rem",
                        "xl": "1.5rem",
                        "2xl": "2rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style type="text/tailwindcss">
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
        }
        .organic-shape {
            border-radius: 60% 40% 70% 30% / 40% 50% 60% 50%;
        }
        .glass-nav {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        h1, h2, h3, h4 {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="text-slate-800 transition-colors duration-300">
<nav class="fixed top-0 w-full z-50 glass-nav border-b border-slate-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-18 py-4">
            <!-- Logo -->
            <a href="index.php" class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: #2563EB">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-slate-900">Crm.Zingbot<span style="color: #2563EB">.io</span></span>
            </a>

            <!-- Nav links -->
            <div class="hidden md:flex items-center gap-8">
                <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="index.php#features">Features</a>
                <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="index.php#workflow">Workflow</a>
                <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="pricing.php">Pricing</a>
                <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="about.php">About</a>
                <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="blog.php">Blog</a>
                <a class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors" href="form.php">Contact</a>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <a href="login.php" class="hidden sm:block text-sm font-semibold px-4 py-2 text-slate-700 hover:text-blue-600 transition-colors rounded-lg hover:bg-blue-50">
                    Login
                </a>
                <a href="register.php"
                    class="text-sm font-semibold px-5 py-2.5 rounded-xl text-white transition-all shadow-md active:scale-95"
                    style="background: #2563EB"
                    onmouseover="this.style.background='#1D4ED8'"
                    onmouseout="this.style.background='#2563EB'">
                    Get Started Free
                </a>
            </div>
        </div>
    </div>
</nav>
