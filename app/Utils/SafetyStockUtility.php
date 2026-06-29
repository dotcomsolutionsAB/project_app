<?php

namespace App\Utils;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class SafetyStockUtility
{
    public const SAFETY_PRODUCT_PREFIX = 'S-';

    public static function isSafetyProduct(?string $productCode): bool
    {
        return $productCode !== null && str_starts_with($productCode, self::SAFETY_PRODUCT_PREFIX);
    }

    public static function cutoffDate(): ?Carbon
    {
        $raw = env('SAFETY_STOCK_DATE');

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function cutoffDateString(): ?string
    {
        return self::cutoffDate()?->toDateString();
    }

    /**
     * Eloquent query on t_stock_order_items: include all non S- items;
     * for S- items only those linked to stock orders on/after SAFETY_STOCK_DATE.
     */
    public static function applyEligibleItemsFilter(Builder $query, string $itemsAlias = 't_stock_order_items'): Builder
    {
        $cutoff = self::cutoffDateString();
        if (!$cutoff) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($cutoff, $itemsAlias) {
            $q->where("{$itemsAlias}.product_code", 'not like', self::SAFETY_PRODUCT_PREFIX . '%')
                ->orWhereHas('stockOrder', fn (Builder $sq) => $sq->where('order_date', '>=', $cutoff));
        });
    }

    /**
     * DB query builder on t_stock_order_items (joins t_stock_orders once).
     */
    public static function applyEligibleItemsToTableQuery(
        QueryBuilder $query,
        string $itemsAlias = 'soi',
        string $orderAlias = 'safety_stock_orders'
    ): QueryBuilder {
        $cutoff = self::cutoffDateString();
        if (!$cutoff) {
            return $query;
        }

        $query->join("t_stock_orders as {$orderAlias}", "{$orderAlias}.id", '=', "{$itemsAlias}.stock_order_id");

        return $query->where(function (QueryBuilder $q) use ($cutoff, $itemsAlias, $orderAlias) {
            $q->where("{$itemsAlias}.product_code", 'not like', self::SAFETY_PRODUCT_PREFIX . '%')
                ->orWhere("{$orderAlias}.order_date", '>=', $cutoff);
        });
    }

    /** Stock balance subquery for product catalog joins. */
    public static function stockBalanceSubquery(): QueryBuilder
    {
        $query = DB::table('t_stock_order_items as soi')
            ->select(
                DB::raw('soi.product_code AS stock_bal_product_code'),
                DB::raw("SUM(CASE WHEN soi.type = 'IN' THEN soi.quantity ELSE 0 END) - SUM(CASE WHEN soi.type = 'OUT' THEN soi.quantity ELSE 0 END) AS balance")
            );

        self::applyEligibleItemsToTableQuery($query, 'soi', 'safety_stock_orders');

        return $query->groupBy('soi.product_code');
    }

    /**
     * History/listing start date: S- products never show movements before SAFETY_STOCK_DATE.
     */
    public static function effectiveHistoryStart($requestedStart, ?array $productCodes = null): Carbon
    {
        $start = $requestedStart
            ? Carbon::parse($requestedStart)->startOfDay()
            : now()->subMonths(3)->startOfDay();

        $cutoff = self::cutoffDate();
        if (!$cutoff) {
            return $start;
        }

        if ($productCodes !== null) {
            $includesSafetyProduct = false;
            foreach ($productCodes as $code) {
                if (self::isSafetyProduct(trim((string) $code))) {
                    $includesSafetyProduct = true;
                    break;
                }
            }

            if (!$includesSafetyProduct) {
                return $start;
            }
        }

        return $start->lt($cutoff) ? $cutoff->copy() : $start;
    }

    public static function isOrderEligibleForSafetyProduct(string $productCode, $orderDate): bool
    {
        if (!self::isSafetyProduct($productCode)) {
            return true;
        }

        $cutoff = self::cutoffDate();
        if (!$cutoff) {
            return true;
        }

        return Carbon::parse($orderDate)->startOfDay()->gte($cutoff);
    }

    public static function applySafetyCartCreatedFilter(Builder $query, string $productCode, string $tableAlias = 't_stock_cart'): Builder
    {
        if (!self::isSafetyProduct($productCode)) {
            return $query;
        }

        $cutoff = self::cutoffDate();
        if (!$cutoff) {
            return $query;
        }

        return $query->where("{$tableAlias}.created_at", '>=', $cutoff);
    }
}
