-- Reference copy of the schema created by the plugin's install hook (index.php) and kept
-- current by the update hook (Helpers/talent_schema_helper.php).
-- Not executed directly by RISE; kept here for documentation, matching the convention
-- used by other plugins (e.g. Remember_Me/install/database.sql).

-- system_key marks the stages the contract workflow depends on ('contract_signing', 'confirmed')
-- and is NULL for every other stage. Added in 1.2.0 (an ALTER on existing installs).
CREATE TABLE IF NOT EXISTS `{PREFIX}talent_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '#2e4053',
  `sort` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `system_key` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_key` (`system_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}talent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `legal_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `preferred_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `pronouns` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8_unicode_ci,
  `contact_number` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `profile_image` text COLLATE utf8_unicode_ci,
  `dietary_restrictions` text COLLATE utf8_unicode_ci,
  `safety_comfort_notes` text COLLATE utf8_unicode_ci,
  `social_links` text COLLATE utf8_unicode_ci,
  `profession` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `on_screen_title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- status is per casting link, not per talent - the same talent can be at a different stage on each project
CREATE TABLE IF NOT EXISTS `{PREFIX}talent_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `talent_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `talent_status_id` int(11) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `talent_id` (`talent_id`),
  KEY `project_id` (`project_id`),
  KEY `talent_status_id` (`talent_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Contract signing (added in 1.2.0). Uninstalling drops the templates table but deliberately
-- keeps talent_contracts and talent_contract_events: they are the signed legal record.
CREATE TABLE IF NOT EXISTS `{PREFIX}talent_contract_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `content` mediumtext COLLATE utf8_unicode_ci,
  `created_by` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `starter_key` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `starter_key` (`starter_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- the agreements a project requires of its talent before they can be confirmed (chosen from the templates). Configuration, not a
-- record: rows are removed for real, and what was signed lives in talent_contracts. Uninstalling drops this table.
CREATE TABLE IF NOT EXISTS `{PREFIX}talent_project_agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT '0',
  `created_by` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_template` (`project_id`, `template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- one row per contract sent for a casting link; content is the frozen snapshot, token_hash is sha256 of the emailed token.
-- The drawn signature (PNG) and the signed PDF are kept here as base64 text: nothing depends on where files/ lives or
-- survives a redeploy, there is no public file URL to guess, and text passes the 3-byte utf8 connection where raw binary would not.
CREATE TABLE IF NOT EXISTS `{PREFIX}talent_contracts` (
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
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- append-only audit trail (sent, viewed, signed, ...): no `deleted` column on purpose
CREATE TABLE IF NOT EXISTS `{PREFIX}talent_contract_events` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Changes the plugin also makes to CORE tables (by the install hook / "Updates", see Helpers/talent_schema_helper.php):
--   * {PREFIX}notifications          + `plugin_talent_contract_id` int(11) NOT NULL DEFAULT '0'  (core keeps plugin data in plugin_* columns)
--   * {PREFIX}notification_settings  + rows for the events talent_contract_signed and talent_contract_declined (category 'talent')
--   * {PREFIX}email_templates        + one row, template_name 'talent_contract_request'
-- All three are removed again on uninstall; talent_contracts and talent_contract_events are kept.
