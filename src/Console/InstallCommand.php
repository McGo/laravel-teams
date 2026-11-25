<?php

namespace MirkoHaaser\Teams\Console;

use Illuminate\Console\Command;
use MirkoHaaser\Teams\Clients\GraphClient;
use MirkoHaaser\Teams\Exceptions\TeamsRequestFailed;
use MirkoHaaser\Teams\Models\TeamsIdentity;

class InstallCommand extends Command
{
    protected $signature = 'teams:install {user? : User key} {--all : Every linked identity without installation}';

    protected $description = 'Install the Teams app for users via Graph, including the RSC consent';

    public function handle(GraphClient $graph): int
    {
        $catalogAppId = (string) config('teams.catalog_app_id');

        if (! $graph->enabled() || $catalogAppId === '') {
            $this->components->error('Teams is disabled or TEAMS_CATALOG_APP_ID is missing.');

            return self::FAILURE;
        }

        $query = TeamsIdentity::query();
        if ($this->option('all')) {
            $query->whereNull('installed_at');
        } elseif ($this->argument('user') !== null) {
            $query->where('user_id', $this->argument('user'));
        } else {
            $this->components->error('Pass a user key or --all.');

            return self::FAILURE;
        }

        $new = $existing = $failed = 0;

        foreach ($query->cursor() as $identity) {
            try {
                $graph->installAppForUser($identity->aad_object_id, $catalogAppId, $this->rsc()) ? $new++ : $existing++;
                $identity->forceFill(['installed_at' => $identity->installed_at ?? now()])->save();
            } catch (TeamsRequestFailed $e) {
                $failed++;
                $this->components->warn("{$identity->upn}: {$e->getMessage()}");
            }
        }

        $this->components->info("Installed: {$new}, already installed: {$existing}, failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    private function rsc(): array
    {
        return config('teams.manifest.activity_types') ? ['TeamsActivity.Send.User'] : [];
    }
}
