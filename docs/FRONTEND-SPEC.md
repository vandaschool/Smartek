# Campaign Loop — Frontend Spec (v4.3)

Visual source of truth: `UI Kit.dc.html` (components, states, mobile). Behavior & copy: `Campaign Loop v4.dc.html`. Emails: `Emails.dc.html`. Tokens: `docs/DESIGN-TOKENS.md`.

## 1. Routes
Public: `/` (landing) · `/pricing` (anchor on landing) · `/legal/terms` · `/legal/privacy` · `/legal/data` · `/login` · `/signup` · `/forgot` · `/reset/:token` · `/verify/:token` · `/invite/:token`.

App (`/app/:workspaceId/...`, auth required):

| phase | route | prototype key | min role to view | primary action |
|---|---|---|---|---|
| 0 آماده‌سازی | `/campaigns` | campaigns | viewer | کمپین تازه (manager+) |
| | `/data` | data | viewer | بارگذاری CSV / افزودن ردیف (owner) |
| | `/setup` | setup | viewer | ذخیره‌ی پروفایل (owner) |
| 1 حلقه | `/c/:id/design` | design | viewer | ساخت ۱۰ دیدگاه (manager+) |
| | `/c/:id/insights` | insights | viewer | ثبت طرح + دلیل (manager+) |
| | `/c/:id/sim` | sim | viewer | ثبت پیش‌بینی · سناریو |
| | `/c/:id/pace` | pace | viewer | ثبت پایش · جابه‌جایی بودجه |
| | `/c/:id/verify` | verify | viewer | ثبت نتیجه (manager+) · کالیبراسیون (analyst+) |
| 2 خروجی | `/c/:id/loop` | loop | viewer | — |
| | `/c/:id/report` | report | viewer | خروجی CSV/PDF · لینک اشتراک |
| | `/log` | log | viewer | — |
| | `/dash?view=cfo\|cmo\|analyst` | dash | viewer | — |
| | `/method` | method | viewer | — |
| | `/ask` | ask | viewer | بپرس |
| 3 پلتفرم | `/connect` | connect | owner | اتصال |
| | `/team` | team | viewer (edit: owner) | دعوت |
| | `/rules` | rules | viewer (edit: owner) | ذخیره |
| | `/audit` | audit | analyst+ | فیلتر |
| | `/analytics` | analytics | owner | — |
| | `/security` | security | self | 2FA · نشست‌ها · داده |
| | `/billing` | billing | owner | تغییر پلن |
| | `/help` | help | viewer | ارسال تیکت |
| | `/settings` | settings | self | — |

`/c/:id/*` without an active campaign → redirect `/campaigns`. Unknown → 404 screen.

## 2. Global shell
- Header 55px: logo-horizontal · Smartech logo slot · phase tabs · workspace switcher (enterprise) · notifications · avatar menu (settings, security, logout).
- Sub-nav pills per phase. Loop status bar (5 steps + "next step" CTA) on phase 1–2 campaign pages.
- Banners (max 2, priority): offline → email unverified → role read-only → plan limit.
- Prototype-only (remove): role switcher, "simulate verify", "simulate accept invite", "simulate offline", demo reset.
- Tour (7 steps) and welcome modal (after signup) persist dismissal per user (`user_pref.onboarding`).

## 3. Per-screen required states
Every data screen implements: loading (skeleton after 300ms) · empty (title + reason + one action) · error (human message + code + retry) · permission (disabled with reason) · success. Specifics:

| screen | empty | special |
|---|---|---|
| campaigns | «هنوز کمپینی ندارید» + demo/upload | statuses: live, awaiting_result (date_to passed, no run), closed, stopped, archived |
| data | readiness checklist | CSV preview, row errors, commit; stale (>3mo) and low-n (<5) badges |
| insights | need readiness ≥ threshold | 10 cards, selected state, reason required (≥10 chars) |
| sim | need plan | band width pills, what-if sliders, POAS verdict, diminishing-returns note |
| pace | need plan | day slider, alerts, realloc callout |
| verify | need plan | 5-branch cause badge, calibrate preview, undo last |
| report | need verified run | print layout, share link (verified email only) |
| log | "بعد از اولین حلقه" | decision quality per perspective, n<3 warning |
| ask | suggestions | grounded vs ungrounded answer styles |
| billing | — | usage meter, trial limit |

## 4. Forms & validation (client mirrors server)
- Money: integer Toman, accept Persian/Latin digits, format with ٬ while typing, `inputmode="decimal"`.
- Percent: 0–100 in UI, 0–1 in API.
- Dates: Jalali `YYYY/MM/DD`; to ≥ from; campaign ≥ 1 day; warn < 7 days.
- Email: RFC-lite; password ≥ 8 with letter+digit.
- Plan reason ≥ 10 chars. Invite: unique email per workspace.
- Validate on blur, re-validate on change after first error; submit disabled only while pending.

## 5. Data fetching
- React Query; keys `[ws, resource, id]`. Optimistic for toggles (2FA, notifications read, archive) with rollback toast.
- Engine calls (`/simulate`, `/scenario`, `/verify` preview) debounced 250ms, cancel in-flight.
- Every number rendered from engine carries `source_ref`; component `<Num value source>` shows the mono chip on hover/tap.

## 6. Components to build (map to UI Kit sections)
Button(primary|secondary|outline|teal|danger|danger-outline|link; sm|md|lg|touch; loading) · Field(Text, Money, Percent, JalaliDate, Select, Range, PillGroup, Textarea, Switch, Checkbox, Dropzone) · Badge(status, cause, verdict, role, source, stale, low-n, demo, count) · Callout(info|ok|warn|danger) · Banner · Card(KPI, Insight, Campaign, Milestone) · Table → CardList < 768 · Tabs/Pills · LoopStatusBar · Modal · ConfirmDialog(type-to-confirm) · Tour · NotificationsPopover · Toast · Skeleton · EmptyState · ErrorState · NotFound.

## 7. Responsive
≥1180 desktop · 768–1179 tablet (2-col, scroll tables) · <768 mobile (single col, tables→cards, sticky bottom action bar, header = mark + title + bell + menu, targets ≥44px). See UI Kit §12 for three reference screens.

## 8. Accessibility & i18n
`<html lang="fa" dir="rtl">`; LTR isolate for ids/emails/formulas; focus ring 2px #066ca5; modals trap focus + Esc; `role="status"` for toasts/alerts; color never sole signal; `prefers-reduced-motion`. Digits Persian in UI via one `formatFa()` util; API Latin.

## 9. Analytics
Fire events in BACKEND-SPEC §7 from the client on success responses only (not on click).

## 10. Definition of done (per screen)
Matches UI Kit visuals · all §3 states · role gating · mobile layout · keyboard path · copy identical to prototype · events fired · Playwright happy path.
