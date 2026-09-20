<?php

namespace Talent_Management\Controllers;

use App\Controllers\Security_Controller;
use Talent_Management\Models\Talent_model;
use Talent_Management\Models\Talent_project_model;
use Talent_Management\Models\Talent_status_model;

class Talent_projects extends Security_Controller {

    private $Talent_model;
    private $Talent_project_model;
    private $Talent_status_model;

    function __construct() {
        parent::__construct();
        $this->Talent_model = new Talent_model();
        $this->Talent_project_model = new Talent_project_model();
        $this->Talent_status_model = new Talent_status_model();
    }

    private function _can_access() {
        if (!talent_can_access_staff()) {
            app_redirect("forbidden");
        }

        //assign() starts casting links on the first non-system stage, which reads talent_status.system_key
        talent_ensure_schema_once();
    }

    //rendered inside the core Project detail view via app_filter_team_members_project_details_tab
    function project_tab($project_id = 0) {
        $this->_can_access();
        validate_numeric_value($project_id);

        $view_data["project_id"] = $project_id;
        return $this->template->view('Talent_Management\Views\talent_projects\project_tab', $view_data);
    }

    function list_data($project_id = 0) {
        $this->_can_access();
        validate_numeric_value($project_id);

        $list_data = $this->Talent_project_model->get_details_for_project($project_id)->getResult();

        $result_data = array();
        foreach ($list_data as $data) {
            $result_data[] = $this->_make_project_row($data);
        }

        echo json_encode(array("data" => $result_data));
    }

    private function _make_project_row($data) {
        $image_url = get_avatar($data->profile_image);
        $name = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> " . ($data->preferred_name ? $data->preferred_name : $data->legal_name);

        return array(
            anchor(get_uri("talent/view/" . $data->id), $name),
            $data->on_screen_title ?: "-",
            js_anchor($data->talent_status_title, array("style" => "background-color: $data->talent_status_color", "class" => "badge")),
            js_anchor("<i data-feather='x' class='icon-16'></i>", array("title" => app_lang("remove_from_project"), "class" => "delete", "data-id" => $data->talent_project_id, "data-action-url" => get_uri("talent_projects/unassign"), "data-action" => "delete-confirmation")),
        );
    }

    function modal_assign_form($project_id = 0) {
        $this->_can_access();
        validate_numeric_value($project_id);

        //the assign() action rejects duplicates, so the dropdown doesn't need to pre-filter already-assigned talent
        $view_data["project_id"] = $project_id;
        $view_data["talent_dropdown"] = $this->Talent_model->get_dropdown_list_with_blank_option(array("legal_name"), "- " . app_lang("select_talent") . " -");

        return $this->template->view('Talent_Management\Views\talent_projects\modal_assign_form', $view_data);
    }

    function assign() {
        $this->_can_access();

        $this->validate_submitted_data(array(
            "talent_id" => "required|numeric",
            "project_id" => "required|numeric",
        ));

        $talent_id = $this->request->getPost("talent_id");
        $project_id = $this->request->getPost("project_id");

        if ($this->Talent_project_model->is_already_assigned($talent_id, $project_id)) {
            echo json_encode(array("success" => false, "message" => app_lang("already_assigned")));
            return;
        }

        $data = array(
            "talent_id" => $talent_id,
            "project_id" => $project_id,
            "talent_status_id" => $this->Talent_status_model->get_first_status(),
            "created_at" => get_current_utc_time(),
        );

        if ($this->Talent_project_model->ci_save($data)) {
            echo json_encode(array("success" => true, "message" => app_lang("record_saved")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function kanban_data($project_id = 0) {
        $this->_can_access();
        validate_numeric_value($project_id);

        $view_data["project_id"] = $project_id;
        $view_data["assignments"] = $this->Talent_project_model->get_details_for_project($project_id)->getResult();

        $statuses = $this->Talent_status_model->get_details();
        $view_data["total_columns"] = $statuses->resultID->num_rows;
        $view_data["columns"] = $statuses->getResult();

        return $this->template->view('Talent_Management\Views\talent_projects\kanban_view', $view_data);
    }

    //drag-drop callback: updates a single casting link's sort position + status column
    function save_sort_and_status() {
        $this->_can_access();

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost("id");
        $talent_status_id = $this->request->getPost("talent_status_id");

        $data = array(
            "sort" => $this->request->getPost("sort")
        );

        if ($talent_status_id) {
            $data["talent_status_id"] = $talent_status_id;
        }

        $this->Talent_project_model->ci_save($data, $id);
    }

    function unassign() {
        $this->_can_access();

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost("id");

        if ($this->Talent_project_model->delete($id)) {
            echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
        }
    }

    function list_for_talent($talent_id = 0) {
        $this->_can_access();
        validate_numeric_value($talent_id);

        $list_data = $this->Talent_project_model->get_details_for_talent($talent_id)->getResult();

        $result_data = array();
        foreach ($list_data as $data) {
            $result_data[] = array(
                anchor(get_uri("projects/view/" . $data->project_id), $data->project_title),
                js_anchor($data->talent_status_title, array("style" => "background-color: $data->talent_status_color", "class" => "badge")),
                js_anchor("<i data-feather='x' class='icon-16'></i>", array("title" => app_lang("remove_from_project"), "class" => "delete", "data-id" => $data->talent_project_id, "data-action-url" => get_uri("talent_projects/unassign"), "data-action" => "delete-confirmation")),
            );
        }

        echo json_encode(array("data" => $result_data));
    }
}
