<?php
declare(strict_types = 1);

namespace Tests\Innmind\Warden\Command;

use Innmind\Warden\Command\Lock;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Stream\Writable;
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\ExitCode,
};
use Innmind\Immutable\{
    Str,
    Map,
};
use PHPUnit\Framework\TestCase;

class LockTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Lock($this->createMock(Server::class))
        );
    }

    public function testExitWhenNotRoot()
    {
        $command = new Lock(
            $server = $this->createMock(Server::class)
        );
        $server
            ->expects($this->never())
            ->method('processes');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))->put('USER', 'foo')
            );
        $env
            ->expects($this->once())
            ->method('error')
            ->willReturn($error = $this->createMock(Writable::class));
        $error
            ->expects($this->once())
            ->method('write')
            ->with(Str::of("Only root can call this action\n"));
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(1);

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testModifyServerConfig()
    {
        $command = new Lock(
            $server = $this->createMock(Server::class)
        );
        $server
            ->expects($this->once())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "service 'ssh' 'stop'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))->put('USER', 'root')
            );
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(0);

        $this->assertNull($command(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testUsage()
    {
        $expected = <<<USAGE
lock

Stop the ssh service
USAGE;

        $this->assertSame($expected, (string) new Lock($this->createMock(Server::class)));
    }
}
