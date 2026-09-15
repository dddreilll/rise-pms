<?php

namespace Agreements\Controllers;

use App\Controllers\App_Controller;
use Agreements\Models\Agreements_Documents_Model;
use Agreements\Models\Agreements_Signatories_Model;

class Agreements_Sign extends App_Controller {

    private $Documents_model;
    private $Signatories_model;

    function __construct() {
        parent::__construct();
        $this->Documents_model = new Agreements_Documents_Model();
        $this->Signatories_model = new Agreements_Signatories_Model();
    }

    function index($token = "") {
        $signatory = $this->Signatories_model->get_by_token($token);
        if (!$signatory) {
            return view('Agreements\Views\sign\invalid', array("message" => app_lang("agreements_invalid_link")));
        }

        $document = $this->Documents_model->get_one($signatory->document_id);
        if (!$document || !$document->id || $document->deleted) {
            return view('Agreements\Views\sign\invalid', array("message" => app_lang("agreements_invalid_link")));
        }

        if ($document->expires_at && $document->expires_at < get_current_utc_time() && $document->status !== "completed") {
            if ($document->status !== "expired") {
                agreements_ci_save($this->Documents_model, array("status" => "expired"), $document->id);
            }
            return view('Agreements\Views\sign\invalid', array("message" => app_lang("agreements_invalid_link")));
        }

        $can_act = agreements_can_signatory_act($document, $signatory);

        $view_data = array(
            "token" => $token,
            "document" => $document,
            "signatory" => $signatory,
            "can_act" => $can_act,
            "already_signed" => $signatory->status === "signed",
            "pdf_url" => $document->pdf_path ? agreements_files_url($document->pdf_path) : "",
            "content_html" => $document->document_type === "html" ? agreements_apply_merge_to_html($document->content, $document) : "",
        );

        agreements_audit($document->id, "opened", $signatory->email, $signatory->name);

        return view('Agreements\Views\sign\sign', $view_data);
    }

    function submit($token = "") {
        $signatory = $this->Signatories_model->get_by_token($token);
        if (!$signatory) {
            echo json_encode(array("success" => false, "message" => app_lang("agreements_invalid_link")));
            return;
        }
        $document = $this->Documents_model->get_one($signatory->document_id);
        if (!agreements_can_signatory_act($document, $signatory)) {
            $msg = $signatory->status === "signed" ? app_lang("agreements_already_signed") : app_lang("agreements_not_your_turn");
            echo json_encode(array("success" => false, "message" => $msg));
            return;
        }

        $signature_data = $this->request->getPost("signature_data");
        $signature_path = "";
        if ($signature_data) {
            $signature_path = agreements_save_signature_data_url($signature_data, "signer");
        }
        if (!$signature_path) {
            echo json_encode(array("success" => false, "message" => app_lang("agreements_your_signature")));
            return;
        }

        agreements_ci_save($this->Signatories_model, array(
            "status" => "signed",
            "signed_at" => get_current_utc_time(),
            "signature_path" => $signature_path,
        ), $signatory->id);

        agreements_audit($document->id, "signed", $signatory->email, $signatory->name);

        $completed = agreements_check_and_complete($document->id);

        if (!$completed && $document->signing_mode === "sequential") {
            $next = agreements_next_sequential_signatory($document->id);
            if ($next) {
                $new_token = agreements_generate_token();
                agreements_ci_save($this->Signatories_model, array(
                    "token_hash" => agreements_hash_token($new_token),
                    "status" => "notified",
                ), $next->id);
                $fresh_doc = $this->Documents_model->get_one($document->id);
                agreements_send_invite_email($fresh_doc, $next, $new_token);
            }
        }

        echo json_encode(array("success" => true, "message" => app_lang("agreements_signed_successfully"), "completed" => $completed));
    }

    function decline($token = "") {
        $signatory = $this->Signatories_model->get_by_token($token);
        if (!$signatory) {
            echo json_encode(array("success" => false, "message" => app_lang("agreements_invalid_link")));
            return;
        }
        $document = $this->Documents_model->get_one($signatory->document_id);
        if (!agreements_can_signatory_act($document, $signatory)) {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            return;
        }

        $reason = $this->request->getPost("decline_reason");
        agreements_ci_save($this->Signatories_model, array(
            "status" => "declined",
            "declined_at" => get_current_utc_time(),
            "decline_reason" => $reason,
        ), $signatory->id);

        agreements_ci_save($this->Documents_model, array(
            "status" => "declined",
            "decline_reason" => $reason,
            "updated_at" => get_current_utc_time(),
        ), $document->id);

        agreements_audit($document->id, "declined", $signatory->email, $signatory->name, array("reason" => $reason));
        echo json_encode(array("success" => true, "message" => app_lang("agreements_declined_successfully")));
    }
}
