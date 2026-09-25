-- Campaign Loop — full database (schema + reference data + admin + demo workspace)
-- Import this file into an EMPTY MySQL/MariaDB database (phpMyAdmin → Import).
-- Default login: admin@example.com / ChangeMe123 (you must change it on first login).

-- Campaign Loop — MySQL 5.7+ / MariaDB 10.3+ schema
-- Charset: utf8mb4 · Engine: InnoDB · Money: BIGINT Toman

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(100) NOT NULL,
  `v` MEDIUMTEXT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `company` VARCHAR(160) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `email_verified_at` DATETIME NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `totp_secret_enc` TEXT NULL,
  `totp_enabled_at` DATETIME NULL,
  `recovery_codes` TEXT NULL,
  `last_workspace_id` INT UNSIGNED NULL,
  `prefs` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `last_login_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `workspaces` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `currency` VARCHAR(40) NOT NULL DEFAULT 'تومان',
  `tz` VARCHAR(60) NOT NULL DEFAULT 'تهران (UTC+۳:۳۰)',
  `tier` VARCHAR(20) NOT NULL DEFAULT 'trial',
  `tier_until` DATE NULL,
  `ai_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
  `benchmark_mode` TINYINT(1) NOT NULL DEFAULT 0,
  `seq_next` INT UNSIGNED NOT NULL DEFAULT 101,
  `history_seq` INT UNSIGNED NOT NULL DEFAULT 200,
  `log_seq` INT UNSIGNED NOT NULL DEFAULT 121,
  `created_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `memberships` (
  `workspace_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'viewer',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`workspace_id`, `user_id`),
  KEY `ix_mem_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `role` VARCHAR(20) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'pending',
  `expires_at` DATETIME NOT NULL,
  `invited_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_inv_ws` (`workspace_id`),
  KEY `ix_inv_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(10) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_et_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` CHAR(40) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `device` VARCHAR(120) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  `last_seen_at` DATETIME NOT NULL,
  `revoked_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sess_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `merchant_profiles` (
  `workspace_id` INT UNSIGNED NOT NULL,
  `monthly_budget` BIGINT NOT NULL DEFAULT 0,
  `gross_margin` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `target_cac` BIGINT NOT NULL DEFAULT 0,
  `ltv` BIGINT NOT NULL DEFAULT 0,
  `business_goal` VARCHAR(20) NOT NULL DEFAULT '',
  `season_note` VARCHAR(255) NOT NULL DEFAULT '',
  `blocked_channels` VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `channel` VARCHAR(40) NOT NULL,
  `segment` VARCHAR(40) NOT NULL,
  `type` VARCHAR(8) NOT NULL DEFAULT 'paid',
  `unit_cost` DECIMAL(14,2) NOT NULL,
  `cvr` DECIMAL(12,6) NOT NULL,
  `aov` BIGINT NOT NULL,
  `variance` DECIMAL(8,4) NOT NULL,
  `sample_n` INT NOT NULL DEFAULT 0,
  `ceiling` INT NOT NULL DEFAULT 0,
  `seasonal_lift` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `d7` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `d30` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `fraud` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `observed_at` DATE NOT NULL,
  `source` VARCHAR(20) NOT NULL DEFAULT 'history',
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate` (`workspace_id`, `channel`, `segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `channel` VARCHAR(40) NOT NULL,
  `segment` VARCHAR(40) NOT NULL,
  `spend` BIGINT NOT NULL,
  `installs` INT NOT NULL,
  `conversions` INT NOT NULL,
  `revenue` BIGINT NOT NULL,
  `month` VARCHAR(20) NOT NULL DEFAULT '',
  `source` VARCHAR(30) NOT NULL DEFAULT 'ورود دستی',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_hist_ws` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rules` (
  `workspace_id` INT UNSIGNED NOT NULL,
  `exec_th` DECIMAL(8,4) NOT NULL DEFAULT 0.15,
  `est_th` DECIMAL(8,4) NOT NULL DEFAULT 0.25,
  `scale_lo` DECIMAL(8,4) NOT NULL DEFAULT 0.8,
  `scale_hi` DECIMAL(8,4) NOT NULL DEFAULT 1.2,
  `inflation` DECIMAL(8,4) NOT NULL DEFAULT 0.035,
  `attr_window` INT NOT NULL DEFAULT 7,
  `fraud_th` DECIMAL(8,4) NOT NULL DEFAULT 0.08,
  `auto_calibrate` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `occasions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `purchase_lift` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `cpi_delta` DECIMAL(8,4) NOT NULL DEFAULT 0,
  `month_from` TINYINT NOT NULL DEFAULT 0,
  `month_to` TINYINT NOT NULL DEFAULT 0,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `industry_factors` (
  `channel` VARCHAR(40) NOT NULL,
  `factor` DECIMAL(8,4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `benchmarks` (
  `channel` VARCHAR(40) NOT NULL,
  `period` CHAR(7) NOT NULL,
  `median_cac` BIGINT NOT NULL,
  `n_merchants` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`channel`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `seq` INT UNSIGNED NULL,
  `name` VARCHAR(160) NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'draft',
  `goal_type` VARCHAR(12) NOT NULL DEFAULT 'خرید',
  `goal_value` BIGINT NOT NULL DEFAULT 0,
  `budget` BIGINT NOT NULL DEFAULT 0,
  `date_from` DATE NULL,
  `date_to` DATE NULL,
  `channels` TEXT NULL,
  `segments` TEXT NULL,
  `occasion` VARCHAR(80) NOT NULL DEFAULT 'بدون مناسبت',
  `risk` VARCHAR(20) NOT NULL DEFAULT 'متعادل',
  `insights` MEDIUMTEXT NULL,
  `insights_at` DATETIME NULL,
  `sel_id` VARCHAR(20) NULL,
  `sec_id` VARCHAR(20) NULL,
  `mix` INT NOT NULL DEFAULT 60,
  `reason_draft` TEXT NULL,
  `sim_band` VARCHAR(12) NOT NULL DEFAULT 'معمول',
  `sim_ext` VARCHAR(20) NOT NULL DEFAULT 'عادی',
  `current_run_id` INT UNSIGNED NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `closed_at` DATETIME NULL,
  `ending_notified_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_camp_ws` (`workspace_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NOT NULL,
  `workspace_id` INT UNSIGNED NOT NULL,
  `seq` INT UNSIGNED NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `version` INT NOT NULL DEFAULT 1,
  `perspective` VARCHAR(120) NOT NULL,
  `primary_id` VARCHAR(20) NOT NULL,
  `secondary_id` VARCHAR(20) NULL,
  `mix` INT NOT NULL DEFAULT 100,
  `reason` TEXT NULL,
  `reason_specific` TINYINT(1) NULL,
  `allocation` MEDIUMTEXT NOT NULL,
  `goal_type` VARCHAR(12) NOT NULL,
  `goal_value` BIGINT NOT NULL DEFAULT 0,
  `benchmark_based` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id` INT UNSIGNED NOT NULL,
  `version` INT NOT NULL,
  `perspective` VARCHAR(120) NOT NULL,
  `reason` TEXT NULL,
  `allocation` MEDIUMTEXT NOT NULL,
  `note` VARCHAR(120) NOT NULL DEFAULT '',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_pv_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `simulations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NOT NULL,
  `plan_version` INT NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `band_width` VARCHAR(12) NOT NULL,
  `ext_factor` VARCHAR(20) NOT NULL,
  `occasion` VARCHAR(80) NOT NULL,
  `allocation` MEDIUMTEXT NOT NULL,
  `result` MEDIUMTEXT NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sim_camp` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pace_snapshots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NOT NULL,
  `day` INT NOT NULL,
  `spend` BIGINT NOT NULL DEFAULT 0,
  `installs` INT NOT NULL DEFAULT 0,
  `conversions` INT NOT NULL DEFAULT 0,
  `source` VARCHAR(12) NOT NULL DEFAULT 'manual',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_pace_camp` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `runs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `campaign_id` INT UNSIGNED NULL,
  `simulation_id` INT UNSIGNED NULL,
  `seq` INT UNSIGNED NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `input` MEDIUMTEXT NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'confirmed',
  `source` VARCHAR(20) NOT NULL DEFAULT 'manual',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_run_ws` (`workspace_id`),
  KEY `ix_run_camp` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id` INT UNSIGNED NOT NULL,
  `cause` VARCHAR(40) NOT NULL,
  `calibratable` TINYINT(1) NOT NULL DEFAULT 0,
  `result` MEDIUMTEXT NOT NULL,
  `rules_snapshot` TEXT NOT NULL,
  `narrative` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ver_run` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calibrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `verification_id` INT UNSIGNED NOT NULL,
  `run_id` INT UNSIGNED NOT NULL,
  `rate_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `row_label` VARCHAR(100) NOT NULL,
  `before_row` TEXT NOT NULL,
  `after_row` TEXT NOT NULL,
  `weights` VARCHAR(255) NOT NULL DEFAULT '',
  `applied_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `reverted_at` DATETIME NULL,
  `reverted_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `ix_cal_ws` (`workspace_id`),
  KEY `ix_cal_rate` (`rate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `perspective_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `campaign_id` INT UNSIGNED NULL,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `perspective` VARCHAR(120) NOT NULL,
  `reason` TEXT NULL,
  `cause` VARCHAR(40) NOT NULL,
  `calibrated` TINYINT(1) NOT NULL DEFAULT 0,
  `planned_cac` DECIMAL(16,2) NOT NULL DEFAULT 0,
  `actual_cac` DECIMAL(16,2) NOT NULL DEFAULT 0,
  `actual_poas` DECIMAL(12,6) NULL,
  `goal_hit` TINYINT(1) NULL,
  `lift` DECIMAL(10,6) NULL,
  `seeded` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_pl_ws` (`workspace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT '',
  `action` VARCHAR(190) NOT NULL,
  `detail` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_audit_ws` (`workspace_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `name` VARCHAR(60) NOT NULL,
  `props` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ev_ws` (`workspace_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(8) NOT NULL DEFAULT 'info',
  `type` VARCHAR(30) NOT NULL DEFAULT 'info',
  `text` VARCHAR(500) NOT NULL,
  `link` VARCHAR(255) NOT NULL DEFAULT '',
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_notif_user` (`user_id`, `workspace_id`, `read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `llm_calls` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `task` VARCHAR(40) NOT NULL,
  `prompt_version` VARCHAR(40) NOT NULL,
  `provider` VARCHAR(20) NOT NULL,
  `model` VARCHAR(80) NOT NULL DEFAULT '',
  `latency_ms` INT NOT NULL DEFAULT 0,
  `tokens_in` INT NOT NULL DEFAULT 0,
  `tokens_out` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(12) NOT NULL,
  `error_code` VARCHAR(190) NOT NULL DEFAULT '',
  `debug` MEDIUMTEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_llm_ws` (`workspace_id`, `created_at`),
  KEY `ix_llm_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_cache` (
  `k` CHAR(64) NOT NULL,
  `v` MEDIUMTEXT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `k` CHAR(64) NOT NULL,
  `hits` INT NOT NULL DEFAULT 0,
  `window_start` INT NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(80) NOT NULL DEFAULT '',
  `key_hash` CHAR(64) NOT NULL,
  `last4` VARCHAR(8) NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `last_used_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_key_hash` (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `connector_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(20) NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'connected',
  `creds_enc` TEXT NULL,
  `base_url` VARCHAR(255) NOT NULL DEFAULT '',
  `last_sync_at` DATETIME NULL,
  `last_error` VARCHAR(255) NOT NULL DEFAULT '',
  `fail_count` INT NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conn` (`workspace_id`, `kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `connector_links` (
  `campaign_id` INT UNSIGNED NOT NULL,
  `connector_account_id` INT UNSIGNED NOT NULL,
  `external_id` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`campaign_id`, `connector_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `connector_syncs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `connector_account_id` INT UNSIGNED NOT NULL,
  `campaign_id` INT UNSIGNED NOT NULL,
  `day` DATE NOT NULL,
  `payload` TEXT NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'ok',
  `error` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sync` (`connector_account_id`, `campaign_id`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `tier` VARCHAR(20) NOT NULL,
  `amount_rial` BIGINT NOT NULL,
  `authority` VARCHAR(80) NOT NULL DEFAULT '',
  `ref_id` VARCHAR(80) NOT NULL DEFAULT '',
  `status` VARCHAR(12) NOT NULL DEFAULT 'pending',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `verified_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `ix_pay_auth` (`authority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `body` TEXT NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'open',
  `reply` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `company` VARCHAR(160) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `phone` VARCHAR(40) NOT NULL DEFAULT '',
  `spend_band` VARCHAR(60) NOT NULL DEFAULT '',
  `utm_source` VARCHAR(80) NOT NULL DEFAULT '',
  `utm_medium` VARCHAR(80) NOT NULL DEFAULT '',
  `utm_campaign` VARCHAR(80) NOT NULL DEFAULT '',
  `referrer` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `data_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(10) NOT NULL,
  `status` VARCHAR(12) NOT NULL DEFAULT 'pending',
  `requested_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_runs` (
  `job` VARCHAR(60) NOT NULL,
  `last_run_at` DATETIME NOT NULL,
  `last_status` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`job`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Global reference data (all workspaces)
SET NAMES utf8mb4;

INSERT INTO `occasions` (`name`, `purchase_lift`, `cpi_delta`, `month_from`, `month_to`, `sort`) VALUES
('بدون مناسبت', 0, 0, 0, 0, 0),
('یلدا (آذر)', 0.12, 0.08, 9, 9, 1),
('بلک‌فرایدی (آبان)', 0.35, 0.25, 8, 8, 2),
('نوروز (اسفند–فروردین)', 0.28, 0.18, 12, 1, 3),
('ماه رمضان', -0.08, -0.05, 0, 0, 4),
('بازگشایی مدارس (شهریور)', 0.10, 0.06, 6, 6, 5);

INSERT INTO `industry_factors` (`channel`, `factor`) VALUES
('گوگل', 1.08), ('تپسل', 0.95), ('یکتانت', 1.02), ('اینستاگرام', 0.90), ('پوش', 1.10), ('پیامک', 1.05);

INSERT INTO `settings` (`k`, `v`) VALUES
('ai_provider', 'mock'),
('metis_base_url', 'https://api.metisai.ir/openai/v1'),
('metis_model_fast', 'gpt-4o-mini'),
('metis_model_smart', 'gpt-4o'),
('ai_timeout_fast_ms', '8000'),
('ai_timeout_smart_ms', '20000'),
('ai_json_schema', '1'),
('ai_debug', '0'),
('ai_budget_trial', '200000'),
('ai_budget_growth', '2000000'),
('ai_budget_enterprise', '20000000'),
('demo_enabled', '1'),
('connector_mode', 'mock'),
('payment_provider', 'sandbox'),
('price_growth_rial', '49000000'),
('price_growth_label', '۴٫۹ میلیون تومان / ماه'),
('smtp_secure', 'tls'),
('smtp_port', '587'),
('mail_from_name', 'Campaign Loop');

INSERT INTO `users` (`id`,`email`,`name`,`company`,`password_hash`,`email_verified_at`,`is_admin`,`must_change_password`,`last_workspace_id`,`prefs`,`created_at`) VALUES (1,'admin@example.com','مدیر سامانه','مرچنت نمونه','$2y$11$xkOFgWaV2PIbP5Pb3UphZ.CCLjr0/1PIK/Ylup9V9M5/Oyw5XxbYa',NOW(),1,1,1,'{"welcome":1}',NOW());
INSERT INTO `workspaces` (`id`,`name`,`tier`,`is_demo`,`created_at`) VALUES (1,'فضای کاری مرچنت نمونه','enterprise',1,NOW());
INSERT INTO `memberships` (`workspace_id`,`user_id`,`role`,`created_at`) VALUES (1,1,'owner',NOW());
INSERT INTO `rules` (`workspace_id`) VALUES (1);
INSERT INTO `merchant_profiles` (`workspace_id`,`monthly_budget`,`gross_margin`,`target_cac`,`ltv`,`business_goal`,`season_note`,`blocked_channels`,`updated_at`) VALUES (1,800000000,0.38,320000,4200000,'سودآوری','اوج فروش در آبان و اسفند','',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'گوگل','کاربر جدید','paid',62000,0.075,850000,0.22,6,25000,0.06,0.32,0.14,0.02,DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'گوگل','فعال','paid',58000,0.11,1150000,0.18,4,8000,0.05,0.61,0.42,0.02,DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'تپسل','کاربر جدید','paid',45000,0.055,850000,0.3,5,30000,0.12,0.32,0.14,0.07,DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'تپسل','بازگشتی','paid',41000,0.09,980000,0.26,3,6000,0.09,0.44,0.24,0.07,DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'یکتانت','فعال','paid',50000,0.135,1150000,0.12,7,9000,0.08,0.61,0.42,0.04,DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'یکتانت','کاربر جدید','paid',54000,0.07,850000,0.19,5,14000,0.07,0.32,0.14,0.04,DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'اینستاگرام','کاربر جدید','paid',38000,0.048,850000,0.34,8,40000,0.21,0.32,0.14,0.05,DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'اینستاگرام','پرارزش','paid',72000,0.065,2400000,0.28,2,2500,0.18,0.71,0.55,0.05,DATE_SUB(CURDATE(), INTERVAL 7 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'پوش','فعال','owned',9000,0.062,1150000,0.14,11,12000,0.04,0.61,0.42,0,DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'پوش','در معرض ریزش','owned',8500,0.041,780000,0.2,9,9000,0.03,0.22,0.09,0,DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'پوش','پرارزش','owned',9500,0.088,2400000,0.16,6,3000,0.05,0.71,0.55,0,DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'پیامک','در معرض ریزش','owned',12000,0.052,780000,0.23,7,11000,0.1,0.22,0.09,0,DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'پیامک','بازگشتی','owned',11500,0.068,980000,0.21,5,7000,0.11,0.44,0.24,0,DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'history',NOW());
INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,'پیامک','پرارزش','owned',13000,0.095,2400000,0.17,3,2600,0.14,0.71,0.55,0,DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'history',NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۲','نصب پاییزه گوگل','گوگل','کاربر جدید',180000000,2790,205,174000000,'مهر','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۳','ریتارگت تپسل','تپسل','کاربر جدید',135000000,2810,148,125000000,'مهر','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۴','یکتانت فعال‌ها','یکتانت','فعال',90000000,1760,231,265000000,'مهر','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۵','پوش هفتگی','پوش','فعال',24000000,2600,158,181000000,'مهر','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۶','اینستا برند','اینستاگرام','کاربر جدید',210000000,5320,251,213000000,'آبان','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۷','یازده‌یازده یکتانت','یکتانت','فعال',160000000,3180,441,507000000,'آبان','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۸','پوش پرارزش','پوش','پرارزش',19000000,1980,176,422000000,'آبان','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۰۹','پیامک بازگشت','پیامک','بازگشتی',46000000,3950,265,259000000,'آبان','ورود دستی',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۰','گوگل فعال','گوگل','فعال',70000000,1190,129,148000000,'آذر','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۱','ضدریزش پوش','پوش','در معرض ریزش',21000000,2420,97,75000000,'آذر','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۲','پیامک ریزش','پیامک','در معرض ریزش',33000000,2680,133,103000000,'آذر','ورود دستی',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۳','اینستا پرارزش','اینستاگرام','پرارزش',88000000,1190,74,177000000,'آذر','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۴','تپسل بازگشتی','تپسل','بازگشتی',52000000,1250,110,107000000,'دی','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۵','یکتانت جدید','یکتانت','کاربر جدید',108000000,1980,136,115000000,'دی','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۶','پوش فعال دی','پوش','فعال',27000000,3050,183,210000000,'دی','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۷','پیامک پرارزش','پیامک','پرارزش',31000000,2350,219,525000000,'بهمن','ورود دستی',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۸','گوگل نوروزی','گوگل','کاربر جدید',240000000,3720,285,242000000,'اسفند','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۹','اینستا نوروزی','اینستاگرام','کاربر جدید',265000000,6980,321,273000000,'اسفند','اسمارتک',1,NOW());
INSERT INTO `campaign_history` (`code`,`name`,`channel`,`segment`,`spend`,`installs`,`conversions`,`revenue`,`month`,`source`,`workspace_id`,`created_at`) VALUES ('ک-۱۲۰','یکتانت اسفند','یکتانت','فعال',140000000,2740,384,442000000,'اسفند','اسمارتک',1,NOW());
INSERT INTO `perspective_log` (`code`,`name`,`perspective`,`reason`,`cause`,`calibrated`,`planned_cac`,`actual_cac`,`actual_poas`,`seeded`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۲','پیامک ریزش — آذر','سگمنت در معرض ریزش','CAC جذب مجدد از کاربر جدید ارزان‌تر بود','در دامنه‌ی انتظار',1,248000,261000,NULL,1,1,NOW());
INSERT INTO `perspective_log` (`code`,`name`,`perspective`,`reason`,`cause`,`calibrated`,`planned_cac`,`actual_cac`,`actual_poas`,`seeded`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۳','اینستا پرارزش — آذر','سگمنت پرارزش','AOV این سگمنت دو برابر میانگین بود','خطای برآورد',1,890000,1189000,NULL,1,1,NOW());
INSERT INTO `perspective_log` (`code`,`name`,`perspective`,`reason`,`cause`,`calibrated`,`planned_cac`,`actual_cac`,`actual_poas`,`seeded`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۴','تپسل بازگشتی — دی','مدیرعامل','فشار هدف سهم بازار در فصل','انحراف اجرا',0,430000,473000,NULL,1,1,NOW());
INSERT INTO `perspective_log` (`code`,`name`,`perspective`,`reason`,`cause`,`calibrated`,`planned_cac`,`actual_cac`,`actual_poas`,`seeded`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۶','پوش فعال — دی','مسئول کمپین','کمترین ریسک عملیاتی برای راه‌اندازی سریع','مقیاس، نه کیفیت',0,141000,148000,NULL,1,1,NOW());
INSERT INTO `perspective_log` (`code`,`name`,`perspective`,`reason`,`cause`,`calibrated`,`planned_cac`,`actual_cac`,`actual_poas`,`seeded`,`workspace_id`,`created_at`) VALUES ('ک-۱۱۷','پیامک پرارزش — بهمن','مدیر مالی','تنها ترکیب زیر سقف حاشیه','در دامنه‌ی انتظار',1,139000,142000,NULL,1,1,NOW());
INSERT INTO `settings` (`k`,`v`) VALUES ('installed_at', NOW()) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);
