<?php
declare(strict_types = 1);

namespace Innmind\Warden;

use Innmind\Warden\{
    Command\Wakeup,
    Command\Lock,
    Command\Grant,
    SshKeyProvider\Github,
};
use Innmind\CLI\Command;
use Innmind\OperatingSystem\OperatingSystem;
use function Innmind\HttpTransport\bootstrap as transports;

/**
 * @return list<Command>
 */
function bootstrap(OperatingSystem $os): array
{
    $transports = transports();
    $throw = $transports['throw_on_error'];
    $transport = $throw(
        $os->remote()->http(),
    );

    return [
        new Wakeup($os->control()),
        new Lock($os->control()),
        new Grant(
            new Github($transport),
            $os->control(),
            $os->filesystem(),
        ),
    ];
}
