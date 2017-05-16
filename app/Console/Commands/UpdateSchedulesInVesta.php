<?php

namespace App\Console\Commands;

use App\Formatters\FormatsOpeninghours;
use App\Services\VestaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateSchedulesInVesta extends Command
{
    use FormatsOpeninghours;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openinghours:update-vesta';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the output of all VESTA related services to VESTA. The output will be the week schedule starting on monday of the week in which the timestamp of the execution of the command falls in.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $vestaServices = app('ServicesRepository')->where('source', 'vesta')->get();

        $startOfWeek = Carbon::today()->startOfWeek();

        foreach ($vestaServices as $vestaService) {
            try {
                // Don't work through events, we want to capture the output in the CLI
                $output = $this->formatWeek($vestaService->id, 'html', '', \Carbon\Carbon::today()->startOfWeek());

                (new VestaService())->updateOpeninghours($vestaService->identifier, $output);

                $this->info('Openingsuren voor dienst ' . $vestaService->label . ' met UID ' . $vestaService->identifier . ' werden geupdatet.');
            } catch (\Exception $ex) {
                $this->error('Er ging iets mis met dienst ' . $vestaService->label . ' met UID ' . $vestaService->identifier . '. Foutbericht: ' . $ex->getMessage());
            }
        }
    }
}