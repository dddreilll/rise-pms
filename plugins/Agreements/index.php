<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Agreements
Plugin URL: https://github.com/local/rise-pms
Description: Create fillable agreements with multi-party parallel or sequential e-sign, HTML or PDF overlays, and signed PDF delivery.
Version: 1.0.0
Requires at least: 2.8
Author: Rise PMS
Author URL: https://github.com/local/rise-pms
*/

$agreements_helper = PLUGINPATH . "Agreements/Helpers/agreements_helper.php";
if (file_exists($agreements_helper)) {
    require_once($agreements_helper);
}

register_installation_hook("Agreements", function ($item_purchase_code) {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");

    // Use raw SQL only here — plugin PSR-4 is not registered until activation,
    // so namespaced models / agreements_save_setting() would fatal and hang the UI.
    $sql_file = PLUGINPATH . "Agreements/install/database.sql";
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        $sql = str_replace("{PREFIX}", $db_prefix, $sql);
        foreach (array_filter(array_map("trim", explode(";", $sql))) as $query) {
            if ($query) {
                $db->query($query);
            }
        }
    }

    $settings_table = $db_prefix . "agreements_settings";
    $db->query("INSERT IGNORE INTO `{$settings_table}` (`setting_name`, `setting_value`, `type`, `deleted`) VALUES
        ('client_can_access_agreements', '', 'app', 0),
        ('default_reminder_days', '3', 'app', 0),
        ('default_template_id', '', 'app', 0)");

    // Seed default template so new agreements cannot be blank
    agreements_ensure_default_template();
});

register_uninstallation_hook("Agreements", function () {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");
    $tables = array(
        "agreements_field_values",
        "agreements_fields",
        "agreements_signatories",
        "agreements_recipients",
        "agreements_audit_logs",
        "agreements_templates",
        "agreements_documents",
        "agreements_settings",
    );
    foreach ($tables as $table) {
        $db->query("DROP TABLE IF EXISTS `" . $db_prefix . $table . "`");
    }
});

register_activation_hook("Agreements", function () {
    agreements_ensure_default_template();
});

register_deactivation_hook("Agreements", function () {
});

app_hooks()->add_filter('app_filter_app_csrf_exclude_uris', function ($app_csrf_exclude_uris) {
    $uris = array("agreements_sign.*+");
    foreach ($uris as $uri) {
        if (!in_array($uri, $app_csrf_exclude_uris)) {
            array_push($app_csrf_exclude_uris, $uri);
        }
    }
    return $app_csrf_exclude_uris;
});

app_hooks()->add_filter('app_filter_staff_left_menu', function ($sidebar_menu) {
    if (agreements_can_access_staff()) {
        $sidebar_menu["agreements"] = array(
            "name" => "agreements",
            "url" => "agreements",
            "class" => "file-text",
            "position" => 5,
            "submenu" => array(
                array("name" => "agreements", "url" => "agreements", "class" => "file-text"),
                array("name" => "agreements_templates", "url" => "agreements_templates", "class" => "copy"),
            ),
        );
    }
    return $sidebar_menu;
});

app_hooks()->add_filter('app_filter_client_left_menu', function ($sidebar_menu) {
    if (agreements_can_access_client()) {
        $sidebar_menu["agreements"] = array(
            "name" => "agreements",
            "url" => "agreements",
            "class" => "file-text",
            "position" => 5,
        );
    }
    return $sidebar_menu;
});

app_hooks()->add_action('app_hook_role_permissions_extension', function () {
    $uri = service('uri');
    $role_id = $uri->getSegment(3);
    $agreements = "";
    if ($role_id) {
        $Roles_model = model("App\Models\Roles_model");
        $role_info = $Roles_model->get_one($role_id);
        $permissions = $role_info->permissions ? unserialize($role_info->permissions) : array();
        $permissions = is_array($permissions) ? $permissions : array();
        $agreements = get_array_value($permissions, "agreements");
    }
    echo view('Agreements\Views\settings\role_permission', array("agreements" => $agreements));
});

app_hooks()->add_filter('app_filter_role_permissions_save_data', function ($permissions) {
    $request = \Config\Services::request();
    $permissions["agreements"] = $request->getPost("agreements");
    return $permissions;
});

app_hooks()->add_action('app_hook_client_permissions_extension', function () {
    echo view('Agreements\Views\settings\client_permission');
});

app_hooks()->add_action('app_hook_client_permissions_save_data', function () {
    $request = \Config\Services::request();
    $value = $request->getPost("client_can_access_agreements");
    agreements_save_setting("client_can_access_agreements", $value ? "1" : "");
});

app_hooks()->add_filter('app_filter_integration_settings_tab', function ($hook_tabs) {
    $hook_tabs[] = array(
        "title" => app_lang("agreements_settings"),
        "url" => get_uri("agreements_settings"),
        "target" => "agreements-settings",
    );
    return $hook_tabs;
});

app_hooks()->add_action('app_hook_after_cron_run', function () {
    agreements_process_expiry_and_reminders();
});
