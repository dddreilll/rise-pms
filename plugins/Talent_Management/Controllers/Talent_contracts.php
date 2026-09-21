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
        return talent_staff_actor($this->login_user, $this->request);
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

        //the agreements that can go out now: everything that isn't already waiting or signed for this casting link, required ones first
        $sendable = $this->Talent_contract_service->get_sendable_templates($talent_project_id);
        $templates_dropdown = array("" => "- " . app_lang("talent_contract_select_template") . " -");
        $moves_back_ids = array();
        foreach ($sendable as $template) {
            $templates_dropdown[$template["id"]] = $template["title"] . ($template["required"] ? " (" . app_lang("talent_contract_required") . ")" : "");

            //sending a listed agreement to someone who is confirmed takes them back to Contract Signing: the form says so beforehand
            if ($context->talent_status_key === "confirmed" && $this->Talent_contract_service->get_stage_for_send($talent_project_id, $template["id"])) {
                $moves_back_ids[] = $template["id"];
            }
        }

        //say why nothing can be sent instead of showing a form that will only fail
        $blocked_message = "";
        if (!filter_var($context->email, FILTER_VALIDATE_EMAIL)) {
            $blocked_message = app_lang("talent_contract_error_no_email");
        } else if (trim((string) $context->legal_name) === "") {
            $blocked_message = app_lang("talent_contract_error_no_legal_name");
        } else if (!$sendable) {
            $blocked_message = count($this->Talent_contract_template_model->get_details()->getResult()) ? app_lang("talent_contract_error_all_sent") : app_lang("talent_contract_error_no_templates");
        }

        $selected_template = $this->request->getPost("template_id");
        if (!$selected_template || !isset($templates_dropdown[$selected_template])) {
            $selected_template = count($sendable) === 1 ? $sendable[0]["id"] : "";
        }

        $view_data["talent_project_id"] = $talent_project_id;
        $view_data["context"] = $context;
        $view_data["templates_dropdown"] = $templates_dropdown;
        $view_data["selected_template"] = $selected_template;
        $view_data["blocked_message"] = $blocked_message;
        $view_data["moves_back_ids"] = $moves_back_ids;
        $view_data["can_manage_templates"] = talent_can_manage_contract_templates();
        $view_data["show_paper_link"] = true;

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

        //withdrawing a signed contract is a legal step, so only an admin gets the button (the service enforces it too)
        $view_data["can_void_signed"] = $this->login_user->is_admin ? true : false;

        return $this->template->view('Talent_Management\Views\talent_contracts\view_modal_form', $view_data);
    }

    //the emailed link was lost or never arrived: a new one goes out and the old one stops working
    function resend() {
        $this->validate_submitted_data(array(
            "contract_id" => "required|numeric"
        ));

        echo json_encode($this->Talent_contract_service->resend($this->request->getPost("contract_id"), $this->_actor()));
    }

    //withdraws a contract; a signed one takes an admin and a reason
    function void() {
        $this->validate_submitted_data(array(
            "contract_id" => "required|numeric"
        ));

        $result = $this->Talent_contract_service->void(
                $this->request->getPost("contract_id"), (string) $this->request->getPost("reason"), $this->_actor(), $this->login_user->is_admin ? true : false
        );

        echo json_encode($result);
    }

    //for a contract the talent signed on paper: the scan goes in here and counts as the signature of the agreement chosen
    function paper_modal_form() {
        $this->validate_submitted_data(array(
            "talent_project_id" => "required|numeric"
        ));

        $talent_project_id = $this->request->getPost("talent_project_id");

        $context = $this->Talent_project_model->get_context($talent_project_id);
        if (!$context) {
            show_404();
        }

        //every agreement that isn't signed yet for this casting link, required ones first; a waiting one is withdrawn by its paper copy
        $states = array();
        foreach ($this->Talent_contract_service->get_agreement_states($talent_project_id) as $state) {
            $states[$state["template_id"]] = $state;
        }

        $withdraws_ids = array();
        $required_first = array();
        $others = array();
        foreach ($this->Talent_contract_template_model->get_details()->getResult() as $template) {
            $state = isset($states[(int) $template->id]) ? $states[(int) $template->id] : null;
            if ($state && $state["status"] === "signed") {
                continue;
            }
            if ($state && $state["status"] === "sent") {
                $withdraws_ids[] = (int) $template->id;
            }

            $is_required = $state && $state["required"];
            $entry = array("id" => (int) $template->id, "title" => $template->title . ($is_required ? " (" . app_lang("talent_contract_required") . ")" : ""));
            if ($is_required) {
                $required_first[] = $entry;
            } else {
                $others[] = $entry;
            }
        }

        $options = array("" => "- " . app_lang("talent_contract_select_template") . " -");
        foreach (array_merge($required_first, $others) as $entry) {
            $options[$entry["id"]] = $entry["title"];
        }

        $blocked_message = "";
        if (trim((string) $context->legal_name) === "") {
            $blocked_message = app_lang("talent_contract_error_no_legal_name");
        } else if (count($options) < 2) {
            $blocked_message = app_lang("talent_contract_error_all_signed");
        }

        $selected_template = $this->request->getPost("template_id");
        if (!$selected_template || !isset($options[$selected_template])) {
            $selected_template = count($options) === 2 ? array_keys($options)[1] : "";
        }

        $view_data["talent_project_id"] = $talent_project_id;
        $view_data["context"] = $context;
        $view_data["blocked_message"] = $blocked_message;
        $view_data["templates_dropdown"] = $options;
        $view_data["selected_template"] = $selected_template;
        $view_data["withdraws_ids"] = $withdraws_ids;
        $view_data["today"] = get_my_local_time("Y-m-d");
        $view_data["max_mb"] = talent_contract_paper_max_mb();

        return $this->template->view('Talent_Management\Views\talent_contracts\paper_modal_form', $view_data);
    }

    function save_paper() {
        $this->validate_submitted_data(array(
            "talent_project_id" => "required|numeric",
            "template_id" => "required|numeric",
            "signed_on" => "required"
        ));

        $file = $this->request->getFile("scan");
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            echo json_encode(array("success" => false, "message" => app_lang("talent_contract_error_paper_missing")));
            return;
        }
        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            echo json_encode(array("success" => false, "message" => sprintf(app_lang("talent_contract_error_paper_too_large"), talent_contract_paper_max_mb())));
            return;
        }
        if (!$file->isValid()) {
            echo json_encode(array("success" => false, "message" => app_lang("talent_contract_error_paper_missing")));
            return;
        }

        //the title and note are plain text; the service cleans them and everything is escaped where it is shown
        $result = $this->Talent_contract_service->record_paper_copy(
                $this->request->getPost("talent_project_id"), $this->request->getPost("template_id"), (string) $this->request->getPost("title"), (string) $this->request->getPost("signed_on"), (string) $this->request->getPost("note"), array("path" => $file->getTempName(), "name" => $file->getClientName()), $this->_actor()
        );

        echo json_encode($result);
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
        $this->response->setHeader("X-Content-Type-Options", "nosniff");
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
