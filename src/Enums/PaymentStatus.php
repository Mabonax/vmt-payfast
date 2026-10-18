<?php

namespace Vmt\PayFast\Enums;

enum PaymentStatus: string
{
    case Created = 'CREATED';
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';

    public function allows(self $next): bool
    {
        return match ($this) {
            self::Created => $next === self::Pending,
            self::Pending => in_array($next, [self::Completed, self::Failed, self::Cancelled], true),
            default => false,
        };
    }
}
