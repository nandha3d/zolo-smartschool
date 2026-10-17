<?php

use App\Addons\AddonRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canteen tables in each school's database. A copy of this file lives in
 * database/migrations/schools so new schools get it when they are provisioned.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('canteen_categories')) {
            Schema::create('canteen_categories', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canteen_items')) {
            Schema::create('canteen_items', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('category_id')->nullable()->constrained('canteen_categories')->nullOnDelete();
                $table->string('name');
                $table->string('code', 32)->nullable();
                $table->decimal('price', 10, 2);
                $table->decimal('cost_price', 10, 2)->nullable();
                $table->string('image')->nullable();
                $table->string('dietary_type', 8)->default('Veg'); // Veg, Non-Veg, Vegan, Egg
                $table->json('allergens')->nullable();
                $table->unsignedSmallInteger('calories')->nullable();
                $table->boolean('is_available')->default(true);
                $table->boolean('track_stock')->default(false);
                $table->integer('stock_qty')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('canteen_wallets')) {
            Schema::create('canteen_wallets', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->decimal('balance', 12, 2)->default(0);
                $table->string('rfid_card', 64)->nullable();
                $table->decimal('daily_limit', 10, 2)->nullable();
                $table->json('blocked_category_ids')->nullable();
                $table->json('allergens')->nullable();
                $table->boolean('is_locked')->default(false);
                $table->timestamps();
                $table->unique(['school_id', 'user_id']);
                $table->unique(['school_id', 'rfid_card']);
            });
        }

        if (!Schema::hasTable('canteen_orders')) {
            Schema::create('canteen_orders', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('order_number', 32)->unique();
                $table->unsignedSmallInteger('token_number');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('wallet_id')->nullable();
                $table->decimal('total_amount', 10, 2);
                $table->string('payment_mode', 8); // Wallet, Cash, Card
                $table->string('order_type', 10)->default('Counter'); // Counter, Pre_Order
                $table->string('status', 10)->default('Pending')->index(); // Pending, Preparing, Ready, Completed, Cancelled
                $table->date('service_date')->index();
                $table->unsignedBigInteger('served_by')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canteen_order_items')) {
            Schema::create('canteen_order_items', static function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('canteen_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('item_id');
                $table->string('name');
                $table->unsignedSmallInteger('quantity');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('subtotal', 10, 2);
            });
        }

        if (!Schema::hasTable('canteen_wallet_transactions')) {
            Schema::create('canteen_wallet_transactions', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('wallet_id')->constrained('canteen_wallets')->cascadeOnDelete();
                $table->string('type', 10); // Topup, Purchase, Refund, Adjustment
                $table->decimal('amount', 10, 2); // signed: credits positive, debits negative
                $table->decimal('balance_after', 12, 2);
                $table->string('payment_mode', 16)->nullable();
                $table->string('reference', 64)->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('note')->nullable();
                $table->timestamps();
                $table->index(['wallet_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('canteen_inventory_items')) {
            Schema::create('canteen_inventory_items', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('unit', 12)->default('kg'); // kg, litres, pcs, packets
                $table->decimal('current_stock', 12, 3)->default(0);
                $table->decimal('reorder_level', 12, 3)->default(0);
                $table->decimal('unit_cost', 10, 2)->nullable();
                $table->string('supplier')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canteen_inventory_movements')) {
            Schema::create('canteen_inventory_movements', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('inventory_item_id')->constrained('canteen_inventory_items')->cascadeOnDelete();
                $table->decimal('quantity_change', 12, 3);
                $table->decimal('stock_after', 12, 3);
                $table->string('reason', 16); // Purchase, Usage, Wastage, Correction
                $table->string('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canteen_meal_plans')) {
            Schema::create('canteen_meal_plans', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->decimal('price', 10, 2);
                $table->unsignedSmallInteger('duration_days')->default(30);
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('canteen_meal_subscriptions')) {
            Schema::create('canteen_meal_subscriptions', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('plan_id')->constrained('canteen_meal_plans')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('amount', 10, 2);
                $table->string('payment_mode', 8); // Wallet, Cash
                $table->string('status', 10)->default('Active'); // Active, Cancelled
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        AddonRegistry::syncSchoolPermissions();
    }

    public function down(): void
    {
        foreach (['canteen_meal_subscriptions', 'canteen_meal_plans', 'canteen_inventory_movements', 'canteen_inventory_items',
                     'canteen_wallet_transactions', 'canteen_order_items', 'canteen_orders', 'canteen_wallets',
                     'canteen_items', 'canteen_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
