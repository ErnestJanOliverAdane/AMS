<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Role;
use App\Http\Controllers\AccountController;

/** Super Admin creates, edits and removes Admin accounts. */
class AdminController extends AccountController
{
    protected function role(): Role
    {
        return Role::Admin;
    }

    protected function routeBase(): string
    {
        return 'super-admin.admins';
    }
}
