<?php

namespace App\Data;

readonly class TenantPermissions
{
    public function __construct(
        public bool $canUpdateTenant,
        public bool $canDeleteTenant,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
    ) {
        //
    }
}
