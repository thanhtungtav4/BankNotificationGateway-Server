<?php

namespace App\Policies;

use App\Models\Tenant;

final class TenantPolicy
{
    public function viewAny(object $user): bool
    {
        return in_array($user->role ?? null, ['super_admin', 'admin'], true);
    }

    public function view(object $user, Tenant $tenant): bool
    {
        return in_array($user->role ?? null, ['super_admin', 'admin'], true) || (int) ($user->tenant_id ?? 0) === (int) $tenant->id;
    }

    public function update(object $user, Tenant $tenant): bool
    {
        return in_array($user->role ?? null, ['super_admin', 'admin'], true) || ((int) ($user->tenant_id ?? 0) === (int) $tenant->id && ($user->role ?? null) === 'owner');
    }
}
