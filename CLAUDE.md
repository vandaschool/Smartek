# CLAUDE.md — Campaign Loop (Smartech)

Persian RTL B2B SaaS: merchants design a marketing campaign, get a deterministic forecast, monitor pacing, record actual results, get the *cause* of any deviation, and recalibrate their rate table. One cycle = one "loop". North-star: closed loops / workspace / month.

## Where things are
- `PROMPT.md` — the full build brief and milestone plan. Follow it in order.
- `design/` — **reference prototype (source of truth for behavior + Persian copy)**. Open the `.dc.html` files in a browser. All app logic lives in the `class Component` inside `design/Campaign Loop v4.dc.html`.
  - `UI Kit.dc.html` = visual source of truth (tokens, components, states, mobile).
  - `Landing v2.dc.html`, `Legal.dc.html`, `Emails.dc.html`, `Service Blueprint.dc.html`.
  - `assets/` = logos (horizontal, mark, tagline). Copy to `apps/web/public/brand/`.
- `docs/` — HANDOFF (data model, API, engine), BACKEND-SPEC, FRONTEND-SPEC, DESIGN-TOKENS, TEST-CASES, AI-INTEGRATION-METIS, INTEGRATIONS.
- `engine/engine.js` + `engine.test.js` — the extracted deterministic engine. Port to TS without changing formulas.
- `fixtures/seed.json` (demo workspace), `fixtures/ai-evals.jsonl` (LLM eval set).
- `product/` — PRD and Smartech design-system notes.
Docs refer to prototype files by bare name (e.g. `Campaign Loop v4.dc.html`) — they are in `design/`.

## Non-negotiable rules
1. **Every number shown to a user comes from the engine or the DB and carries a `source_ref`.** The LLM (Metis) never produces a number; a server-side number firewall enforces this.
2. Engine functions are pure and deterministic. Never change a formula without first adding a failing test in `packages/engine`.
3. Every mutation writes `audit_log` (who, role, what, detail, ts).
4. Role matrix `can(role, action)` is enforced on the server; UI only mirrors it.
5. Persian UI: `<html lang="fa" dir="rtl">`, Vazirmatn, Persian digits via one `formatFa()` util. API uses Latin digits, ISO dates + a `jalali` string. Money = integer Toman (bigint).
6. Copy is verbatim from the prototype. Don't rewrite Persian text.
7. Inline secrets never. Read from env; `env.example` lists them.
8. Stop after each milestone in PROMPT.md, show what you built, run tests, and wait for my OK.

## Commands (create these)
`pnpm dev` · `pnpm test` · `pnpm test:engine` · `pnpm e2e` · `pnpm db:migrate` · `pnpm db:seed` · `pnpm ai:smoke` · `pnpm ai:eval` · `pnpm lint` · `pnpm typecheck`

## Definition of done (every screen)
Matches UI Kit · loading / empty / error / permission / success states · role gating · mobile < 768 (tables → cards) · keyboard path + focus ring · copy identical · analytics events fired on success · Playwright happy path.
