<?php

namespace Agreements\Controllers;

use App\Controllers\Security_Controller;
use Agreements\Models\Agreements_Documents_Model;
use Agreements\Models\Agreements_Signatories_Model;
use Agreements\Models\Agreements_Recipients_Model;
use Agreements\Models\Agreements_Audit_Logs_Model;
use Agreements\Models\Agreements_Templates_Model;

class Agreements extends Security_Controller {

    private $Documents_model;
    private $Signatories_model;
    private $Recipients_model;
    private $Audit_model;
    private $Templates_model;

    function __construct() {
        parent::__construct();
        $this->Documents_model = new Agreements_Documents_Model();
        $this->Signatories_model = new Agreements_Signatories_Model();
        $this->Recipients_model = new Agreements_Recipients_Model();
        $this->Audit_model = new Agreements_Audit_Logs_Model();
        $this->Templates_model = new Agreements_Templates_Model();
    }

    private function _can_access() {
        if (!agreements_can_access()) {
            app_redirect("forbidden");
        }
    }

    private function _can_manage() {
        return $this->login_user->user_type === "staff" && agreements_can_access_staff();
    }

    private function _get_document_or_forbidden($id) {
        $doc = $this->Documents_model->get_one($id);
        if (!$doc || !$doc->id || $doc->deleted) {
            app_redirect("forbidden");
        }
        if ($this->login_user->user_type === "client") {
            $allowed = $this->Documents_model->get_details(array(
                "id" => $id,
                "signatory_user_id" => $this->login_user->id,
            ))->getRow();
            if (!$allowed) {
                $allowed = $this->Documents_model->get_details(array(
                    "id" => $id,
                    "signatory_email" => $this->login_user->email,
                ))->getRow();
            }
            if (!$allowed) {
                app_redirect("forbidden");
            }
        } else if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        return $doc;
    }

    function index() {
        $this->_can_access();
        $view_data["can_manage"] = $this->_can_manage();
        return $this->template->rander('Agreements\Views\agreements\index', $view_data);
    }

    function list_data() {
        $this->_can_access();
        $options = array();
        if ($this->login_user->user_type === "client") {
            $options["signatory_user_id"] = $this->login_user->id;
            $options["exclude_draft"] = true;
        }

        $list_data = $this->Documents_model->get_details($options)->getResult();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        echo json_encode(array("data" => $result));
    }

    private function _make_row($data) {
        $title = anchor(get_uri("agreements/view/" . $data->id), $data->title);
        $status = agreements_status_label($data->status);
        $options = anchor(get_uri("agreements/view/" . $data->id), "<i data-feather='eye' class='icon-16'></i>", array("class" => "edit", "title" => app_lang("agreements_view")));

        if ($this->_can_manage()) {
            $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", array('title' => app_lang('agreements_delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("agreements/delete"), "data-action" => "delete-confirmation"));
        }

        return array(
            $title,
            $data->client_name ? $data->client_name : "-",
            agreements_status_label($data->status),
            $data->signing_mode,
            $data->created_by_user,
            format_to_datetime($data->created_at),
            $options,
        );
    }

    function modal_form() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array("id" => "numeric", "template_id" => "numeric"));
        $id = $this->request->getPost("id");
        $template_id = $this->request->getPost("template_id");

        $view_data["model_info"] = $this->Documents_model->get_one($id);
        $view_data["clients_dropdown"] = $this->get_clients_and_leads_dropdown();
        $view_data["projects_dropdown"] = $this->_get_projects_dropdown();
        $view_data["templates_dropdown"] = $this->_templates_dropdown();
        $view_data["merge_keys"] = agreements_available_merge_keys();

        // Optional: create from a specific template (e.g. Templates list "use template")
        if (!$id && $template_id) {
            $template = $this->Templates_model->get_one($template_id);
            if ($template && $template->id) {
                $view_data["model_info"]->title = $template->title;
                $view_data["model_info"]->document_type = $template->document_type;
                $view_data["model_info"]->content = $template->content;
                $view_data["model_info"]->pdf_path = $template->pdf_path;
                $view_data["model_info"]->template_id = $template->id;
            }
        }

        return $this->template->view('Agreements\Views\agreements\modal_form', $view_data);
    }

    private function _templates_dropdown() {
        $rows = $this->Templates_model->get_details()->getResult();
        $dropdown = array();
        foreach ($rows as $row) {
            $dropdown[$row->id] = $row->title;
        }
        return $dropdown;
    }

    function save() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "title" => "required",
        ));

        $id = $this->request->getPost("id");
        $signing_mode = $this->request->getPost("signing_mode") === "sequential" ? "sequential" : "parallel";

        $data = array(
            "title" => $this->request->getPost("title"),
            "signing_mode" => $signing_mode,
            "client_id" => $this->request->getPost("client_id") ? $this->request->getPost("client_id") : 0,
            "project_id" => $this->request->getPost("project_id") ? $this->request->getPost("project_id") : 0,
            "expires_at" => $this->request->getPost("expires_at") ? $this->request->getPost("expires_at") : null,
            "reminder_days" => $this->request->getPost("reminder_days") !== "" ? (int) $this->request->getPost("reminder_days") : 3,
            "updated_at" => get_current_utc_time(),
        );

        if (!$id) {
            $data["status"] = "draft";
            $data["created_by"] = $this->login_user->id;
            $data["created_at"] = get_current_utc_time();
            $data["version"] = 1;
            $data["document_type"] = "html";
            $data["content"] = "";
            $data["template_id"] = 0;

            // Same as Contracts: silently apply default template content when configured
            $default_template_id = (int) agreements_get_setting("default_template_id");
            if (!$default_template_id) {
                $default_template_id = (int) agreements_ensure_default_template();
            }
            // Prefer explicit template from "Use template" flow if posted
            $posted_template_id = (int) $this->request->getPost("template_id");
            $template_id = $posted_template_id ? $posted_template_id : $default_template_id;
            if ($template_id) {
                $template = $this->Templates_model->get_one($template_id);
                if ($template && $template->id && !$template->deleted) {
                    $data["template_id"] = $template->id;
                    $data["document_type"] = $template->document_type === "pdf" ? "pdf" : "html";
                    $data["content"] = $template->content;
                    if ($template->document_type === "pdf" && $template->pdf_path) {
                        $data["pdf_path"] = $template->pdf_path;
                    }
                }
            }
        } else {
            $existing = $this->_get_document_or_forbidden($id);
            if (in_array($existing->status, array("completed", "cancelled"))) {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
                return;
            }
            $document_type = $this->request->getPost("document_type") === "pdf" ? "pdf" : "html";
            $data["document_type"] = $document_type;
            if ($this->request->getPost("content") !== null) {
                $data["content"] = $this->request->getPost("content");
            }

            $pdf_file = $this->request->getFile("pdf_file");
            if ($document_type === "pdf" && $pdf_file && $pdf_file->isValid() && !$pdf_file->hasMoved()) {
                $new_name = "pdf_" . uniqid() . ".pdf";
                $pdf_file->move(agreements_files_path("pdfs"), $new_name);
                $data["pdf_path"] = "pdfs/" . $new_name;
            }
        }

        $save_id = agreements_ci_save($this->Documents_model, $data, $id);

        if ($save_id) {
            agreements_audit($save_id, $id ? "updated" : "created", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
            echo json_encode(array("success" => true, "id" => $save_id, "message" => app_lang("agreements_saved"), "redirect_url" => get_uri("agreements/view/" . $save_id)));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function delete() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $this->_get_document_or_forbidden($id);
        if ($this->Documents_model->delete($id)) {
            agreements_audit($id, "deleted", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
            echo json_encode(array("success" => true, "message" => app_lang("agreements_deleted")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function view($id = 0) {
        $this->_can_access();
        validate_numeric_value($id);
        $doc = $this->_get_document_or_forbidden($id);

        // Enrich with joined fields when available
        $details = $this->Documents_model->get_details(array("id" => $id))->getRow();
        if ($details) {
            $doc = $details;
        }

        $client_name = "";
        if (!empty($doc->client_id)) {
            $client_name = !empty($doc->client_name) ? $doc->client_name : "";
            if (!$client_name) {
                $Clients_model = model("App\Models\Clients_model");
                $client = $Clients_model->get_one($doc->client_id);
                $client_name = $client && $client->id ? $client->company_name : "";
            }
        }

        $project_title = "";
        if (!empty($doc->project_id)) {
            $Projects_model = model("App\Models\Projects_model");
            $project = $Projects_model->get_one($doc->project_id);
            $project_title = $project && $project->id ? $project->title : "";
        }

        $view_data["document"] = $doc;
        $view_data["can_manage"] = $this->_can_manage();
        $view_data["signatories"] = $this->Signatories_model->get_details(array("document_id" => $id))->getResult();
        $view_data["recipients"] = $this->Recipients_model->get_all_where(array("document_id" => $id, "deleted" => 0))->getResult();
        $view_data["audit_logs"] = $this->Audit_model->get_details(array("document_id" => $id))->getResult();
        $view_data["merge_keys"] = agreements_available_merge_keys();
        $view_data["staff_dropdown"] = $this->_staff_dropdown();
        $view_data["pdf_url"] = $doc->pdf_path ? agreements_files_url($doc->pdf_path) : "";
        $view_data["client_name"] = $client_name;
        $view_data["project_title"] = $project_title;
        $view_data["status_label"] = agreements_status_label($doc->status, true);

        return $this->template->rander('Agreements\Views\agreements\view', $view_data);
    }

    function editor($id = 0) {
        $this->_can_access();
        validate_numeric_value($id);
        $doc = $this->_get_document_or_forbidden($id);
        $view_data["document"] = $doc;
        $view_data["can_manage"] = $this->_can_manage();
        $view_data["merge_keys"] = agreements_available_merge_keys();
        $view_data["pdf_url"] = $doc->pdf_path ? agreements_files_url($doc->pdf_path) : "";
        return $this->template->view('Agreements\Views\agreements\editor', $view_data);
    }

    function preview($id = 0) {
        $this->_can_access();
        validate_numeric_value($id);
        $doc = $this->_get_document_or_forbidden($id);
        $view_data["document"] = $doc;
        $view_data["pdf_url"] = $doc->pdf_path ? agreements_files_url($doc->pdf_path) : "";
        $view_data["preview_html"] = $doc->document_type === "html" ? agreements_apply_merge_to_html($doc->content, $doc) : "";
        return $this->template->view('Agreements\Views\agreements\preview', $view_data);
    }

    function audit($id = 0) {
        $this->_can_access();
        validate_numeric_value($id);
        $this->_get_document_or_forbidden($id);
        $view_data["audit_logs"] = $this->Audit_model->get_details(array("document_id" => $id))->getResult();
        return $this->template->view('Agreements\Views\agreements\audit', $view_data);
    }

    function save_content() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $doc = $this->_get_document_or_forbidden($id);
        if ($doc->document_type !== "html") {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            return;
        }
        if (in_array($doc->status, array("completed", "cancelled"))) {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            return;
        }

        $content = $this->request->getPost("content");
        agreements_ci_save($this->Documents_model, array(
            "content" => $content,
            "updated_at" => get_current_utc_time(),
        ), $id);
        agreements_audit($id, "content_updated", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
        echo json_encode(array("success" => true, "message" => app_lang("agreements_saved")));
    }

    private function _staff_dropdown() {
        $Users_model = model("App\Models\Users_model");
        $users = $Users_model->get_all_where(array("deleted" => 0, "user_type" => "staff", "status" => "active"))->getResult();
        $dropdown = array("" => "-");
        foreach ($users as $u) {
            $dropdown[$u->id] = $u->first_name . " " . $u->last_name . " (" . $u->email . ")";
        }
        return $dropdown;
    }

    function save_signatories() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }

        $document_id = $this->request->getPost("document_id");
        validate_numeric_value($document_id);
        $doc = $this->_get_document_or_forbidden($document_id);

        if (!in_array($doc->status, array("draft", "sent", "partially_signed"))) {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            return;
        }

        $types = $this->request->getPost("signatory_type");
        $names = $this->request->getPost("signatory_name");
        $emails = $this->request->getPost("signatory_email");
        $orders = $this->request->getPost("signatory_order");
        $user_ids = $this->request->getPost("signatory_user_id");

        if (!is_array($emails) || !count($emails)) {
            echo json_encode(array("success" => false, "message" => app_lang("agreements_at_least_one_signatory")));
            return;
        }

        // Soft-delete unsigned existing, then re-add from form (keep signed rows)
        $existing = $this->Signatories_model->get_all_where(array("document_id" => $document_id, "deleted" => 0))->getResult();
        $signed_emails = array();
        foreach ($existing as $ex) {
            if ($ex->status === "signed") {
                $signed_emails[strtolower($ex->email)] = true;
                continue;
            }
            agreements_ci_save($this->Signatories_model, array("deleted" => 1), $ex->id);
        }

        $count = count($emails);
        for ($i = 0; $i < $count; $i++) {
            $email = trim($emails[$i]);
            if (!$email) {
                continue;
            }
            if (isset($signed_emails[strtolower($email)])) {
                continue;
            }
            $type = isset($types[$i]) ? $types[$i] : "external";
            if (!in_array($type, array("staff", "client", "external"))) {
                $type = "external";
            }
            $user_id = isset($user_ids[$i]) ? (int) $user_ids[$i] : 0;
            $name = isset($names[$i]) ? $names[$i] : $email;
            if ($type === "staff" && $user_id) {
                $Users_model = model("App\Models\Users_model");
                $user = $Users_model->get_one($user_id);
                if ($user && $user->id) {
                    $name = $user->first_name . " " . $user->last_name;
                    $email = $user->email;
                }
            }
            agreements_ci_save($this->Signatories_model, array(
                "document_id" => $document_id,
                "type" => $type,
                "user_id" => $user_id,
                "name" => $name,
                "email" => $email,
                "signing_order" => isset($orders[$i]) ? (int) $orders[$i] : ($i + 1),
                "status" => "pending",
                "deleted" => 0,
            ));
        }

        agreements_audit($document_id, "signatories_updated", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
        echo json_encode(array("success" => true, "message" => app_lang("record_saved")));
    }

    function save_recipients() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $document_id = $this->request->getPost("document_id");
        validate_numeric_value($document_id);
        $this->_get_document_or_forbidden($document_id);

        $existing = $this->Recipients_model->get_all_where(array("document_id" => $document_id, "deleted" => 0))->getResult();
        foreach ($existing as $ex) {
            agreements_ci_save($this->Recipients_model, array("deleted" => 1), $ex->id);
        }

        $emails = $this->request->getPost("recipient_email");
        $names = $this->request->getPost("recipient_name");
        if (is_array($emails)) {
            foreach ($emails as $i => $email) {
                $email = trim($email);
                if (!$email) {
                    continue;
                }
                agreements_ci_save($this->Recipients_model, array(
                    "document_id" => $document_id,
                    "email" => $email,
                    "name" => isset($names[$i]) ? $names[$i] : "",
                    "role" => "custom",
                    "deleted" => 0,
                ));
            }
        }

        agreements_audit($document_id, "recipients_updated", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
        echo json_encode(array("success" => true, "message" => app_lang("record_saved")));
    }

    function send() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $doc = $this->_get_document_or_forbidden($id);

        $signatories = $this->Signatories_model->get_details(array("document_id" => $id))->getResult();
        if (!$signatories || !count($signatories)) {
            echo json_encode(array("success" => false, "message" => app_lang("agreements_at_least_one_signatory")));
            return;
        }

        agreements_ci_save($this->Documents_model, array(
            "status" => "sent",
            "updated_at" => get_current_utc_time(),
        ), $id);

        foreach ($signatories as $s) {
            if ($s->status === "signed") {
                continue;
            }
            if ($doc->signing_mode === "sequential" && (int) $s->signing_order > 1) {
                // only notify first order initially
                $min_order = null;
                foreach ($signatories as $x) {
                    if ($x->status !== "signed") {
                        if ($min_order === null || $x->signing_order < $min_order) {
                            $min_order = $x->signing_order;
                        }
                    }
                }
                if ($s->signing_order != $min_order) {
                    continue;
                }
            }
            $token = agreements_generate_token();
            agreements_ci_save($this->Signatories_model, array(
                "token_hash" => agreements_hash_token($token),
                "status" => "notified",
            ), $s->id);
            agreements_send_invite_email($doc, $s, $token);
        }

        agreements_audit($id, "sent", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
        echo json_encode(array("success" => true, "message" => app_lang("agreements_sent_message")));
    }

    function resend() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $doc = $this->_get_document_or_forbidden($id);

        $signatories = $this->Signatories_model->get_details(array("document_id" => $id))->getResult();
        foreach ($signatories as $s) {
            if (!in_array($s->status, array("pending", "notified"))) {
                continue;
            }
            if ($doc->signing_mode === "sequential") {
                $next = agreements_next_sequential_signatory($id);
                if (!$next || $next->id != $s->id) {
                    continue;
                }
            }
            $token = agreements_generate_token();
            agreements_ci_save($this->Signatories_model, array(
                "token_hash" => agreements_hash_token($token),
                "status" => "notified",
            ), $s->id);
            agreements_send_invite_email($doc, $s, $token);
        }

        agreements_audit($id, "resent", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
        echo json_encode(array("success" => true, "message" => app_lang("agreements_sent_message")));
    }

    function cancel() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $this->_get_document_or_forbidden($id);
        agreements_ci_save($this->Documents_model, array("status" => "cancelled", "updated_at" => get_current_utc_time()), $id);
        agreements_audit($id, "cancelled", $this->login_user->email, $this->login_user->first_name . " " . $this->login_user->last_name);
        echo json_encode(array("success" => true, "message" => app_lang("agreements_cancelled_message")));
    }

    function amend() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $this->_get_document_or_forbidden($id);
        $new_id = agreements_clone_document($id, "amend");
        echo json_encode(array("success" => true, "id" => $new_id, "message" => app_lang("agreements_amended_message"), "redirect_url" => get_uri("agreements/view/" . $new_id)));
    }

    function renew() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $this->_get_document_or_forbidden($id);
        $new_id = agreements_clone_document($id, "renew");
        echo json_encode(array("success" => true, "id" => $new_id, "message" => app_lang("agreements_renewed_message"), "redirect_url" => get_uri("agreements/view/" . $new_id)));
    }

    function save_as_template() {
        $this->_can_access();
        if (!$this->_can_manage()) {
            app_redirect("forbidden");
        }
        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");
        $doc = $this->_get_document_or_forbidden($id);
        $template_id = agreements_ci_save($this->Templates_model, array(
            "title" => $doc->title . " Template",
            "document_type" => $doc->document_type,
            "content" => $doc->content,
            "pdf_path" => $doc->pdf_path,
            "fields_json" => "[]",
            "created_by" => $this->login_user->id,
            "created_at" => get_current_utc_time(),
            "deleted" => 0,
        ));
        echo json_encode(array("success" => true, "id" => $template_id, "message" => app_lang("record_saved")));
    }

    function download_signed($id = 0) {
        $this->_can_access();
        validate_numeric_value($id);
        $doc = $this->_get_document_or_forbidden($id);
        if (!$doc->final_pdf_path) {
            show_404();
        }
        $path = agreements_files_path() . $doc->final_pdf_path;
        if (!is_file($path)) {
            show_404();
        }
        return $this->response->download($path, null)->setFileName(basename($path));
    }

}
