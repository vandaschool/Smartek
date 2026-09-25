# Smartech Campaign Loop — Handoff for Claude Code

Front-end reference: `Campaign Loop v4.dc.html` (app, all logic in the `Component` class), `Landing v2.dc.html`, `Legal.dc.html`, `Service Blueprint.dc.html`.
See also `docs/FRONTEND-SPEC.md` (routes, states, components), `docs/BACKEND-SPEC.md`, `docs/DESIGN-TOKENS.md`, `docs/TEST-CASES.md`.
Visual references: `UI Kit.dc.html` (every component + state + mobile), `Emails.dc.html` (6 transactional templates).
Visual spec: `uploads/design.md`. Everything below is what the backend must implement so the UI can stop using localStorage.

## 1. Architecture
- **Layer 1 — engine (deterministic, testable, no LLM):** allocation, forecast, verifier, calibration. Port the methods `infl, cpiAdj, cac, installsAt, poas, allocate, expected, score, buildInsights, mergedAlloc, simulate, verify, applyCalibration` into a pure module (`/engine`) with unit tests. Same input → same output.
- **Layer 2 — narration:** today template strings (`narrate`). Later an LLM may rewrite text **but may only reference numbers returned by Layer 1 with a `source_ref`**. Reject any output containing a number without a ref.
- Every number displayed carries a source id (rate row / plan / sim / run).

## 2. Data model (Postgres)
```
workspace(id, name, currency, tz)
user(id, email, name, password_hash)            -- bcrypt/argon2, never plaintext
membership(workspace_id, user_id, role)          -- owner | analyst | campaign_ops | viewer
merchant_profile(workspace_id PK, budget_monthly, gross_margin, target_cac, ltv, goal, season_note)
rate(id, workspace_id, channel, segment, type[paid|owned], unit_cost, cvr, aov, variance,
     sample_n, volume_ceiling, seasonal_lift, d7, d30, fraud_rate, observed_at, source[history|benchmark|calibration])
campaign_history(id, workspace_id, name, channel, segment, spend, installs, conversions, revenue, month, source)
campaign(id, workspace_id, seq, name, status[draft|live|stopped|closed], budget, date_from, date_to, goal_type, goal_value, created_by)
plan(id, campaign_id, plan_code 'plan-1405-###', perspective_ids[], mix, reason, allocation jsonb, created_by)
simulation(id, plan_id, sim_code, band_width[narrow|normal|cautious], external_factor, result jsonb)
pace_snapshot(id, campaign_id, day, spend, installs, conversions, created_at)
run(id, campaign_id, run_code, actuals jsonb, fraud_pct, attribution_window, seasonal bool, reach, holdout_cvr, data_complete, tracker_matched)
verification(id, run_id, cause, deviations jsonb, lift, calibratable bool, rules_snapshot jsonb)
calibration(id, verification_id, rate_id, before jsonb, after jsonb, weights jsonb, applied_by)
perspective_log(id, campaign_id, perspective, reason, cause, planned_cac, actual_cac, actual_poas, goal_hit, lift)
rules(workspace_id PK, exec_th, est_th, scale_lo, scale_hi, inflation_monthly, attr_window, fraud_th)
audit_log(id, workspace_id, user_id, role, action, detail, created_at)
integration(workspace_id, provider[adtrace|intrack|adverge|affilio], status, credentials_enc)
api_key(id, workspace_id, hash, last4, created_at)
```
IDs: `seq` is per-workspace and monotonic — plan/sim/run/calibration codes all share it (bug in v1–v3 where it was fixed at 1 is fixed in the UI; enforce in DB).

## 3. API (REST, JSON, workspace-scoped, JWT)
```
POST /auth/signup  POST /auth/login  POST /auth/logout
GET/PUT  /profile
GET/PUT  /rates            POST /rates/import-benchmarks   GET /rates.csv
GET/POST /history
GET/POST /campaigns        GET /campaigns/:id
POST /campaigns/:id/insights        -> 10 insights (engine)
POST /campaigns/:id/plan            {perspective, secondary?, mix, reason}
POST /campaigns/:id/simulate        {band_width, external_factor}
POST /campaigns/:id/pace            {day, spend, installs, conversions} -> alerts
POST /campaigns/:id/run             actuals + quality flags
POST /campaigns/:id/verify          -> cause, deviations, lift
POST /campaigns/:id/calibrate       (owner|analyst only; only if calibratable)
POST /campaigns/:id/close           -> perspective_log row
GET  /campaigns/:id/report  (.json | .csv | .pdf)
GET  /perspective-log  (.csv)
GET  /dashboard?view=cfo|ceo|analyst|ops
GET/PUT /rules     GET /audit     GET/POST/DELETE /team
GET/POST /integrations/:provider   POST /api-keys
POST /webhooks/results   (server-to-server, api key auth) -> creates run
```
Every mutating endpoint writes `audit_log`. Permissions matrix = `can()` in the component.

## 4. Engine rules (source of truth = component code)
- Inflation: `cpi_adj = unit_cost × (1+inflation)^age_months`.
- Diminishing returns: `installs = B / cpi_adj / (1 + 0.5 × B / (ceiling × cpi_adj))`.
- Fraud removed from paid installs before CVR. Owned channels (push/SMS): cost = per reached user, fraud = 0.
- POAS = `(revenue × margin − spend) / spend`; payback = `CAC / (margin × AOV × 1.2 orders/month)`; LTV/CAC.
- Band = **empirical**, from `variance × width multiplier` (0.7 / 1 / 1.4). Label must never say "confidence interval".
- Verifier order (first match wins), thresholds from `rules`:
  1. incomplete data | tracker mismatch | attribution window ≠ plan | fraud > fraud_th → data mismatch (no calib)
  2. |Δbudget| > exec_th → execution deviation (no calib)
  3. Δinstalls/Δbudget in [scale_lo, scale_hi] and |ΔCVR| ≤ 15% → scale not quality (no calib)
  4. |Δinstalls| or |ΔCVR| > est_th → estimation error (calib)
  5. else → within expectation (calib)
- Incrementality: `lift = (treated_cvr − holdout_cvr) / treated_cvr` when holdout supplied.
- Calibration: weighted mean, `w_new = clamp(spend / (0.4 × ceiling × cpi), 0.3, 3)`, `w_old = n × 0.9^age`; observations are de-inflated and de-seasoned before merging; reset `age=0`.
- Pacing alerts: spend ±20%, conversions −25% vs `forecast × day/30`.
- Perspective quality = share of closed campaigns with POAS ≥ 0 and attributable cause; show selection-bias note.

## 5. Must-do before production
- Real Jalali date picker + validation (UI validates format/order only).
- Integrations are UI stubs: implement Adtrace (spend, installs, fraud) & Intrack (events, cohorts, retention) ingestion jobs.
- Replace localStorage (`smartech_loop_v5`) with API; keep optimistic UI.
- Shareable report link = signed read-only URL.
- Notifications: campaign end date → "record results" email/push; pacing alert → notify.
- Product analytics: track `plan_created, sim_run, pace_logged, run_verified, calibration_applied, campaign_closed`. North-star: **closed loops per workspace per month**.
- Accessibility: keep text ≥ 4.5:1 (muted greys were raised to #6b7072).
- Mobile: tables need card fallback < 640px.

## 6. Image slots to fill
`app-logo`, landing: `nav-logo`, `footer-logo`, `logo-intrack/adverge/adtress/affilio`, `hero-shot` 16:10, `shot-insight/funnel/verifier` 4:3; integrations page: `int-*` logos.


## 7. Added in v4.1 (UI done, backend needed)
- **Auth:** forgot password (reset link, 30 min), email verification banner, invite → pending → accept.
- **Plans:** versioning (edit before verification keeps seq, bumps version; history kept), what-if scenario (budget ±, shift row1→row2) applied as a new version, in-flight reallocation suggestion from pacing.
- **Campaigns:** archive; trial tier limited to 3/month → billing page.
- **Calibration undo:** last 10 calibrations reversible (store previous row snapshot).
- **Occasion calendar:** Iranian occasions with purchase lift and CPI change → feeds forecast factor. Make it a table `occasion(name, month, lift, cpi_delta)`.
- **Industry benchmark:** per-channel median CAC from anonymized aggregate (min 10 merchants).
- **Ask data:** rule-based Q&A grounded in `rate` rows, returns `source_ref`; unmatched questions return no number. LLM later must keep this contract.
- **Notifications:** in-app inbox (pacing alerts, campaign closed, plan changes). Add email/push + scheduled monthly manager report.
- **Product analytics:** events `plan_created, plan_edited, pace_logged, run_verified, campaign_closed, question_asked, email_verified`.
- **Security:** 2FA toggle, active sessions, data export and deletion request (30-day purge).
- **Billing:** tiers آزمایشی / رشد / سازمانی; payment gateway not implemented.
- **Support:** FAQ, ticket form → support system.
- **System states:** offline banner (navigator events), 404 fallback, role read-only banner.
- **Landing:** pricing, demo request form (store as `lead`), legal links.

## 8. Added in v4.2
- Jalali date picker (day/month/year selects, month lengths + leap Esfand, duration hint) in campaign design.
- Workspace switcher (enterprise tier) in header → `X-Workspace-Id`.
- `:focus-visible` outline, aria-labels on icon/select controls.
- Landing SEO/OG meta; demo form captures UTM.
- Full backend additions: `docs/BACKEND-SPEC.md` (tables, endpoints, state machine, notifications, emails, tracking plan, NFRs).
- Claude Code kickoff: `docs/CLAUDE-CODE-PROMPT.md`.

## 9. Left for the production build (not in prototype)
- Responsive card layout for tables < 640px (prototype scrolls horizontally).
- Real email/payment/integration providers.
- Skeleton loading states (prototype has no network latency).
- Screenshot & partner logo image slots on landing.

## 10. Added in v4.3
- `UI Kit.dc.html`: colors, type, buttons, inputs, badges (incl. cause & verdict mapping), callouts/banners, cards, table, nav shell, overlays, system states, 3 mobile reference screens, a11y/RTL rules.
- `Emails.dc.html`: verify_email, reset_password, invite, pace_alert, campaign_ending, monthly_report.
- `docs/FRONTEND-SPEC.md`: route map with role gating, per-screen states, validation, data fetching, component list, DoD.
- New campaign status `awaiting_result` (date_to passed, no run) — surfaced in list and monthly report.
