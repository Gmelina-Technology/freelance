<?php

namespace App\Enums;

enum AccountCapability: string
{
    case ManageInvoices = 'invoices.manage';
    case ManageQuotes = 'quotes.manage';
    case ManageSales = 'sales.manage';
    case ManageClients = 'clients.manage';
    case LookUpClients = 'clients.lookup';
    case UseApi = 'api.use';
    case ManageMembers = 'members.manage';
    case ManageOwners = 'owners.manage';

    /**
     * @return array<int, AccountRole>
     */
    public function allowedRoles(): array
    {
        return match ($this) {
            self::ManageClients,
            self::ManageOwners => [AccountRole::Owner],
            self::ManageInvoices,
            self::ManageQuotes,
            self::ManageSales,
            self::LookUpClients,
            self::UseApi,
            self::ManageMembers => [AccountRole::Owner, AccountRole::Manager],
        };
    }

    public function isGrantedTo(?AccountRole $role): bool
    {
        return $role !== null && in_array($role, $this->allowedRoles(), true);
    }

    public function describeAllowedRoles(): string
    {
        return implode(' or ', array_map(fn (AccountRole $role): string => $role->value, $this->allowedRoles()));
    }

    /**
     * The capability needed to move someone from $from (null = not yet a member) to $to.
     */
    public static function forRoleChange(?AccountRole $from, AccountRole $to): self
    {
        return in_array(AccountRole::Owner, [$from, $to], true) ? self::ManageOwners : self::ManageMembers;
    }
}
