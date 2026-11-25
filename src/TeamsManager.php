<?php

namespace MirkoHaaser\Teams;

use Illuminate\Contracts\Container\Container;
use MirkoHaaser\Teams\Bot\Contracts\HandlesCardAction;
use MirkoHaaser\Teams\Bot\Contracts\HandlesCommand;
use MirkoHaaser\Teams\Bot\Contracts\HandlesMessage;
use MirkoHaaser\Teams\Clients\BotClient;
use MirkoHaaser\Teams\Clients\GraphClient;
use MirkoHaaser\Teams\Exceptions\TeamsConfigurationError;
use MirkoHaaser\Teams\Testing\FakeBotClient;
use MirkoHaaser\Teams\Testing\FakeGraphClient;
use MirkoHaaser\Teams\Testing\TeamsFake;

/**
 * Registry for what the application does with incoming messages and card
 * actions. Handlers are class names, resolved from the container when used.
 */
class TeamsManager
{
    /** @var class-string<HandlesMessage>|null */
    private ?string $messageHandler = null;

    /** @var array<string, class-string<HandlesCommand>> */
    private array $commands = [];

    /** @var array<string, class-string<HandlesCardAction>> */
    private array $actions = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<HandlesMessage> $handler */
    public function onMessage(string $handler): static
    {
        $this->messageHandler = $handler;

        return $this;
    }

    /** @param class-string<HandlesCommand> $handler */
    public function command(string $word, string $handler): static
    {
        $word = mb_strtolower(trim($word));

        if ($word === '' || str_contains($word, ' ')) {
            throw new TeamsConfigurationError("Teams command [{$word}] must be a single word.");
        }

        if (isset($this->commands[$word])) {
            throw new TeamsConfigurationError("Teams command [{$word}] is registered twice.");
        }

        $this->commands[$word] = $handler;

        return $this;
    }

    /** @param class-string<HandlesCardAction> $handler */
    public function action(string $verb, string $handler): static
    {
        if (isset($this->actions[$verb])) {
            throw new TeamsConfigurationError("Teams card action [{$verb}] is registered twice.");
        }

        $this->actions[$verb] = $handler;

        return $this;
    }

    public function messageHandler(): ?HandlesMessage
    {
        return $this->messageHandler ? $this->container->make($this->messageHandler) : null;
    }

    public function commandHandler(string $word): ?HandlesCommand
    {
        $class = $this->commands[mb_strtolower($word)] ?? null;

        return $class ? $this->container->make($class) : null;
    }

    public function actionHandler(string $verb): ?HandlesCardAction
    {
        $class = $this->actions[$verb] ?? null;

        return $class ? $this->container->make($class) : null;
    }

    /** @return array<string, class-string<HandlesCommand>> */
    public function commands(): array
    {
        return $this->commands;
    }

    /** @return array<string, class-string<HandlesCardAction>> */
    public function actions(): array
    {
        return $this->actions;
    }

    public function bot(): BotClient
    {
        return $this->container->make(BotClient::class);
    }

    public function graph(): GraphClient
    {
        return $this->container->make(GraphClient::class);
    }

    /** Replace the clients with recording fakes. Nothing leaves the test. */
    public function fake(): TeamsFake
    {
        $bot = new FakeBotClient;
        $graph = new FakeGraphClient;

        $this->container->instance(BotClient::class, $bot);
        $this->container->instance(GraphClient::class, $graph);

        return new TeamsFake($bot, $graph);
    }
}
