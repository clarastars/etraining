<?php

namespace App\Console;

use App\Console\Commands\AdhocCommand;
use App\Console\Commands\CompanyTraineeSnapshot;
use App\Console\Commands\InvoiceToPaidCommand;
use App\Console\Commands\TraineeAlertUpcomingSessionCommand;
use App\Console\Commands\DatabaseIndexTextCommand;
use App\Console\Commands\InvitePeopleCommand;
use App\Console\Commands\SeedPermissionsCommand;
use App\Console\Commands\SetupDevCommand;
use App\Console\Commands\FixTraineeGroupsCommand;
use App\Console\Commands\ZoomCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        DatabaseIndexTextCommand::class,
        InvitePeopleCommand::class,
        SetupDevCommand::class,
        SeedPermissionsCommand::class,
        FixTraineeGroupsCommand::class,
        ZoomCommand::class,
        AdhocCommand::class,
        TraineeAlertUpcomingSessionCommand::class,
        CompanyTraineeSnapshot::class,
        InvoiceToPaidCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        if(app()->environment('production')) {
            $schedule->command('etrianing:coursereminder')->daily()->at('05:00')->onOneServer();
            $schedule->command('etraining:company-trainee-snapshot')->daily()->at('23:00')->onOneServer();
            $schedule->command('masdr:gosi-usage-report')->weekly()->sundays()->at('08:00')->onOneServer();
            $schedule->command('invoice-details:sync-documentation-dates')->hourly()->onOneServer()->withoutOverlapping();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
