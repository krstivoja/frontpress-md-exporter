<?php

declare(strict_types=1);

namespace FrontPressMdExp\Rest;

final class Auth
{
    public static function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public static function canManageNetwork(): bool
    {
        return is_multisite() && current_user_can('manage_network_options');
    }
}
