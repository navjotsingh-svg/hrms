<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_family_members', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('date_of_birth');
            $table->text('notes')->nullable()->after('status');
            $table->foreignId('submitted_by_user_id')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->after('submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('reviewed_by_user_id');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
        });

        if (Schema::hasTable('employee_personal_sections')) {
            $familySections = DB::table('employee_personal_sections')
                ->where('section_type', 'family')
                ->get();

            foreach ($familySections as $section) {
                $payload = json_decode($section->payload, true);
                $members = $payload['members'] ?? [];

                if (DB::table('employee_family_members')->where('employee_id', $section->employee_id)->exists()) {
                    continue;
                }

                foreach (array_values($members) as $index => $member) {
                    DB::table('employee_family_members')->insert([
                        'company_id' => $section->company_id,
                        'employee_id' => $section->employee_id,
                        'name' => $member['name'],
                        'relation' => $member['relation'],
                        'phone' => $member['phone'] ?? null,
                        'date_of_birth' => $member['date_of_birth'] ?? null,
                        'sort_order' => $index,
                        'status' => $section->status,
                        'notes' => $section->notes,
                        'submitted_by_user_id' => $section->submitted_by_user_id,
                        'reviewed_by_user_id' => $section->reviewed_by_user_id,
                        'submitted_at' => $section->submitted_at,
                        'reviewed_at' => $section->reviewed_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('employee_personal_sections')->where('section_type', 'family')->delete();
        }

        DB::table('employee_family_members')
            ->whereNull('submitted_at')
            ->update([
                'status' => 'approved',
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('employee_family_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn(['status', 'notes', 'submitted_at', 'reviewed_at']);
        });
    }
};
