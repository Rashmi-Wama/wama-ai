<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Module CRUD
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
            'payments.view',
            'payments.create',
            'payments.update',
            'payments.delete',

            // Admin-only
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Default user access
            'ai-chatbot.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Remove retired Business module permissions if they still exist.
        Permission::query()
            ->whereIn('name', ['business.view', 'business.create', 'business.update', 'business.delete'])
            ->delete();

        $superAdmin = Role::findOrCreate('Super Admin');
        $superAdmin->syncPermissions(Permission::all());

        $hrAdmin = Role::findOrCreate('HR Admin');
        $hrAdmin->syncPermissions([
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'projects.view',
            'projects.create',
            'projects.update',
            'projects.delete',
            'invoices.view',
            'invoices.create',
            'invoices.update',
            'invoices.delete',
            'payments.view',
            'payments.create',
            'payments.update',
            'payments.delete',
            'ai-chatbot.view',
        ]);

        $hrUser = Role::findOrCreate('HR User');
        $hrUser->syncPermissions([
            'ai-chatbot.view',
        ]);
    }
}
