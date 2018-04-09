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
            ->method('fulfill')
            ->with($this->callback(static function($request): bool {
                return (string) $request->url() === 'http://github.com/baptouuuu.keys' &&
                    (string) $request->method() === 'GET' &&
                    (string) $request->protocolVersion() === '2.0';
            }))
            ->willReturn($response = $this->createMock(Response::class));
        $response
            ->expects($this->once())
            ->method('body')
            ->willReturn($body = $this->createMock(Readable::class));
        $body
            ->expects($this->once())
            ->method('read')
            ->willReturn(Str::of("foo\nbar\nbaz"));

        $keys = $fetch(new Name('baptouuuu'));

        $this->assertTrue(Set::of('string', 'foo', 'bar', 'baz')->equals($keys));
    }
}
