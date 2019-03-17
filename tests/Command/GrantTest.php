<?php
declare(strict_types = 1);

namespace Tests\Innmind\Warden\Command;

use Innmind\Warden\{
    Command\Grant,
    SshKeyProvider,
    Name,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\ExitCode,
};
use Innmind\OperatingSystem\Filesystem;
use Innmind\Filesystem\{
    Adapter,
    Directory\Directory,
    File\File,
    Stream\StringStream,
};
use Innmind\Url\Path;
use Innmind\Immutable\{
    Set,
    Map,
};
use PHPUnit\Framework\TestCase;

class GrantTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Grant(
                $this->createMock(SshKeyProvider::class),
                $this->createMock(Server::class),
                $this->createMock(Filesystem::class)
            )
        );
    }

    public function testInvokation()
    {
        $command = new Grant(
            $provider = $this->createMock(SshKeyProvider::class),
            $server = $this->createMock(Server::class),
            $this->createMock(Filesystem::class)
        );
        $provider
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Name('baptouuuu'))
            ->willReturn(Set::of('string', 'foo', 'bar'));
        $server
            ->expects($this->exactly(2))
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'foo' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'bar' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
            ->expects($this->any())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))
                    ->put('USER', 'root')
                    ->put('HOME', '/home/baptouuuu')
            );
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(0);

        $this->assertNull($command(
            $env,
            new Arguments(
                (new Map('string', 'mixed'))->put('name', 'baptouuuu')
            ),
            new Options
        ));
    }

    public function testExitCodeOnError()
    {
        $command = new Grant(
            $provider = $this->createMock(SshKeyProvider::class),
            $server = $this->createMock(Server::class),
            $this->createMock(Filesystem::class)
        );
        $provider
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Name('baptouuuu'))
            ->willReturn(Set::of('string', 'foo', 'bar'));
        $server
            ->expects($this->once())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'foo' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->once())
            ->method('exitCode')
            ->willReturn(new ExitCode(1));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))
                    ->put('USER', 'root')
                    ->put('HOME', '/home/baptouuuu')
            );
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(1);

        $this->assertNull($command(
            $env,
            new Arguments(
                (new Map('string', 'mixed'))->put('name', 'baptouuuu')
            ),
            new Options
        ));
    }

    public function testDoesNothingWhenNoKeys()
    {
        $command = new Grant(
            $provider = $this->createMock(SshKeyProvider::class),
            $server = $this->createMock(Server::class),
            $this->createMock(Filesystem::class)
        );
        $provider
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Name('baptouuuu'))
            ->willReturn(Set::of('string'));
        $server
            ->expects($this->never())
            ->method('processes');
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->any())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))
                    ->put('USER', 'root')
                    ->put('HOME', '/home/baptouuuu')
            );
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(0);

        $this->assertNull($command(
            $env,
            new Arguments(
                (new Map('string', 'mixed'))->put('name', 'baptouuuu')
            ),
            new Options
        ));
    }

    public function testUsage()
    {
        $expected = <<<USAGE
grant name

Lookup the ssh keys of {name} and add them as authorized ones

It will fetch ssh keys from github and happen them in ~/.ssh/authorized_keys
USAGE;

        $this->assertSame($expected, (string) new Grant(
            $this->createMock(SshKeyProvider::class),
            $this->createMock(Server::class),
            $this->createMock(Filesystem::class)
        ));
    }

    public function testDoNotAppendKeysAlreadyAuthorized()
    {
        $command = new Grant(
            $provider = $this->createMock(SshKeyProvider::class),
            $server = $this->createMock(Server::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/home/baptouuuu'))
            ->willReturn($home = new Adapter\MemoryAdapter());
        $home->add(
            (new Directory('.ssh'))->add(new File(
                'authorized_keys',
                new StringStream('bar')
            ))
        );
        $provider
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Name('baptouuuu'))
            ->willReturn(Set::of('string', 'foo', 'bar', 'baz'));
        $server
            ->expects($this->exactly(2))
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'foo' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'baz' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
            ->expects($this->any())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))
                    ->put('USER', 'root')
                    ->put('HOME', '/home/baptouuuu')
            );
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(0);

        $this->assertNull($command(
            $env,
            new Arguments(
                (new Map('string', 'mixed'))->put('name', 'baptouuuu')
            ),
            new Options
        ));
    }

    public function testDoNotFilterKeysWhenNoAuthorizedKeysFile()
    {
        $command = new Grant(
            $provider = $this->createMock(SshKeyProvider::class),
            $server = $this->createMock(Server::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/home/baptouuuu'))
            ->willReturn($home = new Adapter\MemoryAdapter());
        $home->add(new Directory('.ssh'));
        $provider
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Name('baptouuuu'))
            ->willReturn(Set::of('string', 'foo', 'bar', 'baz'));
        $server
            ->expects($this->exactly(3))
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'foo' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'bar' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'baz' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
            ->expects($this->any())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))
                    ->put('USER', 'root')
                    ->put('HOME', '/home/baptouuuu')
            );
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(0);

        $this->assertNull($command(
            $env,
            new Arguments(
                (new Map('string', 'mixed'))->put('name', 'baptouuuu')
            ),
            new Options
        ));
    }

    public function testDoNotFilterKeysWhenNoSshFolder()
    {
        $command = new Grant(
            $provider = $this->createMock(SshKeyProvider::class),
            $server = $this->createMock(Server::class),
            $filesystem = $this->createMock(Filesystem::class)
        );
        $filesystem
            ->expects($this->once())
            ->method('mount')
            ->with(new Path('/home/baptouuuu'))
            ->willReturn($home = new Adapter\MemoryAdapter());
        $provider
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Name('baptouuuu'))
            ->willReturn(Set::of('string', 'foo', 'bar', 'baz'));
        $server
            ->expects($this->exactly(3))
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->at(0))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'foo' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
        $processes
            ->expects($this->at(1))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'bar' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
        $processes
            ->expects($this->at(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return (string) $command === "echo 'baz' >> '.ssh/authorized_keys'" &&
                    $command->workingDirectory() === '/home/baptouuuu';
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
            ->expects($this->any())
            ->method('variables')
            ->willReturn(
                (new Map('string', 'string'))
                    ->put('USER', 'root')
                    ->put('HOME', '/home/baptouuuu')
            );
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(0);

        $this->assertNull($command(
            $env,
            new Arguments(
                (new Map('string', 'mixed'))->put('name', 'baptouuuu')
            ),
            new Options
        ));
    }
}
