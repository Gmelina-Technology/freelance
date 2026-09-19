@props(['title'])

<x-site-layout :title="$title.' | Freelance Manager'" :description="'The '.$title.' for Freelance Manager.'">
    <main class="mx-auto max-w-3xl px-6 py-16 md:py-20">
        <p class="font-mono text-xs uppercase tracking-[0.14em] text-brand-700 dark:text-green-400">Legal</p>
        <h1 class="mt-3 text-4xl font-extrabold leading-[1.1] tracking-tight md:text-5xl">{{ $title }}</h1>
        <p class="mt-3 font-mono text-xs text-brand-ink/50 dark:text-gray-500">
            Effective {{ \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y') }}
        </p>

        <div class="legal mt-10">
            {{ $slot }}
        </div>
    </main>
</x-site-layout>
