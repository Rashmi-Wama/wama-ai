<?php

namespace App\Services\Ai;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ModuleCrudTools
{
    /**
     * @return list<string>
     */
    public function modules(): array
    {
        return ['clients', 'projects', 'invoices', 'payments', 'users'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $moduleEnum = $this->modules();

        return [
            $this->def('listRecords', 'List or search records from a module.', [
                'module' => ['type' => 'string', 'enum' => $moduleEnum],
                'search' => ['type' => 'string', 'description' => 'Optional search keyword'],
                'limit' => ['type' => 'integer', 'description' => 'Max rows (default 10, max 50)'],
            ], ['module']),
            $this->def('getRecord', 'Fetch one record by id from a module.', [
                'module' => ['type' => 'string', 'enum' => $moduleEnum],
                'id' => ['type' => 'integer'],
            ], ['module', 'id']),
            $this->def('createRecord', 'Create a new CRUD record in a module. Provide the required fields for that module.', [
                'module' => ['type' => 'string', 'enum' => $moduleEnum],
                'data' => ['type' => 'object', 'description' => 'Fields for the new record'],
            ], ['module', 'data']),
            $this->def('updateRecord', 'Update an existing CRUD record by id.', [
                'module' => ['type' => 'string', 'enum' => $moduleEnum],
                'id' => ['type' => 'integer'],
                'data' => ['type' => 'object', 'description' => 'Fields to update'],
            ], ['module', 'id', 'data']),
            $this->def('deleteRecord', 'Delete a single CRUD record by id. Never delete multiple records at once.', [
                'module' => ['type' => 'string', 'enum' => $moduleEnum],
                'id' => ['type' => 'integer'],
            ], ['module', 'id']),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments = [], ?User $actor = null): array
    {
        return match ($name) {
            'listRecords' => $this->listRecords($arguments, $actor),
            'getRecord' => $this->getRecord($arguments, $actor),
            'createRecord' => $this->createRecord($arguments, $actor),
            'updateRecord' => $this->updateRecord($arguments, $actor),
            'deleteRecord' => $this->deleteRecord($arguments, $actor),
            default => ['error' => true, 'message' => "Unknown CRUD tool: {$name}"],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function listRecords(array $args, ?User $actor): array
    {
        $module = $this->normalizeModule($args['module'] ?? '');
        if ($denied = $this->authorize($actor, $module, 'view')) {
            return $denied;
        }

        $limit = min(50, max(1, (int) ($args['limit'] ?? 10)));
        $search = trim((string) ($args['search'] ?? ''));

        $query = $this->baseQuery($module);
        $this->applySearch($query, $module, $search);

        $rows = $query->latest('id')->limit($limit)->get()->map(
            fn (Model $model) => $this->serialize($module, $model)
        )->all();

        return [
            'action' => 'list',
            'module' => $module,
            'count' => count($rows),
            'records' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function getRecord(array $args, ?User $actor): array
    {
        $module = $this->normalizeModule($args['module'] ?? '');
        if ($denied = $this->authorize($actor, $module, 'view')) {
            return $denied;
        }

        $id = (int) ($args['id'] ?? 0);
        $record = $this->find($module, $id);

        if (! $record) {
            return ['error' => true, 'message' => ucfirst($module)." record #{$id} was not found."];
        }

        return [
            'action' => 'get',
            'module' => $module,
            'record' => $this->serialize($module, $record),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function createRecord(array $args, ?User $actor): array
    {
        $module = $this->normalizeModule($args['module'] ?? '');
        if ($denied = $this->authorize($actor, $module, 'create')) {
            return $denied;
        }

        $data = is_array($args['data'] ?? null) ? $args['data'] : [];
        $validated = $this->validate($module, $data, null);

        if (($validated['error'] ?? false) === true) {
            return $validated;
        }

        $record = $this->persistCreate($module, $validated['data']);

        return [
            'action' => 'create',
            'module' => $module,
            'created' => true,
            'record' => $this->serialize($module, $record),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function updateRecord(array $args, ?User $actor): array
    {
        $module = $this->normalizeModule($args['module'] ?? '');
        if ($denied = $this->authorize($actor, $module, 'update')) {
            return $denied;
        }

        $id = (int) ($args['id'] ?? 0);
        $record = $this->find($module, $id);

        if (! $record) {
            return ['error' => true, 'message' => ucfirst($module)." record #{$id} was not found."];
        }

        $data = is_array($args['data'] ?? null) ? $args['data'] : [];
        $validated = $this->validate($module, $data, $id);

        if (($validated['error'] ?? false) === true) {
            return $validated;
        }

        $record = $this->persistUpdate($module, $record, $validated['data']);

        return [
            'action' => 'update',
            'module' => $module,
            'updated' => true,
            'record' => $this->serialize($module, $record),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function deleteRecord(array $args, ?User $actor): array
    {
        $module = $this->normalizeModule($args['module'] ?? '');
        if ($denied = $this->authorize($actor, $module, 'delete')) {
            return $denied;
        }

        $id = (int) ($args['id'] ?? 0);

        if ($id <= 0) {
            return ['error' => true, 'message' => 'A specific record id is required to delete.'];
        }

        if ($module === 'users' && $actor && $actor->id === $id) {
            return ['error' => true, 'message' => 'You cannot delete your own user account via AI.'];
        }

        $record = $this->find($module, $id);

        if (! $record) {
            return ['error' => true, 'message' => ucfirst($module)." record #{$id} was not found."];
        }

        $snapshot = $this->serialize($module, $record);
        $record->delete();

        return [
            'action' => 'delete',
            'module' => $module,
            'deleted' => true,
            'record' => $snapshot,
        ];
    }

    private function normalizeModule(string $module): string
    {
        $module = Str::lower(trim($module));

        return match ($module) {
            'client' => 'clients',
            'project' => 'projects',
            'invoice' => 'invoices',
            'payment' => 'payments',
            'user' => 'users',
            default => $module,
        };
    }

    /**
     * @return array{error: bool, message: string}|null
     */
    private function authorize(?User $actor, string $module, string $action): ?array
    {
        if (! in_array($module, $this->modules(), true)) {
            return ['error' => true, 'message' => "Unsupported module \"{$module}\"."];
        }

        if (! $actor) {
            return ['error' => true, 'message' => 'You must be signed in to perform module CRUD actions.'];
        }

        $permission = $this->permissionName($module, $action);

        if (! $actor->can($permission)) {
            return [
                'error' => true,
                'message' => "You do not have permission to {$action} {$module} ({$permission}).",
            ];
        }

        return null;
    }

    private function permissionName(string $module, string $action): string
    {
        // Keep Spatie names: clients.*, projects.*, invoices.*, payments.*, users.*
        $prefix = match ($module) {
            'clients' => 'clients',
            'projects' => 'projects',
            'invoices' => 'invoices',
            'payments' => 'payments',
            'users' => 'users',
            default => $module,
        };

        return "{$prefix}.{$action}";
    }

    private function baseQuery(string $module): Builder
    {
        return match ($module) {
            'clients' => Client::query(),
            'projects' => Project::query()->with('client:id,company_name'),
            'invoices' => Invoice::query()->with('client:id,company_name'),
            'payments' => Payment::query()->with(['invoice:id,invoice_number,client_id', 'invoice.client:id,company_name']),
            'users' => User::query()->with('roles:id,name'),
            default => Client::query()->whereRaw('1=0'),
        };
    }

    private function find(string $module, int $id): ?Model
    {
        return $this->baseQuery($module)->whereKey($id)->first();
    }

    private function applySearch(Builder $query, string $module, string $search): void
    {
        if ($search === '') {
            return;
        }

        match ($module) {
            'clients' => $query->where(function (Builder $q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            }),
            'projects' => $query->where(function (Builder $q) use ($search) {
                $q->where('project_name', 'like', "%{$search}%")
                    ->orWhere('project_status', 'like', "%{$search}%")
                    ->orWhereHas('client', fn (Builder $c) => $c->where('company_name', 'like', "%{$search}%"));
            }),
            'invoices' => $query->where(function (Builder $q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhereHas('client', fn (Builder $c) => $c->where('company_name', 'like', "%{$search}%"));
            }),
            'payments' => $query->where(function (Builder $q) use ($search) {
                $q->where('payment_mode', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn (Builder $i) => $i->where('invoice_number', 'like', "%{$search}%"));
            }),
            'users' => $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{error?: bool, message?: string, data?: array<string, mixed>}
     */
    private function validate(string $module, array $data, ?int $id): array
    {
        $data = $this->normalizeIncomingData($module, $data);

        if (in_array($module, ['projects', 'invoices'], true) && empty($data['client_id']) && ! empty($data['client_name'])) {
            $client = Client::query()
                ->where('company_name', 'like', '%'.$data['client_name'].'%')
                ->first();

            if (! $client) {
                return ['error' => true, 'message' => 'Client "'.$data['client_name'].'" was not found. Create the client first or provide client_id.'];
            }

            $data['client_id'] = $client->id;
        }

        if ($module === 'payments' && empty($data['invoice_id']) && ! empty($data['invoice_number'])) {
            $invoice = Invoice::query()->where('invoice_number', $data['invoice_number'])->first();
            if (! $invoice) {
                return ['error' => true, 'message' => 'Invoice "'.$data['invoice_number'].'" was not found.'];
            }
            $data['invoice_id'] = $invoice->id;
        }

        $rules = match ($module) {
            'clients' => [
                'company_name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'],
                'contact_person' => [$id ? 'sometimes' : 'required', 'string', 'max:255'],
                'email' => [$id ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($id)],
                'mobile' => [$id ? 'sometimes' : 'required', 'string', 'max:20'],
                'status' => [$id ? 'sometimes' : 'required', Rule::in(['active', 'inactive'])],
            ],
            'projects' => [
                'client_id' => [$id ? 'sometimes' : 'required', 'integer', 'exists:clients,id'],
                'client_name' => ['sometimes', 'nullable', 'string'],
                'project_name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'],
                'start_date' => [$id ? 'sometimes' : 'required', 'date'],
                'deadline' => ['nullable', 'date'],
                'total_amount' => [$id ? 'sometimes' : 'required', 'numeric', 'min:0'],
                'payment_received' => ['sometimes', 'numeric', 'min:0'],
                'project_status' => [$id ? 'sometimes' : 'required', Rule::in(['pending', 'in_progress', 'completed', 'on_hold', 'cancelled'])],
            ],
            'invoices' => [
                'client_id' => [$id ? 'sometimes' : 'required', 'integer', 'exists:clients,id'],
                'client_name' => ['sometimes', 'nullable', 'string'],
                'invoice_number' => [$id ? 'sometimes' : 'nullable', 'string', 'max:255', Rule::unique('invoices', 'invoice_number')->ignore($id)],
                'invoice_date' => [$id ? 'sometimes' : 'required', 'date'],
                'due_date' => ['nullable', 'date'],
                'amount' => [$id ? 'sometimes' : 'required', 'numeric', 'min:0'],
                'paid_amount' => ['sometimes', 'numeric', 'min:0'],
                'payment_status' => [$id ? 'sometimes' : 'required', Rule::in(['unpaid', 'partial', 'paid', 'overdue'])],
            ],
            'payments' => [
                'invoice_id' => [$id ? 'sometimes' : 'required', 'integer', 'exists:invoices,id'],
                'invoice_number' => ['sometimes', 'nullable', 'string'],
                'amount' => [$id ? 'sometimes' : 'required', 'numeric', 'min:0.01'],
                'payment_date' => [$id ? 'sometimes' : 'required', 'date'],
                'payment_mode' => [$id ? 'sometimes' : 'required', Rule::in(['cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other'])],
            ],
            'users' => [
                'name' => [$id ? 'sometimes' : 'required', 'string', 'max:255'],
                'email' => [$id ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
                'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
                'role' => [$id ? 'sometimes' : 'required', Rule::in(['Super Admin', 'HR Admin', 'HR User'])],
            ],
            default => [],
        };

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return [
                'error' => true,
                'message' => 'Validation failed: '.$validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $validated = $validator->validated();

        if ($module === 'clients' && empty($validated['status']) && ! $id) {
            $validated['status'] = 'active';
        }
        if ($module === 'projects' && ! isset($validated['payment_received']) && ! $id) {
            $validated['payment_received'] = 0;
        }
        if ($module === 'projects' && empty($validated['project_status']) && ! $id) {
            $validated['project_status'] = 'pending';
        }
        if ($module === 'invoices' && empty($validated['invoice_number']) && ! $id) {
            $validated['invoice_number'] = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        }
        if ($module === 'invoices' && ! isset($validated['paid_amount']) && ! $id) {
            $validated['paid_amount'] = 0;
        }
        if ($module === 'invoices' && empty($validated['payment_status']) && ! $id) {
            $validated['payment_status'] = 'unpaid';
        }

        return ['data' => $validated];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeIncomingData(string $module, array $data): array
    {
        // Allow flatter AI payloads.
        if ($module === 'projects' && isset($data['client']) && ! isset($data['client_name'])) {
            $data['client_name'] = $data['client'];
        }
        if ($module === 'invoices' && isset($data['client']) && ! isset($data['client_name'])) {
            $data['client_name'] = $data['client'];
        }
        if ($module === 'payments' && isset($data['mode']) && ! isset($data['payment_mode'])) {
            $data['payment_mode'] = $data['mode'];
        }

        if (isset($data['payment_mode'])) {
            $data['payment_mode'] = Str::lower(str_replace(' ', '_', (string) $data['payment_mode']));
        }
        if (isset($data['project_status'])) {
            $data['project_status'] = Str::lower(str_replace(' ', '_', (string) $data['project_status']));
        }
        if (isset($data['payment_status'])) {
            $data['payment_status'] = Str::lower((string) $data['payment_status']);
        }
        if (isset($data['status'])) {
            $data['status'] = Str::lower((string) $data['status']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCreate(string $module, array $data): Model
    {
        return match ($module) {
            'clients' => Client::create($data),
            'projects' => Project::create($data),
            'invoices' => Invoice::create($data),
            'payments' => tap(Payment::create($data), fn (Payment $payment) => $this->syncInvoicePaidAmount($payment->invoice_id)),
            'users' => $this->createUser($data),
            default => throw new \InvalidArgumentException('Unsupported module'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistUpdate(string $module, Model $record, array $data): Model
    {
        if ($module === 'users') {
            /** @var User $record */
            $role = $data['role'] ?? null;
            unset($data['role']);

            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $record->fill($data)->save();

            if ($role) {
                $record->syncRoles([$role]);
            }

            return $record->fresh('roles');
        }

        $oldInvoiceId = $module === 'payments' ? (int) $record->getAttribute('invoice_id') : null;
        $record->fill($data)->save();

        if ($module === 'payments') {
            /** @var Payment $record */
            $this->syncInvoicePaidAmount($oldInvoiceId ?? $record->invoice_id);
            if (isset($data['invoice_id']) && (int) $data['invoice_id'] !== $oldInvoiceId) {
                $this->syncInvoicePaidAmount((int) $data['invoice_id']);
            }

            return $record->fresh(['invoice.client']);
        }

        return $record->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createUser(array $data): User
    {
        $role = $data['role'] ?? 'HR User';
        unset($data['role']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        if (Role::where('name', $role)->exists()) {
            $user->syncRoles([$role]);
        }

        return $user->fresh('roles');
    }

    private function syncInvoicePaidAmount(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return;
        }

        $paid = (float) Payment::query()->where('invoice_id', $invoiceId)->sum('amount');
        $invoice->paid_amount = $paid;

        if ($paid <= 0) {
            $invoice->payment_status = 'unpaid';
        } elseif ($paid >= (float) $invoice->amount) {
            $invoice->payment_status = 'paid';
        } else {
            $invoice->payment_status = 'partial';
        }

        $invoice->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(string $module, Model $model): array
    {
        return match ($module) {
            'clients' => [
                'id' => $model->id,
                'company_name' => $model->company_name,
                'contact_person' => $model->contact_person,
                'email' => $model->email,
                'mobile' => $model->mobile,
                'status' => $model->status,
            ],
            'projects' => [
                'id' => $model->id,
                'project_name' => $model->project_name,
                'client_id' => $model->client_id,
                'client' => $model->client?->company_name,
                'start_date' => optional($model->start_date)?->toDateString(),
                'deadline' => optional($model->deadline)?->toDateString(),
                'total_amount' => (float) $model->total_amount,
                'payment_received' => (float) $model->payment_received,
                'project_status' => $model->project_status,
            ],
            'invoices' => [
                'id' => $model->id,
                'invoice_number' => $model->invoice_number,
                'client_id' => $model->client_id,
                'client' => $model->client?->company_name,
                'invoice_date' => optional($model->invoice_date)?->toDateString(),
                'due_date' => optional($model->due_date)?->toDateString(),
                'amount' => (float) $model->amount,
                'paid_amount' => (float) $model->paid_amount,
                'payment_status' => $model->payment_status,
            ],
            'payments' => [
                'id' => $model->id,
                'invoice_id' => $model->invoice_id,
                'invoice_number' => $model->invoice?->invoice_number,
                'client' => $model->invoice?->client?->company_name,
                'amount' => (float) $model->amount,
                'payment_date' => optional($model->payment_date)?->toDateString(),
                'payment_mode' => $model->payment_mode,
            ],
            'users' => [
                'id' => $model->id,
                'name' => $model->name,
                'email' => $model->email,
                'role' => method_exists($model, 'getRoleNames') ? $model->getRoleNames()->first() : null,
            ],
            default => $model->toArray(),
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function def(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->groqSafeProperties($properties),
                    'required' => $required,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, array<string, mixed>>
     */
    private function groqSafeProperties(array $properties): array
    {
        foreach ($properties as $key => $schema) {
            $type = $schema['type'] ?? null;

            if (in_array($type, ['number', 'integer', 'boolean'], true)) {
                $properties[$key]['type'] = 'string';
                $hint = match ($type) {
                    'boolean' => 'Use "true" or "false".',
                    'integer' => 'Provide as a numeric string if needed.',
                    default => 'Provide as a numeric string if needed.',
                };
                $properties[$key]['description'] = trim(($schema['description'] ?? $key).' '.$hint);
            }
        }

        return $properties;
    }
}
