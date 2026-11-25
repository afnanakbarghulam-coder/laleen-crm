<?php

namespace App\Support;

/**
 * Authoritative server-side pricing & discount calculator for standalone Services
 * and "Service + Freebies" anchor line items.
 *
 * Conventions:
 * - `original_price` stores the pre-discount base price (catalog price, or raised custom price).
 * - `discount_type` stores null, 'fixed', or 'percent' (legacy 'flat' is normalized to 'fixed').
 * - `discount_value` stores the entered QAR amount (for 'fixed') or percentage 0-100 (for 'percent').
 * - `discount_amount` always stores the resolved QAR amount deducted (>= 0, <= original_price).
 * - `price` stores the net discounted amount (`round(original_price - discount_amount, 2)`).
 */
class LineDiscountCalculator
{
    public const MAX_REASON_LENGTH = 150;

    /**
     * @return array{
     *     error: ?string,
     *     original_price: float,
     *     price: float,
     *     discount_type: ?string,
     *     discount_value: float,
     *     discount_amount: float,
     *     discount_reason: ?string
     * }
     */
    public static function resolve(
        float $catalogPrice,
        ?string $rawDiscountType = null,
        mixed $rawDiscountValue = null,
        mixed $rawFinalPrice = null,
        ?string $rawDiscountReason = null,
        string $itemLabel = 'Service'
    ): array {
        $base = round(max(0.0, $catalogPrice), 2);

        $reason = $rawDiscountReason !== null ? trim((string) $rawDiscountReason) : '';
        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            return self::errorResult(
                "{$itemLabel}: discount reason must be " . self::MAX_REASON_LENGTH . ' characters or fewer.',
                $base
            );
        }
        $reason = $reason !== '' ? $reason : null;

        $typeRaw = strtolower(trim((string) ($rawDiscountType ?? '')));
        if ($typeRaw === 'flat') {
            $typeRaw = 'fixed';
        }
        if ($typeRaw !== '' && ! in_array($typeRaw, ['fixed', 'percent'], true)) {
            return self::errorResult("{$itemLabel}: discount type must be fixed (QAR) or percent (%).", $base);
        }
        $discountType = $typeRaw !== '' ? $typeRaw : null;

        $hasDiscountVal = $rawDiscountValue !== null && $rawDiscountValue !== '';
        if ($hasDiscountVal && ! is_numeric($rawDiscountValue)) {
            return self::errorResult("{$itemLabel}: discount value must be a valid number.", $base);
        }
        $discountVal = $hasDiscountVal ? round((float) $rawDiscountValue, 2) : 0.0;
        if ($discountVal < 0) {
            return self::errorResult("{$itemLabel}: discount cannot be negative.", $base);
        }

        $hasFinalPrice = $rawFinalPrice !== null && $rawFinalPrice !== '';
        if ($hasFinalPrice && ! is_numeric($rawFinalPrice)) {
            return self::errorResult("{$itemLabel}: final price must be a valid number.", $base);
        }
        $finalPriceInput = $hasFinalPrice ? round((float) $rawFinalPrice, 2) : null;
        if ($finalPriceInput !== null && $finalPriceInput < 0) {
            return self::errorResult("{$itemLabel}: price cannot be negative.", $base);
        }

        // Branch 1: Explicit discount value (> 0) applied against catalog/base price.
        if ($discountVal > 0) {
            $effectiveType = $discountType ?? 'fixed';

            if ($effectiveType === 'percent') {
                if ($discountVal > 100.0) {
                    return self::errorResult("{$itemLabel}: percentage discount cannot exceed 100%.", $base);
                }
                $discountAmount = round($base * ($discountVal / 100.0), 2);
                if ($discountAmount > $base) {
                    $discountAmount = $base;
                }
                $netPrice = round(max(0.0, $base - $discountAmount), 2);

                return [
                    'error' => null,
                    'original_price' => $base,
                    'price' => $netPrice,
                    'discount_type' => $discountAmount > 0 ? 'percent' : null,
                    'discount_value' => $discountAmount > 0 ? $discountVal : 0.0,
                    'discount_amount' => $discountAmount,
                    'discount_reason' => $discountAmount > 0 ? $reason : null,
                ];
            }

            // Fixed QAR discount
            if ($discountVal > $base) {
                return self::errorResult(
                    sprintf('%s: discount (%.2f QAR) cannot exceed base price (%.2f QAR).', $itemLabel, $discountVal, $base),
                    $base
                );
            }

            $discountAmount = round($discountVal, 2);
            $netPrice = round(max(0.0, $base - $discountAmount), 2);

            return [
                'error' => null,
                'original_price' => $base,
                'price' => $netPrice,
                'discount_type' => 'fixed',
                'discount_value' => $discountAmount,
                'discount_amount' => $discountAmount,
                'discount_reason' => $reason,
            ];
        }

        // Branch 2: Direct final price input with no explicit discount value.
        if ($finalPriceInput !== null) {
            if ($finalPriceInput < $base) {
                $discountAmount = round($base - $finalPriceInput, 2);

                return [
                    'error' => null,
                    'original_price' => $base,
                    'price' => $finalPriceInput,
                    'discount_type' => 'fixed',
                    'discount_value' => $discountAmount,
                    'discount_amount' => $discountAmount,
                    'discount_reason' => $reason,
                ];
            }

            // Equal to or higher than catalog price (e.g. extra-long hair adjustment):
            // treat as adjusted base price with zero discount.
            return [
                'error' => null,
                'original_price' => $finalPriceInput,
                'price' => $finalPriceInput,
                'discount_type' => null,
                'discount_value' => 0.0,
                'discount_amount' => 0.0,
                'discount_reason' => null,
            ];
        }

        // Branch 3: Default catalog price, no discount.
        return [
            'error' => null,
            'original_price' => $base,
            'price' => $base,
            'discount_type' => null,
            'discount_value' => 0.0,
            'discount_amount' => 0.0,
            'discount_reason' => null,
        ];
    }

    private static function errorResult(string $message, float $base): array
    {
        return [
            'error' => $message,
            'original_price' => $base,
            'price' => $base,
            'discount_type' => null,
            'discount_value' => 0.0,
            'discount_amount' => 0.0,
            'discount_reason' => null,
        ];
    }
}
