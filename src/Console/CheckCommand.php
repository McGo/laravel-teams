<?php

namespace MirkoHaaser\Teams\Console;

use Illuminate\Console\Command;
use MirkoHaaser\Teams\Clients\BotClient;
use MirkoHaaser\Teams\Clients\GraphClient;
use MirkoHaaser\Teams\Manifest\ManifestBuilder;
use Throwable;

class CheckCommand extends Command
{
    protected $signature = 'teams:check';

    protected $description = 'Check the Teams configuration step by step';

    private bool $failed = false;

    public function handle(GraphClient $graph, BotClient $bot): int
    {
        $this->step('TEAMS_ENABLED is true', (bool) config('teams.enabled'), 'Nothing is sent while it is false.');

        foreach (['tenant_id', 'client_id', 'client_secret', 'app_id'] as $key) {
            $this->step("{$key} set", (string) config("teams.{$key}") !== '', 'See docs/guide/02-entra-setup.md');
        }

        $this->step('allowed_tenants contains the registration tenant', in_array(config('teams.tenant_id'), (array) config('teams.allowed_tenants'), true), 'Tab sign-in from the own tenant will be refused.');
        $this->step('catalog_app_id set', (string) config('teams.catalog_app_id') !== '', 'Needed to install the app via Graph. Take it from the app page URL in the Teams admin center.');

        $this->try('Graph app token', fn () => $graph->accessToken(), 'Check client id, secret and tenant. AADSTS7000215 means a wrong secret value (the secret ID was used).');

        if ($bot->enabled()) {
            $this->try('Bot Framework app token', fn () => $bot->accessToken(), 'Single-tenant bots get their token from their own tenant: TEAMS_BOT_LOGIN_URL must end with the tenant id.');
        } else {
            $this->components->twoColumnDetail('Bot', '<fg=gray>not configured, endpoint answers 404</>');
        }

        $problems = (new ManifestBuilder((array) config('teams')))->problems();
        $this->step('Manifest can be built', $problems === [], implode('; ', $problems));

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function step(string $label, bool $ok, string $hint): void
    {
        $this->components->twoColumnDetail($label, $ok ? '<fg=green>ok</>' : '<fg=red>missing</>');

        if (! $ok) {
            $this->failed = true;
            $this->line("  <fg=gray>{$hint}</>");
        }
    }

    private function try(string $label, callable $fn, string $hint): void
    {
        try {
            $fn();
            $this->step($label, true, '');
        } catch (Throwable $e) {
            $this->step($label, false, $e->getMessage().' '.$hint);
        }
    }
}
