<?php
declare(strict_types = 1);

namespace Innmind\Warden\SshKeyProvider;

use Innmind\Warden\{
    SshKeyProvider,
    Name,
};
use Innmind\HttpTransport\Transport;
use Innmind\Http\{
    Message\Request\Request,
    Message\Method\Method,
    ProtocolVersion\ProtocolVersion,
};
use Innmind\Url\Url;
use Innmind\Immutable\{
    SetInterface,
    Set,
    Str,
};

final class Github implements SshKeyProvider
{
    private $http;

    public function __construct(Transport $http)
    {
        $this->http = $http;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Name $name): SetInterface
    {
        $response = ($this->http)(new Request(
            Url::fromString("http://github.com/$name.keys"),
            new Method(Method::GET),
            new ProtocolVersion(2, 0)
        ));

        return $response
            ->body()
            ->read()
            ->split("\n")
            ->filter(static function(Str $key): bool {
                return !$key->empty();
            })
            ->reduce(
                Set::of('string'),
                static function(SetInterface $keys, Str $key): SetInterface {
                    return $keys->add((string) $key);
                }
            );
    }
}
