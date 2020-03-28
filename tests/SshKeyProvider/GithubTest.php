<?php
declare(strict_types = 1);

namespace Tests\Innmind\Warden\SshKeyProvider;

use Innmind\Warden\{
    SshKeyProvider\Github,
    SshKeyProvider,
    Name,
};
use Innmind\HttpTransport\Transport;
use Innmind\Http\Message\Response;
use Innmind\Stream\Readable;
use Innmind\Immutable\{
    Str,
    Set,
};
use PHPUnit\Framework\TestCase;

class GithubTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            SshKeyProvider::class,
            new Github(
                $this->createMock(Transport::class)
            )
        );
    }

    public function testInvokation()
    {
        $fetch = new Github(
            $http = $this->createMock(Transport::class)
        );
        $http
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function($request): bool {
                return $request->url()->toString() === 'http://github.com/baptouuuu.keys' &&
                    $request->method()->toString() === 'GET' &&
                    $request->protocolVersion()->toString() === '2.0';
            }))
            ->willReturn($response = $this->createMock(Response::class));
        $response
            ->expects($this->once())
            ->method('body')
            ->willReturn($body = $this->createMock(Readable::class));
        $body
            ->expects($this->once())
            ->method('read')
            ->willReturn(Str::of("foo\nbar\nbaz\n"));

        $keys = $fetch(new Name('baptouuuu'));

        $this->assertTrue(Set::of('string', 'foo', 'bar', 'baz')->equals($keys));
    }
}
