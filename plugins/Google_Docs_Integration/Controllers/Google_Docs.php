<?php

namespace Google_Docs_Integration\Controllers;

use App\Controllers\Security_Controller;
use Google_Docs_Integration\Libraries\Google_Docs_Client;
use Google_Docs_Integration\Models\Google_Docs_Model;

class Google_Docs extends Security_Controller {

    private $Google_Docs_Model;
    private $google_docs_client;

    function __construct() {
        parent::__construct();
        $this->Google_Docs_Model = new Google_Docs_Model();
        $this->google_docs_client = new Google_Docs_Client();
    }

    private function _can_access() {
        if (!google_docs_can_access()) {
            app_redirect("forbidden");
        }
    }

    private function _can_manage() {
        if ($this->login_user->user_type === "client") {
            return false;
        }
        return google_docs_can_manage_staff();
    }

    private function _access_options($extra = array()) {
        $options = $extra;
        if ($this->login_user->is_admin) {
            return $options;
        }

        $options["user_id"] = $this->login_user->id;
        $options["team_ids"] = $this->login_user->team_ids;
        if ($this->login_user->user_type === "client") {
            $options["is_client"] = true;
        }
        return $options;
    }

    private function _validate_access_to_doc($doc_info, $edit_mode = false) {
        if (!$doc_info || !$doc_info->id) {
            app_redirect("forbidden");
        }

        if ($this->login_user->is_admin) {
            return true;
        }

        if ($edit_mode && ($this->login_user->user_type !== "staff" || !$this->_can_manage())) {
            app_redirect("forbidden");
        }

        $options = $this->_access_options(array("id" => $doc_info->id));
        $allowed = $this->Google_Docs_Model->get_details($options)->getRow();
        if (!$allowed) {
            app_redirect("forbidden");
        }
        return true;
    }

    function index() {
        $this->_can_access();
        $view_data["can_manage"] = $this->_can_manage();
        $view_data["project_id"] = 0;
        return $this->template->rander('Google_Docs_Integration\Views\google_docs\index', $view_data);
    }

    function project_docs($project_id = 0) {
        $this->_can_access();
        validate_numeric_value($project_id);
        $view_data["can_manage"] = $this->_can_manage();
        $view_data["project_id"] = $project_id;
        return $this->template->view('Google_Docs_Integration\Views\google_docs\project_docs', $view_data);
    }

    function modal_form() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "project_id" => "numeric",
        ));

        $id = $this->request->getPost("id");
        $view_data["model_info"] = $this->Google_Docs_Model->get_one($id);
        $view_data["project_id"] = $this->request->getPost("project_id") ? $this->request->getPost("project_id") : $view_data["model_info"]->project_id;

        if ($view_data["model_info"]->id) {
            $this->_validate_access_to_doc($view_data["model_info"], true);
        }

        $view_data["share_with"] = $view_data["model_info"]->share_with;
        $view_data["id"] = $view_data["model_info"]->id;
        $view_data["client_id"] = "";
        $view_data["options"] = array("only_me", "all_team_members", "specific_members_and_teams");
        if (get_setting("client_can_access_google_docs")) {
            $view_data["options"][] = "all_clients";
        }
        $view_data["members_and_teams_dropdown_source_url"] = get_uri("google_docs/get_members_and_teams_dropdown");
        $view_data["get_sharing_options_view"] = view("includes/sharing_options", $view_data);

        return $this->template->view('Google_Docs_Integration\Views\google_docs\modal_form', $view_data);
    }

    function get_members_and_teams_dropdown() {
        $this->_can_access();
        echo json_encode(get_team_members_and_teams_select2_data_list(true));
    }

    function save() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "title" => "required",
            "project_id" => "numeric",
        ));

        $id = $this->request->getPost("id");
        $title = $this->request->getPost("title");
        $description = $this->request->getPost("description");
        $project_id = $this->request->getPost("project_id") ? $this->request->getPost("project_id") : 0;
        $share_with = $this->request->getPost("share_with");
        validate_share_with_value($share_with);

        if ($id) {
            $doc_info = $this->Google_Docs_Model->get_one($id);
            $this->_validate_access_to_doc($doc_info, true);

            $data = array(
                "title" => $title,
                "description" => $description,
                "share_with" => $share_with,
                "project_id" => $project_id,
            );

            if ($doc_info->google_file_id && $doc_info->title !== $title && google_docs_is_authorized()) {
                $this->google_docs_client->rename_document($doc_info->google_file_id, $title);
            }

            $save_id = $this->Google_Docs_Model->ci_save($data, $id);
        } else {
            if (!google_docs_is_authorized()) {
                echo json_encode(array("success" => false, "message" => app_lang("google_docs_not_authorized")));
                return;
            }

            $google_file_id = $this->google_docs_client->create_document($title);
            if (!$google_file_id) {
                echo json_encode(array("success" => false, "message" => app_lang("google_docs_create_failed")));
                return;
            }

            $data = array(
                "title" => $title,
                "description" => $description,
                "google_file_id" => $google_file_id,
                "project_id" => $project_id,
                "created_by" => $this->login_user->id,
                "share_with" => $share_with,
                "created_at" => get_current_utc_time(),
            );
            $save_id = $this->Google_Docs_Model->ci_save($data);
        }

        if ($save_id) {
            echo json_encode(array("success" => true, "data" => $this->_row_data($save_id), "id" => $save_id, "message" => app_lang("record_saved")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function delete() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "required|numeric",
        ));

        $id = $this->request->getPost("id");
        $doc_info = $this->Google_Docs_Model->get_one($id);
        $this->_validate_access_to_doc($doc_info, true);

        if ($this->Google_Docs_Model->delete($id)) {
            if ($doc_info->google_file_id && google_docs_is_authorized()) {
                $this->google_docs_client->delete_document($doc_info->google_file_id);
            }
            echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
        }
    }

    function list_data($project_id = 0) {
        $this->_can_access();
        validate_numeric_value($project_id);

        $options = $this->_access_options();
        if ($project_id) {
            $options["project_id"] = $project_id;
        }

        $list_data = $this->Google_Docs_Model->get_details($options)->getResult();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        echo json_encode(array("data" => $result));
    }

    function view($id = 0) {
        $this->_can_access();
        validate_numeric_value($id);

        $options = $this->_access_options(array("id" => $id));
        $doc_info = $this->Google_Docs_Model->get_details($options)->getRow();
        if (!$doc_info) {
            show_404();
        }

        $view_data["doc_info"] = $doc_info;
        $view_data["embed_url"] = google_docs_embed_url($doc_info->google_file_id);
        return $this->template->rander('Google_Docs_Integration\Views\google_docs\view', $view_data);
    }

    private function _row_data($id) {
        $options = $this->_access_options(array("id" => $id));
        if ($this->login_user->is_admin) {
            $options = array("id" => $id);
        }
        $data = $this->Google_Docs_Model->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        if (!$data) {
            return array();
        }

        $image_url = get_avatar($data->created_by_avatar);
        $user = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt=''></span> $data->created_by_user";

        $title = anchor(get_uri("google_docs/view/" . $data->id), $data->title);

        $option = "";
        if ($this->_can_manage()) {
            $option .= modal_anchor(get_uri("google_docs/modal_form"), "<i data-feather='edit' class='icon-16'></i>", array(
                "class" => "edit",
                "title" => app_lang("edit_document"),
                "data-post-id" => $data->id,
                "data-post-project_id" => $data->project_id,
            ));
            $option .= js_anchor("<i data-feather='x' class='icon-16'></i>", array(
                "title" => app_lang("delete_document"),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => get_uri("google_docs/delete"),
                "data-action" => "delete",
            ));
        }

        return array(
            $title,
            $data->description,
            get_team_member_profile_link($data->created_by, $user),
            $option,
        );
    }
}
