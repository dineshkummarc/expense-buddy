<?php

declare(strict_types=1);

use App\Enums\AmountGrouping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->enum('amount_grouping', AmountGrouping::values())
                ->default(AmountGrouping::Western->value)
                ->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropColumn('amount_grouping');
        });
    }
};
