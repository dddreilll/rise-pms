<?php

namespace Talent_Management\Libraries;

use Talent_Management\Models\Talent_contract_bundle_model;
use Talent_Management\Models\Talent_contract_event_model;
use Talent_Management\Models\Talent_contract_model;
use Talent_Management\Models\Talent_contract_template_model;
use Talent_Management\Models\Talent_model;
use Talent_Management\Models\Talent_project_agreement_model;
use Talent_Management\Models\Talent_project_model;
use Talent_Management\Models\Talent_status_model;

//the one place the contract workflow lives: the controllers stay thin, and this is the seam to lift out if signing ever has
//to serve something other than talent. It currently covers previewing and issuing a contract.
class Talent_contract_service {

    private $Talent_contract_model;
    private $Talent_contract_bundle_model;
    private $Talent_contract_event_model;
    private $Talent_contract_template_model;
    private $Talent_model;
    private $Talent_project_agreement_model;
    private $Talent_project_model;
    private $Talent_status_model;

    function __construct() {
        $this->Talent_contract_model = new Talent_contract_model();
        $this->Talent_contract_bundle_model = new Talent_contract_bundle_model();
        $this->Talent_contract_event_model = new Talent_contract_event_model();
        $this->Talent_contract_template_model = new Talent_contract_template_model();
        $this->Talent_model = new Talent_model();
        $this->Talent_project_agreement_model = new Talent_project_agreement_model();
        $this->Talent_project_model = new Talent_project_model();
        $this->Talent_status_model = new Talent_status_model();
    }

    //The agreements a project requires of its talent (its "required list"), or an empty list when it has none.
    private function _required_templates($project_id) {
        return $project_id ? $this->Talent_project_agreement_model->get_templates_for_project($project_id) : array();
    }

    private function _ids_of($templates) {
        return array_map(function ($template) {
            return (int) $template->id;
        }, $templates);
    }

    //Where a casting link stands against its project's required list: has_list (the project asks for anything at all), the required
    //templates, the ones still missing a signature, and complete (a list exists and every entry on it is signed).
    function get_requirement_status($talent_project_id) {
        $assignment = $this->Talent_project_model->get_one($talent_project_id);
        $required = $assignment->id ? $this->_required_templates($assignment->project_id) : array();
        $signed = $required ? $this->Talent_contract_model->get_signed_template_ids($talent_project_id) : array();

        $missing = array_values(array_filter($required, function ($template) use ($signed) {
            return !in_array((int) $template->id, $signed, true);
        }));

        return array("has_list" => count($required) > 0, "required" => $required, "missing" => $missing, "complete" => count($required) > 0 && !$missing);
    }

    //Every agreement of a casting link with where it stands: first the ones its project requires (in the order chosen), then extras that
    //were sent although they aren't on the list. Each row: template_id, title, required, contract (the newest one or null), status
    //(its effective state, "" when nothing was sent) and can_send (nothing is waiting or signed).
    function get_agreement_states($talent_project_id) {
        $assignment = $this->Talent_project_model->get_one($talent_project_id);
        $required = $assignment->id ? $this->_required_templates($assignment->project_id) : array();

        $latest = array();
        foreach ($this->Talent_contract_model->get_latest_per_agreement($talent_project_id) as $contract) {
            $latest[(int) $contract->template_id] = $contract;
        }

        $row = function ($template_id, $title, $is_required) use (&$latest) {
            $contract = isset($latest[$template_id]) ? $latest[$template_id] : null;
            unset($latest[$template_id]);
            $status = $contract ? talent_contract_effective_status($contract->status, $contract->token_expires_at) : "";

            return array("template_id" => (int) $template_id, "title" => $title, "required" => $is_required, "contract" => $contract, "status" => $status, "can_send" => !($status === "sent" || $status === "signed"));
        };

        $rows = array();
        foreach ($required as $template) {
            $rows[] = $row((int) $template->id, $template->title, true);
        }
        foreach ($latest as $template_id => $contract) {
            $rows[] = $row((int) $template_id, $contract->title, false);
        }

        return $rows;
    }

    //the templates that can be sent to this casting link right now (nothing of that agreement is waiting or signed), required ones first
    function get_sendable_templates($talent_project_id) {
        $blocked = array();
        foreach ($this->get_agreement_states($talent_project_id) as $state) {
            if (!$state["can_send"]) {
                $blocked[$state["template_id"]] = true;
            }
        }

        $assignment = $this->Talent_project_model->get_one($talent_project_id);
        $required_ids = $assignment->id ? $this->_ids_of($this->_required_templates($assignment->project_id)) : array();

        $sendable = array();
        foreach ($this->Talent_contract_template_model->get_details()->getResult() as $template) {
            if (!isset($blocked[(int) $template->id])) {
                $sendable[] = array("id" => (int) $template->id, "title" => $template->title, "required" => in_array((int) $template->id, $required_ids, true));
            }
        }

        usort($sendable, function ($a, $b) {
            return $a["required"] === $b["required"] ? 0 : ($a["required"] ? -1 : 1);
        });

        return $sendable;
    }

    //Why a casting link can't enter the Confirmed stage yet, as a message naming what is still to sign; null when it can. A project with no
    //required list has no gate at all (staff move cards themselves). The titles are escaped: the message is shown as HTML.
    function get_confirm_block_reason($talent_project_id) {
        $status = $this->get_requirement_status($talent_project_id);
        if (!$status["has_list"] || $status["complete"]) {
            return null;
        }

        $titles = array_map(function ($template) {
            return esc($template->title);
        }, $status["missing"]);

        return sprintf(app_lang("talent_contract_gate_missing"), implode(", ", $titles));
    }

    //Where sending this agreement should put the card: the Contract Signing stage id, or null to leave it where it is.
    //  - an extra (the project has a list and this agreement isn't on it) never moves the card;
    //  - a card in Confirmed goes back only for a listed agreement (that is a requirement it no longer meets);
    //  - a card in a stage before Contract Signing moves there; Contract Signing itself and later stages (Wrapped) are left alone.
    function get_stage_for_send($talent_project_id, $template_id) {
        $assignment = $this->Talent_project_model->get_one($talent_project_id);
        if (!$assignment->id || $assignment->deleted) {
            return null;
        }

        $stages = $this->Talent_status_model->get_system_stage_ids();
        $signing_id = (int) get_array_value($stages, "contract_signing");
        $confirmed_id = (int) get_array_value($stages, "confirmed");
        if (!$signing_id) {
            return null;
        }

        $required_ids = $this->_ids_of($this->_required_templates($assignment->project_id));
        $has_list = count($required_ids) > 0;
        $listed = in_array((int) $template_id, $required_ids, true);
        if ($has_list && !$listed) {
            return null;
        }

        $current_id = (int) $assignment->talent_status_id;
        if ($current_id === $signing_id) {
            return null;
        }
        if ($confirmed_id && $current_id === $confirmed_id) {
            return ($has_list && $listed) ? $signing_id : null;
        }

        $current = $this->Talent_status_model->get_one($current_id);
        $signing = $this->Talent_status_model->get_one($signing_id);
        return (int) $current->sort < (int) $signing->sort ? $signing_id : null;
    }

    //After a signature is saved (inside the caller's transaction): with a required list, the signature that completes it moves the card
    //to Confirmed. No list, an agreement that isn't on the list, or agreements still missing: nothing applies (returns null).
    //Otherwise array(moved, stage_id, reason). Being in Confirmed already needs no move and no entry.
    private function _settle_confirmation($talent_project_id, $template_ids) {
        $assignment = $this->Talent_project_model->get_one($talent_project_id);
        $required_ids = $this->_ids_of($this->_required_templates($assignment->project_id));

        //a signing can cover several agreements at once; it matters if at least one of them is on the list
        $template_ids = array_map('intval', (array) $template_ids);
        if (!$required_ids || !array_intersect($template_ids, $required_ids)) {
            return null;
        }

        $signed = $this->Talent_contract_model->get_signed_template_ids($talent_project_id);
        foreach ($required_ids as $required_id) {
            if (!in_array($required_id, $signed, true)) {
                return null;
            }
        }

        //a valid signature stands even if the card was unassigned meanwhile or the pipeline lost its Confirmed stage; the trail says so
        if (!$assignment->id || $assignment->deleted) {
            return array("moved" => false, "stage_id" => 0, "reason" => "assignment_removed");
        }

        $confirmed_id = (int) get_array_value($this->Talent_status_model->get_system_stage_ids(), "confirmed");
        if (!$confirmed_id) {
            return array("moved" => false, "stage_id" => 0, "reason" => "no_confirmed_stage");
        }
        if ((int) $assignment->talent_status_id === $confirmed_id) {
            return null;
        }

        $card = array("talent_status_id" => $confirmed_id);
        $this->_must($this->Talent_project_model->ci_save($card, $assignment->id), "move the card");
        return array("moved" => true, "stage_id" => $confirmed_id, "reason" => "");
    }

    //what the staff member reads before sending; the signature fields show as blanks so the layout is clear. Null if it can't be built.
    function preview($template_id, $talent_project_id, $notes = "") {
        $context = $this->Talent_project_model->get_context($talent_project_id);
        $template = $this->Talent_contract_template_model->get_one($template_id);
        if (!$context || !$template->id || $template->deleted) {
            return null;
        }

        $values = $this->_merge_values($context, $template->title, $notes, get_current_utc_time());
        $values["CONTRACT_ID"] = "TC-#####";

        return $this->_render($template->content, $values + $this->_blank_signature_values());
    }

    //Sends one agreement for a casting link (a bundle of one). Returns array(success, message, contract_id, emailed, email, link) or
    //array(success => false, message). $actor is array(type, id, ip, user_agent) for the audit trail.
    function issue($talent_project_id, $template_id, $notes, $actor) {
        $result = $this->issue_bundle($talent_project_id, array($template_id), $notes, $actor, false);
        if (!$result["success"]) {
            return array("success" => false, "message" => $result["message"]);
        }

        $bundle = $result["bundles"][0];
        return array(
            "success" => true,
            "message" => $bundle["message"],
            "contract_id" => $bundle["contract_ids"][0],
            "emailed" => $bundle["emailed"],
            "email" => $bundle["email"],
            "link" => $bundle["link"],
        );
    }

    //Sends agreements for a casting link. Several go out in ONE link and email unless $separate, which sends each on its own. Freezes each
    //text, moves the card if the rules say so, and emails the talent. Returns success (something was sent), message, bundles (one entry per
    //email: bundle_id, contract_ids, titles, emailed, email, link, message) and errors (what could not be sent, in split sends).
    function issue_bundle($talent_project_id, $template_ids, $notes, $actor, $separate = false) {
        $context = $this->Talent_project_model->get_context($talent_project_id);
        if (!$context) {
            return $this->_fail("talent_contract_error_assignment_missing");
        }
        if (!filter_var($context->email, FILTER_VALIDATE_EMAIL)) {
            return $this->_fail("talent_contract_error_no_email");
        }

        //the talent signs under their legal name; it is fixed here, so the signature record always matches the name printed in the text
        if (trim((string) $context->legal_name) === "") {
            return $this->_fail("talent_contract_error_no_legal_name");
        }

        $templates = array();
        foreach ((array) $template_ids as $template_id) {
            if (!is_numeric($template_id) || (int) $template_id <= 0 || isset($templates[(int) $template_id])) {
                continue;
            }

            $template = $this->Talent_contract_template_model->get_one($template_id);
            if (!$template->id || $template->deleted || $this->_is_blank($template->content)) {
                return $this->_fail("talent_contract_error_template");
            }
            $templates[(int) $template->id] = $template;
        }
        if (!$templates) {
            return $this->_fail("talent_contract_error_template");
        }

        if (!get_array_value($this->Talent_status_model->get_system_stage_ids(), "contract_signing")) {
            return $this->_fail("talent_contract_error_stage_missing");
        }

        $groups = ($separate || count($templates) === 1) ? array_map(function ($template) {
                    return array($template);
                }, array_values($templates)) : array(array_values($templates));

        $bundles = array();
        $errors = array();
        foreach ($groups as $group) {
            $result = $this->_issue_bundle($context, $talent_project_id, $group, $notes, $actor);
            if ($result["success"]) {
                $bundles[] = $result;
            } else {
                $errors[] = $result["message"];
            }
        }

        if (!$bundles) {
            return array("success" => false, "message" => implode(" ", $errors));
        }

        $message = count($bundles) === 1 ? $bundles[0]["message"] : sprintf(app_lang("talent_contract_sent_many_message"), count($bundles), $context->email);
        if ($errors) {
            $message .= " " . implode(" ", $errors);
        }

        return array("success" => true, "message" => $message, "bundles" => $bundles, "errors" => $errors);
    }

    //One bundle: the link, its contracts and one email, in one transaction (the email goes out after the commit)
    private function _issue_bundle($context, $talent_project_id, $templates, $notes, $actor) {
        //only a hash of the token is stored, so the link exists only in this response and the email
        $token = bin2hex(random_bytes(20));
        $now = get_current_utc_time();
        $expires_at = gmdate("Y-m-d H:i:s", strtotime($now . " UTC") + talent_contract_expiry_days() * 86400);
        $legal_name = trim((string) $context->legal_name);
        $many = count($templates) > 1;

        $db = db_connect('default');
        $db->transBegin();

        try {
            //two people (or a double click) sending for the same casting link must not both get through
            $db->query("SELECT id FROM " . $db->prefixTable("talent_projects") . " WHERE id=" . (int) $talent_project_id . " FOR UPDATE");

            foreach ($templates as $template) {
                $current = $this->Talent_contract_model->get_latest_for_agreement($talent_project_id, $template->id);
                if (!$current) {
                    continue;
                }

                $current_status = talent_contract_effective_status($current->status, $current->token_expires_at);
                if ($current_status === "signed" || $current_status === "sent") {
                    $db->transRollback();
                    $reason = app_lang($current_status === "signed" ? "talent_contract_error_already_signed" : "talent_contract_error_pending");
                    return array("success" => false, "message" => $many ? sprintf(app_lang("talent_contract_error_bundle_item"), $template->title, $reason) : $reason);
                }

                //a lapsed link is marked expired now so the history reads correctly
                if ($current->status === "sent") {
                    $expired = array("status" => "expired");
                    $this->_must($this->Talent_contract_model->ci_save($expired, $current->id), "expire the previous contract");
                    $this->_must($this->Talent_contract_event_model->log($current->id, "expired", array("type" => "system")), "log the expiry");
                }
            }

            $bundle = array(
                "talent_project_id" => $talent_project_id,
                "token_hash" => hash("sha256", $token),
                "token_expires_at" => $expires_at,
                "sent_to_email" => $context->email,
                "sent_by" => (int) get_array_value($actor, "id"),
                "sent_at" => $now,
                "created_at" => $now,
            );
            $bundle_id = $this->Talent_contract_bundle_model->ci_save($bundle);
            $this->_must($bundle_id, "create the link");

            //the card moves once, however many agreements are in the bundle: to Contract Signing if any of them calls for it
            $move_to = null;
            foreach ($templates as $template) {
                $move_to = $move_to ? $move_to : $this->get_stage_for_send($talent_project_id, $template->id);
            }
            if ($move_to) {
                $card = array("talent_status_id" => $move_to);
                $this->_must($this->Talent_project_model->ci_save($card, $talent_project_id), "move the card");
            }

            $contract_ids = array();
            $titles = array();
            foreach ($templates as $template) {
                $contract = array(
                    "talent_project_id" => $talent_project_id,
                    "template_id" => $template->id,
                    "bundle_id" => $bundle_id,
                    "title" => $template->title,
                    "content" => "",
                    "token_expires_at" => $expires_at,
                    "status" => "sent",
                    "signer_name" => $legal_name,
                    "sent_to_email" => $context->email,
                    "sent_by" => (int) get_array_value($actor, "id"),
                    "sent_at" => $now,
                    "created_at" => $now,
                );
                $contract_id = $this->Talent_contract_model->ci_save($contract);
                $this->_must($contract_id, "create the contract");

                //frozen from here on: later template edits never touch a sent contract. The hash covers the exact string stored.
                $values = $this->_merge_values($context, $template->title, $notes, $now);
                $values["CONTRACT_ID"] = esc(talent_contract_label($contract_id));
                $frozen = talent_encode_4byte_chars($this->_render($template->content, $values));
                $content_hash = hash("sha256", $frozen);
                $frozen_data = array("content" => $frozen, "content_hash" => $content_hash);
                $this->_must($this->Talent_contract_model->ci_save($frozen_data, $contract_id), "freeze the contract text");

                $this->_must($this->Talent_contract_event_model->log($contract_id, "sent", $actor, array(
                            "to" => $context->email,
                            "signer_name" => $legal_name,
                            "template_id" => (int) $template->id,
                            "bundle_id" => (int) $bundle_id,
                            "expires_at" => $expires_at,
                            "content_hash" => $content_hash,
                        )), "log the contract");

                $contract_ids[] = (int) $contract_id;
                $titles[] = $template->title;
            }

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        //after the commit on purpose: a slow mail server must not hold the lock, and a failed mail must not undo the contracts
        $link = get_uri("talent_sign/" . $bundle_id . "/" . $token);
        $emailed = $this->_send_email($context, $context->email, $titles, $link, $expires_at);
        foreach ($contract_ids as $contract_id) {
            $this->Talent_contract_event_model->log($contract_id, $emailed ? "email_sent" : "email_failed", $actor, array("to" => $context->email));
        }

        return array(
            "success" => true,
            "message" => $emailed ? sprintf(app_lang("talent_contract_sent_message"), $context->email) : app_lang("talent_contract_email_failed_message"),
            "bundle_id" => (int) $bundle_id,
            "contract_ids" => $contract_ids,
            "titles" => $titles,
            "emailed" => $emailed,
            "email" => $context->email,
            "link" => $link,
        );
    }

    //Staff withdraw a contract. A waiting one (whether or not its link has lapsed) can be voided by any staff member with access; a signed
    //one is a legal record, so that needs $may_void_signed (the controller passes admin) and a reason. Voiding a signed contract also
    //takes its card back out of Confirmed, since Confirmed means "has a valid signature". $meta is added to the audit event.
    function void($contract_id, $reason, $actor, $may_void_signed = false, $meta = array()) {
        if (!is_numeric($contract_id)) {
            return $this->_fail("talent_contract_error_cannot_void");
        }

        $contract = $this->Talent_contract_model->get_public($contract_id);
        if (!$contract || !$contract->id || $contract->deleted || !($contract->status === "sent" || $contract->status === "signed")) {
            return $this->_fail("talent_contract_error_cannot_void");
        }

        $reason = $this->_clean_text($reason, 1000);
        if ($contract->status === "signed") {
            if (!$may_void_signed) {
                return $this->_fail("talent_contract_error_void_signed_admin");
            }
            if ($reason === "") {
                return $this->_fail("talent_contract_error_void_reason");
            }
        }

        $db = db_connect('default');
        $db->transBegin();

        try {
            //the card first, then the contract: the same order issue() takes its locks in
            $db->query("SELECT id FROM " . $db->prefixTable("talent_projects") . " WHERE id=" . (int) $contract->talent_project_id . " FOR UPDATE");
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contracts") . " WHERE id=" . (int) $contract->id . " FOR UPDATE");

            $latest = $this->Talent_contract_model->get_public($contract->id);
            if (!($latest->status === "sent" || $latest->status === "signed")) {
                $db->transRollback();
                return $this->_fail("talent_contract_error_cannot_void");
            }
            $was_signed = $latest->status === "signed";

            $voided = array("status" => "voided");
            $this->_must($this->Talent_contract_model->ci_save($voided, $contract->id), "void the contract");

            $event_meta = $meta + array("was_signed" => $was_signed);
            if ($reason !== "") {
                $event_meta["reason"] = $reason;
            }
            $this->_must($this->Talent_contract_event_model->log($contract->id, "voided", $actor, $event_meta), "log the void");

            if ($was_signed) {
                $stages = $this->Talent_status_model->get_system_stage_ids();
                $assignment = $this->Talent_project_model->get_one($contract->talent_project_id);
                $signing_stage_id = get_array_value($stages, "contract_signing");
                //Confirmed means "everything the project requires is signed", so only withdrawing a LISTED agreement takes the card back out
                $listed = $assignment->id && in_array((int) $contract->template_id, $this->_ids_of($this->_required_templates($assignment->project_id)), true);
                if ($listed && !$assignment->deleted && $signing_stage_id && (int) $assignment->talent_status_id === (int) get_array_value($stages, "confirmed")) {
                    $card = array("talent_status_id" => $signing_stage_id);
                    $this->_must($this->Talent_project_model->ci_save($card, $assignment->id), "move the card back");
                    $this->_must($this->Talent_contract_event_model->log($contract->id, "card_reverted", array("type" => "system"), array("stage_id" => (int) $signing_stage_id)), "log the card move");
                }
            }

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        return array("success" => true, "message" => app_lang("talent_contract_voided_message"));
    }

    //An agreement can be removed from a casting link once it has been withdrawn: its newest record is voided and none of its records is
    //waiting or signed.
    private function _can_remove($records) {
        if (!$records) {
            return false;
        }

        foreach ($records as $record) {
            if ($record->status === "sent" || $record->status === "signed") {
                return false;
            }
        }

        return end($records)->status === "voided";
    }

    //did anyone ever sign any of these records (a signature that was later withdrawn still leaves its date)
    private function _ever_signed($records) {
        foreach ($records as $record) {
            if ($record->signed_at) {
                return true;
            }
        }

        return false;
    }

    //What removing a withdrawn agreement from a casting link takes away, for the confirmation: its title, how many records go with it and
    //whether any of them was ever signed (then a reason is required). Null when it can't be removed.
    function get_removal_preview($talent_project_id, $template_id) {
        if (!is_numeric($talent_project_id) || !is_numeric($template_id)) {
            return null;
        }

        $records = $this->Talent_contract_model->get_records_for_agreement($talent_project_id, $template_id);
        if (!$this->_can_remove($records)) {
            return null;
        }

        return array("title" => end($records)->title, "records" => count($records), "was_signed" => $this->_ever_signed($records));
    }

    //Staff take a withdrawn agreement off a casting link. Every record of that agreement on the casting link goes out of sight, so the
    //agreement reads "not sent" again; nothing is erased (the rows, their audit trails and any signed PDF stay in the database) and each
    //record gets a "removed" event. Admins only, and a reason is required when any of the records was ever signed.
    function remove_voided($talent_project_id, $template_id, $reason, $actor, $may_remove = false) {
        if (!$may_remove) {
            return $this->_fail("talent_contract_error_remove_admin");
        }
        if (!is_numeric($talent_project_id) || !is_numeric($template_id)) {
            return $this->_fail("talent_contract_error_cannot_remove");
        }

        $talent_project_id = (int) $talent_project_id;
        $template_id = (int) $template_id;
        $reason = $this->_clean_text($reason, 1000);

        $db = db_connect('default');
        $db->transBegin();

        try {
            //the card first, then the agreement's contracts by id: the same order everything else takes its locks in
            $db->query("SELECT id FROM " . $db->prefixTable("talent_projects") . " WHERE id=" . $talent_project_id . " FOR UPDATE");
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contracts") . " WHERE talent_project_id=" . $talent_project_id . " AND template_id=" . $template_id . " ORDER BY id FOR UPDATE");

            //looked at again under the lock: it may have been sent again, or removed by someone else, since the button was shown
            $records = $this->Talent_contract_model->get_records_for_agreement($talent_project_id, $template_id);
            if (!$this->_can_remove($records)) {
                $db->transRollback();
                return $this->_fail("talent_contract_error_cannot_remove");
            }
            if ($this->_ever_signed($records) && $reason === "") {
                $db->transRollback();
                return $this->_fail("talent_contract_error_remove_reason");
            }

            foreach ($records as $record) {
                $hidden = array("deleted" => 1);
                $this->_must($this->Talent_contract_model->ci_save($hidden, $record->id), "remove the contract");

                $event_meta = array("was_signed" => $record->signed_at ? true : false, "records" => count($records));
                if ($reason !== "") {
                    $event_meta["reason"] = $reason;
                }
                $this->_must($this->Talent_contract_event_model->log($record->id, "removed", $actor, $event_meta), "log the removal");
            }

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        return array("success" => true, "message" => app_lang("talent_contract_removed_message"));
    }

    //a removed casting link or talent must not leave a live signing link behind; signed contracts are records and stay
    function void_pending_for_assignment($talent_project_id, $actor) {
        $this->_void_pending($this->Talent_contract_model->get_pending_ids(array("talent_project_id" => $talent_project_id)), $actor, "assignment_removed");
    }

    function void_pending_for_talent($talent_id, $actor) {
        $this->_void_pending($this->Talent_contract_model->get_pending_ids(array("talent_id" => $talent_id)), $actor, "talent_removed");
    }

    private function _void_pending($contract_ids, $actor, $cause) {
        foreach ($contract_ids as $contract_id) {
            $this->void($contract_id, "", $actor, false, array("cause" => $cause));
        }
    }

    //Sends the link again, for someone who lost the email or never got it. The token is only stored as a hash, so this makes a new one:
    //the earlier link stops working. It belongs to the bundle, so every waiting or lapsed agreement in it comes along on the new link
    //(one email lists them) with a fresh expiry. The mail goes to the talent's address on file now when it is valid (that is how a typo
    //gets fixed), and the address the signer has to confirm follows it.
    function resend($contract_id, $actor) {
        if (!is_numeric($contract_id)) {
            return $this->_fail("talent_contract_error_cannot_resend");
        }

        $contract = $this->Talent_contract_model->get_public($contract_id);
        if (!$contract || !$contract->id || $contract->deleted || !($contract->status === "sent" || $contract->status === "expired") || !$this->Talent_contract_bundle_model->get_existing($contract->bundle_id)) {
            return $this->_fail("talent_contract_error_cannot_resend");
        }

        $context = $this->Talent_project_model->get_context($contract->talent_project_id);
        if (!$context) {
            return $this->_fail("talent_contract_error_assignment_missing");
        }

        $to = filter_var($context->email, FILTER_VALIDATE_EMAIL) ? trim($context->email) : (string) $contract->sent_to_email;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->_fail("talent_contract_error_no_email");
        }

        $token = bin2hex(random_bytes(20));
        $now = get_current_utc_time();
        $expires_at = gmdate("Y-m-d H:i:s", strtotime($now . " UTC") + talent_contract_expiry_days() * 86400);

        $db = db_connect('default');
        $db->transBegin();

        try {
            //the card, then the bundle, then its contracts in id order: the same order signing takes them in
            $db->query("SELECT id FROM " . $db->prefixTable("talent_projects") . " WHERE id=" . (int) $contract->talent_project_id . " FOR UPDATE");
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contract_bundles") . " WHERE id=" . (int) $contract->bundle_id . " FOR UPDATE");
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contracts") . " WHERE bundle_id=" . (int) $contract->bundle_id . " ORDER BY id FOR UPDATE");

            //signed, declined or voided in the meantime: nothing to send any more. And only the newest contract of an agreement can come
            //back to life; an older lapsed one would otherwise sit next to the newer contract as a second live link.
            $latest = $this->Talent_contract_model->get_public($contract->id);
            $newest = $this->Talent_contract_model->get_latest_for_agreement($contract->talent_project_id, $contract->template_id);
            if (!($latest->status === "sent" || $latest->status === "expired") || !$newest || (int) $newest->id !== (int) $contract->id) {
                $db->transRollback();
                return $this->_fail("talent_contract_error_cannot_resend");
            }

            //everything in the bundle that is still waiting for a signature (or lapsed) and is the newest of its own agreement
            $renewed = array();
            foreach ($this->Talent_contract_model->get_for_bundle($contract->bundle_id) as $mate) {
                if (!($mate->status === "sent" || $mate->status === "expired")) {
                    continue;
                }
                $mate_newest = $this->Talent_contract_model->get_latest_for_agreement($mate->talent_project_id, $mate->template_id);
                if ($mate_newest && (int) $mate_newest->id === (int) $mate->id) {
                    $renewed[] = $mate;
                }
            }

            $bundle_data = array("token_hash" => hash("sha256", $token), "token_expires_at" => $expires_at, "sent_to_email" => $to);
            $this->_must($this->Talent_contract_bundle_model->ci_save($bundle_data, $contract->bundle_id), "renew the link");

            foreach ($renewed as $mate) {
                $renew = array("status" => "sent", "token_expires_at" => $expires_at, "sent_to_email" => $to);
                $this->_must($this->Talent_contract_model->ci_save($renew, $mate->id), "renew the contract");

                $event_meta = array("to" => $to, "expires_at" => $expires_at, "bundle_id" => (int) $contract->bundle_id);
                if ($to !== (string) $mate->sent_to_email) {
                    $event_meta["previous_to"] = (string) $mate->sent_to_email;
                }
                $this->_must($this->Talent_contract_event_model->log($mate->id, "resent", $actor, $event_meta), "log the resend");
            }

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        $titles = array_map(function ($mate) {
            return $mate->title;
        }, $renewed);
        $contract_ids = array_map(function ($mate) {
            return (int) $mate->id;
        }, $renewed);

        $link = get_uri("talent_sign/" . $contract->bundle_id . "/" . $token);
        $emailed = $this->_send_email($context, $to, $titles, $link, $expires_at);
        foreach ($contract_ids as $renewed_id) {
            $this->Talent_contract_event_model->log($renewed_id, $emailed ? "email_sent" : "email_failed", $actor, array("to" => $to));
        }

        return array(
            "success" => true,
            "message" => $emailed ? sprintf(app_lang("talent_contract_resent_message"), $to) : app_lang("talent_contract_resend_email_failed_message"),
            "contract_id" => (int) $contract->id,
            "contract_ids" => $contract_ids,
            "emailed" => $emailed,
            "email" => $to,
            "link" => $link,
        );
    }

    //Records a contract that was signed on paper. The scan (a PDF, or a photo that is turned into one) is kept as the signed copy, and
    //this is the only way a card reaches Confirmed without the talent signing online, so it is audited like everything else. A contract
    //still waiting for the talent is withdrawn in the same step. $file is array(path, name); $signed_on is the date on the paper (Y-m-d).
    function record_paper_copy($talent_project_id, $template_id, $title, $signed_on, $note, $file, $actor) {
        if (!is_numeric($talent_project_id)) {
            return $this->_fail("talent_contract_error_assignment_missing");
        }

        //the scan is for one of the agreements: that is what makes it count toward the project's list
        $template = is_numeric($template_id) ? $this->Talent_contract_template_model->get_one($template_id) : null;
        if (!$template || !$template->id || $template->deleted) {
            return $this->_fail("talent_contract_error_template");
        }

        $context = $this->Talent_project_model->get_context($talent_project_id);
        if (!$context) {
            return $this->_fail("talent_contract_error_assignment_missing");
        }

        $legal_name = trim((string) $context->legal_name);
        if ($legal_name === "") {
            return $this->_fail("talent_contract_error_no_legal_name");
        }

        //the day on the paper, which can be earlier than today but not later
        $signed_on = trim((string) $signed_on);
        $date = \DateTime::createFromFormat("!Y-m-d", $signed_on, new \DateTimeZone("UTC"));
        $date_errors = \DateTime::getLastErrors();
        if (!$date || ($date_errors && ($date_errors["warning_count"] || $date_errors["error_count"])) || $date->format("Y-m-d") !== $signed_on || $signed_on > get_my_local_time("Y-m-d")) {
            return $this->_fail("talent_contract_error_paper_date");
        }

        $title = trim(preg_replace('/\s+/', " ", $this->_clean_text($title, 255)));
        if ($title === "") {
            $title = $template->title;
        }
        $note = $this->_clean_text($note, 1000);

        $scan = $this->_prepare_paper_scan($file, $title);
        if (isset($scan["error"])) {
            return array("success" => false, "message" => sprintf(app_lang($scan["error"]), talent_contract_paper_max_mb()));
        }

        $pdf = $scan["pdf"];
        $pdf_hash = hash("sha256", $pdf);
        $now = get_current_utc_time();

        //stored encrypted like every signed PDF; the hash stays that of the plain bytes, so it can be checked after decrypting
        try {
            $stored_pdf = talent_encrypt($pdf);
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        $db = db_connect('default');
        $db->transBegin();

        try {
            $db->query("SELECT id FROM " . $db->prefixTable("talent_projects") . " WHERE id=" . (int) $talent_project_id . " FOR UPDATE");

            $current = $this->Talent_contract_model->get_latest_for_agreement($talent_project_id, $template->id);
            if ($current) {
                if ($current->status === "signed") {
                    $db->transRollback();
                    return $this->_fail("talent_contract_error_already_signed");
                }

                //a link that is still out would let the talent sign a second time, so the paper copy replaces it
                if ($current->status === "sent") {
                    $withdrawn = array("status" => "voided");
                    $this->_must($this->Talent_contract_model->ci_save($withdrawn, $current->id), "withdraw the waiting contract");
                    $this->_must($this->Talent_contract_event_model->log($current->id, "voided", $actor, array("was_signed" => false, "cause" => "paper_copy")), "log the withdrawal");
                }
            }

            $contract = array(
                "talent_project_id" => $talent_project_id,
                "template_id" => $template->id,
                "title" => $title,
                "content" => "",
                "content_hash" => $pdf_hash,
                "token_hash" => "",
                "status" => "signed",
                "signer_name" => $legal_name,
                "signer_email" => filter_var($context->email, FILTER_VALIDATE_EMAIL) ? trim($context->email) : "",
                "sent_by" => (int) get_array_value($actor, "id"),
                //noon UTC keeps the date the same in every timezone; only the day is known
                "signed_at" => $signed_on . " 12:00:00",
                "signed_via" => "paper",
                "signed_pdf_data" => $stored_pdf,
                "pdf_hash" => $pdf_hash,
                "created_at" => $now,
            );
            $contract_id = $this->Talent_contract_model->ci_save($contract);
            $this->_must($contract_id, "record the paper copy");

            $outcome = $this->_settle_confirmation($talent_project_id, $template->id);
            $confirmed = $outcome && $outcome["moved"];

            $file_name = talent_strip_4byte_chars(preg_replace('/[\x00-\x1F\x7F]/', "", basename(str_replace("\\", "/", (string) get_array_value($file, "name")))));
            $meta = array("signed_on" => $signed_on, "file_name" => substr($file_name, 0, 120), "pdf_hash" => $pdf_hash, "bytes" => strlen($pdf), "from_image" => $scan["from_image"]);
            if ($note !== "") {
                $meta["note"] = $note;
            }
            $this->_must($this->Talent_contract_event_model->log($contract_id, "signed_paper", $actor, $meta), "log the paper copy");
            if ($outcome) {
                $this->_must($this->Talent_contract_event_model->log($contract_id, $outcome["moved"] ? "card_confirmed" : "confirm_skipped", array("type" => "system"), $outcome["moved"] ? array("stage_id" => $outcome["stage_id"]) : array("reason" => $outcome["reason"])), "log the card move");
            }

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        return array("success" => true, "message" => app_lang("talent_contract_paper_saved_message"), "contract_id" => (int) $contract_id, "confirmed" => $confirmed);
    }

    //The bundle behind a signing link, or null when the id or token doesn't match. Every kind of mismatch looks the same on purpose.
    function find_bundle($bundle_id, $token) {
        if (!is_numeric($bundle_id) || !preg_match('/^[0-9a-f]{40}$/', (string) $token)) {
            return null;
        }

        $bundle = $this->Talent_contract_bundle_model->get_existing($bundle_id);
        if (!$bundle || !hash_equals((string) $bundle->token_hash, hash("sha256", $token))) {
            return null;
        }

        return $bundle;
    }

    //a contract of the bundle, or null: what a signing request may act on is only ever what the link delivered
    private function _contract_of_bundle($bundle, $contract_id) {
        if (!is_numeric($contract_id)) {
            return null;
        }

        $contract = $this->Talent_contract_model->get_public($contract_id);
        if (!$contract || !$contract->id || $contract->deleted || (int) $contract->bundle_id !== (int) $bundle->id) {
            return null;
        }
        return $contract;
    }

    //Everything the signing page shows, or null for an unknown link: one entry per agreement in the link (its state, its text, blanks or
    //the filled-in copy), the name the person signs under and a masked hint of the address they have to confirm. Opening the page is
    //logged on every agreement still waiting (once per person per ten minutes). $request is array(ip, user_agent).
    //The first agreement's fields are repeated at the top level for the single-document page.
    function prepare_public_page($bundle_id, $token, $request) {
        $bundle = $this->find_bundle($bundle_id, $token);
        if (!$bundle) {
            return null;
        }

        $contracts = $this->Talent_contract_model->get_for_bundle($bundle->id);
        if (!$contracts) {
            return null;
        }

        $documents = array();
        $waiting = 0;
        foreach ($contracts as $contract) {
            $status = talent_contract_effective_status($contract->status, $contract->token_expires_at);
            if ($status === "sent") {
                $waiting++;
                $this->_record_view($contract, $request);
            }

            $documents[] = array("contract" => $contract, "status" => $status, "html" => $this->_display_html($contract, $status), "label" => talent_contract_label($contract->id));
        }

        $company = $this->_company();

        return array(
            "bundle" => $bundle,
            "documents" => $documents,
            "waiting_count" => $waiting,
            "signer_name" => $this->_signer_name($contracts[0]),
            "masked_email" => talent_mask_email($bundle->sent_to_email),
            "company_name" => (string) $company->name,
            "contract" => $documents[0]["contract"],
            "status" => $documents[0]["status"],
            "html" => $documents[0]["html"],
            "label" => $documents[0]["label"],
        );
    }

    //The contract as staff see it from the project's list: the text as sent (or as signed), its state and its activity trail.
    //Opening it is not logged; the trail is about what the talent did. Null for an unknown contract.
    function get_contract_view($contract_id) {
        if (!is_numeric($contract_id)) {
            return null;
        }

        $contract = $this->Talent_contract_model->get_public($contract_id);
        if (!$contract || !$contract->id || $contract->deleted) {
            return null;
        }

        $status = talent_contract_effective_status($contract->status, $contract->token_expires_at);

        //only the newest contract of an agreement on a casting link can be sent again
        $newest = $this->Talent_contract_model->get_latest_for_agreement($contract->talent_project_id, $contract->template_id);

        return array(
            "contract" => $contract,
            "status" => $status,
            "is_latest" => ($newest && (int) $newest->id === (int) $contract->id) ? true : false,
            "is_paper" => $contract->signed_via === "paper",
            //a paper contract has no text of its own: the scan is the document
            "was_signed" => $contract->signed_at ? true : false,
            "html" => $contract->signed_via === "paper" ? "" : $this->_display_html($contract, $contract->signed_at ? "signed" : $status),
            "label" => talent_contract_label($contract->id),
            "signer_name" => $this->_signer_name($contract),
            "events" => $this->Talent_contract_event_model->get_for_contract($contract->id)->getResult(),
            "bundle_mates" => $this->Talent_contract_model->get_bundle_mates($contract->id),
        );
    }

    //The signed PDF for a staff member, on their own login instead of a token. Same hash check as the talent's download.
    function get_signed_pdf_for_staff($contract_id, $actor) {
        $view = $this->get_contract_view($contract_id);
        if (!$view) {
            return null;
        }

        $pdf = $this->_stored_pdf($view["contract"], true);
        if ($pdf) {
            $this->Talent_contract_event_model->log($view["contract"]->id, "downloaded", $actor);
        }
        return $pdf;
    }

    //The talent signs the agreements they ticked ($contract_ids) with one drawn signature. Each gets its own signed PDF; then, under row
    //locks, every ticked agreement is checked again and signed together with its audit trail, and the card is settled once. If any of them
    //stopped waiting while the page was open (withdrawn, answered, lapsed, link replaced) nothing is signed, so what was signed is what the
    //person saw. Staff get one notification, after the commit.
    function complete($bundle_id, $token, $contract_ids, $email, $signature, $request) {
        $bundle = $this->find_bundle($bundle_id, $token);
        if (!$bundle) {
            return $this->_fail("talent_sign_error_invalid");
        }

        $ids = array();
        foreach ((array) $contract_ids as $contract_id) {
            if (is_numeric($contract_id) && !in_array((int) $contract_id, $ids, true)) {
                $ids[] = (int) $contract_id;
            }
        }
        sort($ids);
        if (!$ids) {
            return $this->_fail("talent_sign_error_consent");
        }

        $contracts = array();
        foreach ($ids as $contract_id) {
            $contract = $this->_contract_of_bundle($bundle, $contract_id);
            if (!$contract) {
                return $this->_fail("talent_sign_error_invalid");
            }

            $state = talent_contract_effective_status($contract->status, $contract->token_expires_at);
            if ($state !== "sent") {
                return count($ids) === 1 ? $this->_fail_for_state($state) : $this->_fail("talent_sign_error_changed");
            }
            $contracts[] = $contract;
        }

        //nothing is typed: the talent signs under the legal name each contract was made out to
        $email = trim((string) $email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strcasecmp($email, (string) $bundle->sent_to_email) !== 0) {
            return $this->_fail("talent_sign_error_email");
        }
        foreach ($contracts as $contract) {
            if ($this->_signer_name($contract) === "") {
                return $this->_fail("talent_sign_error_name");
            }
        }

        $png = $this->_read_signature($signature);
        if ($png === null) {
            return $this->_fail("talent_sign_error_signature");
        }

        $now = get_current_utc_time();
        $actor = $this->_talent_actor($contracts[0], $request);

        //the consent wording on the page depends on whether the link holds one agreement or several; that same text goes on the record
        $consent_text = app_lang(count($this->Talent_contract_model->get_for_bundle($bundle->id)) > 1 ? "talent_sign_consent_many" : "talent_sign_consent");

        //one signed PDF per agreement, built (and encrypted for storage) before any lock is taken
        $pdfs = array();
        try {
            foreach ($contracts as $contract) {
                $pdf = $this->_build_pdf($contract, array("name" => $this->_signer_name($contract), "email" => $email, "signed_at" => $now, "ip" => $actor["ip"], "consent" => $consent_text), $png);
                $pdfs[(int) $contract->id] = array("stored" => talent_encrypt($pdf), "hash" => hash("sha256", $pdf));
            }
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        $talent_project_id = (int) $contracts[0]->talent_project_id;
        $db = db_connect('default');
        $db->transBegin();

        try {
            //the card, then the bundle, then the contracts in id order: one lock order for everything that touches them, so they can't deadlock
            $db->query("SELECT id FROM " . $db->prefixTable("talent_projects") . " WHERE id=" . $talent_project_id . " FOR UPDATE");
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contract_bundles") . " WHERE id=" . (int) $bundle->id . " FOR UPDATE");
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contracts") . " WHERE id IN (" . implode(",", $ids) . ") ORDER BY id FOR UPDATE");

            //a link that was replaced while this request waited for the lock is a dead link
            $fresh_bundle = $this->Talent_contract_bundle_model->get_existing($bundle->id);
            if (!$fresh_bundle || !hash_equals((string) $fresh_bundle->token_hash, hash("sha256", $token))) {
                $db->transRollback();
                return $this->_fail("talent_sign_error_invalid");
            }

            foreach ($ids as $contract_id) {
                $latest = $this->Talent_contract_model->get_public($contract_id);
                $state = talent_contract_effective_status($latest->status, $latest->token_expires_at);
                if ($state !== "sent") {
                    $db->transRollback();
                    return count($ids) === 1 ? $this->_fail_for_state($state) : $this->_fail("talent_sign_error_changed");
                }
            }

            $template_ids = array();
            foreach ($contracts as $contract) {
                $signed = array(
                    "status" => "signed",
                    "signer_name" => $this->_signer_name($contract),
                    "signer_email" => $email,
                    "signed_at" => $now,
                    "signed_via" => "online",
                    "signature_data" => base64_encode($png),
                    "signed_pdf_data" => $pdfs[(int) $contract->id]["stored"],
                    "pdf_hash" => $pdfs[(int) $contract->id]["hash"],
                    "signer_ip" => $actor["ip"],
                    "signer_user_agent" => $actor["user_agent"],
                );
                $this->_must($this->Talent_contract_model->ci_save($signed, $contract->id), "record the signature");
                $template_ids[] = (int) $contract->template_id;

                $this->_must($this->Talent_contract_event_model->log($contract->id, "signed", $actor, array(
                            "name" => $this->_signer_name($contract),
                            "email" => $email,
                            "consent" => $consent_text,
                            "content_hash" => $contract->content_hash,
                            "pdf_hash" => $pdfs[(int) $contract->id]["hash"],
                            "bundle_id" => (int) $bundle->id,
                            "signed_together" => array_values(array_diff($ids, array((int) $contract->id))),
                        )), "log the signature");
            }

            //the signatures that complete the project's required list are what confirm the card (see _settle_confirmation), once for the whole signing
            $assignment = $this->Talent_project_model->get_one($talent_project_id);
            $outcome = $this->_settle_confirmation($talent_project_id, $template_ids);
            $confirmed = $outcome && $outcome["moved"];

            if ($outcome) {
                $last_id = end($ids);
                $this->_must($this->Talent_contract_event_model->log($last_id, $outcome["moved"] ? "card_confirmed" : "confirm_skipped", array("type" => "system"), $outcome["moved"] ? array("stage_id" => $outcome["stage_id"]) : array("reason" => $outcome["reason"])), "log the card move");
            }

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        $this->_notify("talent_contract_signed", (int) $assignment->project_id, $ids[0]);

        return array(
            "success" => true,
            "message" => count($ids) === 1 ? app_lang("talent_sign_done_message") : sprintf(app_lang("talent_sign_done_many_message"), count($ids)),
            "confirmed" => $confirmed,
            "signed" => $ids,
        );
    }

    //Records that one agreement was turned down; the others in the link stay signable. The card is left alone; staff are told and can send it
    //again. The talent's own page no longer offers this (declining is out of scope there): the method and the declined state stay for the
    //staff side and for records that were declined earlier.
    function decline($bundle_id, $token, $contract_id, $reason, $request) {
        $bundle = $this->find_bundle($bundle_id, $token);
        $contract = $bundle ? $this->_contract_of_bundle($bundle, $contract_id) : null;
        if (!$contract) {
            return $this->_fail("talent_sign_error_invalid");
        }

        $state = talent_contract_effective_status($contract->status, $contract->token_expires_at);
        if ($state !== "sent") {
            return $this->_fail_for_state($state);
        }

        $reason = $this->_clean_text($reason, 1000);
        $actor = $this->_talent_actor($contract, $request);

        $db = db_connect('default');
        $db->transBegin();

        try {
            //the bundle, then the contract: the same order signing takes them in
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contract_bundles") . " WHERE id=" . (int) $bundle->id . " FOR UPDATE");
            $db->query("SELECT id FROM " . $db->prefixTable("talent_contracts") . " WHERE id=" . (int) $contract->id . " FOR UPDATE");

            $latest = $this->Talent_contract_model->get_public($contract->id);
            $state = talent_contract_effective_status($latest->status, $latest->token_expires_at);
            if ($state !== "sent") {
                $db->transRollback();
                return $this->_fail_for_state($state);
            }

            $fresh_bundle = $this->Talent_contract_bundle_model->get_existing($bundle->id);
            if (!$fresh_bundle || !hash_equals((string) $fresh_bundle->token_hash, hash("sha256", $token))) {
                $db->transRollback();
                return $this->_fail("talent_sign_error_invalid");
            }

            $declined = array("status" => "declined", "decline_reason" => $reason);
            $this->_must($this->Talent_contract_model->ci_save($declined, $contract->id), "record the decline");
            $meta = array("bundle_id" => (int) $bundle->id);
            if ($reason !== "") {
                $meta["reason"] = $reason;
            }
            $this->_must($this->Talent_contract_event_model->log($contract->id, "declined", $actor, $meta), "log the decline");

            $db->transCommit();
        } catch (\Throwable $ex) {
            $db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return $this->_fail("error_occurred");
        }

        $assignment = $this->Talent_project_model->get_one($contract->talent_project_id);
        $this->_notify("talent_contract_declined", (int) $assignment->project_id, $contract->id);

        return array("success" => true, "message" => app_lang("talent_sign_declined_message"));
    }

    //The signed PDF of one agreement in the link, or null. It is served only if it still matches the hash recorded when it was signed.
    function get_signed_pdf($bundle_id, $token, $contract_id, $request) {
        $bundle = $this->find_bundle($bundle_id, $token);
        $contract = $bundle ? $this->_contract_of_bundle($bundle, $contract_id) : null;
        if (!$contract) {
            return null;
        }

        $pdf = $this->_stored_pdf($contract);
        if ($pdf) {
            $this->Talent_contract_event_model->log($contract->id, "downloaded", $this->_talent_actor($contract, $request));
        }
        return $pdf;
    }

    //the stored signed PDF, only if the contract is signed and the bytes (once decrypted) still match the hash recorded at signing. Staff may also
    //fetch the copy of a contract that was signed and withdrawn afterwards; the talent's link never serves it.
    private function _stored_pdf($contract, $include_withdrawn = false) {
        if (!($contract->status === "signed" || ($include_withdrawn && $contract->status === "voided" && $contract->signed_at))) {
            return null;
        }

        //stored encrypted (or, from before that, as plain base64); a copy that can't be decrypted is treated like one that doesn't match
        $bytes = talent_read_pdf($this->Talent_contract_model->get_signed_pdf_data($contract->id));
        if (!$bytes || !hash_equals((string) $contract->pdf_hash, hash("sha256", $bytes))) {
            log_message('error', 'The signed PDF of contract ' . (int) $contract->id . ' is missing, can not be decrypted, or no longer matches its recorded hash.');
            return null;
        }

        return array("bytes" => $bytes, "file_name" => talent_contract_label($contract->id) . ".pdf");
    }

    //The name the talent signs under: the legal name fixed when the contract was sent. Contracts sent before that was recorded fall back
    //to the talent's legal name on file now.
    private function _signer_name($contract) {
        if (trim((string) $contract->signer_name) !== "") {
            return trim($contract->signer_name);
        }

        $assignment = $this->Talent_project_model->get_one($contract->talent_project_id);
        $talent = $this->Talent_model->get_one($assignment->talent_id);
        return trim((string) $talent->legal_name);
    }

    //the frozen text as a reader sees it: blanks while it is waiting, the filled-in copy (with the drawn signature) once it is signed
    private function _display_html($contract, $status) {
        if ($status !== "signed") {
            return $this->_render($contract->content, $this->_blank_signature_values());
        }

        $png = $contract->signature_data ? base64_decode($contract->signature_data, true) : null;
        return $this->_fill_signature($contract->content, $contract->signer_name, $contract->signer_email, $contract->signed_at, $png ? $png : null, false);
    }

    private function _fail($language_key) {
        return array("success" => false, "message" => app_lang($language_key));
    }

    //what to tell someone holding a link whose contract can't be answered any more
    private function _fail_for_state($state) {
        $keys = array(
            "signed" => "talent_sign_error_already_signed",
            "declined" => "talent_sign_error_declined",
            "expired" => "talent_sign_error_expired",
            "voided" => "talent_sign_error_voided",
        );
        return $this->_fail(isset($keys[$state]) ? $keys[$state] : "talent_sign_error_invalid");
    }

    //the person on the other end of a public request; ip and user agent are cut to the column sizes and stripped of 4-byte characters
    private function _talent_actor($contract, $request) {
        $assignment = $this->Talent_project_model->get_one($contract->talent_project_id);

        $ip = talent_strip_4byte_chars((string) get_array_value($request, "ip"));
        $user_agent = talent_strip_4byte_chars((string) get_array_value($request, "user_agent"));

        return array(
            "type" => "talent",
            "id" => (int) $assignment->talent_id,
            "ip" => substr($ip, 0, 45),
            "user_agent" => function_exists("mb_strcut") ? mb_strcut($user_agent, 0, 255, "UTF-8") : substr($user_agent, 0, 255),
        );
    }

    //a page that gets opened again and again (refreshes, mail scanners) shouldn't bury the trail
    private function _record_view($contract, $request) {
        $actor = $this->_talent_actor($contract, $request);

        $last = $this->Talent_contract_event_model->get_last($contract->id, "viewed");
        if ($last && $last->ip === $actor["ip"] && strtotime($last->created_at . " UTC") > time() - 600) {
            return;
        }

        $this->Talent_contract_event_model->log($contract->id, "viewed", $actor);
    }

    //staff are told by the system bot ("0"); a failure here must never undo an answer that is already saved
    private function _notify($event, $project_id, $contract_id) {
        if (!$project_id) {
            return;
        }

        try {
            log_notification($event, array("project_id" => $project_id, "plugin_talent_contract_id" => $contract_id), "0");
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
        }
    }

    //the four placeholders the frozen text keeps until it is signed, shown as blanks while it isn't
    private function _blank_signature_values() {
        $blank = "<span style=\"color: #999;\">________</span>";
        return array("SIGNER_NAME" => $blank, "SIGNER_EMAIL" => $blank, "SIGNING_DATE" => $blank, "SIGNATURE" => $blank);
    }

    //the signed copy of the text. The browser takes the image as a data URI, TCPDF only takes it as "@" + base64 (it silently drops data URIs).
    private function _fill_signature($content, $name, $email, $signed_at, $png, $for_pdf) {
        $image = "";
        if ($png !== null) {
            $encoded = base64_encode($png);
            $image = $for_pdf ? "<img class=\"signature-image\" src=\"@" . $encoded . "\" style=\"width: 240px;\" />" : "<img class=\"signature-image\" src=\"data:image/png;base64," . $encoded . "\" alt=\"\" style=\"max-width: 220px; height: auto;\" />";
        }

        return $this->_render($content, array(
                    "SIGNER_NAME" => $this->_e($name),
                    "SIGNER_EMAIL" => $this->_e($email),
                    "SIGNING_DATE" => $this->_e($this->_date($signed_at, true)),
                    "SIGNATURE" => $image,
        ));
    }

    //the signed agreement: the frozen text with the signature filled in, followed by the record that ties it to this signing
    private function _build_pdf($contract, $record, $png) {
        $body = $this->_fill_signature($contract->content, $record["name"], $record["email"], $record["signed_at"], $png, true);

        $pdf = new Talent_pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCellPadding(1.5);
        $pdf->setImageScale(1.42);
        $pdf->SetTitle($contract->title . " " . talent_contract_label($contract->id));
        $pdf->AddPage();

        $page_width_in_pixels = ($pdf->getPageWidth() / 25.4) * 91;

        $html = view('Talent_Management\Views\talent_sign\contract_pdf', array(
            "body" => $body,
            "record" => array(
                app_lang("talent_sign_record_contract") => talent_contract_label($contract->id) . " - " . $contract->title,
                app_lang("talent_sign_record_signer") => $record["name"] . " <" . $record["email"] . ">",
                app_lang("talent_sign_record_signed_at") => $record["signed_at"] . " UTC",
                app_lang("talent_sign_record_ip") => $record["ip"],
                app_lang("talent_sign_record_fingerprint") => $contract->content_hash,
                app_lang("talent_sign_record_consent") => $record["consent"],
            ),
        ));

        //core's rebuild_html() fixes table widths and image paths for TCPDF, but it also pins every <p> to line-height 16px, and under
        //that TCPDF squashes hard line breaks (a multi-line address, the notes) on top of each other. That one property is dropped again.
        $html = preg_replace('/(<p\b[^>]*?style="[^"]*?)\s*line-height:\s*16px;/i', '$1', rebuild_html($html, $page_width_in_pixels));

        $pdf->writeHTML($html, true, false, true, false, '');
        return $pdf->Output('', 'S');
    }

    //free text (a decline reason): line breaks are kept
    private function _clean_text($value, $max_length) {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', str_replace("\r\n", "\n", talent_strip_4byte_chars($value)));
        if ($value === null) {
            return "";
        }

        $value = trim($value);
        return function_exists("mb_substr") ? mb_substr($value, 0, $max_length, "UTF-8") : substr($value, 0, $max_length);
    }

    //A drawn signature arrives as a PNG data URI from the browser, i.e. from someone we don't trust. It is accepted only if it decodes as
    //a PNG of a sensible size with real ink on it, and is then re-encoded (flattened on white, capped in width) so only pixels are kept.
    //Returns the PNG bytes or null.
    private function _read_signature($data_uri) {
        if (!is_string($data_uri) || !preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', trim($data_uri), $match) || strlen($match[1]) > 900000) {
            return null;
        }

        $png = base64_decode($match[1], true);
        if ($png === false || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $info = @getimagesizefromstring($png);
        if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] < 100 || $info[1] < 40 || $info[0] > 2400 || $info[1] > 1200) {
            return null;
        }

        //without GD there is no way to look inside the image; the checks above still stand
        if (!function_exists("imagecreatefromstring")) {
            return $png;
        }

        //a damaged PNG makes GD warn; if warnings are turned into exceptions this must still be a refusal, not a server error
        $image = null;
        $flat = null;
        try {
            $image = @imagecreatefromstring($png);
            if (!$image) {
                return null;
            }
            imagepalettetotruecolor($image);

            if (!$this->_has_ink($image)) {
                return null;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $target_width = min($width, 900);
            $target_height = max(1, (int) round($height * ($target_width / $width)));

            $flat = imagecreatetruecolor($target_width, $target_height);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopyresampled($flat, $image, 0, 0, 0, 0, $target_width, $target_height, $width, $height);

            ob_start();
            imagepng($flat);
            $clean = ob_get_clean();

            return $clean ? $clean : null;
        } catch (\Throwable $ex) {
            return null;
        } finally {
            if ($image) {
                imagedestroy($image);
            }
            if ($flat) {
                imagedestroy($flat);
            }
        }
    }

    //The scan of a paper contract as PDF bytes: array("pdf" => bytes, "from_image" => bool), or array("error" => language key).
    //A PDF is taken as it is; a JPG or PNG photo is turned into a one-page PDF.
    private function _prepare_paper_scan($file, $title) {
        $path = (string) get_array_value($file, "path");
        if ($path === "" || !is_file($path) || !is_readable($path)) {
            return array("error" => "talent_contract_error_paper_missing");
        }

        $size = filesize($path);
        if (!$size) {
            return array("error" => "talent_contract_error_paper_missing");
        }
        if ($size > talent_contract_paper_max_bytes()) {
            return array("error" => "talent_contract_error_paper_too_large");
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === "") {
            return array("error" => "talent_contract_error_paper_missing");
        }

        if (substr($bytes, 0, 5) === "%PDF-") {
            //an upload that was cut short has no end marker
            if (strpos(substr($bytes, -2048), "%%EOF") === false) {
                return array("error" => "talent_contract_error_paper_invalid");
            }
            return array("pdf" => $bytes, "from_image" => false);
        }

        return $this->_image_to_pdf($bytes, $path, $title);
    }

    //A photo of the signed pages. The picture is re-encoded, which drops everything but the pixels (a phone photo carries its GPS position
    //in EXIF), turned upright if the phone stored it sideways, flattened on white and capped at 2400 px before it is placed on an A4 page.
    private function _image_to_pdf($bytes, $path, $title) {
        $info = function_exists("imagecreatefromstring") ? @getimagesizefromstring($bytes) : false;
        if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG), true) || $info[0] < 50 || $info[1] < 50) {
            return array("error" => "talent_contract_error_paper_type");
        }

        //the size is read from the header, so a PNG whose header isn't one would report nonsense
        if ($info[2] === IMAGETYPE_PNG && substr($bytes, 12, 4) !== "IHDR") {
            return array("error" => "talent_contract_error_paper_invalid");
        }

        //GD keeps the whole picture in memory at 4 bytes a pixel, and running out of memory can't be caught, so it is checked first
        if ($info[0] * $info[1] > 60000000 || !$this->_gd_fits_in_memory($info[0], $info[1])) {
            return array("error" => "talent_contract_error_paper_image_large");
        }

        $orientation = 1;
        if ($info[2] === IMAGETYPE_JPEG && function_exists("exif_read_data")) {
            try {
                $exif = @exif_read_data($path);
                if (is_array($exif) && isset($exif["Orientation"])) {
                    $orientation = (int) $exif["Orientation"];
                }
            } catch (\Throwable $ex) {
                $orientation = 1;
            }
        }

        $source = null;
        $flat = null;
        try {
            //a photo that was cut short decodes into a half-grey picture with only a warning, which would be filed as the signed copy
            $warnings = "";
            set_error_handler(function ($number, $message) use (&$warnings) {
                $warnings .= $message . "
";
                return true;
            });
            try {
                $source = imagecreatefromstring($bytes);
            } finally {
                restore_error_handler();
            }
            if (!$source || stripos($warnings, "premature end") !== false) {
                return array("error" => "talent_contract_error_paper_invalid");
            }
            imagepalettetotruecolor($source);

            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, 2400 / max($width, $height));
            $target_width = max(1, (int) round($width * $scale));
            $target_height = max(1, (int) round($height * $scale));

            $flat = imagecreatetruecolor($target_width, $target_height);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopyresampled($flat, $source, 0, 0, 0, 0, $target_width, $target_height, $width, $height);

            //the big bitmap is not needed any more; the rotation below works on the reduced one
            imagedestroy($source);
            $source = null;

            $angles = array(3 => 180, 6 => -90, 8 => 90);
            if (isset($angles[$orientation])) {
                $upright = imagerotate($flat, $angles[$orientation], imagecolorallocate($flat, 255, 255, 255));
                if ($upright) {
                    imagedestroy($flat);
                    $flat = $upright;
                }
            }

            $target_width = imagesx($flat);
            $target_height = imagesy($flat);

            ob_start();
            imagejpeg($flat, null, 85);
            $jpeg = ob_get_clean();
            if (!$jpeg) {
                return array("error" => "talent_contract_error_paper_type");
            }

            $pdf = new Talent_pdf();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetTitle($title);
            $pdf->AddPage($target_width > $target_height ? "L" : "P", "A4");

            $box_width = $pdf->getPageWidth() - 20;
            $box_height = $pdf->getPageHeight() - 20;
            $ratio = min($box_width / $target_width, $box_height / $target_height);
            $draw_width = $target_width * $ratio;
            $draw_height = $target_height * $ratio;
            $pdf->Image("@" . $jpeg, 10 + ($box_width - $draw_width) / 2, 10 + ($box_height - $draw_height) / 2, $draw_width, $draw_height, "JPG");

            return array("pdf" => $pdf->Output("", "S"), "from_image" => true);
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return array("error" => "talent_contract_error_paper_type");
        } finally {
            if ($source) {
                imagedestroy($source);
            }
            if ($flat) {
                imagedestroy($flat);
            }
        }
    }

    //will a picture of this size fit in what is left of PHP's memory limit (the decoded bitmap plus room for the reduced copy and the PDF)?
    private function _gd_fits_in_memory($width, $height) {
        $limit = trim((string) ini_get("memory_limit"));
        if ($limit === "" || $limit === "-1") {
            return true;
        }

        $bytes = (float) $limit;
        switch (strtolower(substr($limit, -1))) {
            case "g":
                $bytes *= 1024;
            case "m":
                $bytes *= 1024;
            case "k":
                $bytes *= 1024;
        }

        return memory_get_usage() + $width * $height * 4.8 + 24 * 1048576 <= $bytes;
    }

    //a blank pad exports a plain white (or fully transparent) image, so a few dozen dark opaque pixels is what separates a signature from nothing
    private function _has_ink($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        $ink = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $alpha = ($pixel >> 24) & 0x7F; //127 is fully transparent
                $brightness = (($pixel >> 16) & 0xFF) + (($pixel >> 8) & 0xFF) + ($pixel & 0xFF);

                if ($alpha < 100 && $brightness < 600 && ++$ink >= 40) {
                    return true;
                }
            }
        }

        return false;
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
    //$titles are the agreements in this link: one title reads as itself, several as "3 agreements" with the full list in the body.
    private function _send_email($context, $to, $titles, $link, $expires_at) {
        $Email_templates_model = model("App\Models\Email_templates_model");
        $company = $this->_company();

        $template = $Email_templates_model->get_final_template("talent_contract_request", true);
        $default = talent_contract_default_email();
        $subject = get_array_value($template, "subject_default") ? get_array_value($template, "subject_default") : $default["subject"];
        $message = get_array_value($template, "message_default") ? get_array_value($template, "message_default") : $default["message"];

        $titles = array_values((array) $titles);
        $title = count($titles) === 1 ? $titles[0] : sprintf(app_lang("talent_contract_bundle_title"), count($titles));
        $list = "<ul>" . implode("", array_map(function ($item) {
                            return "<li>" . $this->_e($item) . "</li>";
                        }, $titles)) . "</ul>";

        $name = $context->preferred_name ? $context->preferred_name : $context->legal_name;
        $expiry_date = $this->_date($expires_at, true);

        $html_values = array(
            "TALENT_NAME" => $this->_e($name),
            "PROJECT_TITLE" => $this->_e($context->project_title),
            "CONTRACT_TITLE" => $this->_e($title),
            "AGREEMENT_LIST" => $list,
            "CONTRACT_URL" => $this->_e($link),
            "EXPIRY_DATE" => $this->_e($expiry_date),
            "COMPANY_NAME" => $this->_e($company->name),
            "LOGO_URL" => $this->_e(get_logo_url()),
            "SIGNATURE" => (string) get_array_value($template, "signature_default"),
            "RECIPIENTS_EMAIL_ADDRESS" => $this->_e($to),
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
            return send_app_mail($to, $this->_render($subject, $subject_values), $this->_render($message, $html_values), array(), false) ? true : false;
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return false;
        }
    }
}
