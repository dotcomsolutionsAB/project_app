<?php

namespace App\Utils;

use App\Models\ProductModel;

class CartPricingUtility
{
    private const PURCHASE_PRICE_USER_IDS = [533];

    public static function resolveRate(int $userId, string $productCode, $fallbackRate): float
    {
        if (!in_array($userId, self::PURCHASE_PRICE_USER_IDS, true)) {
            return (float) $fallbackRate;
        }

        $purchase = ProductModel::where('product_code', $productCode)->value('purchase');

        return $purchase !== null ? (float) $purchase : (float) $fallbackRate;
    }
}
