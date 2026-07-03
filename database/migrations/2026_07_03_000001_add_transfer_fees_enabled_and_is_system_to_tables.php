<?php

declare(strict_types=1);

use App\Enums\SystemCategoryKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->boolean('transfer_fees_enabled')->default(true);
        });

        Schema::table('transaction_categories', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false);
            $table->string('key')->nullable()->unique();
        });

        // Insert default "Transfer Fees" category
        DB::table('transaction_categories')->insert([
            'name' => 'Transfer Fees',
            'type' => 'expense',
            'status' => 'active',
            'is_system' => true,
            'key' => SystemCategoryKey::TRANSFER_FEES->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropColumn('transfer_fees_enabled');
        });

        DB::table('transaction_categories')->where('key', SystemCategoryKey::TRANSFER_FEES->value)->delete();

        Schema::table('transaction_categories', function (Blueprint $table): void {
            $table->dropColumn(['is_system', 'key']);
        });
    }
};
