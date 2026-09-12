<?php

if (!function_exists('google_docs_can_access_staff')) {

    function google_docs_can_access_staff() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user || $ci->login_user->user_type !== "staff") {
            return false;
        }
        if ($ci->login_user->is_admin) {
            return true;
        }
        return get_array_value($ci->login_user->permissions, "google_docs") ? true : false;
    }
}

if (!function_exists('google_docs_can_manage_staff')) {

    function google_docs_can_manage_staff() {
        return google_docs_can_access_staff();
    }
}

if (!function_exists('google_docs_can_access_client')) {

    function google_docs_can_access_client() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user || $ci->login_user->user_type !== "client") {
            return false;
        }
        return get_setting("client_can_access_google_docs") ? true : false;
    }
}

if (!function_exists('google_docs_can_access')) {

    function google_docs_can_access() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user) {
            return false;
        }
        if ($ci->login_user->user_type === "staff") {
            return google_docs_can_access_staff();
        }
        return google_docs_can_access_client();
    }
}

if (!function_exists('google_docs_is_authorized')) {

    function google_docs_is_authorized() {
        return get_setting("google_docs_authorized") ? true : false;
    }
}

if (!function_exists('google_docs_embed_url')) {

    function google_docs_embed_url($google_file_id = "") {
        if (!$google_file_id) {
            return "";
        }
        return "https://docs.google.com/document/d/" . $google_file_id . "/edit?usp=sharing&widget=true&rm=embedded";
    }
}
