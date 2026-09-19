<?php

//feather icon for a social platform name, falling back to a generic link icon
if (!function_exists('talent_social_icon')) {

    function talent_social_icon($platform = "") {
        $platform = strtolower($platform);
        foreach (array("facebook", "twitter", "instagram", "linkedin", "youtube", "github", "twitch") as $name) {
            if (strpos($platform, $name) !== false) {
                return $name;
            }
        }
        if (strpos($platform, "x.com") !== false || $platform === "x") {
            return "twitter";
        }
        return "link";
    }
}

if (!function_exists('talent_can_access_staff')) {

    function talent_can_access_staff() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user || $ci->login_user->user_type !== "staff") {
            return false;
        }
        if ($ci->login_user->is_admin) {
            return true;
        }
        return get_array_value($ci->login_user->permissions, "talent_management") ? true : false;
    }
}
