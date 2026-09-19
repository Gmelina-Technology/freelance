<div class="space-y-4">
    @unless ($hasClientEmail)
        <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
            This client has no email address, so the quote cannot be sent.
        </div>
    @endunless

    @unless ($hasTemplate)
        <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
            No quote email template is set up for this account, so the email body will be empty.
        </div>
    @endunless

    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt class="font-medium text-gray-500 dark:text-gray-400">To</dt>
        <dd>{{ $to === [] ? '—' : implode(', ', $to) }}</dd>

        <dt class="font-medium text-gray-500 dark:text-gray-400">Cc</dt>
        <dd>{{ $cc === [] ? '—' : implode(', ', $cc) }}</dd>

        <dt class="font-medium text-gray-500 dark:text-gray-400">Subject</dt>
        <dd>{{ $subject }}</dd>
    </dl>

    <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-900">
        {!! $body !!}
    </div>

    <div class="space-y-2">
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-gray-500 dark:text-gray-400">
                Attached PDF
                @if ($usesCustomAttachment)
                    <span class="ml-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs text-primary-700 dark:bg-primary-400/10 dark:text-primary-400">Using your uploaded PDF</span>
                @endif
            </span>
            <a href="{{ route('quotes.preview', $quote) }}" target="_blank" class="text-primary-600 hover:underline">Open PDF</a>
        </div>
        <iframe src="{{ route('quotes.preview', $quote) }}" class="h-[32rem] w-full rounded-lg border border-gray-200 bg-white" title="Quote PDF preview"></iframe>
    </div>
</div>
