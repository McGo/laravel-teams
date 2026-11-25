<?php

namespace MirkoHaaser\Teams\Tests\Feature;

use MirkoHaaser\Teams\Bot\Contracts\HandlesCommand;
use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Exceptions\TeamsConfigurationError;
use MirkoHaaser\Teams\Facades\Teams;
use MirkoHaaser\Teams\Manifest\ManifestBuilder;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\Tests\TestCase;
use ZipArchive;

class ManifestTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/teams-manifest-'.uniqid();
        mkdir($this->dir);
        $this->png($this->dir.'/color.png', 192);
        $this->png($this->dir.'/outline.png', 32);

        config([
            'teams.manifest.icons' => ['color' => $this->dir.'/color.png', 'outline' => $this->dir.'/outline.png'],
            'teams.manifest.output' => $this->dir,
            'teams.manifest.name' => ['short' => 'Contoso Desk', 'full' => 'Contoso Desk'],
            'teams.manifest.description' => ['short' => 'Desk in Teams', 'full' => 'The desk of Contoso in Teams.'],
            'teams.manifest.activity_types' => ['reportReady' => ['description' => 'Report ready', 'template' => '{actor}: report {name} is ready']],
        ]);
    }

    public function test_builds_tabs_bot_sso_and_activity_types(): void
    {
        $m = (new ManifestBuilder(config('teams')))->manifest();

        $this->assertSame('1.29', $m['manifestVersion']);
        $this->assertSame(['home', 'items'], array_column($m['staticTabs'], 'entityId'));
        $this->assertSame('https://app.contoso.com/teams/items', $m['staticTabs'][1]['contentUrl']);
        $this->assertSame(self::BOT, $m['bots'][0]['botId']);
        $this->assertSame('api://app.contoso.com/botid-'.self::CLIENT, $m['webApplicationInfo']['resource']);
        $this->assertSame('reportReady', $m['activities']['activityTypes'][0]['type']);
        $this->assertSame('TeamsActivity.Send.User', $m['authorization']['permissions']['resourceSpecific'][0]['name']);
    }

    public function test_without_bot_the_resource_has_the_plain_form(): void
    {
        config(['teams.bot.app_id' => '']);
        $m = (new ManifestBuilder(config('teams')))->manifest();

        $this->assertArrayNotHasKey('bots', $m);
        $this->assertSame('api://app.contoso.com/'.self::CLIENT, $m['webApplicationInfo']['resource']);
    }

    public function test_reports_problems_before_writing(): void
    {
        config([
            'teams.manifest.activity_types.tooLong' => ['description' => 'x', 'template' => str_repeat('x', 129)],
            'teams.manifest.icons.outline' => $this->dir.'/color.png',
        ]);
        $builder = new ManifestBuilder(config('teams'));

        $this->assertContains('activity type tooLong: templateText is longer than 128 characters', $builder->problems());
        $this->assertContains('icon outline must be 32x32, is 192x192', $builder->problems());
        $this->expectException(TeamsConfigurationError::class);
        $builder->build();
    }

    public function test_command_writes_a_zip_with_registered_commands(): void
    {
        Teams::command('status', StatusCommand::class);

        $this->artisan('teams:manifest')->assertSuccessful();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->dir.'/contoso-desk-1.0.0.zip'));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertSame('status', $manifest['bots'][0]['commandLists'][0]['commands'][0]['title']);
        $this->assertNotFalse($zip->getFromName('outline.png'));
    }

    private function png(string $path, int $size): void
    {
        $img = imagecreatetruecolor($size, $size);
        imagepng($img, $path);
    }
}

class StatusCommand implements HandlesCommand
{
    public function description(): string
    {
        return 'Shows the status';
    }

    public function handle(string $arguments, IncomingActivity $activity, TeamsIdentity $identity): ?Reply
    {
        return null;
    }
}
