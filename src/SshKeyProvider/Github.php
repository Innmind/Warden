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
    Message\Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use Innmind\Immutable\{
    Set,
    Str,
};

final class Github implements SshKeyProvider
{
    private Transport $http;

    public function __construct(Transport $http)
    {
        $this->http = $http;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Name $name): Set
    {
        $response = ($this->http)(new Request(
            Url::of("http://github.com/$name.keys"),
            Method::get(),
            new ProtocolVersion(2, 0)
        ));

        /** @var Set<string> */
        return $response
            ->body()
            ->read()
            ->split("\n")
            ->filter(static function(Str $key): bool {
                return !$key->empty();
            })
            ->reduce(
                Set::of('string'),
                static function(Set $keys, Str $key): Set {
                    return $keys->add($key->toString());
                }
            );
    }
}
