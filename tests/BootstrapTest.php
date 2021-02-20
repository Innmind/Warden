<?php
declare(strict_types = 1);

namespace Tests\Innmind\Warden;

use function Innmind\Warden\bootstrap;
use Innmind\OperatingSystem\OperatingSystem;
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testBootstrap()
    {
        $this->assertIsArray(bootstrap($this->createMock(OperatingSystem::class)));
        $this->assertCount(3, bootstrap($this->createMock(OperatingSystem::class)));
    }
}
