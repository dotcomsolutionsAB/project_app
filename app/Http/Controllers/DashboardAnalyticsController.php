<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\OrderModel;
use App\Models\OrderItemsModel;
use App\Models\User;

class DashboardAnalyticsController extends Controller
{
    /**
     * Indian financial year: 1 Apr – 31 Mar.
     *
     * @return array{0: string, 1: string} Y-m-d
     */
    public static function currentIndianFinancialYearBounds(): array
    {
        $now = Carbon::now();

        if ((int) $now->month >= 4) {
            $start = $now->copy()->month(4)->startOfMonth();
            $end = $now->copy()->addYear()->month(3)->endOfMonth();
        } else {
            $start = $now->copy()->subYear()->month(4)->startOfMonth();
            $end = $now->copy()->month(3)->endOfMonth();
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = self::currentIndianFinancialYearBounds();

        $reqFrom = $request->query('date_from');
        $reqTo = $request->query('date_to');

        if ($reqFrom && $reqTo) {
            try {
                $parsedFrom = Carbon::parse($reqFrom)->format('Y-m-d');
                $parsedTo = Carbon::parse($reqTo)->format('Y-m-d');
                if ($parsedFrom <= $parsedTo) {
                    $maxDays = 800;
                    if (Carbon::parse($parsedFrom)->diffInDays(Carbon::parse($parsedTo)) <= $maxDays) {
                        $dateFrom = $parsedFrom;
                        $dateTo = $parsedTo;
                    }
                }
            } catch (\Throwable $e) {
                // keep defaults
            }
        }

        $fromDt = Carbon::parse($dateFrom)->startOfDay();
        $toDt = Carbon::parse($dateTo)->endOfDay();

        $summary = $this->buildSummary($fromDt, $toDt);
        $ordersTimeseries = $this->buildOrdersTimeseries($fromDt, $toDt);
        $topClients = $this->buildTopClients($fromDt, $toDt);
        $topProducts = $this->buildTopProducts($fromDt, $toDt);
        $statusBreakdown = $this->buildStatusBreakdown($fromDt, $toDt);

        return response()->json([
            'message' => 'Dashboard analytics fetched successfully.',
            'data' => [
                'period' => [
                    'date_from' => $fromDt->format('Y-m-d'),
                    'date_to' => $toDt->format('Y-m-d'),
                    'display_range' => $fromDt->format('j M Y') . ' – ' . $toDt->format('j M Y'),
                ],
                'summary' => $summary,
                'orders_timeseries' => $ordersTimeseries,
                'top_clients' => $topClients,
                'top_products' => $topProducts,
                'status_breakdown' => $statusBreakdown,
            ],
        ], 200);
    }

    private function buildSummary(Carbon $fromDt, Carbon $toDt): array
    {
        $base = OrderModel::query()
            ->whereBetween('order_date', [$fromDt, $toDt]);

        $totalOrders = (clone $base)->count();
        $totalRevenue = (float) ((clone $base)->sum('amount'));
        $pending = (clone $base)->where('status', 'pending')->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $cancelled = (clone $base)->where('status', 'cancelled')->count();

        $distinctClients = (int) OrderModel::query()
            ->whereBetween('order_date', [$fromDt, $toDt])
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'pending_orders' => $pending,
            'completed_orders' => $completed,
            'cancelled_orders' => $cancelled,
            'average_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            'distinct_clients' => $distinctClients,
        ];
    }

    private function buildOrdersTimeseries(Carbon $fromDt, Carbon $toDt): array
    {
        $driver = DB::connection()->getDriverName();
        $days = $fromDt->diffInDays($toDt);
        $bucket = $days <= 62 ? 'day' : 'month';

        if ($bucket === 'day') {
            $dayExpr = $driver === 'sqlite' ? 'date(order_date)' : 'DATE(order_date)';
            $rows = OrderModel::query()
                ->whereBetween('order_date', [$fromDt, $toDt])
                ->selectRaw("$dayExpr as bucket, COUNT(*) as order_count, COALESCE(SUM(amount), 0) as revenue")
                ->groupBy(DB::raw($dayExpr))
                ->orderBy('bucket')
                ->get();
        } else {
            $monthExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m', order_date)"
                : "DATE_FORMAT(order_date, '%Y-%m')";

            $rows = OrderModel::query()
                ->whereBetween('order_date', [$fromDt, $toDt])
                ->selectRaw("$monthExpr as bucket, COUNT(*) as order_count, COALESCE(SUM(amount), 0) as revenue")
                ->groupBy(DB::raw($monthExpr))
                ->orderBy('bucket')
                ->get();
        }

        return $rows->map(function ($r) use ($bucket) {
            $label = $r->bucket;
            if ($bucket === 'month' && $label) {
                try {
                    $label = Carbon::parse($label . '-01')->format('M Y');
                } catch (\Throwable $e) {
                    //
                }
            } elseif ($bucket === 'day' && $label) {
                try {
                    $label = Carbon::parse($label)->format('d M');
                } catch (\Throwable $e) {
                    //
                }
            }

            return [
                'label' => (string) $label,
                'orders' => (int) $r->order_count,
                'revenue' => round((float) $r->revenue, 2),
            ];
        })->values()->all();
    }

    private function buildTopClients(Carbon $fromDt, Carbon $toDt): array
    {
        $rows = OrderModel::query()
            ->whereBetween('order_date', [$fromDt, $toDt])
            ->whereNotNull('user_id')
            ->select([
                'user_id',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('COALESCE(SUM(amount), 0) as revenue'),
            ])
            ->groupBy('user_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $userIds = $rows->pluck('user_id')->filter()->unique()->values()->all();
        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'mobile'])
            ->keyBy('id');

        return $rows->map(function ($r) use ($users) {
            $u = $users->get($r->user_id);

            return [
                'user_id' => (int) $r->user_id,
                'name' => $u->name ?? 'Unknown',
                'mobile' => $u->mobile ?? '',
                'order_count' => (int) $r->order_count,
                'revenue' => round((float) $r->revenue, 2),
            ];
        })->values()->all();
    }

    private function buildTopProducts(Carbon $fromDt, Carbon $toDt): array
    {
        $rows = OrderItemsModel::query()
            ->join('t_orders', 't_order_items.order_id', '=', 't_orders.id')
            ->whereBetween('t_orders.order_date', [$fromDt, $toDt])
            ->where('t_orders.status', '!=', 'cancelled')
            ->groupBy('t_order_items.product_code')
            ->select([
                't_order_items.product_code',
                DB::raw('MAX(t_order_items.product_name) as product_name'),
                DB::raw('SUM(t_order_items.quantity) as qty_sold'),
                DB::raw('COALESCE(SUM(t_order_items.total), 0) as revenue'),
            ])
            ->orderByDesc('qty_sold')
            ->limit(10)
            ->get();

        return $rows->map(function ($r) {
            return [
                'product_code' => $r->product_code,
                'product_name' => $r->product_name ?? $r->product_code,
                'qty_sold' => (float) $r->qty_sold,
                'revenue' => round((float) $r->revenue, 2),
            ];
        })->values()->all();
    }

    private function buildStatusBreakdown(Carbon $fromDt, Carbon $toDt): array
    {
        $rows = OrderModel::query()
            ->whereBetween('order_date', [$fromDt, $toDt])
            ->select([
                'status',
                DB::raw('COUNT(*) as cnt'),
            ])
            ->groupBy('status')
            ->orderByDesc('cnt')
            ->get();

        return $rows->map(function ($r) {
            return [
                'status' => $r->status ?? 'unknown',
                'count' => (int) $r->cnt,
            ];
        })->values()->all();
    }
}
