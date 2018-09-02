<?php
declare(strict_types = 1);

namespace Innmind\Warden;

use Innmind\Warden\{
    Command\Wakeup,
    Command\Lock,
    Command\Grant,
    SshKeyProvider\Github,
};
use Innmind\CLI\Commands;
use Innmind\Server\Control\ServerFactory;
use function Innmind\HttpTransport\bootstrap as transports;

function bootstrap(): Commands
{
    $transports = transports();
    $throw = $transports['throw_server'];
    $catchException = $transports['cacth_guzzle_exceptions'];
    $guzzle = $transports['guzzle'];
    $transport = $throw(
        $catchException(
            $guzzle()
        )
    );

    $server = ServerFactory::build();

    return new Commands(
        new Wakeup($server),
        new Lock($server),
        new Grant(
            new Github($transport),
            $server
        )
    );
}
