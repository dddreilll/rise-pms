<?php

namespace Google_Docs_Integration\Libraries;

class Google_Docs_Client {

    private $Settings_model;

    public function __construct() {
        $this->Settings_model = model("App\Models\Settings_model");
        require_once(APPPATH . "ThirdParty/Google/2-18-1/autoload.php");
    }

    public function authorize() {
        $client = $this->_get_client_credentials();
        $this->_check_access_token($client, true);
    }

    private function _check_access_token($client, $redirect_to_settings = false) {
        $accessToken = get_setting("google_docs_oauth_access_token");
        if (get_setting("google_docs_authorized") && $accessToken && !$redirect_to_settings) {
            $client->setAccessToken(json_decode($accessToken, true));
        }

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                $this->_persist_access_token($client);
                if ($redirect_to_settings) {
                    app_redirect("settings/integration");
                }
            } else {
                $authUrl = $client->createAuthUrl();
                app_redirect($authUrl, true);
            }
        } else {
            if ($redirect_to_settings) {
                app_redirect("settings/integration");
            }
        }
    }

    public function save_access_token($auth_code) {
        $client = $this->_get_client_credentials();
        $accessToken = $client->fetchAccessTokenWithAuthCode($auth_code);
        $error = get_array_value($accessToken, "error");
        if ($error) {
            die($error);
        }

        $client->setAccessToken($accessToken);
        $this->_persist_access_token($client);
        $this->Settings_model->save_setting("google_docs_authorized", "1");
    }

    private function _persist_access_token($client) {
        $new_access_token = json_encode($client->getAccessToken());
        if ($new_access_token) {
            $this->Settings_model->save_setting("google_docs_oauth_access_token", $new_access_token);
        }
    }

    private function _get_drive_service() {
        $client = $this->_get_client_credentials();
        $this->_check_access_token($client);
        return new \Google_Service_Drive($client);
    }

    public function create_document($title = "") {
        $service = $this->_get_drive_service();
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            "name" => $title ? $title : "Untitled document",
            "mimeType" => "application/vnd.google-apps.document",
        ));

        try {
            $file = $service->files->create($fileMetadata, array(
                "fields" => "id, name, webViewLink",
            ));
            $file_id = $file->id;
            $this->_make_file_editable_by_link($service, $file_id);
            return $file_id;
        } catch (\Exception $e) {
            log_message("error", "Google Docs create error: " . $e->getMessage());
            return false;
        }
    }

    public function rename_document($file_id, $title) {
        if (!$file_id) {
            return false;
        }
        $service = $this->_get_drive_service();
        $file = new \Google_Service_Drive_DriveFile();
        $file->setName($title);
        try {
            $service->files->update($file_id, $file, array("fields" => "id, name"));
            return true;
        } catch (\Exception $e) {
            log_message("error", "Google Docs rename error: " . $e->getMessage());
            return false;
        }
    }

    public function delete_document($file_id) {
        if (!$file_id) {
            return false;
        }
        $service = $this->_get_drive_service();
        try {
            $service->files->delete($file_id);
            return true;
        } catch (\Exception $e) {
            log_message("error", "Google Docs delete error: " . $e->getMessage());
            return false;
        }
    }

    private function _make_file_editable_by_link($service, $file_id = "") {
        $permission = new \Google_Service_Drive_Permission(array(
            "type" => "anyone",
            "role" => "writer",
        ));
        try {
            $service->permissions->create($file_id, $permission);
        } catch (\Exception $e) {
            log_message("error", "Google Docs permission error: " . $e->getMessage());
        }
    }

    private function _get_client_credentials() {
        $url = get_uri("google_docs_api/save_access_token");
        $client = new \Google_Client();
        $client->setApplicationName(get_setting("app_title"));
        $client->setRedirectUri($url);
        $client->setClientId(get_setting("google_docs_client_id"));
        $client->setClientSecret(get_setting("google_docs_client_secret"));
        // Drive scope covers creating Google Docs files (docs mime type).
        $client->setScopes(array(
            \Google_Service_Drive::DRIVE,
            "https://www.googleapis.com/auth/documents",
        ));
        $client->setAccessType("offline");
        $client->setPrompt("select_account consent");
        return $client;
    }
}
