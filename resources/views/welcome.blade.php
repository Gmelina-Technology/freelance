@php
    $loginUrl = route('filament.app.auth.login');
    $registerUrl = route('filament.app.auth.register');
@endphp

<x-site-layout>
    <main>
        {{-- Hero --}}
        <section class="mx-auto grid max-w-6xl items-center gap-14 px-6 pb-20 pt-14 md:pb-28 md:pt-20 lg:grid-cols-[1.05fr_1fr]">
            <div>
                <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-brand-line bg-white px-3 py-1 font-mono text-xs text-brand-ink/70 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                    <span class="size-1.5 rounded-full bg-brand-600"></span>
                    for freelancers &amp; tiny studios
                </p>
                <h1 class="text-4xl font-extrabold leading-[1.05] tracking-tight md:text-6xl">
                    The paperwork side of freelancing, <span class="text-brand-700 dark:text-green-400">handled.</span>
                </h1>
                <p class="mt-6 max-w-md text-lg text-brand-ink/70 dark:text-gray-400">
                    Keep clients, projects and tasks in one place. Send a quote, turn it into an invoice, email the PDF.
                    No spreadsheet in the middle.
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-3">
                    @auth
                        <a href="{{ url('/app') }}"
                            class="rounded-lg bg-brand-700 px-6 py-3 font-semibold text-white transition hover:bg-brand-800">Open your workspace</a>
                    @else
                        <a href="{{ $registerUrl }}"
                            class="rounded-lg bg-brand-700 px-6 py-3 font-semibold text-white transition hover:bg-brand-800">Create a free account</a>
                        <a href="{{ $loginUrl }}"
                            class="rounded-lg border border-brand-line bg-white px-6 py-3 font-semibold transition hover:border-brand-700 dark:border-white/10 dark:bg-white/5">Log in</a>
                    @endauth
                </div>
                <p class="mt-5 font-mono text-xs text-brand-ink/50 dark:text-gray-500">multi-currency · PDF invoices · team invites</p>
            </div>

            {{-- Product mock: task board + invoice --}}
            <div class="relative mx-auto w-full max-w-xl pb-16 lg:max-w-none" aria-hidden="true">
                <div class="-rotate-1 rounded-2xl border border-brand-line bg-white p-5 shadow-[0_40px_70px_-28px_rgba(16,35,26,0.45)] dark:border-white/10 dark:bg-[#0e1a13]">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <p class="font-mono text-[11px] uppercase tracking-widest text-brand-ink/50 dark:text-gray-500">Project</p>
                            <p class="font-extrabold tracking-tight">Harbor Coffee rebrand</p>
                        </div>
                        <span class="rounded-full bg-brand-soft px-2.5 py-1 font-mono text-[11px] text-brand-700 dark:bg-white/5 dark:text-green-400">due Nov 14</span>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-xs">
                        <div>
                            <p class="mb-2 font-semibold text-brand-ink/50 dark:text-gray-500">To do <span class="font-mono">2</span></p>
                            <div class="mb-2 rounded-lg border border-brand-line p-2.5 dark:border-white/10">Menu layout, A4</div>
                            <div class="rounded-lg border border-brand-line p-2.5 dark:border-white/10">Send font licence</div>
                        </div>
                        <div>
                            <p class="mb-2 font-semibold text-brand-ink/50 dark:text-gray-500">Doing <span class="font-mono">1</span></p>
                            <div class="rounded-lg border border-brand-700/40 bg-brand-soft p-2.5 dark:border-green-400/30 dark:bg-white/5">
                                Logo, round 2
                                <p class="mt-2 font-mono text-[10px] text-brand-700 dark:text-green-400">3h 20m logged</p>
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 font-semibold text-brand-ink/50 dark:text-gray-500">Done <span class="font-mono">3</span></p>
                            <div class="mb-2 rounded-lg border border-brand-line p-2.5 line-through opacity-60 dark:border-white/10">Moodboard</div>
                            <div class="rounded-lg border border-brand-line p-2.5 line-through opacity-60 dark:border-white/10">Kickoff call</div>
                        </div>
                    </div>
                </div>

                <div class="absolute -bottom-14 -right-2 w-64 lg:-right-6 rotate-2 rounded-2xl border border-brand-line bg-white p-4 shadow-[0_30px_60px_-24px_rgba(16,35,26,0.5)] dark:border-white/10 dark:bg-[#0e1a13]">
                    <div class="flex items-baseline justify-between">
                        <p class="font-mono text-[11px] uppercase tracking-widest text-brand-ink/50 dark:text-gray-500">Invoice</p>
                        <p class="font-mono text-[11px] text-brand-ink/50 dark:text-gray-500">INV-0042</p>
                    </div>
                    <dl class="mt-3 space-y-1.5 text-xs">
                        <div class="flex justify-between"><dt>Brand identity</dt><dd class="font-mono">$1,800.00</dd></div>
                        <div class="flex justify-between"><dt>Menu design</dt><dd class="font-mono">$600.00</dd></div>
                    </dl>
                    <div class="mt-3 flex items-center justify-between border-t border-brand-line pt-3 dark:border-white/10">
                        <span class="text-xs font-semibold">Total</span>
                        <span class="font-mono text-base font-bold text-brand-700 dark:text-green-400">$2,400.00</span>
                    </div>
                    <p class="mt-3 rounded-md bg-brand-700 py-1.5 text-center text-xs font-semibold text-white">Email PDF to client</p>
                </div>
            </div>
        </section>

        {{-- What you get --}}
        <section id="what-you-get" class="border-y border-brand-line bg-white dark:border-white/10 dark:bg-[#0b150f]">
            <div class="mx-auto grid max-w-6xl gap-10 px-6 py-20 md:py-24 lg:grid-cols-[1fr_2fr]">
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.14em] text-brand-700 dark:text-green-400">What you get</p>
                    <h2 class="mt-3 text-3xl font-extrabold leading-[1.1] tracking-tight md:text-4xl">Fewer tabs. One paper trail.</h2>
                </div>

                <ul class="divide-y divide-brand-line dark:divide-white/10">
                    @foreach ([
                        ['Clients &amp; projects', 'Every client, every job, with its budget, dates and status. Nothing lives in your inbox.', 'clients'],
                        ['Task board', 'Drag tasks across a kanban board, log the hours, and leave comments where the work is.', 'tasks'],
                        ['Quotes that become invoices', 'Write a quote, send it as a PDF, and convert it when the client says yes. Line items carry over.', 'quotes'],
                        ['Invoices by email', 'A clean PDF with your bank details and currency, sent from the app with a template you control.', 'invoices'],
                        ['Bring your team', 'Invite a collaborator to your account. They see the projects you share, and nothing else.', 'teams'],
                    ] as [$title, $body, $tag])
                        <li class="grid gap-2 py-6 first:pt-0 last:pb-0 sm:grid-cols-[1fr_auto] sm:items-baseline sm:gap-8">
                            <div>
                                <h3 class="text-lg font-bold tracking-tight">{!! $title !!}</h3>
                                <p class="mt-1 max-w-xl text-brand-ink/70 dark:text-gray-400">{{ $body }}</p>
                            </div>
                            <span class="font-mono text-xs uppercase tracking-widest text-brand-ink/40 dark:text-gray-500">{{ $tag }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Flow --}}
        <section id="flow" class="mx-auto max-w-6xl px-6 py-20 md:py-24">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-brand-700 dark:text-green-400">How it flows</p>
            <h2 class="mt-3 max-w-2xl text-3xl font-extrabold leading-[1.1] tracking-tight md:text-4xl">From “can you do this?” to paid, in four steps.</h2>

            <ol class="mt-12 grid gap-px overflow-hidden rounded-2xl border border-brand-line bg-brand-line sm:grid-cols-2 lg:grid-cols-4 dark:border-white/10 dark:bg-white/10">
                @foreach ([
                    ['Add the client', 'Name, contact, currency. Once.'],
                    ['Quote the job', 'Line items, validity date, one click to send.'],
                    ['Do the work', 'Tasks on a board, hours on the clock.'],
                    ['Invoice &amp; get paid', 'Convert the quote, email the PDF.'],
                ] as $i => [$title, $body])
                    <li class="bg-brand-soft p-7 dark:bg-[#08110c]">
                        <span class="block text-5xl font-extrabold tracking-tighter text-brand-700/25 dark:text-green-400/30">0{{ $i + 1 }}</span>
                        <h3 class="mt-6 font-bold tracking-tight">{!! $title !!}</h3>
                        <p class="mt-1 text-sm text-brand-ink/70 dark:text-gray-400">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- CTA --}}
        <section class="mx-auto max-w-6xl px-6 pb-24">
            <div class="flex flex-col items-start justify-between gap-6 rounded-2xl border border-brand-line bg-white p-8 md:flex-row md:items-center md:p-10 dark:border-white/10 dark:bg-[#0e1a13]">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight md:text-3xl">Send your first quote today.</h2>
                    <p class="mt-1 text-brand-ink/70 dark:text-gray-400">Sign-up takes a minute. Bring one client and one job.</p>
                </div>
                @auth
                    <a href="{{ url('/app') }}" class="shrink-0 rounded-lg bg-brand-700 px-6 py-3 font-semibold text-white transition hover:bg-brand-800">Open your workspace</a>
                @else
                    <a href="{{ $registerUrl }}" class="shrink-0 rounded-lg bg-brand-700 px-6 py-3 font-semibold text-white transition hover:bg-brand-800">Create a free account</a>
                @endauth
            </div>
        </section>
    </main>
</x-site-layout>
