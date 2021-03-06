<?php
declare(strict_types = 1);

namespace Innmind\Warden\Gene;

use Innmind\Genome\{
    Gene,
    History,
    Exception\PreConditionFailed,
    Exception\ExpressionFailed,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\{
    Server,
    Server\Command,
    Server\Script,
    Exception\ScriptFailed,
};

final class Wakeup implements Gene
{
    public function name(): string
    {
        return 'Warden wakeup';
    }

    public function express(
        OperatingSystem $local,
        Server $target,
        History $history
    ): History {
        try {
            $preCondition = new Script(
                Command::foreground('composer')
                    ->withArgument('global')
                    ->withArgument('exec')
                    ->withArgument('warden help')
                    ->withShortOption('v'),
            );
            $preCondition($target);
        } catch (ScriptFailed $e) {
            throw new PreConditionFailed('warden is missing');
        }

        try {
            $wakeup = new Script(
                Command::foreground('composer')
                    ->withArgument('global')
                    ->withArgument('exec')
                    ->withArgument('warden wakeup')
                    ->withShortOption('v'),
            );
            $wakeup($target);
        } catch (ScriptFailed $e) {
            throw new ExpressionFailed($this->name());
        }

        return $history;
    }
}
