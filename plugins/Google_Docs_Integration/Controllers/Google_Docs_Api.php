<?php

namespace Google_Docs_Integration\Controllers;

use App\Controllers\Security_Controller;
use Google_Docs_Integration\Libraries\Google_Docs_Client;

class Google_Docs_Api extends Security_Controller {

    private $google_docs_client;

    function __construct() {
        parent::__construct();
        $this->google_docs_client = new Google_Docs_Client();
    }

    function index() {
        app_redirect("google_docs_api/authorize");
    }

    function authorize() {
        $this->access_only_admin_or_settings_admin();
        $this->google_docs_client->authorize();
    }

    function save_access_token() {
        $this->access_only_admin_or_settings_admin();

        if (!empty($_GET)) {
            $this->google_docs_client->save_access_token(get_array_value($_GET, "code"));
            app_redirect("settings/integration");
        }
    }
}
