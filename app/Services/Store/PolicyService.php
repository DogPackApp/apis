<?php

namespace App\Services\Store;

use App\Models\Product\Item;
use App\Models\Store\Policy;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Collection;

class PolicyService
{
    public Store $store;

    public Policy $policy;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function policyCountByStore(): int
    {
        return Policy::query()
            ->where('store_id', $this->store->id)
            ->count();
    }

    public function fetchPoliciesByStore(): array|Collection
    {
        return Policy::query()
            ->withCount('items')
            ->where('store_id', $this->store->id)
            ->get();
    }

    public function create($data): PolicyService
    {
        if ($data['time'] == '') {
            $data['time'] = null;
        }

        $policy = new Policy;
        $policy->store_id = $this->store->id;
        $policy->name = $data['name'];
        $policy->is_return = $data['is_return'] ?? 0;
        $policy->is_exchange = $data['is_exchange'] ?? 0;
        $policy->time = $data['time'] ?? null;
        $policy->custom_text = $data['custom_text'] ?? null;
        if ($this->policyCountByStore() <= 0) {
            $policy->is_default = 1;
        } else {
            $policy->is_default = $data['is_default'] ?? 0;
        }

        if ($policy->is_default) {
            Policy::query()
                ->where('store_id', $this->store->id)
                ->update(['is_default' => 0]);
        }

        $policy->save();

        $this->policy = $policy;

        $this->attachPolicyIfMissingFromItems();

        return $this;
    }

    public function attachPolicyIfMissingFromItems(): void
    {
        if ($this->policy->is_default) {
            Item::query()
                ->where('store_id', $this->store->id)
                ->whereNull('policy_id')
                ->update([
                    'policy_id' => $this->policy->id,
                ]);
        }
    }

    public function setDefault($policy): PolicyService
    {
        $this->removeDefaultFromAllPoliciesExcept($policy);

        $policy->update([
            'is_default' => true,
        ]);

        $this->policy = $policy;

        return $this;
    }

    public function removeDefaultFromAllPoliciesExcept($policy): void
    {
        Policy::query()
            ->where('store_id', $this->store->id)
            ->where('id', '!=', $policy->id)
            ->update(['is_default' => 0]);
    }

    public function update($data, $policy): PolicyService
    {
        if (isset($data['is_default']) && $data['is_default'] > 0 && ! $policy->is_default) {
            Policy::query()
                ->where('store_id', $this->store->id)
                ->update(['is_default' => 0]);
        }

        $this->policy = $policy;
        $this->policy->update($data);

        return $this;
    }

    public function isFirstPolicy(): bool
    {
        return Policy::query()
            ->where('store_id', resolve(Store::class)->id)
            ->count() === 1;
    }

    public function updateItemPolicy(array $itemIds, Policy $policy): void
    {
        Item::query()
            ->whereIn('id', $itemIds)
            ->update(['policy_id' => $policy->id]);
    }
}
