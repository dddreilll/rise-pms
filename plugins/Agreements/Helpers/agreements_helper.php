<?php

if (!function_exists('agreements_ci_save')) {

    /**
     * Crud_model::ci_save requires &$data; PHP 8+ rejects temporary arrays.
     */
    function agreements_ci_save($model, $data, $id = 0) {
        return $model->ci_save($data, $id);
    }
}

if (!function_exists('agreements_files_path')) {

    function agreements_files_path($subdir = "") {
        $path = PLUGINPATH . "Agreements/files/";
        if ($subdir) {
            $path .= trim($subdir, "/") . "/";
        }
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }
}

if (!function_exists('agreements_files_url')) {

    function agreements_files_url($relative = "") {
        return base_url(PLUGIN_URL_PATH . "Agreements/files/" . ltrim($relative, "/"));
    }
}

if (!function_exists('agreements_get_setting')) {

    function agreements_get_setting($name, $default = "") {
        $model = new \Agreements\Models\Agreements_Settings_Model();
        $row = $model->get_setting($name);
        if ($row === null || $row === "") {
            return $default;
        }
        return $row;
    }
}

if (!function_exists('agreements_save_setting')) {

    function agreements_save_setting($name, $value) {
        $model = new \Agreements\Models\Agreements_Settings_Model();
        return $model->save_setting($name, $value);
    }
}

if (!function_exists('agreements_can_access_staff')) {

    function agreements_can_access_staff() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user || $ci->login_user->user_type !== "staff") {
            return false;
        }
        if ($ci->login_user->is_admin) {
            return true;
        }
        return get_array_value($ci->login_user->permissions, "agreements") ? true : false;
    }
}

if (!function_exists('agreements_can_access_client')) {

    function agreements_can_access_client() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user || $ci->login_user->user_type !== "client") {
            return false;
        }
        return agreements_get_setting("client_can_access_agreements") === "1";
    }
}

if (!function_exists('agreements_can_access')) {

    function agreements_can_access() {
        $ci = new \App\Controllers\Security_Controller(false);
        if (!$ci->login_user) {
            return false;
        }
        if ($ci->login_user->user_type === "staff") {
            return agreements_can_access_staff();
        }
        return agreements_can_access_client();
    }
}

if (!function_exists('agreements_default_template_html')) {

    function agreements_default_template_html() {
        $file = PLUGINPATH . "Agreements/install/default_template.html";
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return "<p><strong>{{agreement_title}}</strong></p><p>This agreement is between {{company_name}} and {{client_name}}.</p><p>Date: {{current_date}}</p>";
    }
}

if (!function_exists('agreements_ensure_default_template')) {

    /**
     * Ensure a default HTML template exists and default_template_id is set.
     * Safe to call during install/activation (uses raw SQL) or later.
     * @return int default template id
     */
    function agreements_ensure_default_template() {
        $db = db_connect('default');
        $db_prefix = get_db_prefix();
        $templates_table = $db_prefix . "agreements_templates";
        $settings_table = $db_prefix . "agreements_settings";

        // Ensure settings table keys exist
        $db->query("INSERT IGNORE INTO `{$settings_table}` (`setting_name`, `setting_value`, `type`, `deleted`) VALUES
            ('client_can_access_agreements', '', 'app', 0),
            ('default_reminder_days', '3', 'app', 0),
            ('default_template_id', '', 'app', 0)");

        $default_id = "";
        $row = $db->query("SELECT setting_value FROM `{$settings_table}` WHERE setting_name='default_template_id' AND deleted=0 LIMIT 1")->getRow();
        if ($row) {
            $default_id = $row->setting_value;
        }

        if ($default_id) {
            $exists = $db->query("SELECT id FROM `{$templates_table}` WHERE id=" . (int) $default_id . " AND deleted=0 LIMIT 1")->getRow();
            if ($exists) {
                return (int) $default_id;
            }
        }

        // Prefer any existing non-deleted template as default
        $any = $db->query("SELECT id FROM `{$templates_table}` WHERE deleted=0 ORDER BY id ASC LIMIT 1")->getRow();
        if ($any) {
            $db->query("UPDATE `{$settings_table}` SET setting_value='" . (int) $any->id . "' WHERE setting_name='default_template_id'");
            return (int) $any->id;
        }

        $title = "Default Agreement Template";
        $content = agreements_default_template_html();
        $now = function_exists("get_current_utc_time") ? get_current_utc_time() : date("Y-m-d H:i:s");
        $builder = $db->table($templates_table);
        $builder->insert(array(
            "title" => $title,
            "document_type" => "html",
            "content" => $content,
            "pdf_path" => "",
            "fields_json" => "",
            "created_by" => 0,
            "deleted" => 0,
            "created_at" => $now,
        ));
        $new_id = (int) $db->insertID();
        if ($new_id) {
            $db->table($settings_table)->where("setting_name", "default_template_id")->update(array("setting_value" => (string) $new_id));
        }
        return $new_id;
    }
}

if (!function_exists('agreements_get_default_template')) {

    function agreements_get_default_template() {
        $id = agreements_ensure_default_template();
        if (!$id) {
            return null;
        }
        $model = new \Agreements\Models\Agreements_Templates_Model();
        $template = $model->get_one($id);
        return ($template && $template->id) ? $template : null;
    }
}

if (!function_exists('agreements_get_id')) {

    function agreements_get_id($document_id) {
        return strtoupper(app_lang("agreements")) . " #" . $document_id;
    }
}

if (!function_exists('agreements_status_label')) {

    function agreements_status_label($status, $html = false) {
        $key = "agreements_status_" . $status;
        $label = app_lang($key);
        if ($label === $key) {
            $label = ucfirst(str_replace("_", " ", $status));
        }
        if (!$html) {
            return $label;
        }

        $class = "bg-secondary";
        if ($status === "draft") {
            $class = "bg-dark";
        } else if ($status === "sent" || $status === "partially_signed") {
            $class = "bg-info";
        } else if ($status === "completed") {
            $class = "bg-success";
        } else if ($status === "declined" || $status === "cancelled" || $status === "expired") {
            $class = "bg-danger";
        }

        return "<span class='badge $class'>" . $label . "</span>";
    }
}

if (!function_exists('agreements_generate_token')) {

    function agreements_generate_token() {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('agreements_hash_token')) {

    function agreements_hash_token($token) {
        return hash("sha256", $token);
    }
}

if (!function_exists('agreements_sign_url')) {

    function agreements_sign_url($token) {
        return get_uri("agreements_sign/" . $token);
    }
}

if (!function_exists('agreements_client_ip')) {

    function agreements_client_ip() {
        $request = \Config\Services::request();
        return $request->getIPAddress();
    }
}

if (!function_exists('agreements_audit')) {

    function agreements_audit($document_id, $event, $actor_email = "", $actor_name = "", $meta = array()) {
        $model = new \Agreements\Models\Agreements_Audit_Logs_Model();
        agreements_ci_save($model, array(
            "document_id" => $document_id,
            "event" => $event,
            "actor_email" => $actor_email,
            "actor_name" => $actor_name,
            "ip" => agreements_client_ip(),
            "meta_json" => $meta ? json_encode($meta) : "",
            "created_at" => get_current_utc_time(),
        ));
    }
}

if (!function_exists('agreements_crm_merge_values')) {

    function agreements_crm_merge_values($document) {
        $values = array(
            "company_name" => get_setting("company_name"),
            "agreement_title" => $document->title,
            "current_date" => format_to_date(get_current_utc_time(), false),
        );

        if (!empty($document->client_id)) {
            $Clients_model = model("App\Models\Clients_model");
            $client = $Clients_model->get_one($document->client_id);
            if ($client && $client->id) {
                $values["client_name"] = $client->company_name;
                $values["client_address"] = $client->address;
                $values["client_city"] = $client->city;
                $values["client_state"] = $client->state;
                $values["client_zip"] = $client->zip;
                $values["client_country"] = $client->country;
                $values["client_phone"] = $client->phone;
                $values["client_website"] = $client->website;
                $values["client_vat_number"] = $client->vat_number;
            }
        }

        if (!empty($document->project_id)) {
            $Projects_model = model("App\Models\Projects_model");
            $project = $Projects_model->get_one($document->project_id);
            if ($project && $project->id) {
                $values["project_title"] = $project->title;
            }
        }

        return $values;
    }
}

if (!function_exists('agreements_available_merge_keys')) {

    function agreements_available_merge_keys() {
        return array(
            "company_name",
            "agreement_title",
            "current_date",
            "client_name",
            "client_address",
            "client_city",
            "client_state",
            "client_zip",
            "client_country",
            "client_phone",
            "client_website",
            "client_vat_number",
            "project_title",
        );
    }
}

if (!function_exists('agreements_apply_merge_to_html')) {

    function agreements_apply_merge_to_html($html, $document, $field_values = array()) {
        $merge = agreements_crm_merge_values($document);
        foreach ($merge as $key => $value) {
            $html = str_replace("{{" . $key . "}}", $value, $html);
            $html = str_replace("{" . strtoupper($key) . "}", $value, $html);
        }
        foreach ($field_values as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $html = str_replace("{{field:" . $key . "}}", $value, $html);
            }
        }
        return $html;
    }
}

if (!function_exists('agreements_save_signature_data_url')) {

    function agreements_save_signature_data_url($data_url, $prefix = "sig") {
        if (!$data_url || strpos($data_url, "base64,") === false) {
            return "";
        }
        $parts = explode("base64,", $data_url, 2);
        $binary = base64_decode($parts[1]);
        if (!$binary) {
            return "";
        }
        $name = $prefix . "_" . uniqid() . ".png";
        $path = agreements_files_path("signatures") . $name;
        file_put_contents($path, $binary);
        return "signatures/" . $name;
    }
}

if (!function_exists('agreements_send_invite_email')) {

    function agreements_send_invite_email($document, $signatory, $token) {
        $link = agreements_sign_url($token);
        $subject = str_replace("{TITLE}", $document->title, app_lang("agreements_email_invite_subject"));
        $message = str_replace(
            array("{NAME}", "{TITLE}", "{LINK}"),
            array($signatory->name, $document->title, $link),
            app_lang("agreements_email_invite_message")
        );
        try {
            return send_app_mail($signatory->email, $subject, $message);
        } catch (\Throwable $e) {
            log_message("error", "Agreements invite email failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('agreements_send_reminder_email')) {

    function agreements_send_reminder_email($document, $signatory, $token_plain_or_empty = "") {
        // Token is not stored plain; include portal/staff note — use stored invite via regenerating is unsafe.
        // Reminder emails use a resend that regenerates token when called from admin resend.
        $link = $token_plain_or_empty ? agreements_sign_url($token_plain_or_empty) : get_uri("agreements");
        $subject = str_replace("{TITLE}", $document->title, app_lang("agreements_email_reminder_subject"));
        $message = str_replace(
            array("{NAME}", "{TITLE}", "{LINK}"),
            array($signatory->name, $document->title, $link),
            app_lang("agreements_email_reminder_message")
        );
        try {
            return send_app_mail($signatory->email, $subject, $message);
        } catch (\Throwable $e) {
            log_message("error", "Agreements reminder email failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('agreements_get_completion_emails')) {

    function agreements_get_completion_emails($document_id) {
        $Recipients_model = new \Agreements\Models\Agreements_Recipients_Model();
        $Signatories_model = new \Agreements\Models\Agreements_Signatories_Model();

        $recipients = $Recipients_model->get_all_where(array("document_id" => $document_id, "deleted" => 0))->getResult();
        $emails = array();
        if ($recipients) {
            foreach ($recipients as $r) {
                if ($r->email) {
                    $emails[$r->email] = $r->name ? $r->name : $r->email;
                }
            }
        }
        if (!$emails) {
            $signatories = $Signatories_model->get_all_where(array("document_id" => $document_id, "deleted" => 0))->getResult();
            foreach ($signatories as $s) {
                if ($s->email) {
                    $emails[$s->email] = $s->name ? $s->name : $s->email;
                }
            }
        }
        return $emails;
    }
}

if (!function_exists('agreements_build_final_pdf')) {

    function agreements_build_final_pdf($document_id) {
        $Documents_model = new \Agreements\Models\Agreements_Documents_Model();
        $Signatories_model = new \Agreements\Models\Agreements_Signatories_Model();

        $document = $Documents_model->get_one($document_id);
        if (!$document || !$document->id) {
            return "";
        }

        $signatories = $Signatories_model->get_all_where(array("document_id" => $document_id, "deleted" => 0))->getResult();

        $sign_html = "<h3>Signatories</h3><table border=\"1\" cellpadding=\"4\" cellspacing=\"0\" width=\"100%\">";
        foreach ($signatories as $s) {
            $sign_html .= "<tr><td>" . htmlspecialchars($s->name) . " (" . htmlspecialchars($s->email) . ")</td><td>" . htmlspecialchars($s->status) . "</td><td>" . ($s->signed_at ? $s->signed_at : "") . "</td></tr>";
        }
        $sign_html .= "</table>";

        $body = "";
        if ($document->document_type === "html") {
            $body = agreements_apply_merge_to_html($document->content, $document);
        } else {
            $body = "<p><i>Original PDF document on file. Signatures are recorded below.</i></p>";
            if ($document->pdf_path) {
                $body .= "<p>Source file: " . htmlspecialchars(basename($document->pdf_path)) . "</p>";
            }
        }

        $sig_images = "";
        foreach ($signatories as $s) {
            if ($s->signature_path) {
                $abs = agreements_files_path() . $s->signature_path;
                if (is_file($abs)) {
                    $sig_images .= "<p><b>" . htmlspecialchars($s->name) . "</b><br><img src=\"" . $abs . "\" height=\"60\" /></p>";
                }
            }
        }

        $html = "<h1>" . htmlspecialchars($document->title) . "</h1>";
        $html .= "<p>Status: Completed<br>Completed at: " . ($document->completed_at ? $document->completed_at : get_current_utc_time()) . "</p>";
        $html .= "<hr/>" . $body . "<hr/>" . $sign_html . $sig_images;

        $pdf = new \App\Libraries\Pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCellPadding(1.5);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, "");

        $file_name = "agreement_" . $document_id . "_signed_" . time() . ".pdf";
        $relative = "final/" . $file_name;
        $abs = agreements_files_path("final") . $file_name;
        $pdf->Output($abs, "F");

        agreements_ci_save($Documents_model, array("final_pdf_path" => $relative), $document_id);
        return $relative;
    }
}

if (!function_exists('agreements_send_completion_emails')) {

    function agreements_send_completion_emails($document_id) {
        $Documents_model = new \Agreements\Models\Agreements_Documents_Model();
        $document = $Documents_model->get_one($document_id);
        if (!$document || !$document->id) {
            return false;
        }

        if (!$document->final_pdf_path) {
            agreements_build_final_pdf($document_id);
            $document = $Documents_model->get_one($document_id);
        }

        $attachments = array();
        if ($document->final_pdf_path) {
            $attachments[] = array("file_path" => agreements_files_path() . $document->final_pdf_path);
        }
        if ($document->document_type === "pdf" && $document->pdf_path) {
            $attachments[] = array("file_path" => agreements_files_path() . $document->pdf_path);
        }

        $emails = agreements_get_completion_emails($document_id);
        foreach ($emails as $email => $name) {
            $subject = str_replace("{TITLE}", $document->title, app_lang("agreements_email_completed_subject"));
            $message = str_replace(
                array("{NAME}", "{TITLE}"),
                array($name, $document->title),
                app_lang("agreements_email_completed_message")
            );
            try {
                send_app_mail($email, $subject, $message, array("attachments" => $attachments));
            } catch (\Throwable $e) {
                log_message("error", "Agreements completion email failed: " . $e->getMessage());
            }
        }

        agreements_audit($document_id, "completion_emails_sent", "", "system", array("recipients" => array_keys($emails)));
        return true;
    }
}

if (!function_exists('agreements_check_and_complete')) {

    function agreements_check_and_complete($document_id) {
        $Documents_model = new \Agreements\Models\Agreements_Documents_Model();
        $Signatories_model = new \Agreements\Models\Agreements_Signatories_Model();
        $document = $Documents_model->get_one($document_id);
        if (!$document || !$document->id) {
            return false;
        }

        $signatories = $Signatories_model->get_all_where(array("document_id" => $document_id, "deleted" => 0))->getResult();
        $total = count($signatories);
        $signed = 0;
        foreach ($signatories as $s) {
            if ($s->status === "signed") {
                $signed++;
            }
        }

        if ($total > 0 && $signed >= $total) {
            agreements_ci_save($Documents_model, array(
                "status" => "completed",
                "completed_at" => get_current_utc_time(),
                "updated_at" => get_current_utc_time(),
            ), $document_id);
            agreements_audit($document_id, "completed", "", "system");
            agreements_build_final_pdf($document_id);
            agreements_send_completion_emails($document_id);
            return true;
        }

        if ($signed > 0) {
            agreements_ci_save($Documents_model, array(
                "status" => "partially_signed",
                "updated_at" => get_current_utc_time(),
            ), $document_id);
        }
        return false;
    }
}

if (!function_exists('agreements_next_sequential_signatory')) {

    function agreements_next_sequential_signatory($document_id) {
        $Signatories_model = new \Agreements\Models\Agreements_Signatories_Model();
        $rows = $Signatories_model->get_details(array("document_id" => $document_id))->getResult();
        foreach ($rows as $row) {
            if ($row->status !== "signed" && $row->status !== "declined" && $row->status !== "skipped") {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('agreements_can_signatory_act')) {

    function agreements_can_signatory_act($document, $signatory) {
        if (!$document || !$signatory || $signatory->document_id != $document->id) {
            return false;
        }
        if (in_array($document->status, array("completed", "declined", "expired", "cancelled", "draft"))) {
            return false;
        }
        if ($signatory->status === "signed" || $signatory->status === "declined") {
            return false;
        }
        if ($document->expires_at && $document->expires_at < get_current_utc_time()) {
            return false;
        }
        if ($document->signing_mode === "sequential") {
            $next = agreements_next_sequential_signatory($document->id);
            if (!$next || $next->id != $signatory->id) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('agreements_process_expiry_and_reminders')) {

    function agreements_process_expiry_and_reminders() {
        $Documents_model = new \Agreements\Models\Agreements_Documents_Model();
        $Signatories_model = new \Agreements\Models\Agreements_Signatories_Model();
        $now = get_current_utc_time();

        $open = $Documents_model->get_details(array("open_statuses" => true))->getResult();
        foreach ($open as $doc) {
            if ($doc->expires_at && $doc->expires_at < $now && !in_array($doc->status, array("completed", "declined", "cancelled", "expired"))) {
                agreements_ci_save($Documents_model, array("status" => "expired", "updated_at" => $now), $doc->id);
                agreements_audit($doc->id, "expired", "", "system");
                continue;
            }

            $reminder_days = (int) $doc->reminder_days;
            if ($reminder_days < 1) {
                continue;
            }
            $should_remind = false;
            if (!$doc->last_reminder_at) {
                $should_remind = true;
            } else {
                $next = date("Y-m-d H:i:s", strtotime($doc->last_reminder_at . " +" . $reminder_days . " days"));
                if ($next <= $now) {
                    $should_remind = true;
                }
            }
            if (!$should_remind) {
                continue;
            }

            $pending = $Signatories_model->get_all_where(array("document_id" => $doc->id, "deleted" => 0))->getResult();
            foreach ($pending as $s) {
                if (in_array($s->status, array("pending", "notified"))) {
                    if ($doc->signing_mode === "sequential") {
                        $next_s = agreements_next_sequential_signatory($doc->id);
                        if (!$next_s || $next_s->id != $s->id) {
                            continue;
                        }
                    }
                    // Regenerate token for reminder link
                    $token = agreements_generate_token();
                    agreements_ci_save($Signatories_model, array("token_hash" => agreements_hash_token($token)), $s->id);
                    agreements_send_reminder_email($doc, $s, $token);
                }
            }
            agreements_ci_save($Documents_model, array("last_reminder_at" => $now), $doc->id);
            agreements_audit($doc->id, "reminders_sent", "", "system");
        }
    }
}

if (!function_exists('agreements_clone_document')) {

    function agreements_clone_document($source_id, $mode = "amend") {
        $Documents_model = new \Agreements\Models\Agreements_Documents_Model();
        $Signatories_model = new \Agreements\Models\Agreements_Signatories_Model();
        $Recipients_model = new \Agreements\Models\Agreements_Recipients_Model();

        $source = $Documents_model->get_one($source_id);
        if (!$source || !$source->id) {
            return 0;
        }

        $version = ((int) $source->version) + 1;
        $title = $source->title;
        if ($mode === "amend") {
            $title .= " (Amendment v" . $version . ")";
        } else if ($mode === "renew") {
            $title .= " (Renewal)";
        }

        $new_id = agreements_ci_save($Documents_model, array(
            "title" => $title,
            "status" => "draft",
            "document_type" => $source->document_type,
            "content" => $source->content,
            "pdf_path" => $source->pdf_path,
            "final_pdf_path" => "",
            "signing_mode" => $source->signing_mode,
            "client_id" => $source->client_id,
            "project_id" => $source->project_id,
            "template_id" => $source->template_id,
            "expires_at" => null,
            "reminder_days" => $source->reminder_days,
            "parent_document_id" => $source->id,
            "version" => $version,
            "created_by" => $source->created_by,
            "deleted" => 0,
            "created_at" => get_current_utc_time(),
            "updated_at" => get_current_utc_time(),
        ));

        $signatories = $Signatories_model->get_all_where(array("document_id" => $source_id, "deleted" => 0))->getResult();
        foreach ($signatories as $s) {
            agreements_ci_save($Signatories_model, array(
                "document_id" => $new_id,
                "type" => $s->type,
                "user_id" => $s->user_id,
                "name" => $s->name,
                "email" => $s->email,
                "signing_order" => $s->signing_order,
                "status" => "pending",
                "token_hash" => "",
                "signed_at" => null,
                "signature_path" => "",
                "deleted" => 0,
            ));
        }

        $recipients = $Recipients_model->get_all_where(array("document_id" => $source_id, "deleted" => 0))->getResult();
        foreach ($recipients as $r) {
            agreements_ci_save($Recipients_model, array(
                "document_id" => $new_id,
                "email" => $r->email,
                "name" => $r->name,
                "role" => $r->role,
                "deleted" => 0,
            ));
        }

        agreements_audit($new_id, $mode === "renew" ? "renewed_from" : "amended_from", "", "system", array("source_id" => $source_id));
        return $new_id;
    }
}
