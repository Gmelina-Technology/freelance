<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />


    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="bg-white dark:bg-[#0a0a0a] text-gray-900 dark:text-gray-100">
    <!-- Navigation Header -->
    <header class="sticky top-0 z-50 bg-white dark:bg-[#0a0a0a] border-b border-gray-200 dark:border-gray-800">
        <nav class="max-w-7xl mx-auto px-6 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                    </path>
                </svg>
                <span class="font-bold text-xl text-gray-900 dark:text-white">Freelance Manager</span>
            </div>

            <div class="hidden md:flex items-center gap-8">
                <a href="#features"
                    class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">Features</a>
                <a href="#how-it-works"
                    class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">How It Works</a>
            </div>

            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ url('/app') }}"
                        class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">Dashboard</a>
                @else
                    <a href="{{ route('filament.app.auth.login') }}"
                        class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">Log
                        in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Sign up</a>
                    @endif
                @endauth
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section
        class="relative min-h-screen flex items-center justify-center bg-gradient-to-b from-white to-gray-50 dark:from-[#0a0a0a] dark:to-[#1a1a1a] overflow-hidden">
        <div class="absolute inset-0 opacity-10 dark:opacity-5">
            <div class="absolute top-10 right-10 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl">
            </div>
            <div
                class="absolute bottom-10 left-10 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl">
            </div>
        </div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 py-20 text-center">
            <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold text-gray-900 dark:text-white mb-6 leading-tight">
                Manage Your Freelance Projects with Ease
            </h1>
            <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto mb-10">
                All-in-one platform to organize projects, track tasks, manage clients, and collaborate seamlessly with
                your team
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @auth
                    <a href="{{ url('/app') }}"
                        class="px-8 py-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('filament.app.auth.login') }}"
                        class="px-8 py-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                        Get Started Free
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('filament.app.auth.register') }}"
                            class="px-8 py-4 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900 transition">
                            Learn More
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white dark:bg-[#0a0a0a] border-t border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Powerful Features</h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">Everything you need to manage your freelance
                    business effectively</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div
                    class="p-8 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 hover:border-blue-500 dark:hover:border-blue-500 transition">
                    <svg class="w-12 h-12 text-blue-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                        </path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Project Management</h3>
                    <p class="text-gray-600 dark:text-gray-400">Organize and track all your projects with clear
                        deadlines, status updates, and client information</p>
                </div>

                <!-- Feature 2 -->
                <div
                    class="p-8 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 hover:border-blue-500 dark:hover:border-blue-500 transition">
                    <svg class="w-12 h-12 text-blue-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Task Tracking</h3>
                    <p class="text-gray-600 dark:text-gray-400">Create, assign, and monitor tasks with priority levels,
                        due dates, and progress indicators</p>
                </div>

                <!-- Feature 3 -->
                <div
                    class="p-8 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 hover:border-blue-500 dark:hover:border-blue-500 transition">
                    <svg class="w-12 h-12 text-blue-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a6 6 0 11-12 0 6 6 0 0112 0z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Team Collaboration</h3>
                    <p class="text-gray-600 dark:text-gray-400">Invite team members, assign responsibilities, and
                        collaborate in real-time on projects</p>
                </div>

                <!-- Feature 4 -->
                <div
                    class="p-8 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 hover:border-blue-500 dark:hover:border-blue-500 transition">
                    <svg class="w-12 h-12 text-blue-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Client Management</h3>
                    <p class="text-gray-600 dark:text-gray-400">Store client details, communication history, and project
                        references all in one place</p>
                </div>

                <!-- Feature 5 -->
                <div
                    class="p-8 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 hover:border-blue-500 dark:hover:border-blue-500 transition">
                    <svg class="w-12 h-12 text-blue-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Analytics & Reports</h3>
                    <p class="text-gray-600 dark:text-gray-400">Get insights into project progress, team productivity,
                        and business metrics</p>
                </div>

                <!-- Feature 6 -->
                <div
                    class="p-8 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 hover:border-blue-500 dark:hover:border-blue-500 transition">
                    <svg class="w-12 h-12 text-blue-600 mb-4" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Fast & Reliable</h3>
                    <p class="text-gray-600 dark:text-gray-400">Lightning-fast performance with 99.9% uptime to keep
                        your business running smoothly</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-20 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">How It Works</h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">Get started in three simple steps</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div
                        class="flex items-center justify-center w-16 h-16 bg-blue-600 text-white rounded-full mx-auto mb-6 text-2xl font-bold">
                        1</div>
                    <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-2">Create Your Account</h3>
                    <p class="text-gray-600 dark:text-gray-400">Sign up quickly and set up your workspace in minutes
                    </p>
                </div>

                <div class="text-center">
                    <div
                        class="flex items-center justify-center w-16 h-16 bg-blue-600 text-white rounded-full mx-auto mb-6 text-2xl font-bold">
                        2</div>
                    <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-2">Add Your Projects</h3>
                    <p class="text-gray-600 dark:text-gray-400">Create projects, add clients, and organize your work
                    </p>
                </div>

                <div class="text-center">
                    <div
                        class="flex items-center justify-center w-16 h-16 bg-blue-600 text-white rounded-full mx-auto mb-6 text-2xl font-bold">
                        3</div>
                    <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-2">Collaborate & Deliver</h3>
                    <p class="text-gray-600 dark:text-gray-400">Manage tasks, track progress, and deliver excellence
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-blue-600 dark:bg-blue-950">
        <div class="max-w-4xl mx-auto px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold text-white mb-6">Ready to transform how you work?</h2>
            <p class="text-xl text-blue-100 mb-10">Join thousands of freelancers and teams already using Freelance
                Manager</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @auth
                    <a href="{{ url('/dashboard') }}"
                        class="px-8 py-4 bg-white text-blue-600 font-medium rounded-lg hover:bg-gray-100 transition">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('filament.app.auth.login') }}"
                        class="px-8 py-4 bg-white text-blue-600 font-medium rounded-lg hover:bg-gray-100 transition">
                        Start Free Trial
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                            class="px-8 py-4 border-2 border-white text-white font-medium rounded-lg hover:bg-blue-700 transition">
                            Create Account
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 dark:bg-black text-gray-300 py-16">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                            </path>
                        </svg>
                        <span class="font-bold text-white text-lg">Freelance Manager</span>
                    </div>
                    <p class="text-sm">The all-in-one platform for freelance project management</p>
                </div>

                <!-- Product -->
                <div>
                    <h4 class="font-semibold text-white mb-4">Product</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#features" class="hover:text-white transition">Features</a></li>
                        <li><a href="#pricing" class="hover:text-white transition">Pricing</a></li>
                        <li><a href="#" class="hover:text-white transition">Security</a></li>
                        <li><a href="#" class="hover:text-white transition">Roadmap</a></li>
                    </ul>
                </div>

                <!-- Company -->
                <div>
                    <h4 class="font-semibold text-white mb-4">Company</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">About</a></li>
                        <li><a href="#" class="hover:text-white transition">Blog</a></li>
                        <li><a href="#" class="hover:text-white transition">Careers</a></li>
                        <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="font-semibold text-white mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-white transition">Cookies</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm">© 2024 Freelance Manager. All rights reserved.</p>
                    <div class="flex gap-6 mt-4 md:mt-0">
                        <a href="#" class="hover:text-white transition">Twitter</a>
                        <a href="#" class="hover:text-white transition">LinkedIn</a>
                        <a href="#" class="hover:text-white transition">GitHub</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>
