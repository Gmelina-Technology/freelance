@props([
    'title' => 'Freelance Manager: quotes, tasks and invoices for solo studios',
    'description' => 'Track the work, send the quote, get paid. A small tool for freelancers and tiny teams.',
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">

    {{-- Runs before first paint to avoid a flash of the wrong theme. Shares Filament's `theme` key, so the choice carries into the app. --}}
    <script>
        (() => {
            const root = document.documentElement;
            const stored = () => {
                try {
                    return localStorage.getItem('theme');
                } catch (e) {
                    return null;
                }
            };
            const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;
            const apply = () => root.classList.toggle('dark', stored() === 'dark' || (stored() !== 'light' && prefersDark()));

            apply();

            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-theme-toggle]')) {
                    return;
                }

                try {
                    localStorage.setItem('theme', root.classList.contains('dark') ? 'light' : 'dark');
                } catch (e) {
                    root.classList.toggle('dark');

                    return;
                }

                apply();
            });
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('fonts/filament/filament/inter/index.css') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-brand-soft text-brand-ink antialiased dark:bg-[#08110c] dark:text-gray-100">
    @php
        $loginUrl = route('filament.app.auth.login');
        $registerUrl = route('filament.app.auth.register');
    @endphp

    <header
        class="sticky top-0 z-50 border-b border-brand-line bg-brand-soft/85 backdrop-blur dark:border-white/10 dark:bg-[#08110c]/85">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3.5">
            <a href="/" class="flex items-center gap-2.5 font-extrabold tracking-tight">
                <span class="grid size-7 place-items-center rounded-lg bg-brand-700 text-sm text-white">F</span>
                Freelance Manager
            </a>

            <div class="hidden items-center gap-8 text-sm font-medium text-brand-ink/70 md:flex dark:text-gray-400">
                <a href="{{ url('/') }}#what-you-get" class="transition hover:text-brand-700">What you get</a>
                <a href="{{ url('/') }}#flow" class="transition hover:text-brand-700">How it flows</a>
            </div>

            <div class="flex items-center gap-2 text-sm font-semibold">
                <button type="button" data-theme-toggle aria-label="Switch between light and dark theme"
                    class="grid size-9 place-items-center rounded-lg text-brand-ink/70 transition hover:text-brand-700 dark:text-gray-300 dark:hover:text-green-400">
                    <svg class="size-5 dark:hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                    <svg class="hidden size-5 dark:block" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                </button>
                @auth
                    <a href="{{ url('/app') }}"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-white transition hover:bg-brand-800">Open app</a>
                @else
                    <a href="{{ $loginUrl }}" class="px-3 py-2 text-brand-ink/70 transition hover:text-brand-700 dark:text-gray-300">Log in</a>
                    <a href="{{ $registerUrl }}"
                        class="rounded-lg bg-brand-700 px-4 py-2 text-white transition hover:bg-brand-800">Sign up</a>
                @endauth
            </div>
        </nav>
    </header>

    {{ $slot }}

    <footer class="border-t border-brand-line dark:border-white/10">
        <div class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-3 px-6 py-8 text-sm text-brand-ink/60 sm:flex-row sm:items-center dark:text-gray-500">
            <p>© {{ date('Y') }} Freelance Manager</p>
            <div class="flex flex-wrap gap-x-6 gap-y-2 font-medium">
                <a href="{{ route('terms') }}" class="transition hover:text-brand-700">Terms</a>
                <a href="{{ route('privacy') }}" class="transition hover:text-brand-700">Privacy</a>
                <a href="{{ $loginUrl }}" class="transition hover:text-brand-700">Log in</a>
                <a href="{{ $registerUrl }}" class="transition hover:text-brand-700">Sign up</a>
            </div>
        </div>
    </footer>
</body>

</html>
