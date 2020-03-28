<?php
declare(strict_types = 1);

namespace Innmind\Warden;

use Innmind\Immutable\Set;

interface SshKeyProvider
{
    /**
     * @return Set<string>
     */
    public function __invoke(Name $name): Set;
}
