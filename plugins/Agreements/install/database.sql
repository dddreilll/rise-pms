CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` text COLLATE utf8_unicode_ci NOT NULL,
  `status` enum('draft','sent','partially_signed','completed','declined','expired','cancelled') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'draft',
  `document_type` enum('html','pdf') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'html',
  `content` longtext COLLATE utf8_unicode_ci,
  `pdf_path` text COLLATE utf8_unicode_ci,
  `final_pdf_path` text COLLATE utf8_unicode_ci,
  `signing_mode` enum('parallel','sequential') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'parallel',
  `client_id` int(11) NOT NULL DEFAULT '0',
  `project_id` int(11) NOT NULL DEFAULT '0',
  `template_id` int(11) NOT NULL DEFAULT '0',
  `expires_at` datetime DEFAULT NULL,
  `reminder_days` int(11) NOT NULL DEFAULT '3',
  `last_reminder_at` datetime DEFAULT NULL,
  `parent_document_id` int(11) NOT NULL DEFAULT '0',
  `version` int(11) NOT NULL DEFAULT '1',
  `decline_reason` text COLLATE utf8_unicode_ci,
  `created_by` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` text COLLATE utf8_unicode_ci NOT NULL,
  `document_type` enum('html','pdf') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'html',
  `content` longtext COLLATE utf8_unicode_ci,
  `pdf_path` text COLLATE utf8_unicode_ci,
  `fields_json` longtext COLLATE utf8_unicode_ci,
  `created_by` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL DEFAULT '0',
  `template_id` int(11) NOT NULL DEFAULT '0',
  `field_key` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `type` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'text',
  `label` text COLLATE utf8_unicode_ci,
  `options_json` text COLLATE utf8_unicode_ci,
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `page` int(11) NOT NULL DEFAULT '1',
  `pos_x` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pos_y` decimal(10,2) NOT NULL DEFAULT '0.00',
  `width` decimal(10,2) NOT NULL DEFAULT '150.00',
  `height` decimal(10,2) NOT NULL DEFAULT '30.00',
  `merge_key` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `assigned_signatory_id` int(11) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_signatories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL DEFAULT '0',
  `type` enum('staff','client','external') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'external',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `name` text COLLATE utf8_unicode_ci,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `signing_order` int(11) NOT NULL DEFAULT '1',
  `status` enum('pending','notified','signed','declined','skipped') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pending',
  `token_hash` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `signed_at` datetime DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  `signature_path` text COLLATE utf8_unicode_ci,
  `decline_reason` text COLLATE utf8_unicode_ci,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_field_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field_id` int(11) NOT NULL DEFAULT '0',
  `signatory_id` int(11) NOT NULL DEFAULT '0',
  `document_id` int(11) NOT NULL DEFAULT '0',
  `value` longtext COLLATE utf8_unicode_ci,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `field_id` (`field_id`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL DEFAULT '0',
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `name` text COLLATE utf8_unicode_ci,
  `role` enum('signer','cc','custom') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'custom',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL DEFAULT '0',
  `event` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `actor_email` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `actor_name` text COLLATE utf8_unicode_ci,
  `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `meta_json` text COLLATE utf8_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}agreements_settings` (
  `setting_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `setting_value` mediumtext COLLATE utf8_unicode_ci,
  `type` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'app',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
