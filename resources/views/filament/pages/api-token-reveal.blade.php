<div
    class="space-y-3"
    x-data="{
        copied: false,
        failed: false,
        async copy() {
            const text = @js($token)
            this.failed = false

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text)
                } else {
                    throw new Error('Clipboard API unavailable')
                }
            } catch (error) {
                // Fallback for insecure contexts or blocked clipboard permission.
                const field = document.createElement('textarea')
                field.value = text
                field.setAttribute('readonly', '')
                field.style.position = 'fixed'
                field.style.opacity = '0'
                this.$el.appendChild(field)
                field.select()
                field.setSelectionRange(0, text.length)

                try {
                    this.copied = document.execCommand('copy')
                } catch (e) {
                    this.copied = false
                }

                this.failed = ! this.copied
                field.remove()

                if (this.copied) {
                    setTimeout(() => (this.copied = false), 2000)
                }

                return
            }

            this.copied = true
            setTimeout(() => (this.copied = false), 2000)
        },
    }"
>
    <p class="text-sm font-medium text-danger-600 dark:text-danger-400">
        Copy this token now. You won't see it again.
    </p>

    <div class="flex items-center gap-2">
        <code
            class="block flex-1 select-all break-all rounded-lg bg-gray-100 p-3 text-sm dark:bg-white/10"
            x-on:click="window.getSelection().selectAllChildren($el)"
        >{{ $token }}</code>

        <x-filament::button
            type="button"
            color="gray"
            icon="heroicon-o-clipboard"
            x-on:click.prevent.stop="copy()"
        >
            <span x-text="copied ? 'Copied' : 'Copy'"></span>
        </x-filament::button>
    </div>

    <p x-show="failed" x-cloak class="text-sm text-danger-600 dark:text-danger-400">
        Couldn't copy automatically. Click the token to select it, then press Ctrl+C.
    </p>
</div>
