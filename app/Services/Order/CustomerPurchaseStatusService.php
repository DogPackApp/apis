<?php

namespace App\Services\Order;

use App\Models\Order\Customers;

class CustomerPurchaseStatusService
{
    public function canPurchase(?int $userId, ?string $email, ?string $paymentFingerprint): bool
    {
        $userId = $userId !== null && $userId > 0 ? $userId : null;
        $email = $this->normalizeEmail($email);
        $paymentFingerprint = is_string($paymentFingerprint) && $paymentFingerprint !== ''
            ? $paymentFingerprint
            : null;

        if ($userId === null && $email === null && $paymentFingerprint === null) {
            return true;
        }

        return ! Customers::on('mysql_reader')
            ->where('purchase_status', Customers::PURCHASE_STATUS_BLOCKED)
            ->where(function ($query) use ($userId, $email, $paymentFingerprint) {
                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }

                if ($email !== null) {
                    $query->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                }

                if ($paymentFingerprint !== null) {
                    $query->orWhereHas('storeOrder', function ($orderQuery) use ($paymentFingerprint) {
                        $orderQuery->withoutGlobalScopes()
                            ->whereHas('paymentDetail', function ($paymentQuery) use ($paymentFingerprint) {
                                $paymentQuery->where('payment_fingerprint', $paymentFingerprint);
                            });
                    });
                }
            })
            ->exists();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim($email ?? ''));

        return $email !== '' ? $email : null;
    }
}
