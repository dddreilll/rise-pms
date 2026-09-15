<?php

namespace Agreements\Controllers;

use App\Controllers\Security_Controller;
use Agreements\Models\Agreements_Templates_Model;

class Agreements_Templates extends Security_Controller {

    private $Templates_model;

    function __construct() {
        parent::__construct();
        $this->Templates_model = new Agreements_Templates_Model();
    }

    private function _can_manage() {
        if (!agreements_can_access_staff()) {
            app_redirect("forbidden");
        }
    }

    function index() {
        $this->_can_manage();
        agreements_ensure_default_template();
        return $this->template->rander('Agreements\Views\templates\index');
    }

    function settings_list() {
        $this->access_only_admin_or_settings_admin();
        agreements_ensure_default_template();
        return $this->template->view('Agreements\Views\templates\settings_list');
    }

    function list_data() {
        $this->_can_manage();
        agreements_ensure_default_template();
        $default_id = (string) agreements_get_setting("default_template_id");
        $list_data = $this->Templates_model->get_details()->getResult();
        $result = array();
        foreach ($list_data as $data) {
            $options = modal_anchor(get_uri("agreements_templates/modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang("agreements_edit_template"), "data-post-id" => $data->id));
            if ((string) $data->id !== $default_id) {
                $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", array('title' => app_lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("agreements_templates/delete"), "data-action" => "delete-confirmation"));
            }
            $options .= modal_anchor(get_uri("agreements/modal_form"), "<i data-feather='plus' class='icon-16'></i>", array("class" => "edit", "title" => app_lang("agreements_use_template"), "data-post-template_id" => $data->id));
            $title = $data->title;
            if ((string) $data->id === $default_id) {
                $title .= " <span class='badge bg-primary'>" . app_lang("agreements_default_template") . "</span>";
            }
            $result[] = array(
                $title,
                $data->document_type,
                format_to_datetime($data->created_at),
                $options,
            );
        }
        echo json_encode(array("data" => $result));
    }

    function modal_form() {
        $this->_can_manage();
        $this->validate_submitted_data(array("id" => "numeric"));
        $id = $this->request->getPost("id");
        $view_data["model_info"] = $this->Templates_model->get_one($id);
        return $this->template->view('Agreements\Views\templates\modal_form', $view_data);
    }

    function save() {
        $this->_can_manage();
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "title" => "required",
        ));
        $id = $this->request->getPost("id");
        $document_type = $this->request->getPost("document_type") === "pdf" ? "pdf" : "html";
        $data = array(
            "title" => $this->request->getPost("title"),
            "document_type" => $document_type,
            "content" => $this->request->getPost("content"),
        );

        $pdf_file = $this->request->getFile("pdf_file");
        if ($document_type === "pdf" && $pdf_file && $pdf_file->isValid() && !$pdf_file->hasMoved()) {
            $new_name = "tpl_pdf_" . uniqid() . ".pdf";
            $pdf_file->move(agreements_files_path("pdfs"), $new_name);
            $data["pdf_path"] = "pdfs/" . $new_name;
        }

        if (!$id) {
            $data["created_by"] = $this->login_user->id;
            $data["created_at"] = get_current_utc_time();
            $data["deleted"] = 0;
        }

        $save_id = agreements_ci_save($this->Templates_model, $data, $id);
        if ($save_id) {
            echo json_encode(array("success" => true, "id" => $save_id, "message" => app_lang("record_saved")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function delete() {
        $this->_can_manage();
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        if ((string) $id === (string) agreements_get_setting("default_template_id")) {
            echo json_encode(array("success" => false, "message" => app_lang("agreements_cannot_delete_default_template")));
            return;
        }
        if ($this->Templates_model->delete($id)) {
            echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }
}
