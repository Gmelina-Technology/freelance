<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Connect an MCP client</x-slot>

        <div class="space-y-3 text-sm">
            <p>
                Tokens belong to you, not to the current account, and act on the accounts you can access.
                A token without any ability can only read.
            </p>

            <p>MCP endpoint: <code>{{ $this->getMcpUrl() }}</code></p>

            <p>Claude Code:</p>
            <pre class="overflow-x-auto rounded-lg bg-gray-100 p-3 dark:bg-white/10"><code>claude mcp add --transport http billing {{ $this->getMcpUrl() }} --header "Authorization: Bearer &lt;token&gt;"</code></pre>

            <p>
                Any other MCP client: use the endpoint above over HTTP and send the token in an
                <code>Authorization: Bearer &lt;token&gt;</code> header. The same token works for the REST API under
                <code>{{ url('/api') }}</code>.
            </p>
        </div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
