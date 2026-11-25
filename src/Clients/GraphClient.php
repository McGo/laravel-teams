<?php

namespace MirkoHaaser\Teams\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MirkoHaaser\Teams\Exceptions\TeamsRequestFailed;
use MirkoHaaser\Teams\Exceptions\TeamsThrottled;

/**
 * Thin app-only wrapper around Microsoft Graph. Knows tokens and transport,
 * not what is sent.
 */
class GraphClient
{
    use AppTokens;

    public const RSC_INSTALL_PERMISSION = 'TeamsAppInstallation.ReadWriteAndConsentSelfForUser.All';

    public function __construct(
        protected readonly bool $enabled,
        protected readonly string $tenantId,
        protected readonly string $clientId,
        protected readonly ?string $clientSecret,
        protected readonly string $graphBaseUrl = 'https://graph.microsoft.com/v1.0',
        protected readonly string $loginBaseUrl = 'https://login.microsoftonline.com',
        protected readonly int $timeout = 15,
        protected readonly int $tokenLeeway = 120,
    ) {}

    /** Configured and switched on. If false, no request leaves the app. */
    public function enabled(): bool
    {
        return $this->enabled && $this->tenantId !== '' && $this->clientId !== '' && ! empty($this->clientSecret);
    }

    public function accessToken(): string
    {
        return $this->fetchAppToken(
            "{$this->loginBaseUrl}/{$this->tenantId}/oauth2/v2.0/token",
            $this->clientId,
            (string) $this->clientSecret,
            'https://graph.microsoft.com/.default',
            $this->timeout,
            $this->tokenLeeway,
        );
    }

    /**
     * @param  string  $userId  Entra object id or UPN
     * @param  array<string, mixed>  $payload
     */
    public function sendActivityNotification(string $userId, array $payload): void
    {
        $this->post("/users/{$userId}/teamwork/sendActivityNotification", $payload);
    }

    /**
     * Install the Teams app for a user and grant the RSC permissions in the
     * same call. Needs TeamsAppInstallation.ReadWriteAndConsentSelfForUser.All
     * as soon as the manifest declares RSC permissions.
     *
     * @param  list<string>  $rscPermissions
     * @return bool true on a new installation, false if it was installed
     */
    public function installAppForUser(string $userId, string $catalogAppId, array $rscPermissions = []): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $payload = ['teamsApp@odata.bind' => "{$this->graphBaseUrl}/appCatalogs/teamsApps/{$catalogAppId}"];

        if ($rscPermissions !== []) {
            $payload['consentedPermissionSet'] = [
                'resourceSpecificPermissions' => array_map(
                    fn (string $name) => ['permissionValue' => $name, 'permissionType' => 'Application'],
                    $rscPermissions,
                ),
            ];
        }

        $response = $this->request()->post("{$this->graphBaseUrl}/users/{$userId}/teamwork/installedApps", $payload);

        // An existing installation is a conflict, which is the normal case
        // on the second run.
        if ($response->status() === 409) {
            return false;
        }

        if ($response->status() === 403) {
            throw new TeamsRequestFailed(
                'Teams: app installation refused. The registration needs the application permission '
                .self::RSC_INSTALL_PERMISSION.' with admin consent.',
                403,
                (string) $response->body(),
            );
        }

        $this->check($response, 'installedApps');

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $path): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $response = $this->request()->get($this->graphBaseUrl.$path);
        $this->check($response, $path);

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $path, array $payload): ?Response
    {
        if (! $this->enabled()) {
            return null;
        }

        $response = $this->request()->post($this->graphBaseUrl.$path, $payload);
        $this->check($response, $path);

        return $response;
    }

    protected function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())->timeout($this->timeout)->acceptJson();
    }

    protected function check(Response $response, string $path): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            throw new TeamsThrottled("Teams: throttled on {$path}", (int) ($response->header('Retry-After') ?: 30), (string) $response->body());
        }

        throw new TeamsRequestFailed("Teams: Graph call failed on {$path}", $response->status(), (string) $response->body());
    }
}
