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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- one row per contract sent for a casting link; content is the frozen snapshot, token_hash is sha256 of the emailed token
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
