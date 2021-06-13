<?php

declare(strict_types=1);

namespace DevineOnline\SmsBroadcast\Tests\Unit\Factory;

use DevineOnline\SmsBroadcast\Api\Client;
use DevineOnline\SmsBroadcast\Factory\ClientFactory;
use PHPUnit\Framework\TestCase;

class ClientFactoryTest extends TestCase
{
    public function testCreate()
    {
        $client = ClientFactory::create('a', 'b');

        $this->assertInstanceOf(Client::class, $client);
    }
}
