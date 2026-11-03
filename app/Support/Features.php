<?php

namespace App\Support;

use App\Models\Organization;

final class Features
{
    public static function customerPortalEnabled(?Organization $organization = null): bool
    {
        if (! (bool) config('features.customer_portal', false)) {
            return false;
        }

        if ($organization !== null) {
            $settings = $organization->settings;
            if (is_array($settings)) {
                $flags = $settings['flags'] ?? null;
                if (is_array($flags) && in_array('customer_portal', $flags, true)) {
                    return true;
                }
                if (is_array($flags) && in_array('!customer_portal', $flags, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
