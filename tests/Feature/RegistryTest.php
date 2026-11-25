<?php

namespace MirkoHaaser\Teams\Tests\Feature;

use MirkoHaaser\Teams\Exceptions\TeamsConfigurationError;
use MirkoHaaser\Teams\Facades\Teams;
use MirkoHaaser\Teams\Tests\TestCase;

class RegistryTest extends TestCase
{
    public function test_duplicate_commands_fail_at_registration(): void
    {
        Teams::command('status', StatusCommand::class);

        $this->expectException(TeamsConfigurationError::class);
        Teams::command('Status', StatusCommand::class);
    }

    public function test_commands_are_single_words(): void
    {
        $this->expectException(TeamsConfigurationError::class);
        Teams::command('two words', StatusCommand::class);
    }
}
