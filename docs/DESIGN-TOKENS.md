# Campaign Loop — Design Tokens & Components

Source of truth: inline styles in `Campaign Loop v4.dc.html`, based on `uploads/design.md`.

## Color
| token | value | use |
|---|---|---|
| ink | `#00142b` | primary text, dark surfaces (tour, celebration, blueprint headers) |
| text-2 | `#3a3f41` | body text |
| text-3 | `#5b6062` | secondary text |
| text-muted | `#6b7072` | captions (≥4.5:1 on white) |
| primary | `#066ca5` | primary buttons, links |
| primary-bg | `#f2f8fc` / border `#cfe2ef` | info callouts |
| teal | `#0d8a8a` | brand accent, active tab, success dots |
| teal-bg | `#e8f4f4` / border `#cde6e6` / text `#075e5e` | success / "done" chips |
| warn-bg | `#fdf6e8` / border `#f0e0bd` / text `#6b4d06` | warnings, role notes |
| danger-bg | `#fdeeec` / border `#f3d3ce` / text `#7a1f17`, strong `#b3261e` | errors, loss |
| positive | `#0a7a52` | favorable deltas |
| surface | `#ffffff`, soft `#f8f9fa`, `#f5f5f5` | cards, table heads |
| border | `#e5e5e5`, soft `#e9e9e9`, `#f0f0f0` | card and row borders |
Logo colors: teal `#0d8a8a`, blue `#066ca5`, ink `#00142b`.

## Type
- Families: `Poppins, Vazirmatn` (UI), `Montserrat, Vazirmatn` (page titles), `ui-monospace` (ids, formulas).
- Scale: page title 24/600 · section 15.5/600 · body 13.5–14 · caption 12–12.5 · kpi 19–30/700.
- Line-height: 1.8–2 for Persian body.
- Digits: Persian digits, thousands separator `٬`, decimal `٫`.

## Shape & spacing
- Radius: controls 6px · cards 12px · chips 20px (pill) · modals 14px.
- Spacing: 6 / 8 / 10 / 12 / 14 / 18 / 20 / 22 / 24.
- Header 55px, sticky. Content max-width 1180px.

## Components
- **Button primary:** bg primary, white 13px/600, pad 9×15, r6.
- **Button secondary:** white, border `#e5e5e5`, text-2.
- **Button danger-outline:** white, border danger, text `#b3261e`.
- **Card:** white, 1px border, r12, pad 18–20.
- **Chip status:** done = teal-bg, pending = white dashed `#d9d9d9`, warn = warn-bg.
- **Callout:** info / ok / warn / danger, r6, pad 10–14.
- **Table row:** 1px top border `#e9e9e9`, pad 8–10×14–16; head bg `#f8f9fa`.
- **Id label:** monospace 11–12px, ltr, text-3.
- **Modal:** overlay `rgba(0,20,43,.45)`, card r14 pad 28.
- **Toast/tour:** ink bg, fixed bottom-left, r12.

## Assets
`assets/logo-horizontal.png` (header), `assets/logo-tagline.png` (footer), `assets/logo-mark.png` (favicon, auth, celebration).
