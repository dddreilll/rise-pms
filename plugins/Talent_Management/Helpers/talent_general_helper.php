<?php

//feather icon for a social platform name, falling back to a generic link icon
if (!function_exists('talent_social_icon')) {

    function talent_social_icon($platform = "") {
        $platform = strtolower($platform);
        foreach (array("facebook", "twitter", "instagram", "linkedin", "youtube", "github", "twitch") as $name) {
            if (strpos($platform, $name) !== false) {
                return $name;
            }
        }
        if (strpos($platform, "x.com") !== false || $platform === "x") {
            return "twitter";
        }
        return "link";
    }
}

if (!function_exists('talent_can_access_staff')) {

    function talent_can_access_staff() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user || $ci->login_user->user_type !== "staff") {
            return false;
        }
        if ($ci->login_user->is_admin) {
            return true;
        }
        return get_array_value($ci->login_user->permissions, "talent_management") ? true : false;
    }
}

//contract wording is legally sensitive, so templates are admin-only (sending contracts only needs the talent permission)
if (!function_exists('talent_can_manage_contract_templates')) {

    function talent_can_manage_contract_templates() {
        $ci = new \App\Controllers\Security_Controller(false);
        return ($ci->login_user && $ci->login_user->user_type === "staff" && $ci->login_user->is_admin) ? true : false;
    }
}

//who a staff request comes from, as the contract audit trail records it
if (!function_exists('talent_staff_actor')) {

    function talent_staff_actor($login_user, $request) {
        return array(
            "type" => "staff",
            "id" => $login_user->id,
            "ip" => $request->getIPAddress(),
            "user_agent" => $request->getUserAgent()->getAgentString(),
        );
    }
}

//merge fields a contract template can use; the template editor lists them and the contract renderer fills them
if (!function_exists('talent_contract_available_variables')) {

    function talent_contract_available_variables() {
        return array(
            "CONTRACT_ID", "CONTRACT_TITLE", "CONTRACT_DATE", "CONTRACT_NOTES",
            "TALENT_LEGAL_NAME", "TALENT_PREFERRED_NAME", "TALENT_EMAIL", "TALENT_ADDRESS", "TALENT_PROFESSION", "TALENT_ON_SCREEN_TITLE",
            "PROJECT_TITLE", "PROJECT_START_DATE", "PROJECT_DEADLINE",
            "COMPANY_NAME", "COMPANY_ADDRESS", "COMPANY_PHONE", "COMPANY_EMAIL", "COMPANY_WEBSITE",
            //empty until the talent has signed
            "SIGNER_NAME", "SIGNER_EMAIL", "SIGNING_DATE", "SIGNATURE",
        );
    }
}

//RISE talks to MySQL over a 3-byte utf8 connection, and with sql_mode='' a 4-byte character (an emoji) silently cuts off the rest
//of the string. Numeric entities keep the text intact and render identically in HTML, so contract text goes through this before saving.
if (!function_exists('talent_encode_4byte_chars')) {

    function talent_encode_4byte_chars($html) {
        $encoded = preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', function ($match) {
            $bytes = $match[0];
            $code_point = ((ord($bytes[0]) & 0x07) << 18) | ((ord($bytes[1]) & 0x3F) << 12) | ((ord($bytes[2]) & 0x3F) << 6) | (ord($bytes[3]) & 0x3F);
            return "&#" . $code_point . ";";
        }, $html);

        //null means the input wasn't valid UTF-8; keep it untouched rather than wiping the text
        return $encoded === null ? $html : $encoded;
    }
}

//structure only, no terms: the wording is the admin's (and their lawyer's) to write
if (!function_exists('talent_contract_default_template')) {

    function talent_contract_default_template() {
        return "<h2 style=\"text-align: center;\">{CONTRACT_TITLE}</h2>"
            . "<p>Date: {CONTRACT_DATE}</p>"
            . "<p>This agreement is between <strong>{COMPANY_NAME}</strong> and <strong>{TALENT_LEGAL_NAME}</strong> for the project <strong>{PROJECT_TITLE}</strong>.</p>"
            . "<p>[Add your terms here.]</p>"
            . "<p>{CONTRACT_NOTES}</p>"
            . "<table style=\"width: 100%;\"><tbody><tr>"
            . "<td style=\"width: 50%;\">Signed by: {SIGNER_NAME}<br />Date: {SIGNING_DATE}</td>"
            . "<td style=\"width: 50%;\">{SIGNATURE}</td>"
            . "</tr></tbody></table>";
    }
}

//Starter agreements: the layout and merge fields only. Every "[...]" is a prompt for the wording the company (and its lawyer) writes;
//none of the terms are supplied here. Keyed so each one is added once and never put back if an admin deletes it.
if (!function_exists('talent_starter_templates')) {

    function talent_starter_templates() {
        $section = function ($heading, $prompt) {
            return "<h4>" . $heading . "</h4><p>[" . $prompt . "]</p>";
        };

        $opening = function ($what) {
            return "<h2 style=\"text-align: center;\">{CONTRACT_TITLE}</h2>"
                . "<p>Date: {CONTRACT_DATE}</p>"
                . "<p>This " . $what . " is between <strong>{COMPANY_NAME}</strong> (the \"Company\") and <strong>{TALENT_LEGAL_NAME}</strong> (the \"Talent\") in connection with the project <strong>{PROJECT_TITLE}</strong>.</p>";
        };

        $closing = "<p>{CONTRACT_NOTES}</p>"
            . "<table style=\"width: 100%;\"><tbody><tr>"
            . "<td style=\"width: 50%;\">Signed by: {SIGNER_NAME}<br />Date: {SIGNING_DATE}</td>"
            . "<td style=\"width: 50%;\">{SIGNATURE}</td>"
            . "</tr></tbody></table>";

        return array(
            "nda" => array(
                "title" => "Non-Disclosure & Confidentiality Agreement",
                "content" => $opening("agreement")
                . $section("1. Confidential information", "Define what counts as confidential information.")
                . $section("2. What the Talent must and must not do", "State how confidential information may be used, shared and protected.")
                . $section("3. Exceptions", "List what is not treated as confidential.")
                . $section("4. How long this lasts", "State the period the obligations continue for.")
                . $section("5. Return or destruction of material", "State what happens to material when the project ends.")
                . $section("6. Governing law", "State which law applies and where disputes are handled.")
                . $closing,
            ),
            "conduct" => array(
                "title" => "Code of Conduct & Anti-Harassment Policy",
                "content" => $opening("policy")
                . $section("1. Purpose and who it applies to", "Explain why the policy exists and who it covers.")
                . $section("2. Expected conduct", "Describe the behaviour expected of everyone on set and around the production.")
                . $section("3. Unacceptable conduct", "Describe harassment, discrimination and other conduct that is not allowed.")
                . $section("4. Raising a concern", "Explain how and to whom a concern can be reported, and how it is handled.")
                . $section("5. No retaliation", "State how people who raise concerns are protected.")
                . $section("6. Consequences", "State what can happen when the policy is broken.")
                . $section("7. Acknowledgement", "State what the Talent confirms by signing.")
                . $closing,
            ),
        );
    }
}

//one line saying what a project requires of its talent (plain text: callers escape it, or set it with .text())
if (!function_exists('talent_project_agreements_summary')) {

    function talent_project_agreements_summary($templates) {
        if (!$templates) {
            return app_lang("talent_project_agreements_none");
        }

        $titles = array_map(function ($template) {
            return $template->title;
        }, $templates);

        return sprintf(app_lang("talent_project_agreements_summary"), implode(", ", $titles));
    }
}

//how long an emailed signing link stays valid
if (!function_exists('talent_contract_expiry_days')) {

    function talent_contract_expiry_days() {
        return 14;
    }
}

//the largest scan that can be attached to a contract signed on paper, in bytes. It is stored in the database as base64 text (a third
//bigger) in a single statement, so the limit follows the server's max_allowed_packet (4 MB on a stock MySQL 5.7) up to 5 MB.
if (!function_exists('talent_contract_paper_max_bytes')) {

    function talent_contract_paper_max_bytes() {
        static $max = null;

        if ($max === null) {
            $max = 5 * 1024 * 1024;

            try {
                $row = db_connect('default')->query("SELECT @@max_allowed_packet AS packet_size")->getRow();
                if ($row && (int) $row->packet_size > 0) {
                    $max = max(0, min($max, (int) floor(((int) $row->packet_size - 262144) * 3 / 4)));
                }
            } catch (\Throwable $ex) {
                //keep the default; the insert itself still fails cleanly if the server turns out to be smaller
            }
        }

        return $max;
    }
}

//the same limit as the number shown to people, in MB with one decimal, rounded down so nothing under it is refused
if (!function_exists('talent_contract_paper_max_mb')) {

    function talent_contract_paper_max_mb() {
        return floor(talent_contract_paper_max_bytes() / 104857.6) / 10;
    }
}

//the number people quote when they talk about a contract, e.g. TC-00042
if (!function_exists('talent_contract_label')) {

    function talent_contract_label($contract_id) {
        return "TC-" . str_pad((int) $contract_id, 5, "0", STR_PAD_LEFT);
    }
}

//a contract still marked "sent" whose link has lapsed is expired, whether or not anything has updated the row yet
if (!function_exists('talent_contract_effective_status')) {

    function talent_contract_effective_status($status, $token_expires_at = "") {
        if ($status === "sent" && $token_expires_at && strtotime($token_expires_at . " UTC") < time()) {
            return "expired";
        }
        return (string) $status;
    }
}

//badge for the newest contract of a casting link; empty when nothing has been sent yet
if (!function_exists('talent_contract_status_html')) {

    function talent_contract_status_html($contract_status, $token_expires_at = "") {
        if (!$contract_status) {
            return "";
        }

        $status = talent_contract_effective_status($contract_status, $token_expires_at);
        $colors = array("sent" => "#ef6c00", "signed" => "#2e7d32", "declined" => "#c62828", "expired" => "#7c8798", "voided" => "#7c8798");
        $color = isset($colors[$status]) ? $colors[$status] : "#7c8798";

        return "<span class='badge' style='background-color: $color'>" . app_lang("talent_contract_status_" . $status) . "</span>";
    }
}

//the "Send contract" button, offered while nothing is out for signature and nothing is signed
if (!function_exists('talent_contract_send_action_html')) {

    function talent_contract_send_action_html($talent_project_id, $contract_status = "", $token_expires_at = "") {
        $status = talent_contract_effective_status($contract_status, $token_expires_at);
        if ($status === "sent" || $status === "signed") {
            return "";
        }

        $label = $status ? app_lang("talent_contract_send_again") : app_lang("talent_contract_send");
        return modal_anchor(get_uri("talent_contracts/send_modal_form"), "<i data-feather='send' class='icon-14'></i> " . $label, array("class" => "btn btn-default btn-sm", "title" => app_lang("talent_contract_send"), "data-post-talent_project_id" => $talent_project_id));
    }
}

//opens the contract of a row (as sent, or as signed) with its activity and, once signed, the PDF
if (!function_exists('talent_contract_view_action_html')) {

    function talent_contract_view_action_html($contract_id) {
        if (!$contract_id) {
            return "";
        }

        return modal_anchor(get_uri("talent_contracts/view_modal_form"), "<i data-feather='eye' class='icon-16'></i>", array("class" => "btn btn-default btn-sm", "title" => app_lang("talent_contract_view"), "data-post-contract_id" => $contract_id, "data-modal-lg" => "1"));
    }
}

//badge + view + send buttons for one row of the project's talent list or one kanban card
if (!function_exists('talent_contract_cell_html')) {

    function talent_contract_cell_html($row) {
        $badge = talent_contract_status_html($row->contract_status, $row->contract_expires_at);
        $view = talent_contract_view_action_html($row->contract_id);
        $action = talent_contract_send_action_html($row->talent_project_id, $row->contract_status, $row->contract_expires_at);
        return trim(implode(" ", array_filter(array($badge, $view, $action))));
    }
}

//the mail that carries the signing link; also what gets seeded as the editable "Contract request" email template
if (!function_exists('talent_contract_default_email')) {

    function talent_contract_default_email() {
        return array(
            "subject" => "Please review and sign your agreement for {PROJECT_TITLE}",
            "message" => "<div style=\"background-color: #eeeeef; padding: 50px 0;\"><div style=\"max-width:640px; margin:0 auto;\">"
            . "<div style=\"color: #fff; text-align: center; background-color:#33333e; padding: 30px; border-top-left-radius: 3px; border-top-right-radius: 3px; margin: 0;\"><h1>Your agreement is ready</h1></div>"
            . "<div style=\"padding: 20px; background-color: rgb(255, 255, 255); color: #555; font-size: 14px;\">"
            . "<p>Hello {TALENT_NAME},</p>"
            . "<p>{COMPANY_NAME} has prepared an agreement for you for <strong>{PROJECT_TITLE}</strong>. Please review and sign it online:</p>"
            . "<p><a href=\"{CONTRACT_URL}\" target=\"_blank\">Review and sign</a></p>"
            . "<p>This link is personal to you, so please don't forward it. It expires on {EXPIRY_DATE}.</p>"
            . "<p>{SIGNATURE}</p>"
            . "</div></div></div>",
        );
    }
}

//jordan@example.com -> j*****@example.com: enough to recognise the address on the signing page without handing it to whoever holds the link
if (!function_exists('talent_mask_email')) {

    function talent_mask_email($email) {
        $parts = explode("@", (string) $email, 2);
        if (count($parts) !== 2 || $parts[0] === "") {
            return "";
        }
        return substr($parts[0], 0, 1) . str_repeat("*", max(1, min(5, strlen($parts[0]) - 1))) . "@" . $parts[1];
    }
}

//text typed by a signer goes into 3-byte utf8 columns; a 4-byte character (emoji) would cut the rest of the value off, so it is dropped
if (!function_exists('talent_strip_4byte_chars')) {

    function talent_strip_4byte_chars($text) {
        $stripped = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string) $text);

        //null means the input wasn't valid UTF-8
        return $stripped === null ? "" : $stripped;
    }
}

//the lines added under a "signed" / "declined" notification: which contract, and who answered it
if (!function_exists('talent_contract_notification_description')) {

    function talent_contract_notification_description($contract_id) {
        $db = db_connect('default');
        $prefix = get_db_prefix();

        $row = $db->query("SELECT c.id, c.title, c.status, c.signer_name, t.legal_name, t.preferred_name
                FROM `" . $prefix . "talent_contracts` c
                LEFT JOIN `" . $prefix . "talent_projects` tp ON tp.id=c.talent_project_id
                LEFT JOIN `" . $prefix . "talent` t ON t.id=tp.talent_id
                WHERE c.id=" . (int) $contract_id)->getRow();
        if (!$row) {
            return "";
        }

        //a signature is under the legal name; otherwise use the name staff know the person by
        $who = ($row->status === "signed" && $row->signer_name) ? $row->signer_name : ($row->preferred_name ? $row->preferred_name : $row->legal_name);

        return "<div>" . esc($row->title) . " (" . esc(talent_contract_label($row->id)) . ")</div>"
                . "<div>" . app_lang("talent") . ": " . esc($who) . "</div>";
    }
}
