<?php

use App\Models\AssetType;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_ASSETS = [
        'Desktop PC',
        'Headset',
        'Keyboard',
        'Laptop',
        'Monitor',
        'Mouse',
        'SIM',
        'Stand',
        'Web Cam',
    ];

    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Company::query()->pluck('id')->each(function (int $companyId) {
            foreach (self::DEFAULT_ASSETS as $index => $name) {
                AssetType::query()->create([
                    'company_id' => $companyId,
                    'name' => $name,
                    'sort_order' => $index + 1,
                    'status' => 'active',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_types');
    }
};
