<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('helpdesk_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helpdesk_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->default(0);
            $table->timestamps();
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreignId('helpdesk_category_id')->nullable()->after('description');
        });

        $defaultCategories = config('helpdesk.categories', []);
        $companyIds = DB::table('helpdesk_tickets')->distinct()->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $slugToId = [];
            $sort = 0;

            foreach ($defaultCategories as $slug => $name) {
                $slugToId[$slug] = DB::table('helpdesk_categories')->insertGetId([
                    'company_id' => $companyId,
                    'name' => $name,
                    'sort_order' => $sort++,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('helpdesk_tickets')
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->chunkById(100, function ($tickets) use ($slugToId, $defaultCategories, $companyId) {
                    foreach ($tickets as $ticket) {
                        $slug = $ticket->category ?: 'general';

                        if (! isset($slugToId[$slug])) {
                            $name = $defaultCategories[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
                            $maxSort = (int) DB::table('helpdesk_categories')
                                ->where('company_id', $companyId)
                                ->max('sort_order');

                            $slugToId[$slug] = DB::table('helpdesk_categories')->insertGetId([
                                'company_id' => $companyId,
                                'name' => $name,
                                'sort_order' => $maxSort + 1,
                                'status' => 'active',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('helpdesk_tickets')
                            ->where('id', $ticket->id)
                            ->update(['helpdesk_category_id' => $slugToId[$slug]]);
                    }
                });
        }

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreign('helpdesk_category_id')
                ->references('id')
                ->on('helpdesk_categories')
                ->nullOnDelete();
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->string('category', 40)->default('general')->after('description');
        });

        DB::table('helpdesk_tickets')
            ->whereNotNull('helpdesk_category_id')
            ->orderBy('id')
            ->chunkById(100, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $name = DB::table('helpdesk_categories')
                        ->where('id', $ticket->helpdesk_category_id)
                        ->value('name');

                    $slug = strtolower(str_replace([' ', '/'], ['_', ''], (string) $name));
                    $slug = preg_replace('/[^a-z0-9_]/', '', $slug) ?: 'general';

                    DB::table('helpdesk_tickets')
                        ->where('id', $ticket->id)
                        ->update(['category' => $slug]);
                }
            });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign(['helpdesk_category_id']);
            $table->dropColumn('helpdesk_category_id');
        });

        Schema::dropIfExists('helpdesk_ticket_attachments');
        Schema::dropIfExists('helpdesk_categories');
    }
};
