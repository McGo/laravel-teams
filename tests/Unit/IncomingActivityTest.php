<?php

namespace MirkoHaaser\Teams\Tests\Unit;

use MirkoHaaser\Teams\Bot\IncomingActivity;
use PHPUnit\Framework\TestCase;

class IncomingActivityTest extends TestCase
{
    public function test_text_without_mentions_and_markup(): void
    {
        $a = new IncomingActivity(['type' => 'message', 'text' => '<at>My Bot</at>&nbsp; status  <b>now</b>']);

        $this->assertSame('status now', $a->text());
    }

    public function test_card_data_from_submit_and_execute(): void
    {
        $submit = new IncomingActivity(['type' => 'message', 'value' => ['verb' => 'x.y', 'source' => 'card', 'id' => 1]]);
        $execute = new IncomingActivity(['type' => 'invoke', 'name' => 'adaptiveCard/action', 'value' => ['action' => ['verb' => 'x.y', 'data' => ['id' => 2]]]]);

        $this->assertTrue($submit->isCardSubmit());
        $this->assertSame(['x.y', ['id' => 1]], [$submit->cardVerb(), $submit->cardData()]);
        $this->assertTrue($execute->isCardExecute());
        $this->assertSame(['x.y', ['id' => 2]], [$execute->cardVerb(), $execute->cardData()]);
    }

    public function test_installation_from_conversation_update(): void
    {
        $a = new IncomingActivity([
            'type' => 'conversationUpdate',
            'recipient' => ['id' => '28:bot'],
            'membersAdded' => [['id' => '29:someone'], ['id' => '28:bot']],
        ]);

        $this->assertSame('add', $a->installation());
        $this->assertNull((new IncomingActivity(['type' => 'conversationUpdate', 'recipient' => ['id' => '28:bot'], 'membersAdded' => [['id' => '29:x']]]))->installation());
    }
}
