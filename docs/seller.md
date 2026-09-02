# Seller Model

`App\Models\Seller\Seller` — `app/Models/Seller/Seller.php`

The authenticatable entity for the marketplace seller-facing API. Authenticates on the `marketplace` Passport guard (`config/auth.php`), separate from the app's default `web`/`users` guard.

## Table: `sellers`

Migration: `database/migrations/2026_08_06_210000_create_sellers_table.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint, PK | |
| `uuid` | uuid, unique | Public identifier; auto-generated on create (see below). Route-model-bound via `{seller:uuid}`. |
| `first_name` | string | |
| `last_name` | string | |
| `email` | string, unique | Login identifier. |
| `phone` | string(100), nullable | |
| `password` | string | Hashed. |
| `google_id` | string, nullable | Set when the seller has signed in with Google at least once. |
| `status` | tinyint, default `0` | `0` = unverified, `1` = verified. See `isVerified()`. |
| `twofa_enabled` | tinyint, default `0` | `1` = OTP required on password/Google login. See `is2FAEnabled()`. |
| `remember_token` | string | Laravel standard. |
| `created_at` / `updated_at` | timestamps | |

## Class contract

- Extends `Illuminate\Foundation\Auth\User` (`Authenticatable`).
- Implements `Illuminate\Contracts\Auth\CanResetPassword` and `Laravel\Passport\Contracts\OAuthenticatable`.
- Traits: `Laravel\Passport\HasApiTokens`, `Illuminate\Database\Eloquent\Factories\HasFactory`, `Illuminate\Notifications\Notifiable`.

### Fillable

`uuid, first_name, last_name, email, phone, password, status, google_id, twofa_enabled`

### Hidden

`password, remember_token` — excluded from array/JSON serialization (not that `Seller` is serialized directly; API responses go through `App\Http\Resources\Seller\SellerResource`).

### Casts

```php
'status' => 'integer',
'twofa_enabled' => 'integer',
```

## Behavior

### UUID generation

On `creating`, `uuid` is auto-populated with `Str::uuid()` if not already set. Never set manually.

### `sendPasswordResetNotification(string $token): void`

Overrides the default `CanResetPassword` behavior. Instead of Laravel's built-in `ResetPassword` notification, it sends `App\Mail\SellerForgotPasswordEmail` directly via the `Mail` facade, using the `sellers` password broker (`config/auth.php: passwords.sellers`, table `password_resets`, 60-minute expiry). Triggered by `Password::broker('sellers')->sendResetLink()`.

### `isVerified(): bool`

`true` when `status === 1`. A seller becomes verified via `SellerVerifyController` after submitting a correct OTP (see [Registration & verification](#registration--verification)) — or immediately on Google sign-up, where `status` is set to `1` at creation.

### `is2FAEnabled(): bool`

`true` when `twofa_enabled === 1`. When enabled, both password login and Google login short-circuit into an OTP challenge instead of issuing a token immediately.

### `store(): HasOne`

```php
$seller->store; // App\Models\Store\Store|null
```

A seller has at most one `Store` (see [store.md](./store.md)). No store exists until the seller completes the "create store" onboarding step (`POST /api/seller/store`).

## Related models

- `App\Models\Seller\SellerOtps` — one-time codes for registration verification, 2FA login, and Google-login 2FA. Each row has `seller_id`, `otp`, `is_active`, `login_type` (defaults to `'seller'`, allowing the same table to serve multiple OTP purposes). Managed exclusively through `App\Services\OTPService`.
- `App\Models\Seller\OnboardingStatus` — tracks onboarding step completion (`is_product`, `is_shipping`, `is_store_setting`, `is_finance`, `is_subscribe`), one row per seller, `seller_id` unique. Managed through `App\Services\OnboardingService`. See `App\Enums\OnboardingStep` for the typed step names.
- `App\Models\Store\Store` — see [store.md](./store.md).

## Auth guard & tokens

- Guard: `marketplace` (`config/auth.php`), driver `passport`, provider `sellers`.
- Tokens are personal-access tokens issued via `HasApiTokens::createToken('SellerAuthToken', [...scopes])`, not the OAuth authorization-code flow — `Passport::routes()` is not registered.
- Scopes (`Passport::tokensCan()`, set in `App\Providers\AppServiceProvider::boot()`): `seller` (normal API access), `master-login` (reserved for future admin impersonation — not currently issued anywhere).
- Token lifetimes: access tokens 30 days, refresh tokens 90 days (`Passport::tokensExpireIn` / `refreshTokensExpireIn`, also in `AppServiceProvider::boot()`).

## HTTP surface (`routes/api.php`, prefix `/api/seller`)

All routes are rate-limited via the `seller-auth` limiter (10 requests/minute, keyed by `email` input or IP — `AppServiceProvider::boot()`) where noted "public".

| Method | Path | Controller | Auth | Notes |
|---|---|---|---|---|
| POST | `/register` | `SellerRegistrationController` | public | Creates seller (`status=0`), issues a token immediately, sends OTP via `SellerOTPMail`. |
| POST | `/login` | `SellerLoginController` | public | Password login. Returns `202` + OTP email if `is2FAEnabled()`, otherwise a token. |
| POST | `/login/otp` | `SellerLoginOTPController` | public | Completes a 2FA login by submitting the OTP. |
| POST | `/password/forgot` | `SellerForgotPasswordController` | public | Sends `SellerForgotPasswordEmail` via the `sellers` password broker. |
| POST | `/password/reset` | `SellerResetPasswordController` | public | Resets password, sends `SellerPasswordUpdatedMail`. |
| POST | `/google/login` | `SellerGoogleLoginController` | public | Hand-rolled Google OAuth code exchange (not Socialite). Creates the seller pre-verified (`status=1`) on first sign-in. |
| GET | `/me` | `SellerProfileController` | `auth:marketplace` | Returns `SellerResource`, eager-loading `store`. |
| POST | `/logout` | `SellerLogoutController` | `auth:marketplace` | Revokes the current access token only (not the refresh token). |
| POST | `/verify` | `SellerVerifyController` | `auth:marketplace` | Verifies registration OTP, sets `status=1`, calls `OnboardingService::initiateOnboarding()`. |
| GET | `/onboarding/status` | `SellerOnboardingStatusController` | `auth:marketplace` | Returns `OnboardingResource`, or `204` if onboarding hasn't started. |
| PUT | `/{seller:uuid}` | `SellerUpdateController` | `auth:marketplace` | Updates `first_name`/`last_name`/`phone`. A seller may only update themself. |

### Registration & verification

1. `POST /register` — seller created with `status=0`, token issued, OTP emailed (`login_type='seller'`).
2. `POST /verify` (authenticated with the token from step 1) — submits the OTP; on success sets `status=1` and starts onboarding (`OnboardingStatus` row created with every step at `0`).

### Failure notifications

Unexpected exceptions (`\Throwable`) in `register`, `login`, `login/otp`, `password/forgot`, `password/reset`, and `google/login` are reported via `report()` and additionally sent to Slack through `App\Services\AuthFailureNotifier` → `App\Notifications\Seller\SellerAuthFailed`, routed on-demand to the webhook in `config('services.slack.seller_auth_webhook')` (env `SLACK_SELLER_AUTH_WEBHOOK`). Trigger a real webhook manually with:

```
php artisan seller:test-slack-alert {email=test@example.com}
```

## Factory

`Database\Factories\SellerFactory` — `database/factories/SellerFactory.php`

- Default password for every factory-created seller: `password` (hashed once and cached per test run via a static property, for speed).
- States: `verified()` (sets `status=1`), `withTwoFactor()` (sets `twofa_enabled=1`).

## See also

- [store.md](./store.md) — the store a seller owns.
