<?php

namespace App\Console\Commands;

use App\Services\ProbationCompletionService;
use Illuminate\Console\Command;

class ProcessProbationCompletionsCommand extends Command
{
    protected $signature = 'probation:process';

    protected $description = 'Confirm completed probations and send reminder alerts for upcoming end dates';

    public function handle(ProbationCompletionService $probationCompletionService): int
    {
        $result = $probationCompletionService->processDaily();

        $this->info(sprintf(
            'Probation processing finished. Completed: %d, upcoming reminders: %d.',
            $result['completed'],
            $result['reminded'],
        ));

        return self::SUCCESS;
    }
}
