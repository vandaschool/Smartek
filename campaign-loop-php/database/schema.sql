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
