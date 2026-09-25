# Build brief for Claude Code — Campaign Loop

> Paste this whole file as your first message to Claude Code, from the root of this folder.

You are the lead engineer building the **production version of Campaign Loop** for Smartech. A complete, working reference prototype and full specs are in this folder. Your job is to turn them into a real, deployable product — backend, frontend, jobs, emails, AI layer (via **Metis**), and tests — with behavior and Persian copy identical to the prototype.

## 0. Read first (in this order) and summarize back to me before writing code
1. `CLAUDE.md`
2. `docs/HANDOFF.md` — data model, API, engine, roles
3. `docs/FRONTEND-SPEC.md` — routes, per-screen states, validation, components
4. `docs/BACKEND-SPEC.md` — extra tables/endpoints, state machine, business rules, notifications, tracking
5. `docs/AI-INTEGRATION-METIS.md` — LLM layer through Metis
6. `docs/INTEGRATIONS.md` — Adtrace / Intrack connectors
7. `docs/TEST-CASES.md`, `docs/DESIGN-TOKENS.md`
8. `product/PRD-MVP-Campaign-Loop-v5.md`
9. Open `design/Campaign Loop v4.dc.html` in a browser and click through: signup → welcome → data (CSV) → setup → design → 10 perspectives → plan → simulate + what-if → pace + realloc → verify + calibrate + undo → close loop → report → dashboards → method → ask → platform pages. Read its `class Component` — it is the executable spec.
Then give me: a list of every screen, every entity, every endpoint, and any contradictions you found between docs and prototype (prototype wins unless it's a bug).

## 1. Stack (use exactly this unless you ask first)
- Monorepo: **pnpm + Turborepo**
  - `apps/web` — Next.js 15 (App Router, RSC), TypeScript strict, Tailwind (theme generated from DESIGN-TOKENS), React Query, react-hook-form + zod, next-intl not needed (fa only).
  - `apps/worker` — BullMQ workers + cron (Asia/Tehran timezone).
  - `packages/engine` — pure TS port of `engine/engine.js` (no I/O).
  - `packages/db` — PostgreSQL 16 + Prisma; migrations + seed.
  - `packages/ai` — Metis provider, prompts, number firewall, evals.
  - `packages/connectors` — Adtrace / Intrack adapters (mock + real).
  - `packages/emails` — react-email templates matching `design/Emails.dc.html`.
  - `packages/ui` — components from `design/UI Kit.dc.html`.
- Auth: email + password (argon2id), sessions in DB (httpOnly cookie), TOTP 2FA, email verification, reset tokens (hashed, 30 min).
- Redis for queues, cache, rate limits. S3-compatible storage (ArvanCloud/MinIO) for CSV uploads and PDF reports.
- PDF: Playwright print of the report route.
- Email: SMTP (configurable) — dev uses Mailpit.
- Jalali dates: `date-fns-jalali`.
- Observability: pino logs, OpenTelemetry traces, Sentry (optional env).
- Docker Compose for local: postgres, redis, minio, mailpit. Provide `Dockerfile`s for web + worker.

## 2. Milestones — stop after each, run tests, show me, wait for OK
**M0 · Scaffold** — monorepo, lint, typecheck, CI (GitHub Actions: lint, typecheck, unit, e2e), docker-compose, `env.example` → `.env`. Acceptance: `pnpm dev` shows an RTL page with the logo.

**M1 · Database** — Prisma schema for every table in HANDOFF §2 + BACKEND-SPEC §1 (+ `llm_call`, `connector_account`, `connector_sync` from the new docs). All scoped by `workspace_id`. Seed from `fixtures/seed.json`. Acceptance: `pnpm db:migrate && pnpm db:seed` creates the demo workspace.

**M2 · Engine** — port to `packages/engine`; port `engine.test.js` and every case in TEST-CASES.md; add property tests (budget↑ ⇒ installs↑ with diminishing returns; POAS sign consistency). Acceptance: 100% of cases green; engine coverage ≥ 95%.

**M3 · Auth & workspace** — signup, verify email, login, logout, forgot/reset, sessions list/revoke, 2FA, invites (pending → accept/revoke/expire), roles + server `can()`, workspace switcher (tier سازمانی). Rate limits per BACKEND-SPEC §8.

**M4 · Data** — merchant profile, rate table CRUD, campaign history, **CSV import** (template download, parse Persian/Latin digits and ٬ ٫, row-level errors, preview, commit only valid rows), readiness checklist, stale/low-n flags, industry benchmarks (hidden if n_merchants < 10).

**M5 · The loop** — campaigns list with statuses (live, awaiting_result, closed, stopped, archived); design inputs incl. occasion calendar; 10 perspectives; plan with mandatory reason + versioning; simulate with band width; what-if scenario; pace logging + alerts + realloc suggestion; run result + verifier (5 branches, configurable thresholds); calibration (weighted, de-inflated) + revert last 10; close loop + perspective log (decision quality, n<3 warning). State machine exactly per BACKEND-SPEC §3.

**M6 · Outputs** — report (JSON/CSV/PDF, share link for verified users), dashboards (?view=cfo|cmo|analyst), methodology page (live thresholds), audit log page with filters.

**M7 · AI layer via Metis** — implement `docs/AI-INTEGRATION-METIS.md` fully: provider abstraction (`mock` default, `metis` when key present), all 7 tasks, JSON-schema outputs, number firewall, source_ref validation, cache, circuit breaker, budgets, `llm_call` logging, feature flag. **When you reach M7, ask me for `METIS_API_KEY` and the model names; until then build and test everything on the mock provider.** Acceptance: `pnpm ai:smoke` passes on metis; `pnpm ai:eval` ≥ 90% intent accuracy and **0 number violations**; with the key removed, every AI surface falls back to the deterministic template with no UI error.

**M8 · Notifications, emails, jobs** — in-app inbox + triggers (BACKEND-SPEC §5), 6 email templates, monthly report cron (1st of Jalali month 09:00 Tehran), campaign_ending reminder, pace alert. Notification preferences + unsubscribe (not for security mails).

**M9 · Connectors** — Adtrace + Intrack per `docs/INTEGRATIONS.md`: mock adapters first, daily sync job, webhook receiver, idempotency, backfill. Real API keys will be given later; keep everything behind `CONNECTOR_MODE=mock|live`.

**M10 · Platform** — billing tiers + limits (trial 3 campaigns/month → 402 + upgrade), payment gateway interface (ZarinPal/IDPay adapter, sandbox), support tickets, leads from landing (with UTM), data export + delete request (30-day purge job), product analytics events (BACKEND-SPEC §7) + funnel/north-star endpoints.

**M11 · Frontend 1:1** — rebuild every screen of the prototype in `apps/web` using `packages/ui`: landing (+ pricing, demo form), legal, auth, welcome modal, 7-step tour, all app pages, 404, offline banner, role banners. Mobile < 768 (phase tabs + step pills, tables → cards), keyboard (Esc closes overlays, focus ring), reduced motion. Copy verbatim. Every number rendered through `<Num value sourceRef>`.

**M12 · Hardening** — Playwright E2E: (a) signup → first closed loop on demo data in < 5 min, (b) CSV import with errors, (c) calibration + revert, (d) role gating for each role, (e) AI fallback when Metis is down. Load test engine endpoints (p95 < 300 ms). Security pass (OWASP top 10, CSRF, rate limits, headers). Accessibility audit (axe: 0 serious). Write `README.md` for deploy.

## 3. Working rules
- Small PR-sized commits with clear messages; one milestone per branch.
- If a doc and the prototype disagree, tell me and follow the prototype.
- If something is ambiguous, ask — don't invent product behavior.
- Never show a number in the UI without a `source_ref`. Never let the LLM create one.
- Keep a `docs/DECISIONS.md` log of every non-trivial technical choice.
- At the end of each milestone post: what's done, how to run it, test results, open questions.

Start with step 0.
