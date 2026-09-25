# Campaign Loop — Backend Spec (v4.2, complements HANDOFF.md §2–4)

## 1. Additional tables
```
workspace_member_invite(id, workspace_id, email, role, token_hash, status[pending|accepted|revoked|expired], expires_at, invited_by)
email_verification(user_id, token_hash, expires_at, verified_at)
password_reset(user_id, token_hash, expires_at, used_at)                 -- 30 min, single use
session(id, user_id, device, ip_city, created_at, last_seen_at, revoked_at)
user_2fa(user_id PK, secret_enc, enabled_at, recovery_codes_hash[])
plan_version(id, plan_id, version, perspective_ids[], allocation jsonb, reason, created_by, created_at)  -- plan.current_version FK
scenario(id, plan_id, budget_delta_pct, shift_pct, result jsonb, applied bool)
realloc_suggestion(id, campaign_id, pace_snapshot_id, from_row, to_row, shift_pct, expected_before, expected_after, status[open|applied|dismissed])
calibration.reverted_at, calibration.reverted_by                             -- undo = restore `before`
occasion(id, name, date_from, date_to, purchase_lift, cpi_delta, channels[])  -- global table, admin-editable
benchmark(channel, segment, period, median_cac, p25, p75, n_merchants)       -- rebuilt nightly, only if n_merchants >= 10
qa_log(id, workspace_id, user_id, question, answer, source_refs[], grounded bool, created_at)
notification(id, workspace_id, user_id NULL=all, kind[info|ok|bad], type, text, link_page, entity_id, read_at, created_at)
notification_pref(user_id, type, in_app bool, email bool, push bool)
event(id, workspace_id, user_id, name, props jsonb, created_at)             -- product analytics
subscription(workspace_id PK, tier[trial|growth|enterprise], status, period_end, seats, provider_ref)
usage_counter(workspace_id, period 'YYYY-MM', campaigns_created)
support_ticket(id, workspace_id, user_id, body, status, created_at)
lead(id, name, company, email, spend_band, utm_source, utm_medium, utm_campaign, referrer, created_at)
data_request(id, workspace_id, type[export|delete], status, requested_by, completed_at)
import_job(id, workspace_id, kind[history|rates], filename, ok_rows, error_rows jsonb, status, created_by)
```
Campaign status adds `archived`.

## 2. Additional endpoints
```
POST /auth/verify-email {token}          POST /auth/resend-verification
POST /auth/forgot {email}  -> always 200 (no user enumeration)
POST /auth/reset {token, password}
POST /auth/2fa/setup -> otpauth uri     POST /auth/2fa/enable {code}   POST /auth/2fa/disable {code}
GET  /sessions      DELETE /sessions/:id
POST /invites {email, role}   GET /invites   DELETE /invites/:id   POST /invites/accept {token}
GET  /workspaces    POST /workspaces    (enterprise only; header X-Workspace-Id selects scope)
POST /imports/:kind  (multipart csv) -> {ok_rows, errors[{line,msg}]} preview
POST /imports/:id/commit
GET  /templates/:kind.csv
PUT  /campaigns/:id/plan        -> new plan_version (only before run exists)
POST /campaigns/:id/scenario    {budget_delta_pct, shift_pct} -> forecast diff
POST /campaigns/:id/scenario/:sid/apply
GET  /campaigns/:id/realloc     POST /campaigns/:id/realloc/:rid/apply
POST /campaigns/:id/archive
POST /calibrations/:id/revert   (owner|analyst, only most recent per rate row)
GET  /occasions?from&to
GET  /benchmarks?channel
POST /ask {question} -> {answer, source_refs[], grounded}
GET  /notifications   POST /notifications/read-all   GET/PUT /notification-prefs
POST /events (batch)  GET /analytics/funnel   GET /analytics/north-star
GET  /billing   POST /billing/checkout {tier}   POST /webhooks/payment
POST /support/tickets
POST /leads  (public, rate-limited, captcha)
POST /data-requests {type}
GET  /methodology/rules  (same as /rules, public to all roles)
```

## 3. Campaign state machine
```
draft ──plan──▶ planned(v1..n) ──simulate──▶ live ──pace*──▶ live
live ──run──▶ ended ──verify──▶ verified ──(calibrate?)──▶ closed ──archive──▶ archived
live ──stop──▶ stopped ──archive──▶ archived
```
- Plan edits allowed in `planned|live` until a `run` exists → new `plan_version`.
- `verify` is idempotent per run; re-running with changed rules creates a new verification row.
- Only one `live` campaign per (workspace, channel, segment) — others are auto-`stopped` (UI behavior).

## 4. Business rules
- Trial: 3 campaigns / calendar month (`usage_counter`); block `POST /campaigns` with 402 + upgrade link.
- Unverified email: block invites, report export, share link (403 `EMAIL_NOT_VERIFIED`).
- Occasion factor: forecast factor × (1+lift)/(1+cpi_delta) for occasions overlapping ≥50% of campaign window.
- Realloc suggestion: only if pace conversions ≤ −25% vs expected and plan has ≥2 rows; shift 30% from row 1 to row 2 only if expected conversions rise.
- Ask: deterministic intent matching → rate rows. Unmatched intent returns `grounded:false` and no number. Future LLM must return `source_refs` referencing existing rows; server rejects numbers not present in referenced rows.
- Benchmarks: never expose if `n_merchants < 10`.
- Delete request: purge in ≤30 days incl. backups; keep an anonymized `data_request` row.

## 5. Notifications (triggers)
| type | trigger | channels |
|---|---|---|
| pace_spend | pace spend > +20% expected | in-app, email |
| pace_conv | pace conversions < −25% | in-app, email |
| campaign_ending | 2 days before date_to, no run | in-app, email |
| campaign_closed | close | in-app |
| calibration_applied | calibrate / revert | in-app (owner, analyst) |
| invite_accepted | accept | in-app (owner) |
| plan_changed | new tier | in-app |
| monthly_report | 1st of Jalali month 09:00 Asia/Tehran | email to owner + viewers |

## 6. Emails (transactional templates, RTL, Vazirmatn fallback Tahoma)
verify_email · reset_password · invite · pace_alert · campaign_ending · monthly_report (KPIs: closed loops, POAS, CAC vs target, best perspective, link to dashboard?view=cfo).
Header: `assets/logo-horizontal.png`; footer: logo-tagline + unsubscribe (except security mails).

## 7. Tracking plan
| event | props |
|---|---|
| signup_completed | source(utm_*) |
| email_verified | — |
| welcome_choice | tour\|demo\|upload |
| import_committed | kind, ok_rows, error_rows |
| plan_created / plan_edited | perspective, version |
| scenario_applied | budget_delta_pct, shift_pct |
| pace_logged | day |
| realloc_applied | shift_pct |
| run_verified | cause |
| calibration_applied / calibration_reverted | rate_row |
| campaign_closed | poas, goal_hit |
| question_asked | grounded |
| upgrade_clicked / subscription_started | tier |
| lead_submitted (landing) | spend_band, utm_* |

North star = `campaign_closed` per workspace per month. Activation = first `plan_created` within 24h of signup. Retention = ≥1 `campaign_closed` in month 2.

## 8. Non-functional
- Persian digits in UI only; API uses Latin digits, ISO dates + `jalali` string field.
- Money in integer Toman (bigint). Rates as numeric(12,6).
- Rate limit: auth 10/min/IP, leads 5/min/IP, ask 30/min/user.
- Audit every mutation; PII minimal; secrets via KMS.
- p95 < 300ms for engine endpoints (pure functions, `engine/engine.js`).
