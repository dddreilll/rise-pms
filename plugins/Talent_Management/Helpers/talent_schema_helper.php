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
                PRIMARY KEY (`id`)
            ) $table_options;",

            //one row per contract sent for a casting link; content is the frozen snapshot, token_hash is sha256 of the emailed token
            "talent_contracts" => "CREATE TABLE IF NOT EXISTS `" . $db_prefix . "talent_contracts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `talent_project_id` int(11) NOT NULL,
                `template_id` int(11) NOT NULL DEFAULT '0',
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
                `signature_file` text COLLATE utf8_unicode_ci,
                `signed_pdf_file` text COLLATE utf8_unicode_ci,
                `pdf_hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `signer_ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `signer_user_agent` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `decline_reason` text COLLATE utf8_unicode_ci,
                `created_at` datetime DEFAULT NULL,
                `deleted` tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `talent_project_id` (`talent_project_id`),
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

//the mail that carries a signing link. A row in core's email_templates is what makes it editable under Settings > Email templates
//(the plugin registers its variables with app_filter_email_templates); the sender falls back to the built-in text if it's missing.
if (!function_exists('talent_email_template_exists')) {

    function talent_email_template_exists($db, $db_prefix) {
        return $db->query("SELECT id FROM `" . $db_prefix . "email_templates` WHERE template_name='talent_contract_request' LIMIT 1")->getRow() ? true : false;
    }
}

if (!function_exists('talent_ensure_email_template')) {

    function talent_ensure_email_template($db, $db_prefix) {
        if (talent_email_template_exists($db, $db_prefix)) {
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
            $changes = array_merge($changes, talent_ensure_system_stages($db, $db_prefix));
            $changes = array_merge($changes, talent_ensure_email_template($db, $db_prefix));
        } finally {
            $db->query("SELECT RELEASE_LOCK('talent_management_schema')");
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

            //the email template is the last thing the update adds, so finding it means the update finished
            if (!talent_column_exists($db, $db_prefix . "talent_status", "system_key") || !talent_table_exists($db, $db_prefix . "talent_contract_events") || !talent_email_template_exists($db, $db_prefix)) {
                talent_ensure_schema();
            }
        } catch (\Throwable $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
        }
    }
}
