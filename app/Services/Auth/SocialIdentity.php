<?php

namespace App\Services\Auth;

final readonly class SocialIdentity
{
    public function __construct(
        public string $provider,
        public string $providerId,
        public string $email,
        public string $name,
        public ?string $avatar,
        public bool $emailVerified,
    ) {}
}
