<?php

namespace MirkoHaaser\Teams\Testing;

use Illuminate\Http\Client\Response;
use MirkoHaaser\Teams\Clients\GraphClient;

/** Records what would go to Microsoft Graph. */
class FakeGraphClient extends GraphClient
{
    /** @var list<array{user: string, payload: array<string, mixed>}> */
    public array $notifications = [];

    /** @var list<string> */
    public array $installed = [];

    public function __construct()
    {
        parent::__construct(true, 'fake-tenant', 'fake-client', 'fake');
    }

    public function accessToken(): string
    {
        return 'fake-token';
    }

    public function sendActivityNotification(string $userId, array $payload): void
    {
        $this->notifications[] = ['user' => $userId, 'payload' => $payload];
    }

    public function installAppForUser(string $userId, string $catalogAppId, array $rscPermissions = []): bool
    {
        $this->installed[] = $userId;

        return true;
    }

    public function get(string $path): ?array
    {
        return null;
    }

    public function post(string $path, array $payload): ?Response
    {
        return null;
    }
}
