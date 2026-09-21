<?php

namespace Talent_Management\Libraries;

use Talent_Management\Models\Talent_contract_event_model;
use Talent_Management\Models\Talent_contract_model;
use Talent_Management\Models\Talent_contract_template_model;
use Talent_Management\Models\Talent_project_model;
use Talent_Management\Models\Talent_status_model;

//the one place the contract workflow lives: the controllers stay thin, and this is the seam to lift out if signing ever has
//to serve something other than talent. It currently covers previewing and issuing a contract.
class Talent_contract_service {

    private $Talent_contract_model;
    private $Talent_contract_event_model;
    private $Talent_contract_template_model;
    private $Talent_project_model;
    private $Talent_status_model;

    function __construct() {
        $this->Talent_contract_model = new Talent_contract_model();
        $this->Talent_contract_event_model = new Talent_contract_event_model();
        $this->Talent_contract_template_model = new Talent_contract_template_model();
        $this->Talent_project_model = new Talent_project_model();
        $this->Talent_status_model = new Talent_status_model();
    }

    //the newest contract of a casting link and whether another one can be sent right now
    function get_state($talent_project_id) {
        $contract = $this->Talent_contract_model->get_latest_for_talent_project($talent_project_id);
        $status = $contract ? talent_contract_effective_status($contract->status, $contract->token_expires_at) : "";

        return array(
            "contract" => $contract,
            "status" => $status,
            "can_send" => !($status === "sent" || $status === "signed"),
        );
    }

    //what the staff member reads before sending; the signature fields show as blanks so the layout is clear. Null if it can't be built.
    function preview($template_id, $talent_project_id, $notes = "") {
        $context = $this->Talent_project_model->get_context($talent_project_id);
        $template = $this->Talent_contract_template_model->get_one($template_id);
        if (!$context || !$template->id || $template->deleted) {
            return null;
        }

        $values = $this->_merge_values($context, $template->title, $notes, get_current_utc_time());

        $blank = "<span style=\"color: #999;\">________</span>";
        $values["CONTRACT_ID"] = "TC-#####";
        foreach (array("SIGNER_NAME", "SIGNER_EMAIL", "SIGNING_DATE", "SIGNATURE") as $key) {
            $values[$key] = $blank;
        }

        return $this->_render($template->content, $values);
    }

    //Sends a contract for a casting link: freezes the text, creates the signing link, moves the card to Contract Signing and emails
    //the talent. Returns array(success, message, ...). $actor is array(type, id, ip, user_agent) for the audit trail.
    function issue($talent_project_id, $template_id, $notes, $actor) {
        $context = $this->Talent_project_model->get_context($talent_project_id);
        if (!$context) {
            return $this->_fail("talent_contract_error_assignment_missing");
        }
        if (!filter_var($context->email, FILTER_VALIDATE_EMAIL)) {
            return $this->_fail("talent_contract_error_no_email");
        }

        $template = $this->Talent_contract_template_model->get_one($template_id);
        if (!$template->id || $template->deleted || $this->_is_blank($template->content)) {
            return $this->_fail("talent_contract_error_template");
        }

        $signing_stage_id = get_array_value($this->Talent_status_model->get_system_stage_ids(), "contract_signing");
        if (!$signing_stage_id) {
            return $this->_fail("talent_contract_error_stage_missing");
        }

        //only a hash of the token is stored, so the link exists only in this response and the email
        $token = bin2hex(random_bytes(20));
        $now = get_current_utc_time();
        $expires_at = gmdate("Y-m-d H:i:s", strtotime($now . " UTC") + talent_contract_expiry_days() * 86400);
        $title = $template->title;

        $db = db_connect('default');
        $db->transBegin();

        try {
            //two people (or a double click) sending for the same casting link must not both get through
            $db->query("SELECT id FROM " . $db->prefixTable("talent_projects") . " WHERE id=" . (int) $talent_project_id . " FOR UPDATE");

            $current = $this->Talent_contract_model->get_latest_for_talent_project($talent_project_id);
            if ($current) {
                $current_status = talent_contract_effective_status($current->status, $current->token_expires_at);
                if ($current_status === "signed" || $current_status === "sent") {
                    $db->transRollback();
                    return $this->_fail($current_status === "signed" ? "talent_contract_error_already_signed" : "talent_contract_error_pending");
                }

                //a lapsed link is marked expired now so the history reads correctly
                if ($current->status === "sent") {
                    $expired = array("status" => "expired");
                    $this->_must($this->Talent_contract_model->ci_save($expired, $current->id), "expire the previous contract");
                    $this->_must($this->Talent_contract_event_model->log($current->id, "expired", array("type" => "system")), "log the expiry");
                }
            }

            $contract = array(
                "talent_project_id" => $talent_project_id,
                "template_id" => $template->id,
                "title" => $title,
                "content" => "",
                "token_hash" => hash("sha256", $token),
                "token_expires_at" => $expires_at,
                "status" => "sent",
                "sent_to_email" => $context->email,
                "sent_by" => (int) get_array_value($actor, "id"),
                "sent_at" => $now,
                "created_at" => $now,
            );
            $contract_id = $this->Talent_contract_model->ci_save($contract);
            $this->_must($contract_id, "create the contract");

            //frozen from here on: later template edits never touch a sent contract. The hash covers the exact string stored.
            $values = $this->_merge_values($context, $title, $notes, $now);
            $values["CONTRACT_ID"] = esc(talent_contract_label($contract_id));
            $frozen = talent_encode_4byte_chars($this->_render($template->content, $values));
            $content_hash = hash("sha256", $frozen);
            $frozen_data = array("content" => $frozen, "content_hash" => $content_hash);
            $this->_must($this->Talent_contract_model->ci_save($frozen_data, $contract_id), "freeze the contract text");

            $card = array("talent_status_id" => $signing_stage_id);
            $this->_must($this->Talent_project_model->ci_save($card, $talent_project_id), "move the card");

            $this->_must($this->Talent_contract_event_model->log($contract_id, "sent", $actor, array(
                        "to" => $context->email,
                        "template_id" => (int) $template->id,
                        "expires_at" => $expires_at,
                        "content_hash" => $content_hash,
                    )), "log the contract");

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        //after the commit on purpose: a slow mail server must not hold the lock, and a failed mail must not undo the contract
        $link = get_uri("talent_sign/" . $contract_id . "/" . $token);
        $emailed = $this->_send_email($context, $title, $link, $expires_at);
        $this->Talent_contract_event_model->log($contract_id, $emailed ? "email_sent" : "email_failed", $actor, array("to" => $context->email));

        return array(
            "success" => true,
            "message" => $emailed ? sprintf(app_lang("talent_contract_sent_message"), $context->email) : app_lang("talent_contract_email_failed_message"),
            "contract_id" => $contract_id,
            "emailed" => $emailed,
            "email" => $context->email,
            "link" => $link,
        );
    }

    private function _fail($language_key) {
        return array("success" => false, "message" => app_lang($language_key));
    }

    private function _must($result, $what) {
        if (!$result) {
            throw new \RuntimeException("Could not " . $what);
        }
    }

    //the editor saves an "empty" template as <p>&nbsp;</p>, so tags, entities and non-breaking spaces all count as blank
    private function _is_blank($html) {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, "UTF-8");
        return trim($text, " \t\n\r\0\x0B\xC2\xA0") === "";
    }

    //escaped for HTML; a missing value becomes an empty string
    private function _e($value) {
        return esc((string) $value);
    }

    //the default company's details, the same way core's contracts pick them
    private function _company() {
        $Company_model = model("App\Models\Company_model");
        return $Company_model->get_one_where(array("is_default" => true, "deleted" => 0));
    }

    //projects without a date hold an empty value or 0000-00-00, which core's formatter would happily print
    private function _date($value, $convert_to_local) {
        return is_date_exists($value) ? format_to_date($value, $convert_to_local) : "";
    }

    //merge-field values, already HTML-escaped. The signature fields are left out on purpose: they belong to the signed copy.
    private function _merge_values($context, $title, $notes, $date_time) {
        $company = $this->_company();

        return array(
            "CONTRACT_TITLE" => $this->_e($title),
            "CONTRACT_DATE" => $this->_e($this->_date($date_time, true)),
            "CONTRACT_NOTES" => nl2br($this->_e($notes)),
            "TALENT_LEGAL_NAME" => $this->_e($context->legal_name),
            "TALENT_PREFERRED_NAME" => $this->_e($context->preferred_name),
            "TALENT_EMAIL" => $this->_e($context->email),
            "TALENT_ADDRESS" => nl2br($this->_e($context->address)),
            "TALENT_PROFESSION" => $this->_e($context->profession),
            "TALENT_ON_SCREEN_TITLE" => $this->_e($context->on_screen_title),
            "PROJECT_TITLE" => $this->_e($context->project_title),
            "PROJECT_START_DATE" => $this->_e($this->_date($context->project_start_date, false)),
            "PROJECT_DEADLINE" => $this->_e($this->_date($context->project_deadline, false)),
            "COMPANY_NAME" => $this->_e($company->name),
            "COMPANY_ADDRESS" => nl2br($this->_e($company->address)),
            "COMPANY_PHONE" => $this->_e($company->phone),
            "COMPANY_EMAIL" => $this->_e($company->email),
            "COMPANY_WEBSITE" => $this->_e($company->website),
        );
    }

    //one pass over the template, so a value that happens to contain "{PROJECT_TITLE}" is never expanded a second time
    private function _render($content, $values) {
        $map = array();
        foreach ($values as $key => $value) {
            $map["{" . $key . "}"] = (string) $value;
        }
        return strtr((string) $content, $map);
    }

    //true when the mail server accepted it. In a non-production environment core's mailer throws on failure instead of returning
    //false, so both outcomes are handled; the error text can hold SMTP details, so it goes to the log only.
    private function _send_email($context, $title, $link, $expires_at) {
        $Email_templates_model = model("App\Models\Email_templates_model");
        $company = $this->_company();

        $template = $Email_templates_model->get_final_template("talent_contract_request", true);
        $default = talent_contract_default_email();
        $subject = get_array_value($template, "subject_default") ? get_array_value($template, "subject_default") : $default["subject"];
        $message = get_array_value($template, "message_default") ? get_array_value($template, "message_default") : $default["message"];

        $name = $context->preferred_name ? $context->preferred_name : $context->legal_name;
        $expiry_date = $this->_date($expires_at, true);

        $html_values = array(
            "TALENT_NAME" => $this->_e($name),
            "PROJECT_TITLE" => $this->_e($context->project_title),
            "CONTRACT_TITLE" => $this->_e($title),
            "CONTRACT_URL" => $this->_e($link),
            "EXPIRY_DATE" => $this->_e($expiry_date),
            "COMPANY_NAME" => $this->_e($company->name),
            "LOGO_URL" => $this->_e(get_logo_url()),
            "SIGNATURE" => (string) get_array_value($template, "signature_default"),
            "RECIPIENTS_EMAIL_ADDRESS" => $this->_e($context->email),
        );

        //the subject is plain text: no HTML escaping, but no line breaks either (header injection)
        $subject_values = array(
            "TALENT_NAME" => $name,
            "PROJECT_TITLE" => $context->project_title,
            "CONTRACT_TITLE" => $title,
            "EXPIRY_DATE" => $expiry_date,
            "COMPANY_NAME" => $company->name,
        );
        foreach ($subject_values as $key => $value) {
            $subject_values[$key] = trim(preg_replace('/[\r\n]+/', ' ', (string) $value));
        }

        try {
            //the message is already escaped, so the mailer's own htmlspecialchars_decode is switched off
            return send_app_mail($context->email, $this->_render($subject, $subject_values), $this->_render($message, $html_values), array(), false) ? true : false;
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return false;
        }
    }
}
