<?php
declare(strict_types = 1);

namespace Innmind\Warden\Command;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Server\Control\{
    Server,
    Server\Command as ServerCommand,
};
use Innmind\Immutable\Str;

final class Lock implements Command
{
    private Server $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        if ($env->variables()->get('USER') !== 'root') {
            $env->error()->write(Str::of("Only root can call this action\n"));
            $env->exit(1);

            return;
        }

        $process = $this
            ->server
            ->processes()
            ->execute(
                ServerCommand::foreground('service')
                    ->withArgument('ssh')
                    ->withArgument('stop')
            );
        $process->wait();
        $env->exit($process->exitCode()->toInt());
    }

    public function toString(): string
    {
        return <<<USAGE
lock

Stop the ssh service
USAGE;
    }
}
