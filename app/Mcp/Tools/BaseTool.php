<?php

namespace App\Mcp\Tools;

use App\Enums\AccountRole;
use App\Mcp\Exceptions\ToolFailedException;
use App\Models\Account;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base class for every Billing MCP tool (drop subclasses in app/Mcp/Tools/**; the
 * BillingServer discovers them automatically).
 *
 * A tool extends this class and implements execute() instead of handle():
 *
 *     #[Description('Mark a task as completed.')]
 *     class CompleteTaskTool extends BaseTool
 *     {
 *         protected ?string $requiredAbility = 'tasks:write';
 *
 *         public function schema(JsonSchema $schema): array
 *         {
 *             return $this->withAccountSchema($schema, ['task_id' => $schema->integer()->required()]);
 *         }
 *
 *         protected function execute(Request $request, Account $account, User $user): Response
 *         {
 *             $task = $account->tasks()->findOrFail($request->get('task_id'));
 *
 *             return $this->success(['id' => $task->id]);
 *         }
 *     }
 *
 * What handle() does for you, before execute() runs:
 *  - $requiredAbility (e.g. 'tasks:write'): the token must carry it, or '*'. Null means read-only.
 *  - $requiredRoles: roles allowed on the account (the owner always passes); [] means any member.
 *    Use [AccountRole::Owner, AccountRole::Manager] for send/accept/void/mark-paid.
 *  - the account is resolved from the optional `account_id` argument (see resolveAccount()).
 *  - ToolFailedException, ValidationException and ModelNotFoundException thrown anywhere in
 *    execute() are turned into Response::error(...).
 *
 * Helpers available inside execute():
 *  - success(mixed $data): Response       JSON payload
 *  - failure(string $message): Response   error response (return it)
 *  - fail(string $message): never         throw to abort from nested code
 *  - withAccountSchema($schema, $props): array   adds the optional `account_id` property
 *  - resolveAccount(Request, User): Account, requireAbility(User, string): void,
 *    requireRole(Account, User, array): void   (all throw ToolFailedException)
 *
 * Testing: Sanctum::actingAs($user, ['tasks:write']); then
 *     BillingServer::tool(CompleteTaskTool::class, ['task_id' => 1])->assertOk()->assertSee('...');
 *     ->assertHasErrors(['message']) for failures.
 */
abstract class BaseTool extends Tool
{
    /**
     * Token ability needed to run the tool (tasks:write, quotes:write, invoices:write), or null for reads.
     */
    protected ?string $requiredAbility = null;

    /**
     * Account roles allowed to run the tool. An empty list allows any member.
     *
     * @var array<int, AccountRole>
     */
    protected array $requiredRoles = [];

    /**
     * Run the tool for the resolved account.
     */
    abstract protected function execute(Request $request, Account $account, User $user): Response;

    public function handle(Request $request): Response
    {
        try {
            $user = $request->user();

            if (! $user instanceof User) {
                $this->fail('Authentication is required.');
            }

            if ($this->requiredAbility !== null) {
                $this->requireAbility($user, $this->requiredAbility);
            }

            $account = $this->resolveAccount($request, $user);

            if ($this->requiredRoles !== []) {
                $this->requireRole($account, $user, $this->requiredRoles);
            }

            return $this->execute($request, $account, $user);
        } catch (ToolFailedException $exception) {
            return $this->failure($exception->getMessage());
        } catch (ValidationException $exception) {
            return $this->failure('Validation failed: '.collect($exception->errors())->flatten()->implode(' '));
        } catch (ModelNotFoundException) {
            return $this->failure('The requested record was not found in this account.');
        }
    }

    /**
     * Merge the optional `account_id` property into a tool's input schema.
     *
     * @param  array<string, Type>  $properties
     * @return array<string, Type>
     */
    protected function withAccountSchema(JsonSchema $schema, array $properties = []): array
    {
        return $properties + [
            'account_id' => $schema->integer()
                ->description('Account to act on. Defaults to your first owned account.'),
        ];
    }

    /**
     * Pick the account the request acts on: the `account_id` argument when given
     * (it must be one the user owns or belongs to), otherwise the first owned
     * account, then the first membership. Mirrors BindApiAccount.
     */
    protected function resolveAccount(Request $request, User $user): Account
    {
        $requested = $request->get('account_id');

        if ($requested !== null) {
            $account = $user->accounts()->whereKey($requested)->first()
                ?? $user->ownedAccounts()->whereKey($requested)->first();

            return $account ?? $this->fail('You do not have access to that account.');
        }

        return $user->ownedAccounts()->first()
            ?? $user->accounts()->first()
            ?? $this->fail('This user is not attached to any account.');
    }

    /**
     * Require the current token to carry the ability ('*' tokens pass). Requests
     * without an access token are rejected.
     */
    protected function requireAbility(User $user, string $ability): void
    {
        if ($user->currentAccessToken() === null || ! $user->tokenCan($ability)) {
            $this->fail("Your token lacks the [{$ability}] ability.");
        }
    }

    /**
     * Require the user to hold one of the roles on the account. The account's
     * owner_id always counts as Owner; otherwise the account_user pivot role is used.
     *
     * @param  array<int, AccountRole>  $roles
     */
    protected function requireRole(Account $account, User $user, array $roles): void
    {
        $role = $account->owner_id === $user->getKey()
            ? AccountRole::Owner
            : $account->getUserRole($user);

        if ($role === null || ! in_array($role, $roles, true)) {
            $allowed = implode(' or ', array_map(fn (AccountRole $allowedRole): string => $allowedRole->value, $roles));

            $this->fail("This action requires the {$allowed} role on the account.");
        }
    }

    protected function success(mixed $data): Response
    {
        return Response::json($data);
    }

    protected function failure(string $message): Response
    {
        return Response::error($message);
    }

    /**
     * @throws ToolFailedException
     */
    protected function fail(string $message): never
    {
        throw new ToolFailedException($message);
    }
}
