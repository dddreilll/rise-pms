<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Google Docs Integration
Plugin URL: https://github.com/local/rise-pms
Description: Create and manage Google Docs inside RISE with in-app editing.
Version: 1.0.0
Requires at least: 2.8
Author: Rise PMS
Author URL: https://github.com/local/rise-pms
*/

$google_docs_helper = PLUGINPATH . "Google_Docs_Integration/Helpers/google_docs_general_helper.php";
if (file_exists($google_docs_helper)) {
    require_once($google_docs_helper);
}

register_installation_hook("Google_Docs_Integration", function ($item_purchase_code) {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");
    $db->query("CREATE TABLE IF NOT EXISTS `" . $db_prefix . "google_docs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` text COLLATE utf8_unicode_ci NOT NULL,
        `description` text COLLATE utf8_unicode_ci,
        `google_file_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `project_id` int(11) NOT NULL DEFAULT '0',
        `created_by` int(11) NOT NULL DEFAULT '0',
        `share_with` text COLLATE utf8_unicode_ci,
        `deleted` tinyint(1) NOT NULL DEFAULT '0',
        `created_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
});

register_uninstallation_hook("Google_Docs_Integration", function () {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");
    $db->query("DROP TABLE IF EXISTS `" . $db_prefix . "google_docs`");

    $Settings_model = model("App\Models\Settings_model");
    $settings = array(
        "google_docs_client_id",
        "google_docs_client_secret",
        "google_docs_oauth_access_token",
        "google_docs_authorized",
        "client_can_access_google_docs",
    );
    foreach ($settings as $setting) {
        $Settings_model->save_setting($setting, "");
    }
});

register_activation_hook("Google_Docs_Integration", function () {
});

register_deactivation_hook("Google_Docs_Integration", function () {
});

app_hooks()->add_filter('app_filter_app_csrf_exclude_uris', function ($app_csrf_exclude_uris) {
    if (!in_array("google_docs_api.*+", $app_csrf_exclude_uris)) {
        array_push($app_csrf_exclude_uris, "google_docs_api.*+");
    }
    return $app_csrf_exclude_uris;
});

app_hooks()->add_filter('app_filter_integration_settings_tab', function ($hook_tabs) {
    $hook_tabs[] = array(
        "title" => app_lang("google_docs"),
        "url" => get_uri("google_docs_integration_settings"),
        "target" => "google-docs-integration",
    );
    return $hook_tabs;
});

app_hooks()->add_filter('app_filter_staff_left_menu', function ($sidebar_menu) {
    if (google_docs_can_access_staff()) {
        $sidebar_menu["google_docs"] = array(
            "name" => "google_docs",
            "url" => "google_docs",
            "class" => "file-text",
            "position" => 4,
        );
    }
    return $sidebar_menu;
});

app_hooks()->add_filter('app_filter_client_left_menu', function ($sidebar_menu) {
    if (google_docs_can_access_client()) {
        $sidebar_menu["google_docs"] = array(
            "name" => "google_docs",
            "url" => "google_docs",
            "class" => "file-text",
            "position" => 4,
        );
    }
    return $sidebar_menu;
});

app_hooks()->add_action('app_hook_role_permissions_extension', function () {
    $uri = service('uri');
    $role_id = $uri->getSegment(3);
    $google_docs = "";
    if ($role_id) {
        $Roles_model = model("App\Models\Roles_model");
        $role_info = $Roles_model->get_one($role_id);
        $permissions = $role_info->permissions ? unserialize($role_info->permissions) : array();
        $permissions = is_array($permissions) ? $permissions : array();
        $google_docs = get_array_value($permissions, "google_docs");
    }
    echo view('Google_Docs_Integration\Views\settings\role_permission', array("google_docs" => $google_docs));
});

app_hooks()->add_filter('app_filter_role_permissions_save_data', function ($permissions) {
    $request = \Config\Services::request();
    $permissions["google_docs"] = $request->getPost("google_docs");
    return $permissions;
});

app_hooks()->add_action('app_hook_client_permissions_extension', function () {
    echo view('Google_Docs_Integration\Views\settings\client_permission');
});

app_hooks()->add_action('app_hook_client_permissions_save_data', function () {
    $request = \Config\Services::request();
    $Settings_model = model("App\Models\Settings_model");
    $value = $request->getPost("client_can_access_google_docs");
    $Settings_model->save_setting("client_can_access_google_docs", $value ? "1" : "");
});

app_hooks()->add_filter('app_filter_team_members_project_details_tab', function ($project_tabs, $project_id = 0) {
    if (google_docs_can_access_staff()) {
        $project_tabs["google_docs"] = "google_docs/project_docs/" . $project_id;
    }
    return $project_tabs;
});

app_hooks()->add_filter('app_filter_clients_project_details_tab', function ($project_tabs, $project_id = 0) {
    if (google_docs_can_access_client()) {
        $project_tabs["google_docs"] = "google_docs/project_docs/" . $project_id;
    }
    return $project_tabs;
});
