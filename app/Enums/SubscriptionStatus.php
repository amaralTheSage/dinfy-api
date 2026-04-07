<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Canceled = 'canceled';

    public function isOpen(): bool
    {
        return match ($this) {
            self::Pending, self::Active => true,
            default => false,
        };
    }

    public function canExpire(): bool
    {
        return match ($this) {
            self::Active => true,
            default => false,
        };
    }
}
