<?php

namespace Talent_Management\Controllers;

use App\Controllers\Security_Controller;
use Talent_Management\Libraries\Talent_contract_service;
use Talent_Management\Models\Talent_contract_template_model;
use Talent_Management\Models\Talent_project_model;

//staff side of contract sending; the recipient's signing page is Talent_sign
class Talent_contracts extends Security_Controller {

    private $Talent_contract_service;
    private $Talent_contract_template_model;
    private $Talent_project_model;

    function __construct() {
        parent::__construct();

        if (!talent_can_access_staff()) {
            app_redirect("forbidden");
        }

        talent_ensure_schema_once();
        $this->Talent_contract_service = new Talent_contract_service();
        $this->Talent_contract_template_model = new Talent_contract_template_model();
        $this->Talent_project_model = new Talent_project_model();
    }

    //who is doing this, for the audit trail
    private function _actor() {
        return array(
            "type" => "staff",
            "id" => $this->login_user->id,
            "ip" => $this->request->getIPAddress(),
            "user_agent" => $this->request->getUserAgent()->getAgentString(),
        );
    }

    function send_modal_form() {
        $this->validate_submitted_data(array(
            "talent_project_id" => "required|numeric"
        ));

        $talent_project_id = $this->request->getPost("talent_project_id");

        $context = $this->Talent_project_model->get_context($talent_project_id);
        if (!$context) {
            show_404();
        }

        $templates_dropdown = array("" => "- " . app_lang("talent_contract_select_template") . " -");
        foreach ($this->Talent_contract_template_model->get_details()->getResult() as $template) {
            $templates_dropdown[$template->id] = $template->title;
        }

        //say why nothing can be sent instead of showing a form that will only fail
        $state = $this->Talent_contract_service->get_state($talent_project_id);
        $blocked_message = "";
        if ($state["status"] === "signed") {
            $blocked_message = app_lang("talent_contract_error_already_signed");
        } else if ($state["status"] === "sent") {
            $blocked_message = app_lang("talent_contract_error_pending");
        } else if (!filter_var($context->email, FILTER_VALIDATE_EMAIL)) {
            $blocked_message = app_lang("talent_contract_error_no_email");
        } else if (trim((string) $context->legal_name) === "") {
            $blocked_message = app_lang("talent_contract_error_no_legal_name");
        } else if (count($templates_dropdown) < 2) {
            $blocked_message = app_lang("talent_contract_error_no_templates");
        }

        $view_data["talent_project_id"] = $talent_project_id;
        $view_data["context"] = $context;
        $view_data["templates_dropdown"] = $templates_dropdown;
        $view_data["selected_template"] = count($templates_dropdown) === 2 ? array_keys($templates_dropdown)[1] : "";
        $view_data["blocked_message"] = $blocked_message;
        $view_data["moves_from_confirmed"] = $context->talent_status_key === "confirmed";
        $view_data["can_manage_templates"] = talent_can_manage_contract_templates();

        return $this->template->view('Talent_Management\Views\talent_contracts\send_modal_form', $view_data);
    }

    //a contract behind a row of the project's talent list: the text as sent, or as signed, with its state and activity
    function view_modal_form() {
        $this->validate_submitted_data(array(
            "contract_id" => "required|numeric"
        ));

        $view_data = $this->Talent_contract_service->get_contract_view($this->request->getPost("contract_id"));
        if (!$view_data) {
            show_404();
        }

        return $this->template->view('Talent_Management\Views\talent_contracts\view_modal_form', $view_data);
    }

    //the signed PDF, on the staff member's own login
    function download($contract_id = 0) {
        validate_numeric_value($contract_id);

        $pdf = $this->Talent_contract_service->get_signed_pdf_for_staff($contract_id, $this->_actor());
        if (!$pdf) {
            show_404();
        }

        $this->response->setHeader("Content-Type", "application/pdf");
        $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $pdf["file_name"] . '"');
        $this->response->setHeader("Cache-Control", "no-store, max-age=0");
        $this->response->setBody($pdf["bytes"]);
        return $this->response;
    }

    //the merged contract text, so nothing goes out unread
    function preview() {
        $this->validate_submitted_data(array(
            "talent_project_id" => "required|numeric",
            "template_id" => "required|numeric"
        ));

        $html = $this->Talent_contract_service->preview($this->request->getPost("template_id"), $this->request->getPost("talent_project_id"), $this->request->getPost("notes"));

        if ($html === null) {
            echo json_encode(array("success" => false, "message" => app_lang("talent_contract_error_template")));
        } else {
            echo json_encode(array("success" => true, "html" => $html));
        }
    }

    function send() {
        $this->validate_submitted_data(array(
            "talent_project_id" => "required|numeric",
            "template_id" => "required|numeric"
        ));

        //the notes are plain text and are escaped where they're merged, so they aren't run through clean_data (that would double-encode)
        $result = $this->Talent_contract_service->issue(
                $this->request->getPost("talent_project_id"), $this->request->getPost("template_id"), (string) $this->request->getPost("notes"), $this->_actor()
        );

        echo json_encode($result);
    }
}
