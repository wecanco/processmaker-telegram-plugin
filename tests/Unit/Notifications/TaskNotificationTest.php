<?php

namespace ProcessMaker\TelegramPlugin\Tests\Unit\Notifications;

use ProcessMaker\TelegramPlugin\Notifications\TaskNotification;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    /** @test */
    public function it_generates_correct_telegram_message()
    {
        $task = (object)[
            'id' => 123,
            'name' => 'Sample Task',
            'process' => (object)['name' => 'Onboarding'],
            'created_at' => now()->format('Y-m-d H:i:s')
        ];

        $notification = new TaskNotification($task, [
            'complete' => 'Mark Complete',
            'reject' => 'Reject Task'
        ]);

        $message = $notification->toTelegram(new \stdClass());

        $this->assertStringContainsString('Sample Task', $message['text']);
        $this->assertStringContainsString('Onboarding', $message['text']);
        $this->assertCount(2, $message['buttons']);
        $this->assertEquals('Mark Complete', $message['buttons'][0]['text']);
        $this->assertEquals('task:123:complete', $message['buttons'][0]['action']);
    }

    /** @test */
    public function it_should_not_send_when_no_telegram_chat_id()
    {
        $user = new class {
            public $telegram_chat_id = null;
        };

        $notification = new TaskNotification(
            (object)['id' => 1, 'name' => 'Test'],
            ['complete' => 'Complete']
        );

        $this->assertFalse($notification->shouldSend($user, 'telegram'));
    }
}