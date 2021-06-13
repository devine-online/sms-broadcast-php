<?php

declare(strict_types=1);

namespace DevineOnline\SmsBroadcast\Tests\Unit\Api;

use DevineOnline\SmsBroadcast\Api\Client;
use DevineOnline\SmsBroadcast\Api\SendResponse;
use DevineOnline\SmsBroadcast\Exception\InvalidMessageException;
use DevineOnline\SmsBroadcast\Exception\InvalidNumberException;
use DevineOnline\SmsBroadcast\Exception\InvalidSenderException;
use DevineOnline\SmsBroadcast\Exception\SendException;
use DevineOnline\SmsBroadcast\Exception\SmsBroadcastException;
use BlastCloud\Guzzler\UsesGuzzler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    use UsesGuzzler;

    /** @var Client */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $http = $this->guzzler->getClient();
        $this->client = new Client($http, 'user', 'password', '0412345678');
    }

    /**
     * @dataProvider dataSendValidationInvalid
     */
    public function testSendValidationInvalid(array $args, string $expectedException, ?string $expectedMessage = null)
    {
        $this->expectException($expectedException);

        if ($expectedMessage) {
            $this->expectExceptionMessage($expectedMessage);
        }

        $this->client->send(...$args);
    }

    public function dataSendValidationInvalid()
    {
        return [
            'empty to' => [
                'args' => [
                    '',
                    'test message',
                ],
                'exception' => InvalidNumberException::class,
                'message' => 'Message to number `` is invalid',
            ],
            'invalid to number' => [
                'args' => [
                    '041234567',
                    'test message',
                ],
                'exception' => InvalidNumberException::class,
                'message' => 'Message to number `041234567` is invalid',
            ],
            'empty message' => [
                'args' => [
                    '0412345678',
                    '',
                ],
                'exception' => InvalidMessageException::class,
                'message' => 'Message is empty',
            ],
            'message too long' => [
                'args' => [
                    '0412345678',
                    str_repeat('test ', 200),
                ],
                'exception' => InvalidMessageException::class,
                'message' => 'Message length `1000` of chars is over maximum length of `765` chars',
            ],
            'invalid sender' => [
                'args' => [
                    '0412345678',
                    'test message',
                    '',
                ],
                'exception' => InvalidSenderException::class,
            ],
        ];
    }

    public function testSendSuccess()
    {
        $this->guzzler->expects($this->once())
            ->get(Client::API_ENDPOINT)
            ->withQuery([
                'username' => 'user',
                'password' => 'password',
                'to' => '0412345678',
                'from' => '0412345678',
                'message' => 'test message',
                'ref' => 'ref234',
                'maxsplit' => 5,
            ], true)
            ->willRespond(new Response(200, [], 'OK: 61412345678:ref234 '));

        $this->client->send('0412345678', 'test message', null, 'ref234');
    }

    public function testSendInvalidUserPass()
    {
        $this->guzzler->expects($this->once())
            ->get(Client::API_ENDPOINT)
            ->willRespond(new Response(200, [], 'ERROR: Username or password is incorrect '));

        $this->expectException(SendException::class);
        $this->expectExceptionMessage('Failed to send message to `` with error `Username or password is incorrect`');

        $this->client->send('0412345678', 'test message', null, 'ref234');
    }

    public function testSendInvalidToNumber()
    {
        $this->guzzler->expects($this->once())
            ->get(Client::API_ENDPOINT)
            ->willRespond(new Response(200, [], 'BAD:0412345678:Invalid Number'));

        $this->expectException(SendException::class);
        $this->expectExceptionMessage('Failed to send message to `0412345678` with error `Invalid Number`');

        $this->client->send('0412345678', 'test message', null, 'ref234');
    }

    public function testSendMultiple()
    {
        $this->guzzler->expects($this->once())
            ->get(Client::API_ENDPOINT)
            ->withQuery([
                'username' => 'user',
                'password' => 'password',
                'to' => '0412345678,0413345678,0414345678',
                'from' => '0412345678',
                'message' => 'test message',
                'maxsplit' => 5,
            ], true)
            ->willRespond(new Response(200, [], "OK: 0412345678:abcd1\nOK: 0413345678:abcd2\nOK: 0414345678:abcd3\n"));

        $results = $this->client->sendMany(
            [
                '0412345678',
                '0413345678',
                '0414345678',
            ],
            'test message'
        );

        $this->assertContainsOnlyInstancesOf(SendResponse::class, $results);
        $this->assertCount(3, $results);
    }

    public function testGetBalanceSuccess()
    {
        $this->guzzler->expects($this->once())
            ->get(Client::API_ENDPOINT)
            ->withQuery([
                'action' => 'balance',
                'username' => 'user',
                'password' => 'password',
            ], true)
            ->willRespond(new Response(200, [], 'OK: 3321'));

        $bal = $this->client->getBalance();

        $this->assertSame(3321, $bal);
    }

    public function testGetBalanceFailure()
    {
        $this->expectException(SmsBroadcastException::class);

        $this->guzzler->expects($this->once())
            ->get(Client::API_ENDPOINT)
            ->withQuery([
                'action' => 'balance',
                'username' => 'user',
                'password' => 'password',
            ], true)
            ->willRespond(new Response(200, [], 'ERROR: Unknown Error'));

        $this->client->getBalance();
    }
}
