<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use App\Services\Order\CountryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrderBilling extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        'uuid',
        'store_order_id',
        'discount',
        'dp_contribution',
        'seller_contribution',
        'promo',
        'wallet_discount',
        'wallet',
        'tax_rate',
        'tax_breakdown',
        'tax',
        'total',
        'exchange_rate',
    ];

    protected $casts = [
        'tax_breakdown' => 'array',
        'currency_details' => 'array',
        'promo' => 'array',
        'wallet' => 'array',
    ];

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    /**
     * Get total amount of a Store order
     *
     * @return float|int
     */
    public function getTotalAmountAttribute()
    {
        return $this->total / 100;
    }

    /**
     * Get shipping amount of a Store order
     */
    public function getShippingAmountAttribute(): int|float
    {
        $value = $this->currency_details['delivery'] ?? 0;

        return round(10 ? (float) $value : 0, mode: PHP_ROUND_HALF_UP);
    }

    /**
     * Get tax amount of a Store order
     *
     * @return float|int
     */
    public function getTaxAmountAttribute()
    {
        return $this->tax / 100;
    }

    /**
     * Seller-facing order total in cents (basis for payout / commission / Shopify transaction).
     *
     * Customer-paid total already reflects the full discount. When DogPack contributes, to a
     * promo or to a Subscribe & Save first order, add that share back so the seller-facing
     * total reflects the subsidized value.
     *
     * The seller's part of a Subscribe & Save early-cancellation charge is added too: the
     * customer paid it after the order, so it is not in the customer-paid total.
     */
    public function sellerOrderTotalCents(): int
    {
        $total = (int) $this->total + (int) ($this->subscribe_save_cancel_fee ?? 0);
        $dpContribution = (int) ($this->dp_contribution ?? 0);
        $walletDiscount = (int) ($this->wallet_discount ?? 0);

        if ($dpContribution > 0) {
            return $total + $dpContribution + $walletDiscount;
        }

        if ($walletDiscount > 0) {
            return $total + $walletDiscount;
        }

        return $total;
    }

    /**
     * Combined DogPack subsidy for API display: promo dp_contribution + seller-currency wallet discount (cents).
     */
    public function displayDpContributionCents(): int
    {
        return (int) ($this->dp_contribution ?? 0) + $this->sellerWalletDiscountCents();
    }

    /**
     * Wallet discount in store currency cents for seller-panel display.
     *
     * New orders store seller cents in wallet_discount and buyer amount on wallet.discount_amount.
     * Legacy cross-border rows stored buyer cents in the column; convert those with exchange_rate.
     */
    public function sellerWalletDiscountCents(?string $storeCurrency = null): int
    {
        $columnCents = (int) ($this->wallet_discount ?? 0);
        if ($columnCents <= 0) {
            return 0;
        }

        $wallet = is_array($this->wallet) ? $this->wallet : [];
        $buyerCurrency = strtoupper(trim((string) ($wallet['currency'] ?? '')));
        $buyerAmountCents = (int) round(((float) ($wallet['discount_amount'] ?? 0)) * 100);
        $rate = (float) ($this->exchange_rate ?? 1);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        $storeCurrency = strtoupper(trim((string) ($storeCurrency ?? $this->resolveStoreCurrency())));
        $currenciesDiffer = $storeCurrency !== '' && $buyerCurrency !== '' && $buyerCurrency !== $storeCurrency;
        $legacyWithoutStore = $storeCurrency === '' && $buyerCurrency !== '' && abs($rate - 1.0) > 0.0001;
        $isLegacyBuyerColumn = $buyerAmountCents === $columnCents
            && $buyerAmountCents > 0
            && ($currenciesDiffer || $legacyWithoutStore);

        if ($isLegacyBuyerColumn) {
            return (int) round(($columnCents / 100 / $rate) * 100);
        }

        return $columnCents;
    }

    private function resolveStoreCurrency(): string
    {
        $countryId = $this->order?->store?->country_id;
        if ($countryId === null) {
            return '';
        }

        return strtoupper((new CountryService)->getCurrencyCodeByCountryId((int) $countryId));
    }

    /**
     * Discount amount attributed to the seller for Shopify sync, in cents.
     *
     * Orders may mix seller-panel discounts and promo-code discounts across line items.
     * `discount` is the combined total; subtract DogPack's promo share so Shopify receives
     * panel discounts plus the seller's promo contribution.
     */
    public function shopifyDiscountCents(): int
    {
        $discount = (int) ($this->discount ?? 0);
        $dpContribution = (int) ($this->dp_contribution ?? 0);

        return max(0, $discount - $dpContribution);
    }
}
