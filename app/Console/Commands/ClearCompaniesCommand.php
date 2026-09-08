<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ClearCompaniesCommand extends Command
{
    protected $signature = 'companies:clear {--force : Skip confirmation}';

    protected $description = 'Remove all companies, company admin users, logos, and related tokens';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete all companies and related data. Continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $userIds = User::query()->whereNotNull('company_id')->pluck('id');

        DB::transaction(function () use ($userIds) {
            if ($userIds->isNotEmpty()) {
                PersonalAccessToken::query()
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();

                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            }

            Company::query()->each(function (Company $company) {
                if ($company->logo && file_exists(public_path($company->logo))) {
                    @unlink(public_path($company->logo));
                }
            });

            User::query()->whereNotNull('company_id')->delete();
            Company::query()->delete();
        });

        $this->info('All companies and related data have been cleared.');

        return self::SUCCESS;
    }
}
