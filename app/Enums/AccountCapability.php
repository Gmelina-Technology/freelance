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

    /**
     * @return array<int, AccountRole>
     */
    public function allowedRoles(): array
    {
        return match ($this) {
            self::ManageClients => [AccountRole::Owner],
            self::ManageInvoices,
            self::ManageQuotes,
            self::ManageSales,
            self::LookUpClients,
            self::UseApi => [AccountRole::Owner, AccountRole::Manager],
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
}
