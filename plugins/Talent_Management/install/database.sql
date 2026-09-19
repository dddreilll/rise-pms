-- Reference copy of the schema created by the plugin's install hook (index.php).
-- Not executed directly by RISE; kept here for documentation, matching the convention
-- used by other plugins (e.g. Remember_Me/install/database.sql).

CREATE TABLE IF NOT EXISTS `{PREFIX}talent_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '#2e4053',
  `sort` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
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
