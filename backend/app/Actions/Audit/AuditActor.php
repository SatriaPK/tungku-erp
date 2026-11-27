<?php

namespace App\Actions\Audit;

use App\Models\User;
use InvalidArgumentException;

final readonly class AuditActor
{
    private function __construct(public string $kind, public ?int $userId, public string $source) {}

    public static function user(User $user): self
    {
        return new self('user', $user->id, 'user');
    }

    public static function system(string $source): self
    {
        return new self('system', null, $source);
    }

    public static function provider(string $provider): self
    {
        if (! in_array($provider, ['midtrans', 'xendit'], true)) {
            throw new InvalidArgumentException('Unsupported audit provider.');
        }

        return new self('provider', null, $provider);
    }
}
