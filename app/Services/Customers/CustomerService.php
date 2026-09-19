<?php

namespace App\Services\Customers;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Arr;

class CustomerService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(User $user, array $data): Customer
    {
        if (! empty($data['client_uuid'])) {
            $existing = Customer::forUser($user)->where('client_uuid', $data['client_uuid'])->first();

            if ($existing) {
                return $existing;
            }
        }

        $customer = $user->customers()->create(Arr::only($data, ['name', 'phone', 'address', 'note', 'client_uuid']));

        $this->audit->record($customer, AuditService::ACTION_CREATED, [], $customer->only(['name', 'phone']));

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $data = Arr::only($data, ['name', 'phone', 'address', 'note']);
        $old = Arr::only($customer->getAttributes(), array_keys($data));

        $customer->fill($data)->save();

        if ($customer->wasChanged()) {
            $this->audit->record($customer, AuditService::ACTION_UPDATED, $old, $customer->getChanges());
        }

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        if ((float) $customer->balance != 0.0) {
            throw new ApiException(__('messages.customer.has_debt'), 422, 'customer_has_debt', [
                'balance' => (float) $customer->balance,
            ]);
        }

        $this->audit->record($customer, AuditService::ACTION_DELETED, $customer->only(['name', 'phone']));

        $customer->delete();
    }
}
