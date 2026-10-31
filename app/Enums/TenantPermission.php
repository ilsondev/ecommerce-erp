<?php

namespace App\Enums;

enum TenantPermission: string
{
    case UpdateTenant = 'tenant:update';
    case DeleteTenant = 'tenant:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}
