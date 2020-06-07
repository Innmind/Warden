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
use Innmind\Filesystem\{
    Directory,
    Name as FileName
};
use Innmind\Url\Path;
use Innmind\Immutable\Set;

final class Grant implements Command
{
    private SshKeyProvider $fetch;
    private Server $server;
    private Filesystem $filesystem;

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
        $home = Path::of($env->variables()->get('HOME').'/');

        $exitCode = $this->filter($home, $keys)->reduce(
            new ExitCode(0),
            function(ExitCode $exitCode, string $key) use ($home): ExitCode {
                if (!$exitCode->isSuccessful()) {
                    return $exitCode;
                }

                $process = $this
                    ->server
                    ->processes()
                    ->execute(
                        ServerCommand::foreground('echo')
                            ->withArgument($key)
                            ->append(Path::of('.ssh/authorized_keys'))
                            ->withWorkingDirectory($home),
                    );
                $process->wait();

                return $process->exitCode();
            }
        );
        $env->exit($exitCode->toInt());
    }

    public function toString(): string
    {
        return <<<USAGE
grant name

Lookup the ssh keys of {name} and add them as authorized ones

It will fetch ssh keys from github and happen them in ~/.ssh/authorized_keys
USAGE;
    }

    /**
     * @param Set<string> $keys
     *
     * @return Set<string>
     */
    private function filter(Path $home, Set $keys): Set
    {
        $home = $this->filesystem->mount($home);

        if (!$home->contains(new FileName('.ssh'))) {
            return $keys;
        }

        /** @var Directory */
        $ssh = $home->get(new FileName('.ssh'));

        if (!$ssh->contains(new FileName('authorized_keys'))) {
            return $keys;
        }

        $authorized = $ssh->get(new FileName('authorized_keys'))->content();

        while (!$authorized->end()) {
            $key = $authorized->readLine()->toString();

            if ($keys->contains($key)) {
                $keys = $keys->remove($key);
            }
        }

        return $keys;
    }
}
