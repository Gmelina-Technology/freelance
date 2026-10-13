<?php

use App\Enums\AccountCapability;
use App\Enums\AccountRole;
use App\Mcp\Servers\BillingServer;
use App\Mcp\Tools\BaseTool;
use App\Mcp\Tools\Invoices\ListInvoices;
use App\Mcp\Tools\Lookups\GetProjectPeople;
use App\Mcp\Tools\Lookups\ListCategories;
use App\Mcp\Tools\Lookups\ListClients;
use App\Mcp\Tools\Quotes\CreateQuote;
use App\Mcp\Tools\Quotes\ListQuotes;
use App\Mcp\Tools\Tasks\ListTasks;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every concrete tool the BillingServer registers, with the capability it declares.
 *
 * @return array<class-string<BaseTool>, ?AccountCapability>
 */
function declaredToolCapabilities(): array
{
    $server = (new ReflectionClass(BillingServer::class))->newInstanceWithoutConstructor();
    $discover = new ReflectionMethod($server, 'discoverTools');

    $capabilities = [];

    foreach ($discover->invoke($server) as $class) {
        $property = new ReflectionProperty(BaseTool::class, 'requiredCapability');
        $capabilities[$class] = $property->getValue(app($class));
    }

    return $capabilities;
}

/**
 * The capability every tool must carry. A new tool fails the inventory test until it is added here.
 *
 * @return array<string, ?AccountCapability>
 */
function expectedToolCapabilities(): array
{
    $expected = [];

    foreach (['CreateInvoice', 'GenerateInvoice', 'GetInvoice', 'GetInvoicePdf', 'ListBillableTasks', 'ListInvoices', 'MarkInvoicePaid', 'SendInvoice', 'VoidInvoice'] as $name) {
        $expected["App\Mcp\Tools\Invoices\\".$name] = AccountCapability::ManageInvoices;
    }

    foreach (['AcceptQuote', 'CreateQuote', 'DeclineQuote', 'GetQuote', 'GetQuotePdf', 'ListQuotes', 'SendQuote', 'UpdateQuote', 'VoidQuote'] as $name) {
        $expected["App\Mcp\Tools\Quotes\\".$name] = AccountCapability::ManageQuotes;
    }

    foreach (['ListClients', 'GetProjectPeople'] as $name) {
        $expected["App\Mcp\Tools\Lookups\\".$name] = AccountCapability::LookUpClients;
    }

    foreach (['ListProjects', 'ListCategories', 'ListUnits'] as $name) {
        $expected["App\Mcp\Tools\Lookups\\".$name] = null;
    }

    foreach (['CreateTask', 'UpdateTask', 'CompleteTask', 'DeleteTask', 'GetTask', 'ListTasks'] as $name) {
        $expected["App\Mcp\Tools\Tasks\\".$name] = null;
    }

    return $expected;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->for($this->owner, 'owner')->create();
});

it('classifies every registered tool', function () {
    $declared = declaredToolCapabilities();

    expect($declared)->toHaveCount(29)
        ->and($declared)->toEqual(expectedToolCapabilities());
});

it('keeps role lists out of the tool classes', function () {
    expect((new ReflectionClass(BaseTool::class))->hasProperty('requiredRoles'))->toBeFalse()
        ->and((new ReflectionClass(BaseTool::class))->hasMethod('requireRole'))->toBeFalse();

    foreach (array_keys(declaredToolCapabilities()) as $class) {
        expect((new ReflectionClass($class))->hasProperty('requiredRoles'))->toBeFalse();
    }
});

it('refuses a member token on every tool', function (string $class) {
    $member = makeAccountUser($this->account, AccountRole::Member);
    actingAsToken($member, ['*'], $this->account);

    BillingServer::tool($class)->assertHasErrors(['API access is not available for your role on this account.']);
})->with(fn () => array_keys(expectedToolCapabilities()));

it('lets owners read one tool of each group', function (string $class) {
    actingAsToken($this->owner, ['*'], $this->account);

    BillingServer::tool($class)->assertHasNoErrors();
})->with([ListInvoices::class, ListQuotes::class, ListClients::class, ListTasks::class, ListCategories::class]);

it('lets managers use client lookups and quote tools', function () {
    $manager = makeAccountUser($this->account, AccountRole::Manager);
    actingAsToken($manager, ['*'], $this->account);

    BillingServer::tool(ListClients::class)->assertHasNoErrors();
    BillingServer::tool(ListInvoices::class)->assertHasNoErrors();
    BillingServer::tool(GetProjectPeople::class, ['project_id' => 0])->assertHasErrors(['not found']);
    BillingServer::tool(CreateQuote::class, [])->assertHasErrors(['Validation failed']);
});
