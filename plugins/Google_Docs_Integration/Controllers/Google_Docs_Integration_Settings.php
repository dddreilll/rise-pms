<?php

namespace Google_Docs_Integration\Controllers;

use App\Controllers\Security_Controller;

class Google_Docs_Integration_Settings extends Security_Controller {

    function __construct() {
        parent::__construct();
        $this->access_only_admin_or_settings_admin();
    }

    function index() {
        return $this->template->view("Google_Docs_Integration\Views\settings\google_docs");
    }

    function save() {
        $Settings_model = model("App\Models\Settings_model");

        $Settings_model->save_setting("google_docs_client_id", $this->request->getPost("google_docs_client_id"));
        $Settings_model->save_setting("google_docs_client_secret", $this->request->getPost("google_docs_client_secret"));

        $authorize = $this->request->getPost("authorize_after_save");

        echo json_encode(array(
            "success" => true,
            "message" => app_lang("settings_updated"),
            "authorize" => $authorize ? true : false,
            "authorize_url" => get_uri("google_docs_api"),
        ));
    }
}
