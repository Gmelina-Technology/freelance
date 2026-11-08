<div class="space-y-2">
    <div class="flex items-center justify-between gap-4 text-sm">
        <span class="truncate font-medium text-gray-500 dark:text-gray-400">{{ basename($quote->attachment_path) }}</span>
        <a href="{{ route('quotes.attachment.preview', $quote) }}" target="_blank" class="shrink-0 text-primary-600 hover:underline">Open PDF</a>
    </div>
    <iframe src="{{ route('quotes.attachment.preview', $quote) }}" class="h-[32rem] w-full rounded-lg border border-gray-200 bg-white" title="Custom attachment preview"></iframe>
</div>
