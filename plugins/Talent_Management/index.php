<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Talent Management
Plugin URL: https://github.com/local/rise-pms
Description: Manage a roster of on-camera talent (actors, extras, hosts, subject-matter experts) with a per-project status pipeline and casting links.
Version: 1.2.0
Requires at least: 2.8
Author: Rise PMS
Author URL: https://github.com/local/rise-pms
*/

$talent_general_helper = PLUGINPATH . "Talent_Management/Helpers/talent_general_helper.php";
if (file_exists($talent_general_helper)) {
    require_once($talent_general_helper);
}

$talent_schema_helper = PLUGINPATH . "Talent_Management/Helpers/talent_schema_helper.php";
if (file_exists($talent_schema_helper)) {
    require_once($talent_schema_helper);
}

register_installation_hook("Talent_Management", function ($item_purchase_code) {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");

    $db->query("CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_status` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        `color` varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '#2e4053',
        `sort` int(11) NOT NULL DEFAULT '0',
        `deleted` tinyint(1) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->query("CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `legal_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `preferred_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `pronouns` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `address` text COLLATE utf8_unicode_ci,
        `contact_number` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `profile_image` text COLLATE utf8_unicode_ci,
        `dietary_restrictions` text COLLATE utf8_unicode_ci,
        `safety_comfort_notes` text COLLATE utf8_unicode_ci,
        `social_links` text COLLATE utf8_unicode_ci,
        `profession` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `on_screen_title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `created_by` int(11) NOT NULL DEFAULT '0',
        `created_at` datetime DEFAULT NULL,
        `deleted` tinyint(1) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    //status is per casting link, not per talent - the same talent can be at a different stage on each project
    $db->query("CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_projects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `talent_id` int(11) NOT NULL,
        `project_id` int(11) NOT NULL,
        `talent_status_id` int(11) NOT NULL DEFAULT '0',
        `sort` int(11) NOT NULL DEFAULT '0',
        `created_at` datetime DEFAULT NULL,
        `deleted` tinyint(1) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`),
        KEY `talent_id` (`talent_id`),
        KEY `project_id` (`project_id`),
        KEY `talent_status_id` (`talent_status_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    //contract-signing schema: system_key on the stages + the templates/contracts/audit tables
    talent_ensure_schema_structure($db, $db_prefix);

    //seed a default pipeline so the kanban board isn't empty on first use
    $existing = $db->query("SELECT COUNT(id) AS total FROM `" . $db_prefix . "talent_status`")->getRow();
    if (!$existing || !$existing->total) {
        foreach (talent_default_pipeline() as $sort => $status) {
            $status["sort"] = $sort;
            $db->table($db_prefix . "talent_status")->insert($status);
        }
    }

    //nothing to do on a fresh pipeline; repairs stages left behind by an earlier install
    talent_ensure_system_stages($db, $db_prefix);
});

//RISE runs this when an admin clicks "Updates" on the plugin and shows the output in a modal. It brings an existing install
//(1.1.0 and older) up to the current schema; every step is idempotent, so clicking twice is harmless.
register_update_hook("Talent_Management", function () {
    $view_data = array("changes" => array(), "error" => "");

    try {
        $view_data["changes"] = talent_ensure_schema();
    } catch (\Throwable $ex) {
        log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
        $view_data["error"] = $ex->getMessage();
    }

    echo view('Talent_Management\Views\settings\update_modal', $view_data);
});

register_uninstallation_hook("Talent_Management", function () {
    $db = db_connect('default');
    $db_prefix = get_db_prefix();
    $db->query("SET sql_mode = ''");

    $db->query("DROP TABLE IF EXISTS `" . $db_prefix . "talent_projects`");
    $db->query("DROP TABLE IF EXISTS `" . $db_prefix . "talent`");
    $db->query("DROP TABLE IF EXISTS `" . $db_prefix . "talent_status`");
    $db->query("DROP TABLE IF EXISTS `" . $db_prefix . "talent_contract_templates`");

    //talent_contracts and talent_contract_events are kept on purpose: they hold the signed legal record (frozen contract text,
    //signer, timestamps, IP) and shouldn't disappear because a plugin was removed. Drop them by hand if that's really wanted.

    //custom field data lives in core tables (related_to='talent') - clean it up so uninstall leaves no orphaned rows
    $db->query("DELETE FROM `" . $db_prefix . "custom_field_values` WHERE related_to_type='talent'");
    $db->query("DELETE FROM `" . $db_prefix . "custom_fields` WHERE related_to='talent'");
});

register_activation_hook("Talent_Management", function () {
});

register_deactivation_hook("Talent_Management", function () {
});

app_hooks()->add_filter('app_filter_staff_left_menu', function ($sidebar_menu) {
    if (talent_can_access_staff()) {
        $sidebar_menu["talent"] = array(
            "name" => "talent",
            "url" => "talent",
            "class" => "video",
            "position" => 5,
        );
    }
    return $sidebar_menu;
});

app_hooks()->add_action('app_hook_role_permissions_extension', function () {
    $uri = service('uri');
    $role_id = $uri->getSegment(3);
    $talent_management = "";
    if ($role_id) {
        $Roles_model = model("App\Models\Roles_model");
        $role_info = $Roles_model->get_one($role_id);
        $permissions = $role_info->permissions ? unserialize($role_info->permissions) : array();
        $permissions = is_array($permissions) ? $permissions : array();
        $talent_management = get_array_value($permissions, "talent_management");
    }
    echo view('Talent_Management\Views\settings\role_permission', array("talent_management" => $talent_management));
});

app_hooks()->add_filter('app_filter_role_permissions_save_data', function ($permissions) {
    $request = \Config\Services::request();
    $permissions["talent_management"] = $request->getPost("talent_management");
    return $permissions;
});

app_hooks()->add_filter('app_filter_team_members_project_details_tab', function ($project_tabs, $project_id = 0) {
    if (talent_can_access_staff()) {
        $project_tabs["talent"] = "talent_projects/project_tab/" . $project_id;
    }
    return $project_tabs;
});
