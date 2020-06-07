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

final class Grant implements Gene
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return 'Warden grant '.$this->name;
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
            $grant = new Script(
                Command::foreground('composer')
                    ->withArgument('global')
                    ->withArgument('exec')
                    ->withArgument("warden grant {$this->name}")
                    ->withShortOption('v'),
            );
            $grant($target);
        } catch (ScriptFailed $e) {
            throw new ExpressionFailed($this->name());
        }

        return $history;
    }
}
