<?php

namespace Talent_Management\Controllers;

use App\Controllers\Security_Controller;
use Talent_Management\Models\Talent_contract_template_model;

class Talent_contract_templates extends Security_Controller {

    private $Talent_contract_template_model;

    function __construct() {
        parent::__construct();

        if (!talent_can_manage_contract_templates()) {
            app_redirect("forbidden");
        }

        talent_ensure_schema_once();
        $this->Talent_contract_template_model = new Talent_contract_template_model();
    }

    function index() {
        return $this->template->rander('Talent_Management\Views\talent_contract_templates\index');
    }

    //add/rename modal: the title only, the wording is edited in the side panel (form/save_content)
    function modal_form() {
        $this->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $view_data["model_info"] = $this->Talent_contract_template_model->get_one($this->request->getPost("id"));
        return $this->template->view('Talent_Management\Views\talent_contract_templates\modal_form', $view_data);
    }

    function save() {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "title" => "required"
        ));

        $id = $this->request->getPost("id");

        $data = array("title" => clean_data($this->request->getPost("title")));

        //a new template starts from a structural scaffold so the merge fields are visible straight away
        if (!$id) {
            $data["content"] = talent_contract_default_template();
            $data["created_by"] = $this->login_user->id;
            $data["created_at"] = get_current_utc_time();
        }

        $save_id = $this->Talent_contract_template_model->ci_save($data, $id);
        if ($save_id) {
            echo json_encode(array("success" => true, "data" => $this->_row_data($save_id), "id" => $save_id, "message" => app_lang("record_saved")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    //the editor panel for one template
    function form($id = 0) {
        validate_numeric_value($id);

        $model_info = $this->Talent_contract_template_model->get_one($id);
        if (!$model_info->id || $model_info->deleted) {
            show_404();
        }

        $view_data["model_info"] = $model_info;
        return $this->template->view('Talent_Management\Views\talent_contract_templates\form', $view_data);
    }

    //the contract text is admin-authored HTML, stored the way core stores its contract templates (not run through clean_data)
    function save_content() {
        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost("id");

        $model_info = $this->Talent_contract_template_model->get_one($id);
        if (!$model_info->id || $model_info->deleted) {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            return;
        }

        $data = array("content" => talent_encode_4byte_chars(decode_ajax_post_data($this->request->getPost("content"))));

        if ($this->Talent_contract_template_model->ci_save($data, $id)) {
            echo json_encode(array("success" => true, "id" => $id, "message" => app_lang("record_saved")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function delete() {
        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost("id");

        //sent contracts keep their own frozen copy of the text, so a template is always safe to delete
        if ($this->request->getPost("undo")) {
            if ($this->Talent_contract_template_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Talent_contract_template_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    function list_data() {
        $list_data = $this->Talent_contract_template_model->get_details()->getResult();

        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        echo json_encode(array("data" => $result));
    }

    private function _row_data($id) {
        $data = $this->Talent_contract_template_model->get_details(array("id" => $id))->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data) {
        $edit = modal_anchor(get_uri("talent_contract_templates/modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang("edit_talent_contract_template"), "data-post-id" => $data->id));
        $delete = js_anchor("<i data-feather='x' class='icon-16'></i>", array("title" => app_lang("delete_talent_contract_template"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("talent_contract_templates/delete"), "data-action" => "delete"));

        return array(
            "<a href='#' data-id='$data->id' class='talent-contract-template-row link'>" . $data->title . "</a>",
            $edit . $delete
        );
    }
}
