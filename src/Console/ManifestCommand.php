<?php

namespace MirkoHaaser\Teams\Console;

use Illuminate\Console\Command;
use MirkoHaaser\Teams\Manifest\ManifestBuilder;
use MirkoHaaser\Teams\TeamsManager;

class ManifestCommand extends Command
{
    protected $signature = 'teams:manifest {--print : Print manifest.json instead of writing the zip}';

    protected $description = 'Build the Teams app package from config/teams.php';

    public function handle(TeamsManager $teams): int
    {
        $config = (array) config('teams');

        // Registered commands appear in the bot's command menu unless the
        // config lists them explicitly.
        if (empty($config['manifest']['bot_commands'])) {
            foreach (array_keys($teams->commands()) as $word) {
                $config['manifest']['bot_commands'][$word] = mb_substr((string) $teams->commandHandler($word)?->description(), 0, 128);
            }
        }

        $builder = new ManifestBuilder($config);

        if ($problems = $builder->problems()) {
            $this->components->error('The manifest cannot be built yet:');
            $this->components->bulletList($problems);

            return self::FAILURE;
        }

        if ($this->option('print')) {
            $this->line((string) json_encode($builder->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $path = $builder->build();
        $this->components->info("Written: {$path}");
        $this->line('  Upload it in Teams (Apps > Manage your apps > Upload an app) or in the Teams admin center.');

        return self::SUCCESS;
    }
}
