<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds up lng_get_product: stock aggregates on t_stock_order_items, cart lookups by user + product_code.
 * If an index already exists with the same name, skip or drop this migration’s up() for that index in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_stock_order_items', function (Blueprint $table) {
            if (!$this->indexExists('t_stock_order_items', 't_stock_order_items_product_code_index')) {
                $table->index('product_code', 't_stock_order_items_product_code_index');
            }
        });

        Schema::table('t_cart', function (Blueprint $table) {
            if (!$this->indexExists('t_cart', 't_cart_user_id_product_code_index')) {
                $table->index(['user_id', 'product_code'], 't_cart_user_id_product_code_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_stock_order_items', function (Blueprint $table) {
            $table->dropIndex('t_stock_order_items_product_code_index');
        });

        Schema::table('t_cart', function (Blueprint $table) {
            $table->dropIndex('t_cart_user_id_product_code_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        if ($driver === 'mysql') {
            $db = $connection->getDatabaseName();
            $result = $connection->selectOne(
                'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$db, $table, $indexName]
            );

            return isset($result->c) && (int) $result->c > 0;
        }

        return false;
    }
};
