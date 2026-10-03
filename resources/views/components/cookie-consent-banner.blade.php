{{-- Markup only. Shown by html[data-cookie-banner=open], which <x-cookie-consent-script> sets in <head>. --}}
<section id="cookie-banner" data-cookie-banner role="region" aria-labelledby="cookie-banner-title"
    class="fixed inset-x-0 bottom-0 z-[60] hidden border-t border-brand-line bg-white text-brand-ink shadow-lg [html[data-cookie-banner=open]_&]:block dark:border-white/10 dark:bg-[#0d1a13] dark:text-gray-100">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-5 md:flex-row md:items-center md:justify-between md:gap-8">
        <div class="text-sm">
            <h2 id="cookie-banner-title" tabindex="-1" class="mb-1 text-base font-bold outline-none">Cookies on this site</h2>
            <p>We only use cookies and storage that are needed to run the site: signing in, security and your theme. We don't
                use analytics or advertising. If that changes, we'll ask before anything optional runs. Read our
                <a href="{{ route('privacy') }}" class="text-brand-700 underline dark:text-green-400">Privacy Policy</a>.</p>
        </div>

        <div class="flex shrink-0 gap-3">
            <button type="button" data-cookie-reject
                class="flex-1 rounded-lg border border-brand-700 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-700/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700 md:flex-none dark:border-green-400 dark:text-green-400 dark:focus-visible:outline-green-400">Reject</button>
            <button type="button" data-cookie-accept
                class="flex-1 rounded-lg border border-brand-700 bg-brand-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700 md:flex-none dark:focus-visible:outline-green-400">Accept</button>
        </div>
    </div>
</section>
