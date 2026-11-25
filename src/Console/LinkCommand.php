<?php

namespace MirkoHaaser\Teams\Console;

use Illuminate\Console\Command;
use MirkoHaaser\Teams\Clients\GraphClient;
use MirkoHaaser\Teams\Models\TeamsIdentity;

class LinkCommand extends Command
{
    protected $signature = 'teams:link {user : User key} {upn : Sign-in name in Entra} {--tenant= : Tenant id, defaults to TEAMS_TENANT_ID}';

    protected $description = 'Link an application user to an Entra user by hand';

    public function handle(GraphClient $graph): int
    {
        $upn = (string) $this->argument('upn');
        $entra = $graph->get('/users/'.rawurlencode($upn).'?$select=id,userPrincipalName');

        if (empty($entra['id'])) {
            $this->components->error("Entra user {$upn} not found (or Teams is disabled).");

            return self::FAILURE;
        }

        $identity = TeamsIdentity::updateOrCreate(
            ['tenant_id' => (string) ($this->option('tenant') ?: config('teams.tenant_id')), 'aad_object_id' => (string) $entra['id']],
            ['user_id' => $this->argument('user'), 'upn' => (string) ($entra['userPrincipalName'] ?? $upn)],
        );

        $this->components->info("Linked {$identity->upn} ({$identity->aad_object_id}) to user {$identity->user_id}.");

        return self::SUCCESS;
    }
}
