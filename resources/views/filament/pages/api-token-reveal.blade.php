<div class="space-y-3" x-data="{ copied: false }">
    <p class="text-sm font-medium text-danger-600 dark:text-danger-400">
        Copy this token now. You won't see it again.
    </p>

    <div class="flex items-center gap-2">
        <code class="block flex-1 break-all rounded-lg bg-gray-100 p-3 text-sm dark:bg-white/10">{{ $token }}</code>

        <x-filament::button
            color="gray"
            icon="heroicon-o-clipboard"
            x-on:click="navigator.clipboard.writeText(@js($token)); copied = true"
        >
            <span x-text="copied ? 'Copied' : 'Copy'"></span>
        </x-filament::button>
    </div>
</div>
