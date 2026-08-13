<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Enums;

enum ContactPointUse: string
{
    case Home = 'home';
    case Mobile = 'mobile';
    case Work = 'work';
}
