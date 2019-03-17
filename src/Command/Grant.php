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
use Innmind\OperatingSystem\Filesystem;
use Innmind\Url\Path;
use Innmind\Immutable\SetInterface;

final class Grant implements Command
{
    private $fetch;
    private $server;
    private $filesystem;

    public function __construct(
        SshKeyProvider $fetch,
        Server $server,
        Filesystem $filesystem
    ) {
        $this->fetch = $fetch;
        $this->server = $server;
        $this->filesystem = $filesystem;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $keys = ($this->fetch)(new Name($arguments->get('name')));
        $home = $env->variables()->get('HOME');

        $exitCode = $this->filter($home, $keys)->reduce(
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

    private function filter(string $home, SetInterface $keys): SetInterface
    {
        $home = $this->filesystem->mount(new Path($home));

        if (!$home->has('.ssh')) {
            return $keys;
        }

        $ssh = $home->get('.ssh');

        if (!$ssh->has('authorized_keys')) {
            return $keys;
        }

        $authorized = $ssh->get('authorized_keys')->content();

        while (!$authorized->end()) {
            $key = (string) $authorized->readLine();

            if ($keys->contains($key)) {
                $keys = $keys->remove($key);
            }
        }

        return $keys;
    }
}
