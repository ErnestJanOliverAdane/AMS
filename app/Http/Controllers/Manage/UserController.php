<?php

namespace App\Http\Controllers\Manage;

use App\Enums\Role;
use App\Http\Controllers\AccountController;

/** Admins and Super Admins create, edit and remove User accounts. */
class UserController extends AccountController
{
    protected function role(): Role
    {
        return Role::User;
    }

    protected function routeBase(): string
    {
        return 'manage.users';
    }
}
