<?php

namespace ProcessMaker\TelegramPlugin\Tests\Feature;

use ProcessMaker\Models\User;
use ProcessMaker\TelegramPlugin\Notifications\TaskNotification;
use ProcessMaker\TelegramPlugin\Jobs\SendTelegramNotification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    /** @test */
    public function it_dispatches_telegram_notification_job()
    {
        Queue::fake();

        $user = User::factory()->create([
            'telegram_chat_id' => '12345',
            'telegram_verified_at' => now()
        ]);

        $notification = new TaskNotification(
            (object)['id' => 1, 'name' => 'Test Task', 'process' => (object)['name' => 'Test Process']],
            ['complete' => 'Complete Task']
        );

        $user->notify($notification);

        Queue::assertPushed(SendTelegramNotification::class, function ($job) use ($user) {
            return $job->userId === $user->id;
        });
    }

    /** @test */
    public function it_does_not_dispatch_for_users_without_telegram()
    {
        Queue::fake();

        $user = User::factory()->create([
            'telegram_chat_id' => null
        ]);

        $notification = new TaskNotification(
            (object)['id' => 1, 'name' => 'Test Task'],
            ['complete' => 'Complete Task']
        );

        $user->notify($notification);

        Queue::assertNotPushed(SendTelegramNotification::class);
    }
}