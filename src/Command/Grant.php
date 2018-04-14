<?php
declare(strict_types = 1);

namespace Innmind\Warden\Command;

use Innmind\Warden\{
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
    Server\Command as ServerCommand,
    Server\Process\ExitCode,
};

final class Grant implements Command
{
    private $fetch;
    private $server;

    public function __construct(SshKeyProvider $fetch, Server $server)
    {
        $this->fetch = $fetch;
        $this->server = $server;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $keys = ($this->fetch)(new Name($arguments->get('name')));

        $home = $env->variables()->get('HOME');
        $exitCode = $keys->reduce(
            new ExitCode(0),
            function(ExitCode $exitCode, string $key) use ($home): ExitCode {
                if (!$exitCode->isSuccessful()) {
                    return $exitCode;
                }

                return $this
                    ->server
                    ->processes()
                    ->execute(
                        ServerCommand::foreground('echo')
                            ->withArgument($key)
                            ->append('.ssh/authorized_keys')
                            ->withWorkingDirectory($home)
                    )
                    ->wait()
                    ->exitCode();
            }
        );
        $env->exit($exitCode->toInt());
    }

    public function __toString(): string
    {
        return <<<USAGE
grant name

Lookup the ssh keys of {name} and add them as authorized ones

It will fetch ssh keys from github and happen them in ~/.ssh/authorized_keys
USAGE;
    }
}
