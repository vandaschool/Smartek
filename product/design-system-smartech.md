# Adtrace Visual Design Spec (for Claude Code)

**Source:** live Adtrace panel (`panel.adtrace.io`), captured Sep 2026.  
**Stack clue:** Ant Design + custom theme; fonts Montserrat (headings) + Poppins (body).  
**Direction:** LTR, English UI.

## Purpose

Match the **visual feel** of the Adtrace dashboard so MVP screens feel like they belong in the same product family.

## Non-goals

- Do **not** clone Adtrace product features (trackers, attribution, SDK setup, etc.).
- Do **not** copy Adtrace logo/wordmark into the MVP unless the team explicitly wants brand spoofing (prefer a distinct MVP name with the same *chrome language*).
- Do **not** invent a purple SaaS theme, heavy glassmorphism, or dense card grids that fight this shell.

## Visual direction (1 sentence)

A light, airy B2B analytics shell: white surfaces, thin gray hairlines, teal accent for selection/ink, steel-blue for primary CTAs, Montserrat titles + Poppins UI text, soft 6–12px radii, data density without clutter.

---

## Design tokens

Use these as CSS variables (values from computed styles on the live panel).

```css
:root {
  /* Surfaces */
  --color-bg-page: #ffffff;
  --color-bg-surface: #ffffff;
  --color-bg-muted: #f8f9fa;          /* subtle page/header wash when needed */
  --color-bg-selected: rgba(13, 138, 138, 0.05);
  --color-bg-nav-active: rgba(13, 33, 44, 0.05);

  /* Metric card tints (Overview) */
  --color-card-teal: #e8f4f4;
  --color-card-blue: #edf4f9;
  --color-card-gray: #eff0f1;

  /* Brand / accent */
  --color-accent: #0d8a8a;            /* teal — tabs ink, FAB, active nav text */
  --color-cta: #066ca5;               /* steel blue — Add / Save primary actions */
  --color-checkbox: #189a87;          /* checked checkbox fill */

  /* Text */
  --color-text: #00142b;
  --color-text-secondary: rgba(41, 44, 45, 0.7);
  --color-text-metric: #585d5e;
  --color-text-on-accent: #ffffff;
  --color-text-muted: rgba(0, 0, 0, 0.45); /* placeholders / empty */

  /* Borders */
  --color-border: #e5e5e5;
  --color-border-strong: #d9d9d9;
  --color-border-table: #e9e9e9;
  --color-border-card: #eeeeee;

  /* Status */
  --color-danger: #d04234;            /* NEW badge, destructive accents */
  --color-required: #f5222d;          /* form asterisks (login/forms) */

  /* Typography */
  --font-heading: montserrat, system-ui, sans-serif;
  --font-body: poppins, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  --font-size-body: 14px;
  --font-size-title: 24px;
  --font-size-metric: 30px;
  --font-size-badge: 10px;
  --line-height-body: 22px;
  --font-weight-title: 600;
  --font-weight-metric: 700;
  --font-weight-button: 500;

  /* Radii */
  --radius-input: 8px;
  --radius-button: 6px;
  --radius-card: 12px;
  --radius-nav-item: 5px;
  --radius-fab: 16px;
  --radius-badge: 10px;

  /* Sizing */
  --header-height: 55px;
  --icon-rail-width: 64px;            /* approximate; icon-only left rail */
  --button-height: 36px;
  --input-height: 32px;
  --settings-nav-width: 215px;
  --fab-size: 55px;

  /* Spacing rhythm */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 24px;
  --space-6: 32px;

  /* Elevation */
  --shadow-button: rgba(0, 0, 0, 0.016) 0 2px 0 0;
  --shadow-header-inset: rgba(0, 20, 43, 0.2) 0 -0.5px 0 0 inset;
}
```

**Load fonts:** Google Fonts (or self-host) `Montserrat:600` and `Poppins:400,500,700`.

---

## App shell

```
┌──────────────────────────────────────────────────────────────┐
│ Logo │ App switcher │  Statistics │ Tracker │ Export │ Settings │  Balance · 🔔 · ? · ↻ · Avatar+email │
├────┬─────────────────────────────────────────────────────────┤
│ ⬛ │  Page title (+ optional Learning menu)     [primary CTA] │
│ ⬛ │  Filters / date range                                  │
│ ⬛ │  Content (cards / table / form)                        │
│ ⬛ │                                                         │
│ ≡  │                                              (FAB chat) │
└────┴─────────────────────────────────────────────────────────┘
```

### Top bar

- Height ~`55px`, white background, `1px solid #e5e5e5` bottom border.
- Left: brand mark + optional app/workspace switcher.
- Center-left: **text tabs** (not pills). Active tab: teal wash `rgba(13,138,138,0.05)` + teal ink bar (top or bottom, ~2px `#0d8a8a`).
- Right: muted status text, outline utility icons, circular avatar + email.

### Icon rail (left)

- Narrow vertical strip (~56–72px), white/light, outline icons.
- Active item: rounded square highlight with teal tint.
- Collapse control at bottom.

### Main content

- White canvas; generous horizontal padding (~24px).
- Page title: Montserrat 24px / 600 / `#00142b`.
- Optional secondary control next to title (outlined “Learning”-style dropdown).
- Primary page action aligned top-right (`#066ca5`, white label).

### Do / don’t

- **Do** keep chrome flat (borders > heavy shadows).
- **Do** use teal for *selection/state* and steel-blue for *actions that create/save*.
- **Don’t** put primary CTAs in teal unless matching Fab-style accents; match panel: Create/Save ≈ `#066ca5`.
- **Don’t** use a dark sidebar unless you intentionally fork the look.

---

## Component recipes

### Buttons

| Variant | Use | Spec |
|---|---|---|
| CTA / secondary-filled | Add X, Save Changes | bg `#066ca5`, color white, h `36px`, radius `6px`, weight `500`, padding `8px 15px` |
| Accent / primary Ant | FAB, rare primary | bg `#0d8a8a`, white icon/text |
| Ghost / default | Learning, filters | white/light, border `#d9d9d9`, same height |
| Danger text | Delete App | text/icon red `#d04234`, no filled red button by default |

### Inputs & selects

- Height `32px`, radius `8px`, border `1px solid #d9d9d9`, padding `4px 11px`, font 14px Poppins.
- Focus: blue/teal ring consistent with Ant Design focus (do not invent neon glow).
- Labels above fields: 14px, `#00142b`; required `*` in danger red.
- Help: small circular `?` beside label (muted).

### Checkboxes

- Checked fill `#189a87` with white check; size ~20px.

### Tabs (content)

- Text tabs; active underline ink `#0d8a8a` (~2px).
- Inactive: default body text; no pill cluster.

### Metric cards (Overview pattern)

- Radius `12px`, no heavy shadow, soft tinted backgrounds (`--color-card-*`).
- Label small; value Poppins **30px / 700** `#585d5e`; optional % delta; circular icon button on the right.

### Entity cards (Applications pattern)

- Width ~300px, radius `12px`, border `2px solid #eeeeee`, white fill, no shadow.
- Header: thumbnail + name + gear.
- Body: muted section divider (“Last 7 days”) + label/value rows.

### Tables (Trackers pattern)

- Ant `table-small` density feel.
- Header: white/near-white, text `rgba(41,44,45,0.7)`, weight 500, padding ~`16px 12px 16px 24px`, bottom border `#e9e9e9`.
- Body: dark text, hairline row separators, horizontal scroll OK for wide columns.
- Column headers may include filter/search/`?` icons.

### Side settings nav

- Width ~215px; item height ~40px; radius `5px`.
- Selected: bg `rgba(13,33,44,0.05)`, text/icon `#0d8a8a`.
- Badge `NEW`: bg `#d04234`, white 10px text, pill radius `10px`.

### Empty state

- Centered muted illustration + “No data” (or equivalent); no noisy placeholders.

### FAB

- Bottom-right circle `55px`, radius `16px`, bg `#0d8a8a`, white icon. Optional for MVP support/chat.

---

## Layout patterns

### List page

1. Title row + primary CTA  
2. Optional filter row  
3. Full-width table or card grid  

### Dashboard / overview

1. Title + date range  
2. Filter row  
3. 3 metric cards in a row  
4. Sub-tabs + chart/table area  

### Settings / form

1. Left vertical settings list  
2. Right multi-column form (sections with hairline dividers)  
3. Save CTA (steel blue)  

### Density

- Prefer **one** primary action per page header.
- Prefer tables/lists over nested card stacks.
- Keep ~24px section gaps; avoid dashboard “widget soup”.

---

## MVP mapping (do this)

When building MVP screens:

1. Reuse this **shell** (top tabs + icon rail + white content) even if MVP nav labels differ.
2. Map MVP entities to **Adtrace patterns**, not Adtrace nouns:
   - lists → Trackers table pattern  
   - home/summary → Overview metric cards + empty chart  
   - detail/config → Settings form + side nav  
   - pickers → Applications card grid  
3. Keep MVP domain copy (campaigns, recommendations, approvals, …) — only the **chrome and components** should feel Adtrace-like.
4. Implement tokens in `:root` first; style components from tokens only.
5. Prefer Ant Design (or equivalent) themed to these tokens over bespoke CSS frameworks with conflicting defaults.

### Acceptance check

A teammate glancing at the MVP should say “this looks like Adtrace,” not “this is Adtrace.” Same teal/steel-blue language, same title weight, same table/header density — different product content.

---

## Screenshot index

| File | What to match |
|---|---|
| [screenshots/01-overview.png](screenshots/01-overview.png) | Shell + metric cards + content tabs + empty chart |
| [screenshots/02-trackers.png](screenshots/02-trackers.png) | List page + steel-blue CTA + data table |
| [screenshots/03-settings-app-info.png](screenshots/03-settings-app-info.png) | Settings side nav + form fields + checkboxes + NEW badge |
| [screenshots/04-applications.png](screenshots/04-applications.png) | Card grid entity picker |

If screenshots and tokens disagree, **trust tokens** (computed CSS); screenshots are visual reference for layout and density.

---

## Implementation hints

1. Install `Montserrat` + `Poppins`; set body to Poppins 14px / `#00142b`.
2. Theme Ant Design `colorPrimary` to `#0d8a8a`; style `.btn-secondary` / create-save actions as `#066ca5`.
3. Global header fixed 55px; content under it with left padding for icon rail.
4. Tables: sticky header, horizontal scroll container, muted header text.
5. Cards: tinted Overview metrics vs bordered white entity cards — do not mix both styles for the same object type.
6. Skip purple gradients, glass cards, oversized hero headers, and emoji icons.

## Completion criterion for the agent

MVP UI work is done for *visual parity* when: tokens are applied, shell matches the diagram, list/dashboard/form screens use the recipes above, and a side-by-side glance against the screenshots shows the same color roles and density — without copying Adtrace feature IA.
