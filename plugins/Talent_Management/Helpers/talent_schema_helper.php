<?php

//schema upkeep for the contract-signing workflow. Everything here is idempotent because three callers share it: the install
//hook, the plugin's "Updates" action, and talent_ensure_schema_once(). RISE only runs the update hook when an admin clicks
//"Updates", so a redeploy alone would otherwise leave new code talking to an old schema.

//pipeline stages the contract workflow depends on, keyed by an identity that survives renaming
if (!function_exists('talent_system_stage_definitions')) {

    function talent_system_stage_definitions() {
        return array(
            "contract_signing" => array("title" => "Contract Signing", "color" => "#ef6c00"),
            "confirmed" => array("title" => "Confirmed", "color" => "#2e7d32"),
        );
    }
}

//pipeline seeded on a fresh install, in order; system stages carry their key
if (!function_exists('talent_default_pipeline')) {

    function talent_default_pipeline() {
        $system = talent_system_stage_definitions();

        return array(
            array("title" => "Prospective", "color" => "#7c8798"),
            array("title" => "Contacted", "color" => "#3f51b5"),
            array_merge($system["contract_signing"], array("system_key" => "contract_signing")),
            array_merge($system["confirmed"], array("system_key" => "confirmed")),
            array("title" => "Wrapped", "color" => "#8d6e63"),
        );
    }
}

//LIKE treats "_" as a wildcard, so escape it to match the exact name only
if (!function_exists('talent_table_exists')) {

    function talent_table_exists($db, $table) {
        $like = $db->escape(str_replace(array("_", "%"), array("\\_", "\\%"), $table));
        return count($db->query("SHOW TABLES LIKE $like")->getResult()) > 0;
    }
}

if (!function_exists('talent_column_exists')) {

    function talent_column_exists($db, $table, $column) {
        $like = $db->escape(str_replace(array("_", "%"), array("\\_", "\\%"), $column));
        return count($db->query("SHOW COLUMNS FROM `$table` LIKE $like")->getResult()) > 0;
    }
}

if (!function_exists('talent_index_exists')) {

    function talent_index_exists($db, $table, $index) {
        return $db->query("SHOW INDEX FROM `$table` WHERE Key_name=" . $db->escape($index))->getRow() ? true : false;
    }
}

//frozen contract text and its audit trail live in these tables. utf8 like the rest of RISE, which is why
//talent_encode_4byte_chars() exists: a 4-byte character would otherwise cut the stored text short.
if (!function_exists('talent_contract_table_definitions')) {

    function talent_contract_table_definitions($db_prefix) {
        $table_options = "ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        return array(
            "talent_contract_templates" => "CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_contract_templates` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `content` mediumtext COLLATE utf8_unicode_ci,
                `created_by` int(11) NOT NULL DEFAULT '0',
                `created_at` datetime DEFAULT NULL,
                `deleted` tinyint(1) NOT NULL DEFAULT '0',
                `starter_key` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `starter_key` (`starter_key`)
            ) $table_options;",

            //the agreements a project requires of its talent before they can be confirmed (chosen from the templates). Configuration, not a
            //record: rows are removed for real, and what was signed lives in talent_contracts.
            "talent_project_agreements" => "CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_project_agreements` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `project_id` int(11) NOT NULL,
                `template_id` int(11) NOT NULL,
                `sort` int(11) NOT NULL DEFAULT '0',
                `created_by` int(11) NOT NULL DEFAULT '0',
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `project_template` (`project_id`, `template_id`)
            ) $table_options;",

            //One row per email sent: a bundle is the unit that carries the signing link. It holds the token (only its sha256) and the expiry, and the
            //contracts it delivers point back with bundle_id; a send of one agreement is a bundle of one. Kept together with the contracts on uninstall.
            "talent_contract_bundles" => "CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_contract_bundles` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `talent_project_id` int(11) NOT NULL,
                `token_hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `token_expires_at` datetime DEFAULT NULL,
                `sent_to_email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `sent_by` int(11) NOT NULL DEFAULT '0',
                `sent_at` datetime DEFAULT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `talent_project_id` (`talent_project_id`)
            ) $table_options;",

            //one row per contract sent for a casting link; content is the frozen snapshot, token_hash is sha256 of the emailed token.
            //The drawn signature (PNG) and the signed PDF are kept here as base64 text: nothing depends on where files/ lives or survives
            //a redeploy, there is no public file URL to guess, and text passes the 3-byte utf8 connection where raw binary would not.
            "talent_contracts" => "CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_contracts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `talent_project_id` int(11) NOT NULL,
                `template_id` int(11) NOT NULL DEFAULT '0',
                `bundle_id` int(11) NOT NULL DEFAULT '0',
                `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `content` mediumtext COLLATE utf8_unicode_ci,
                `content_hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `token_hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `token_expires_at` datetime DEFAULT NULL,
                `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'sent',
                `sent_to_email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `sent_by` int(11) NOT NULL DEFAULT '0',
                `sent_at` datetime DEFAULT NULL,
                `signer_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `signer_email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `signed_at` datetime DEFAULT NULL,
                `signed_via` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'online',
                `signature_data` mediumtext COLLATE utf8_unicode_ci,
                `signed_pdf_data` mediumtext COLLATE utf8_unicode_ci,
                `pdf_hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `signer_ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `signer_user_agent` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `decline_reason` text COLLATE utf8_unicode_ci,
                `created_at` datetime DEFAULT NULL,
                `deleted` tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `talent_project_id` (`talent_project_id`),
                KEY `talent_project_template` (`talent_project_id`, `template_id`),
                KEY `bundle_id` (`bundle_id`),
                KEY `status` (`status`)
            ) $table_options;",

            //append-only audit trail (sent, viewed, signed, ...): no `deleted` column on purpose
            "talent_contract_events" => "CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_contract_events` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `contract_id` int(11) NOT NULL,
                `event` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `actor_type` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `actor_id` int(11) NOT NULL DEFAULT '0',
                `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `user_agent` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `meta` text COLLATE utf8_unicode_ci,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `contract_id` (`contract_id`)
            ) $table_options;",
        );
    }
}

//system_key column on the stages + the three contract tables; returns a list of what it changed
if (!function_exists('talent_ensure_schema_structure')) {

    function talent_ensure_schema_structure($db, $db_prefix) {
        $changes = array();
        $status_table = $db_prefix . "talent_status";

        if (!talent_column_exists($db, $status_table, "system_key")) {
            $db->query("ALTER TABLE `$status_table` ADD `system_key` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL, ADD KEY `system_key` (`system_key`)");
            $changes[] = "Added system_key to talent_status";
        }

        foreach (talent_contract_table_definitions($db_prefix) as $table => $sql) {
            if (!talent_table_exists($db, $db_prefix . $table)) {
                $db->query($sql);
                $changes[] = "Created table " . $table;
            }
        }

        //slice 2 kept the signature and PDF as file references (columns that were never filled); they now live in the row itself
        $contracts_table = $db_prefix . "talent_contracts";
        if (talent_column_exists($db, $contracts_table, "signature_file")) {
            $db->query("ALTER TABLE `$contracts_table` CHANGE `signature_file` `signature_data` mediumtext COLLATE utf8_unicode_ci, CHANGE `signed_pdf_file` `signed_pdf_data` mediumtext COLLATE utf8_unicode_ci");
            $changes[] = "Changed talent_contracts to keep the signature and signed PDF in the row";
        }

        //how a contract was signed: 'online' through the emailed link, 'paper' when staff recorded a signed scan (every earlier row is online)
        if (!talent_column_exists($db, $contracts_table, "signed_via")) {
            $db->query("ALTER TABLE `$contracts_table` ADD `signed_via` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'online' AFTER `signed_at`");
            $changes[] = "Added signed_via to talent_contracts";
        }

        //a starter template is remembered by its key, so one the admin deleted is never put back
        $templates_table = $db_prefix . "talent_contract_templates";
        if (!talent_column_exists($db, $templates_table, "starter_key")) {
            $db->query("ALTER TABLE `$templates_table` ADD `starter_key` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL, ADD KEY `starter_key` (`starter_key`)");
            $changes[] = "Added starter_key to talent_contract_templates";
        }

        //which bundle (email + signing link) delivered a contract; 0 for contracts that were never sent through one (paper copies, old records)
        if (!talent_column_exists($db, $contracts_table, "bundle_id")) {
            $db->query("ALTER TABLE `$contracts_table` ADD `bundle_id` int(11) NOT NULL DEFAULT '0' AFTER `template_id`, ADD KEY `bundle_id` (`bundle_id`)");
            $changes[] = "Added bundle_id to talent_contracts";
        }

        //agreements are tracked per casting link and template, so that pair is looked up a lot
        if (!talent_index_exists($db, $contracts_table, "talent_project_template")) {
            $db->query("ALTER TABLE `$contracts_table` ADD KEY `talent_project_template` (`talent_project_id`, `template_id`)");
            $changes[] = "Added an index on talent_contracts (casting link, template)";
        }

        //core's notifications table takes plugin data in columns named plugin_*; this one says which contract a notification is about
        $notifications_table = $db_prefix . "notifications";
        if (!talent_column_exists($db, $notifications_table, "plugin_talent_contract_id")) {
            $db->query("ALTER TABLE `$notifications_table` ADD `plugin_talent_contract_id` int(11) NOT NULL DEFAULT '0'");
            $changes[] = "Added plugin_talent_contract_id to notifications";
        }

        return $changes;
    }
}

if (!function_exists('talent_find_stage_index')) {

    function talent_find_stage_index($items, $system_key) {
        foreach ($items as $index => $item) {
            if ($item["system_key"] === $system_key) {
                return $index;
            }
        }
        return -1;
    }
}

if (!function_exists('talent_new_stage_item')) {

    function talent_new_stage_item($system_key) {
        return array("id" => null, "title" => "", "sort" => null, "system_key" => $system_key);
    }
}

//makes sure both system stages exist and Contract Signing sorts before Confirmed. An existing stage with a matching title is
//adopted (so its cards stay put) instead of getting a duplicate; a missing one is created next to its counterpart.
if (!function_exists('talent_ensure_system_stages')) {

    function talent_ensure_system_stages($db, $db_prefix) {
        $status_table = $db_prefix . "talent_status";
        $definitions = talent_system_stage_definitions();
        $changes = array();

        $items = array();
        $rows = $db->query("SELECT id, title, sort, system_key FROM `$status_table` WHERE deleted=0 ORDER BY sort ASC, id ASC")->getResult();
        foreach ($rows as $row) {
            $items[] = array("id" => (int) $row->id, "title" => $row->title, "sort" => (int) $row->sort, "system_key" => (string) $row->system_key);
        }

        foreach ($definitions as $key => $definition) {
            if (talent_find_stage_index($items, $key) >= 0) {
                continue;
            }
            foreach ($items as $index => $item) {
                if ($item["system_key"] === "" && strcasecmp(trim($item["title"]), $definition["title"]) === 0) {
                    $db->table($status_table)->where("id", $item["id"])->update(array("system_key" => $key));
                    $items[$index]["system_key"] = $key;
                    $changes[] = "Adopted stage \"" . $item["title"] . "\" as " . $key;
                    break;
                }
            }
        }

        $signing = talent_find_stage_index($items, "contract_signing");
        $confirmed = talent_find_stage_index($items, "confirmed");
        $reordered = false;

        if ($signing < 0 && $confirmed < 0) {
            $items[] = talent_new_stage_item("contract_signing");
            $items[] = talent_new_stage_item("confirmed");
            $reordered = true;
        } else if ($signing < 0) {
            array_splice($items, $confirmed, 0, array(talent_new_stage_item("contract_signing")));
            $reordered = true;
        } else if ($confirmed < 0) {
            array_splice($items, $signing + 1, 0, array(talent_new_stage_item("confirmed")));
            $reordered = true;
        } else if ($signing > $confirmed) {
            //taking Confirmed out shifts Contract Signing up by one, so its old index is the slot right after it
            $moved = array_splice($items, $confirmed, 1);
            array_splice($items, $signing, 0, $moved);
            $changes[] = "Moved the confirmed stage after the contract signing stage";
            $reordered = true;
        }

        if (!$reordered) {
            return $changes;
        }

        //write the new order back as 0..n-1 (the same scheme the Pipeline stages drag-and-drop uses)
        foreach ($items as $position => $item) {
            if ($item["id"] === null) {
                $definition = get_array_value($definitions, $item["system_key"]);
                $db->table($status_table)->insert(array("title" => $definition["title"], "color" => $definition["color"], "sort" => $position, "system_key" => $item["system_key"]));
                $changes[] = "Created stage \"" . $definition["title"] . "\"";
            } else if ($item["sort"] !== $position) {
                $db->table($status_table)->where("id", $item["id"])->update(array("sort" => $position));
            }
        }

        return $changes;
    }
}

//starter agreements, added once each: a key that already has a row (even a deleted one) is never inserted again, and nothing is overwritten
if (!function_exists('talent_starter_templates_exist')) {

    function talent_starter_templates_exist($db, $db_prefix) {
        $keys = array_map(function ($key) use ($db) {
            return $db->escape($key);
        }, array_keys(talent_starter_templates()));

        $row = $db->query("SELECT COUNT(DISTINCT starter_key) AS total FROM `" . $db_prefix . "talent_contract_templates` WHERE starter_key IN (" . implode(",", $keys) . ")")->getRow();
        return $row && (int) $row->total === count($keys);
    }
}

if (!function_exists('talent_ensure_starter_templates')) {

    function talent_ensure_starter_templates($db, $db_prefix) {
        $table = $db_prefix . "talent_contract_templates";
        $changes = array();

        foreach (talent_starter_templates() as $key => $starter) {
            if ($db->query("SELECT id FROM `$table` WHERE starter_key=" . $db->escape($key) . " LIMIT 1")->getRow()) {
                continue;
            }

            $db->table($table)->insert(array(
                "title" => $starter["title"],
                "content" => $starter["content"],
                "created_by" => 0,
                "created_at" => get_current_utc_time(),
                "deleted" => 0,
                "starter_key" => $key,
            ));
            $changes[] = "Added starter template " . $starter["title"];
        }

        return $changes;
    }
}

//the mail that carries a signing link. A row in core's email_templates is what makes it editable under Settings > Email templates
//(the plugin registers its variables with app_filter_email_templates); the sender falls back to the built-in text if it's missing.
if (!function_exists('talent_email_template_exists')) {

    function talent_email_template_exists($db, $db_prefix) {
        return $db->query("SELECT id FROM `" . $db_prefix . "email_templates` WHERE template_name='talent_contract_request' LIMIT 1")->getRow() ? true : false;
    }
}

if (!function_exists('talent_ensure_email_template')) {

    //true when the stored template is still one of the built-in defaults of an earlier version (nobody has changed its subject or default text)
    function talent_email_template_is_outdated($db, $db_prefix) {
        $row = $db->query("SELECT email_subject, default_message FROM `" . $db_prefix . "email_templates` WHERE template_name='talent_contract_request' LIMIT 1")->getRow();
        if (!$row) {
            return false;
        }

        foreach (talent_contract_previous_default_emails() as $previous) {
            if ((string) $row->email_subject === $previous["subject"] && (string) $row->default_message === $previous["message"]) {
                return true;
            }
        }
        return false;
    }

    function talent_ensure_email_template($db, $db_prefix) {
        if (talent_email_template_exists($db, $db_prefix)) {
            //a template that still holds an earlier built-in text is brought up to date; anything an admin edited is left alone
            if (talent_email_template_is_outdated($db, $db_prefix)) {
                $default = talent_contract_default_email();
                $db->query("UPDATE `" . $db_prefix . "email_templates` SET email_subject=" . $db->escape($default["subject"]) . ", default_message=" . $db->escape($default["message"]) . " WHERE template_name='talent_contract_request'");
                return array("Updated email template talent_contract_request");
            }
            return array();
        }

        $default = talent_contract_default_email();
        $db->table($db_prefix . "email_templates")->insert(array(
            "template_name" => "talent_contract_request",
            "email_subject" => $default["subject"],
            "default_message" => $default["message"],
            "custom_message" => "",
            "template_type" => "default",
            "language" => "",
        ));

        return array("Created email template talent_contract_request");
    }
}

//Contracts that were waiting for a signature before bundles existed each get a bundle of their own (copying the token hash, expiry, address and
//sender), so they can be resent. Their old emailed link used the contract's id and stops working; resending makes a fresh one.
if (!function_exists('talent_bundles_pending_migration')) {

    function talent_bundles_pending_migration($db, $db_prefix) {
        $contracts_table = $db_prefix . "talent_contracts";
        if (!talent_column_exists($db, $contracts_table, "bundle_id")) {
            return false;
        }

        return $db->query("SELECT id FROM `$contracts_table` WHERE bundle_id=0 AND deleted=0 AND status IN ('sent','expired') AND token_hash!='' LIMIT 1")->getRow() ? true : false;
    }
}

if (!function_exists('talent_ensure_bundles')) {

    function talent_ensure_bundles($db, $db_prefix) {
        $contracts_table = $db_prefix . "talent_contracts";
        $bundles_table = $db_prefix . "talent_contract_bundles";
        $moved = 0;

        $rows = $db->query("SELECT id, talent_project_id, token_hash, token_expires_at, sent_to_email, sent_by, sent_at FROM `$contracts_table` WHERE bundle_id=0 AND deleted=0 AND status IN ('sent','expired') AND token_hash!='' ORDER BY id ASC")->getResult();
        foreach ($rows as $row) {
            $db->table($bundles_table)->insert(array(
                "talent_project_id" => $row->talent_project_id,
                "token_hash" => $row->token_hash,
                "token_expires_at" => $row->token_expires_at,
                "sent_to_email" => $row->sent_to_email,
                "sent_by" => $row->sent_by,
                "sent_at" => $row->sent_at,
                "created_at" => get_current_utc_time(),
            ));
            $db->query("UPDATE `$contracts_table` SET bundle_id=" . (int) $db->insertID() . " WHERE id=" . (int) $row->id);
            $moved++;
        }

        return $moved ? array("Moved " . $moved . " waiting contract(s) into bundles (their old emailed links need to be sent again)") : array();
    }
}

//events staff are told about when a talent answers a contract
if (!function_exists('talent_notification_events')) {

    function talent_notification_events() {
        return array("talent_contract_signed", "talent_contract_declined");
    }
}

//core's create_notification() silently does nothing for an event without a row in notification_settings, so these are seeded.
//Web notifications to the project's members are on by default; admins change that under Settings > Notifications > Talent.
if (!function_exists('talent_notification_settings_exist')) {

    function talent_notification_settings_exist($db, $db_prefix) {
        $events = array_map(function ($event) use ($db) {
            return $db->escape($event);
        }, talent_notification_events());

        $row = $db->query("SELECT COUNT(DISTINCT event) AS total FROM `" . $db_prefix . "notification_settings` WHERE event IN (" . implode(",", $events) . ")")->getRow();
        return $row && (int) $row->total === count($events);
    }
}

if (!function_exists('talent_ensure_notification_settings')) {

    function talent_ensure_notification_settings($db, $db_prefix) {
        $table = $db_prefix . "notification_settings";
        $changes = array();

        foreach (talent_notification_events() as $event) {
            if ($db->query("SELECT id FROM `$table` WHERE event=" . $db->escape($event) . " LIMIT 1")->getRow()) {
                continue;
            }

            $max = $db->query("SELECT MAX(sort) AS sort FROM `$table`")->getRow();
            $db->table($table)->insert(array(
                "event" => $event,
                "category" => "talent",
                "enable_email" => 0,
                "enable_web" => 1,
                "enable_slack" => 0,
                "notify_to_team" => "",
                "notify_to_team_members" => "",
                "notify_to_terms" => "project_members",
                "sort" => ($max ? (int) $max->sort : 0) + 1,
                "deleted" => 0,
            ));
            $changes[] = "Created notification setting " . $event;
        }

        return $changes;
    }
}

//full upkeep pass. The named lock stops two requests that land right after a deploy from creating the stages twice.
if (!function_exists('talent_ensure_schema')) {

    function talent_ensure_schema() {
        $db = db_connect('default');
        $db_prefix = get_db_prefix();
        $db->query("SET sql_mode = ''");

        $lock = $db->query("SELECT GET_LOCK('talent_management_schema', 10) AS acquired")->getRow();
        if (!$lock || (int) $lock->acquired !== 1) {
            throw new \RuntimeException("Another Talent Management update is still running. Try again in a moment.");
        }

        try {
            $changes = talent_ensure_schema_structure($db, $db_prefix);
            $changes = array_merge($changes, talent_ensure_bundles($db, $db_prefix));
            $changes = array_merge($changes, talent_ensure_system_stages($db, $db_prefix));
            $changes = array_merge($changes, talent_ensure_starter_templates($db, $db_prefix));
            $changes = array_merge($changes, talent_ensure_email_template($db, $db_prefix));
            $changes = array_merge($changes, talent_ensure_notification_settings($db, $db_prefix));
        } finally {
            $db->query("SELECT RELEASE_LOCK('talent_management_schema')");
        }

        return $changes;
    }
}

//Encrypts the signed PDFs that were stored before encryption existed (plain base64). It runs from the Updates button only, not from the
//per-request guard below, because finding them means reading every stored PDF. One row at a time, and each is decrypted again and compared
//before it replaces the old value; it is safe to run twice, and it stops after $seconds so a big backlog is finished by clicking Updates
//again. Returns the lines the Updates window shows.
if (!function_exists('talent_encrypt_legacy_pdfs')) {

    function talent_encrypt_legacy_pdfs($db, $db_prefix, $seconds = 20) {
        $table = $db_prefix . "talent_contracts";
        $changes = array();

        if (!talent_column_exists($db, $table, "signed_pdf_data")) {
            return $changes;
        }

        $lock = $db->query("SELECT GET_LOCK('talent_management_pdf_encryption', 0) AS acquired")->getRow();
        if (!$lock || (int) $lock->acquired !== 1) {
            return array("Signed PDFs are being encrypted by another update. Click Updates again in a moment.");
        }

        try {
            $ids = $db->query("SELECT id FROM `$table` WHERE signed_pdf_data<>'' AND signed_pdf_data NOT LIKE 'enc1:%' ORDER BY id")->getResult();
            $deadline = microtime(true) + $seconds;
            $encrypted = 0;
            $unreadable = array();
            $left = 0;

            foreach ($ids as $position => $row) {
                if (microtime(true) > $deadline) {
                    $left = count($ids) - $position;
                    break;
                }

                $stored = (string) $db->query("SELECT signed_pdf_data FROM `$table` WHERE id=" . (int) $row->id)->getRow()->signed_pdf_data;
                if (talent_is_encrypted($stored)) {
                    continue;
                }

                $plain = base64_decode($stored, true);
                if ($plain === false || $plain === "") {
                    $unreadable[] = (int) $row->id;
                    continue;
                }

                try {
                    $cipher = talent_encrypt($plain);
                    if (talent_decrypt($cipher) !== $plain) {
                        throw new \RuntimeException("the encrypted copy did not decrypt to the same bytes");
                    }
                } catch (\Throwable $ex) {
                    //no key, or no OpenSSL: it would fail for every row, so stop here and say why
                    log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
                    $changes[] = "Could not encrypt the signed PDFs: " . $ex->getMessage();
                    return $changes;
                }

                //the NOT LIKE keeps a row that was encrypted by someone else in the meantime from being encrypted twice
                $db->query("UPDATE `$table` SET signed_pdf_data=" . $db->escape($cipher) . " WHERE id=" . (int) $row->id . " AND signed_pdf_data NOT LIKE 'enc1:%'");
                $encrypted++;
            }

            if ($encrypted) {
                $changes[] = "Encrypted " . $encrypted . " signed PDF" . ($encrypted === 1 ? "" : "s");
            }
            if ($unreadable) {
                $changes[] = "Left " . count($unreadable) . " stored PDF" . (count($unreadable) === 1 ? "" : "s") . " alone because they could not be read (contract ids " . implode(", ", $unreadable) . ")";
            }
            if ($left) {
                $changes[] = $left . " more signed PDF" . ($left === 1 ? "" : "s") . " to encrypt: click Updates again";
            }
        } finally {
            $db->query("SELECT RELEASE_LOCK('talent_management_pdf_encryption')");
        }

        return $changes;
    }
}

//self-healing guard for the controllers that need the new schema: a redeploy without clicking "Updates" fixes itself on first use
if (!function_exists('talent_ensure_schema_once')) {

    function talent_ensure_schema_once() {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            $db = db_connect('default');
            $db_prefix = get_db_prefix();

            //cheapest checks first, and the events table is created together with the rest of the contract tables
            if (!talent_column_exists($db, $db_prefix . "talent_status", "system_key")
                    || !talent_table_exists($db, $db_prefix . "talent_contract_events")
                    || !talent_column_exists($db, $db_prefix . "talent_contracts", "signature_data")
                    || !talent_column_exists($db, $db_prefix . "talent_contracts", "signed_via")
                    || !talent_table_exists($db, $db_prefix . "talent_contract_bundles")
                    || !talent_column_exists($db, $db_prefix . "talent_contracts", "bundle_id")
                    || talent_bundles_pending_migration($db, $db_prefix)
                    || talent_email_template_is_outdated($db, $db_prefix)
                    || !talent_table_exists($db, $db_prefix . "talent_project_agreements")
                    || !talent_column_exists($db, $db_prefix . "talent_contract_templates", "starter_key")
                    || !talent_starter_templates_exist($db, $db_prefix)
                    || !talent_column_exists($db, $db_prefix . "notifications", "plugin_talent_contract_id")
                    || !talent_email_template_exists($db, $db_prefix)
                    || !talent_notification_settings_exist($db, $db_prefix)) {
                talent_ensure_schema();
            }
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
        }
    }
}
