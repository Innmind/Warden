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

final class Lock implements Gene
{
    public function name(): string
    {
        return 'Warden lock';
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
            $lock = new Script(
                Command::foreground('composer')
                    ->withArgument('global')
                    ->withArgument('exec')
                    ->withArgument('warden lock')
                    ->withShortOption('v'),
            );
            $lock($target);
        } catch (ScriptFailed $e) {
            throw new ExpressionFailed($this->name());
        }

        return $history;
    }
}
