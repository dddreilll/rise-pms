<?php

namespace Agreements\Controllers;

use App\Controllers\Security_Controller;
use Agreements\Models\Agreements_Templates_Model;

class Agreements_Settings extends Security_Controller {

    function __construct() {
        parent::__construct();
        $this->access_only_admin_or_settings_admin();
    }

    function index() {
        agreements_ensure_default_template();
        $Templates_model = new Agreements_Templates_Model();
        $templates = $Templates_model->get_details()->getResult();
        $templates_dropdown = array();
        foreach ($templates as $row) {
            $templates_dropdown[$row->id] = $row->title;
        }

        $view_data["client_can_access_agreements"] = agreements_get_setting("client_can_access_agreements");
        $view_data["default_reminder_days"] = agreements_get_setting("default_reminder_days", "3");
        $view_data["default_template_id"] = agreements_get_setting("default_template_id");
        $view_data["templates_dropdown"] = $templates_dropdown;
        return $this->template->view('Agreements\Views\settings\index', $view_data);
    }

    function save() {
        agreements_ensure_default_template();
        $default_template_id = (int) $this->request->getPost("default_template_id");
        if ($default_template_id) {
            $Templates_model = new Agreements_Templates_Model();
            $template = $Templates_model->get_one($default_template_id);
            if (!$template || !$template->id) {
                echo json_encode(array("success" => false, "message" => app_lang("agreements_template_required")));
                return;
            }
            agreements_save_setting("default_template_id", (string) $default_template_id);
        }

        agreements_save_setting("client_can_access_agreements", $this->request->getPost("client_can_access_agreements") ? "1" : "");
        agreements_save_setting("default_reminder_days", (string) ((int) $this->request->getPost("default_reminder_days")));
        echo json_encode(array("success" => true, "message" => app_lang("settings_updated")));
    }
}
