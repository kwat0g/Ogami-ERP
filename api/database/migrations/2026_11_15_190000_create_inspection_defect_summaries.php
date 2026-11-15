<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_defect_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->unique()->constrained('inspections')->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('other_category', 100)->nullable();
            $table->string('severity', 20);
            $table->decimal('affected_quantity', 15, 3);
            $table->text('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inspection_defect_summary_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_defect_summary_id')->constrained('inspection_defect_summaries')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inspection_defect_summary_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_defect_summary_photos');
        Schema::dropIfExists('inspection_defect_summaries');
    }
};
