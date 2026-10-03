<?php

namespace App\Mcp\Servers;

use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

#[Name('Billing')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Manage freelance billing: tasks, quotes and invoices.

    - Tasks: list, create, update, complete and delete tasks.
    - Quotes: draft with line items, send, accept (creates tasks), decline, void and fetch the PDF.
    - Invoices: generate from completed tasks or create manually, send, mark as paid, void and fetch the PDF.

    Every tool works on the one account your token is bound to; there is no account argument.
    Tokens are created per account on the API Tokens page. Write tools need a token with the
    matching ability (tasks:write, quotes:write, invoices:write).
    MARKDOWN)]
class BillingServer extends Server
{
    /**
     * List every tool in a single `tools/list` page for clients that don't follow cursors.
     */
    public int $defaultPaginationLength = 50;

    /**
     * Register every concrete tool found under app/Mcp/Tools so new tools never
     * require editing this file.
     */
    protected function boot(): void
    {
        $this->tools = $this->discoverTools();
    }

    /**
     * @return array<int, class-string<Tool>>
     */
    protected function discoverTools(): array
    {
        $directory = app_path('Mcp/Tools');

        if (! is_dir($directory)) {
            return [];
        }

        $tools = [];

        foreach (Finder::create()->files()->name('*.php')->in($directory)->sortByName() as $file) {
            $class = 'App\\Mcp\\Tools\\'.Str::of($file->getRelativePathname())
                ->beforeLast('.php')
                ->replace(['/', '\\'], '\\');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Tool::class)) {
                continue;
            }

            $tools[] = $class;
        }

        return $tools;
    }
}
