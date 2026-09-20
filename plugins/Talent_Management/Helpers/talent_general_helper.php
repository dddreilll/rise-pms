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
