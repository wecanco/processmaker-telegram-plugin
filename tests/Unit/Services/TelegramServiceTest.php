<?php

namespace ProcessMaker\TelegramPlugin\Tests\Unit\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use ProcessMaker\TelegramPlugin\Services\TelegramService;
use Tests\TestCase;
use Mockery;

class TelegramServiceTest extends TestCase
{
    private $telegram;
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockClient = Mockery::mock(Client::class);
        $this->telegram = new TelegramService('test_token');
        $this->setProtectedProperty($this->telegram, 'client', $this->mockClient);
    }

    /** @test */
    public function it_sends_message_successfully()
    {
        $this->mockClient->shouldReceive('post')
            ->once()
            ->with('sendMessage', Mockery::any())
            ->andReturn(new Response(200, [], json_encode(['ok' => true])));

        $result = $this->telegram->sendMessage('123', 'Test message');
        
        $this->assertTrue($result['ok']);
    }

    /** @test */
    public function it_handles_send_message_failure()
    {
        $this->mockClient->shouldReceive('post')
            ->once()
            ->andThrow(new \Exception('API error'));

        $result = $this->telegram->sendMessage('123', 'Test message');
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_sets_webhook_properly()
    {
        $this->mockClient->shouldReceive('post')
            ->once()
            ->with('setWebhook', Mockery::any())
            ->andReturn(new Response(200, [], json_encode(['ok' => true])));

        $result = $this->telegram->setWebhook('https://example.com/webhook');
        
        $this->assertTrue($result['ok']);
    }

    private function setProtectedProperty($object, $property, $value)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}