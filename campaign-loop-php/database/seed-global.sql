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
