<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Remember Me
Plugin URL: https://github.com/local/rise-pms
Description: Stay signed in with a secure remember-me cookie so users are not sent to the login page after the session expires.
Version: 1.0.0
Requires at least: 2.8
Author: Rise PMS
Author URL: https://github.com/local/rise-pms
*/

$remember_me_helper = PLUGINPATH . "Remember_Me/Helpers/remember_me_helper.php";
if (file_exists($remember_me_helper)) {
    require_once($remember_me_helper);
}

$remember_me_filter = PLUGINPATH . "Remember_Me/Filters/RememberMeFilter.php";
if (file_exists($remember_me_filter)) {
    require_once($remember_me_filter);
}

register_installation_hook("Remember_Me", function ($item_purchase_code) {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");
    $db->query("CREATE TABLE IF NOT EXISTS `" . $db_prefix . "remember_me_tokens` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `selector` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
        `token_hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` datetime DEFAULT NULL,
        `last_used_at` datetime DEFAULT NULL,
        `user_agent` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `selector` (`selector`),
        KEY `user_id` (`user_id`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
});

register_uninstallation_hook("Remember_Me", function () {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");
    $db->query("DROP TABLE IF EXISTS `" . $db_prefix . "remember_me_tokens`");
});

register_activation_hook("Remember_Me", function () {
});

register_deactivation_hook("Remember_Me", function () {
});

// Register a global before-filter so the session is restored before Security_Controller redirects.
// (app_hook_before_app_access runs too late when login is required — the redirect exits first.)
$remember_me_filters = config('Filters');
$remember_me_filters->aliases['remember_me'] = \Remember_Me\Filters\RememberMeFilter::class;
if (!in_array('remember_me', $remember_me_filters->globals['before'], true)) {
    $remember_me_filters->globals['before'][] = 'remember_me';
}

app_hooks()->add_action('app_hook_signin_extension', function () {
    echo view('Remember_Me\Views\signin\remember_checkbox');
});

app_hooks()->add_action('app_hook_after_signin', function () {
    remember_me_on_after_signin();
});

app_hooks()->add_action('app_hook_before_app_access', function ($data) {
    // Backup path for controllers that skip the login redirect (Security_Controller(false)).
    remember_me_on_before_app_access($data);
});

app_hooks()->add_action('app_hook_before_signout', function () {
    remember_me_on_before_signout();
});
