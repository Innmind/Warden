<?php
declare(strict_types = 1);

namespace Innmind\Warden;

use Innmind\Immutable\SetInterface;

interface SshKeyProvider
{
    /**
     * @return SetInterface<string>
     */
    public function __invoke(Name $name): SetInterface;
}
