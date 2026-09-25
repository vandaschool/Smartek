---
task: monthly_summary
version: v1
model: smart
temperature: 0.3
max_tokens: 380
timeout_ms: 20000
response_format: json_schema
fallback: deterministic KPI list template (below)
schedule: worker cron, 1st of each Jalali month 09:00 Asia/Tehran
---

# monthly_summary — خلاصه‌ی ماهانه برای مالک و ناظرها

**کجا:** ایمیل `monthly_report` (قالب در `design/Emails.dc.html`) و کارت بالای داشبورد مدیر مالی در روز اول ماه.
**ورودی:** KPIهای ماه قبل که موتور از `perspective_log`، `campaign`، `calibration`، `pace_snapshot` و `campaign_history` ساخته است.
**خروجی:** یک تیتر و دقیقاً سه بولت: پول، یادگیری، اقدام.

## System

```text
TASK: Write the monthly management summary of a merchant's campaign loop for the owner and read-only viewers.

WRITE
- headline: one sentence, max 14 words, stating the single most important fact of the month. Priority:
  1) portfolio POAS negative → say the month was loss-making on first orders;
  2) CAC above target_cac → say CAC is above target;
  3) campaigns awaiting results > 0 → say results are pending;
  4) otherwise → the number of closed loops and the POAS.
- bullets: exactly 3 strings, each max 25 words, in this order:
  1) MONEY — POAS and CAC versus target_cac (use cac_vs_target display). If previous-month facts exist, one comparison using their displays.
  2) LEARNING — closed loops, calibrations applied, and the perspective with the best decision quality. If flags.best_perspective_low_n is true, add «با نمونه‌ی کم» to that clause.
  3) ACTION — the most urgent open item: campaigns awaiting results, open pacing alerts, or «حلقه‌ی بعد را با نرخ‌های کالیبره‌شده طراحی کنید» if nothing is open.

If closed_loops is zero, the headline says no loop was closed this month and bullet 2 says learning did not happen this month; do not praise or blame.

Tone: a concise board note. No greetings, no sign-off, no congratulations, no exclamation marks.
```

## User template

```json
{
  "month_label": "شهریور ۱۴۰۵",
  "flags": { "has_previous": true, "best_perspective_low_n": false },
  "best_perspective": "مدیر مالی",
  "facts": [
    { "ref": "kpi:1405-06#closed_loops", "label": "حلقه‌های بسته‌شده", "value": 3, "display": "۳" },
    { "ref": "kpi:1405-06#poas", "label": "POAS کل ماه", "value": 0.21, "display": "+۲۱٪" },
    { "ref": "kpi:1405-06#cac", "label": "CAC کل ماه", "value": 298000, "display": "۲۹۸٬۰۰۰ تومان" },
    { "ref": "profile#target_cac", "label": "CAC هدف", "value": 320000, "display": "۳۲۰٬۰۰۰ تومان" },
    { "ref": "kpi:1405-06#cac_vs_target", "label": "CAC نسبت به هدف", "value": -0.069, "display": "−۶٫۹٪" },
    { "ref": "kpi:1405-06#calibrations", "label": "کالیبراسیون‌های اعمال‌شده", "value": 2, "display": "۲" },
    { "ref": "log#best_quality", "label": "کیفیت تصمیم بهترین دیدگاه", "value": 0.75, "display": "۳ از ۴" },
    { "ref": "kpi:1405-06#awaiting_result", "label": "کمپین در انتظار نتیجه", "value": 1, "display": "۱" },
    { "ref": "kpi:1405-06#pace_alerts_open", "label": "هشدار پایش باز", "value": 0, "display": "۰" },
    { "ref": "kpi:1405-05#poas", "label": "POAS ماه قبل", "value": 0.08, "display": "+۸٪" }
  ]
}
```

## Output schema

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["headline", "bullets"],
  "properties": {
    "headline": { "type": "string", "minLength": 10, "maxLength": 140 },
    "bullets": { "type": "array", "minItems": 3, "maxItems": 3, "items": { "type": "string", "minLength": 10, "maxLength": 220 } }
  }
}
```

## Few-shot (ورودی بالا)

```json
{"headline":"یک کمپین هنوز در انتظار ثبت نتیجه است؛ ماه با POAS +۲۱٪ سودده بود.","bullets":["POAS کل ماه +۲۱٪ بود در برابر +۸٪ ماه قبل؛ CAC با ۲۹۸٬۰۰۰ تومان −۶٫۹٪ زیر هدف ماند.","۳ حلقه بسته و ۲ کالیبراسیون اعمال شد؛ دیدگاه مدیر مالی با ۳ از ۴ تصمیم درست بهترین کیفیت تصمیم را داشت.","نتیجه‌ی ۱ کمپین پایان‌یافته را ثبت کنید تا حلقه‌اش بسته شود و نرخ‌ها به‌روز شوند."]}
```

## اعتبارسنجی و جایگزین

zod → دیوار عدد → واژه‌های ممنوع → دقیقاً ۳ بولت.
جایگزین قطعی (همان ایمیل بدون متن مدل):

```
تیتر:   «گزارش {month_label}: {closed_loops} حلقه‌ی بسته، POAS {poas}»
بولت ۱: «CAC {cac} در برابر هدف {target_cac} ({cac_vs_target})»
بولت ۲: «{calibrations} کالیبراسیون · بهترین کیفیت تصمیم: {best_perspective} ({best_quality})»
بولت ۳: «کمپین در انتظار نتیجه: {awaiting_result} · هشدار پایش باز: {pace_alerts_open}»
```
