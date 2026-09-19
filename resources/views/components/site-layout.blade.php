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
