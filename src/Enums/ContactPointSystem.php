<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Enums;

enum ContactPointSystem: string
{
    case Email = 'email';
    case Fax = 'fax';
    case Phone = 'phone';
    case Sms = 'sms';
}
