<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_shipments', function (Blueprint $table): void {
            $table->string('vehicle', 120)->nullable();
            $table->string('driver_name', 120)->nullable();
            $table->string('driver_phone', 40)->nullable();
            $table->string('plate_number', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_shipments', function (Blueprint $table): void {
            $table->dropColumn(['vehicle', 'driver_name', 'driver_phone', 'plate_number']);
        });
    }
};
