# Policies — not implemented

The seller policy routes are a 1:1 port of `backend-api`. The items below are real problems in that code. They are recorded here so they are not "fixed" during the port.

## Response and URL

- Paths live under `/api/seller/policy`, not `/api/marketplace/policy`. The route list and payloads match.
- `GET /policy/{uuid}/items` still eager-loads `items.policy` and `items.media`, but the product JSON is this app's `ProductResource`. It does not return media, policy, variants, tags, or sections. `backend-api`'s product resource does.
- `ConvertTimeZone` converts `created_at` and `updated_at` to the bound store timezone.
- `product_policies` exists in `backend-api` and is not used by these endpoints. It was not created here. Items are linked through `items.policy_id`.

## Authorization

- A policy is loaded by `uuid` only. The seller is not checked. Anyone with a seller token can show, rename, delete, or attach items to another store's policy if they know the uuid.
- `updateItemPolicy()` updates every item id it is given. It does not check that the item belongs to the seller's store.
- `backend-api` resolves the store through `getAdminUser()` so a team member acts as the store owner. Teams are not moving, so this app resolves the authenticated seller's own store.

## Copied behavior

- Create reads `$data['time']` before the null fallback. A request that omits `time` throws on that key. Return and exchange still require `time` through validation when those flags are true.
- The first policy for a store is always saved as the default, then every item on that store with a null `policy_id` is pointed at it. That also marks onboarding `is_store_setting`.
- A store is limited to 4 policies. The error is the string `Cannot Create More than 4 Policies` inside `errors`, not a field bag.
- Update calls `DB::commit()` without `DB::beginTransaction()`.
- Clearing the default is blocked only when `is_default` is strictly `false`. Sending `0` does not trip that guard.
- The default policy cannot be deleted. If a store has no default, delete takes `first()->id` (an integer) and then calls `update()` on it, so that path errors.
- `PolicyItemsController` swaps the environment check. Outside production the catch returns `Something went wrong`. In production it returns the exception message. The other policy controllers do the opposite, and create, update, and delete put the exception object itself in the JSON when not in production.
- Validation stays inline. Field failures return `{ "errors": { ... } }` with no top-level `message`.
- A seller with no store does not get the store routes' 404. Resolving `Store` returns null and the policy action errors.
- Foreign keys use `constrained()`. `backend-api` adds them in a later migration as `ON DELETE NO ACTION`.
