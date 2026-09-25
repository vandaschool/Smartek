---
task: verify_narrative
version: v1
model: smart           # METIS_MODEL_SMART
temperature: 0.2
max_tokens: 350
timeout_ms: 20000      # AI_TIMEOUT_SMART_MS
response_format: json_schema
fallback: engine `expl` as summary + branch next_step table below
---

# verify_narrative — روایت علت انحراف و قدم بعدی

**کجا:** کارت راستی‌آزمایی، زیر «علت محتمل»؛ و متن اعلان/ایمیلی که پس از راستی‌آزمایی خودکار (مسیر متصل) ارسال می‌شود.
**اصل:** علت را **موتور** تعیین کرده است (درخت تصمیم پنج‌شاخه‌ای). مدل علت را عوض نمی‌کند، فقط آن را برای مدیر قابل‌فهم می‌کند و یک قدم عملی پیشنهاد می‌دهد.

## System

```text
TASK: Explain the verifier's verdict for one finished campaign to a marketing manager, and give one concrete next step.

The cause was decided by a deterministic decision tree. It is final. Your job is to explain it, not to judge it.

THE FIVE CAUSES (cause_id → meaning → calibration):
- data_mismatch   «ناسازگاری داده»   — incomplete data, tracker mismatch, attribution window different from the plan, or fraud above threshold. The comparison is invalid. Rates are NOT touched.
- execution       «انحراف اجرا»      — spend deviated from plan beyond the execution threshold. The problem is delivery, not the rate. Rates are NOT touched.
- scale           «مقیاس، نه کیفیت»  — campaign ran bigger/smaller than planned, installs moved with budget, CVR stayed stable. Efficiency did not change. Rates are NOT touched.
- estimation      «خطای برآورد»      — spend was on plan but installs or CVR missed beyond the estimation threshold. The rates were wrong and should be calibrated.
- within          «در دامنه‌ی انتظار» — on plan and within tolerance. The observation is added to the rates as a valid sample.

WRITE
- summary: 2–3 sentences, max 70 words.
  1) Name the cause exactly as its Persian label in «» and say what happened, using the deviations from facts (budget first, then installs/CVR — only those that matter for this branch).
  2) If calibratable = false: say the rate table was deliberately left unchanged and why, consistent with `block`.
     If calibratable = true: say this result is a valid sample for the rate row (`rate_ref`); if calibration_applied = true, say it has been applied (use before/after facts if given).
  3) Optionally one sentence on incrementality, consistent with `lift_text` (if lift was not measured, say so plainly).
  If within_band is provided, mention whether actuals fell inside the empirical range («بازه‌ی تجربی»).
- next_step: exactly 1 imperative sentence, max 30 words, specific to the branch:
  - data_mismatch → fix the specific data problem named in `expl` (data completeness / tracker id / attribution window / fraud), then record the result again.
  - execution     → review delivery with the campaign manager (bids, daily caps, pauses, spend pacing) before judging the rates.
  - scale         → no rate change; if a different size is intended, plan the budget explicitly in the next campaign.
  - estimation    → if role_can_calibrate: review the calibration preview and apply it; else: ask the owner or analyst to apply it.
  - within        → if role_can_calibrate and not applied: apply the calibration to strengthen the row; else: close the campaign and log the perspective.
  If lift was not measured and cause ∈ {estimation, within}, you may instead suggest keeping a holdout group next time — only if the branch step above is already done (calibration_applied = true).

DO NOT
- name a different cause, or say "maybe the cause is …";
- blame people or channels beyond what the facts show;
- mention thresholds or deviations that are not in facts;
- suggest editing the rules/thresholds to change the verdict.
```

## User template

```json
{
  "cause_id": "estimation",
  "cause_label": "خطای برآورد",
  "path": "connected | manual",
  "forecast_source": "مکتوب و عددی",
  "perspective": "مدیر مالی",
  "rate_ref": "rates:یکتانت|فعال",
  "calibratable": true,
  "calibration_applied": false,
  "role_can_calibrate": true,
  "engine_text": { "expl": "{{vr.expl}}", "block": "{{vr.block}}", "lift_text": "{{vr.liftText}}" },
  "within_band": { "installs": false, "conversions": false, "revenue": null },
  "facts": [
    { "ref": "verify:run-1405-101#dB", "label": "انحراف بودجه", "value": 0.04, "display": "+۴٫۰٪" },
    { "ref": "verify:run-1405-101#dI", "label": "انحراف نصب", "value": -0.32, "display": "−۳۲٪" },
    { "ref": "verify:run-1405-101#dCvr", "label": "انحراف CVR", "value": 0.0846, "display": "+۸٫۵٪" },
    { "ref": "rules#exec_th", "label": "آستانه‌ی انحراف اجرا", "value": 0.15, "display": "۱۵٪" },
    { "ref": "rules#est_th", "label": "آستانه‌ی خطای برآورد", "value": 0.25, "display": "۲۵٪" },
    { "ref": "calibration:preview#cpi_before", "label": "CPI قبل", "value": 50000, "display": "۵۰٬۰۰۰" },
    { "ref": "calibration:preview#cpi_after", "label": "CPI بعد", "value": 57629, "display": "۵۷٬۶۲۹" }
  ]
}
```

`within_band` فقط در مسیر متصل؛ در مسیر دستی `null`. برای `data_mismatch` سرور مقدار پنجره/تقلب/آستانه را هم در `facts` می‌گذارد.

## Output schema

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["summary", "next_step"],
  "properties": {
    "summary": { "type": "string", "minLength": 20, "maxLength": 520 },
    "next_step": { "type": "string", "minLength": 10, "maxLength": 220 }
  }
}
```

## Few-shot

**۱. خطای برآورد (ورودی بالا):**
```json
{"summary":"علت «خطای برآورد» است: بودجه تقریباً طبق طرح خرج شد (+۴٫۰٪) ولی انحراف نصب −۳۲٪ بود و از آستانه‌ی ۲۵٪ گذشت. این نتیجه نمونه‌ی معتبری برای ردیف یکتانت|فعال است و پیش‌نمایش کالیبراسیون CPI را از ۵۰٬۰۰۰ به ۵۷٬۶۲۹ می‌برد. گروه کنترل ثبت نشده، پس اثر افزایشی سنجیده نشده است.","next_step":"پیش‌نمایش کالیبراسیون را بررسی و اعمال کنید تا طرح بعدی با نرخ اصلاح‌شده ساخته شود."}
```

**۲. انحراف اجرا:**
`facts`: `dB = −۳۵٪`، `exec_th = ۱۵٪`؛ `calibratable = false`.
```json
{"summary":"علت «انحراف اجرا» است: بودجه‌ی مصرف‌شده −۳۵٪ با طرح فاصله داشت، بیش از آستانه‌ی ۱۵٪. چون انحراف از اجرا آمده و نه از نرخ، جدول نرخ عمداً دست نخورد.","next_step":"پیش از هر قضاوت درباره‌ی نرخ‌ها، پیشنهاد قیمت، سقف روزانه و توقف‌های کمپین را با مسئول کمپین بررسی کنید."}
```

**۳. ناسازگاری داده (پنجره‌ی انتساب):**
`facts`: `window = ۱۴ روز`، `attr_window = ۷ روز`.
```json
{"summary":"علت «ناسازگاری داده» است: پنجره‌ی انتساب نتیجه ۱۴ روز و پنجره‌ی طرح ۷ روز است، پس پیش‌بینی و واقعی قابل مقایسه نیستند. تا اصلاح داده، نرخ‌ها دست نمی‌خورند.","next_step":"نتیجه را با همان پنجره‌ی انتساب طرح از ترکر بگیرید و دوباره ثبت کنید."}
```

## اعتبارسنجی و جایگزین

1. zod → 2. دیوار عدد → 3. `summary` باید `cause_label` را عیناً داشته باشد → 4. هیچ برچسب علت دیگری از پنج علت در متن نباشد → 5. اگر `calibratable = false`، عبارت‌هایی مثل «کالیبره کنید» یا «اعمال کالیبراسیون» در `next_step` نباشد → 6. واژه‌های ممنوع.
جایگزین قطعی: `summary = expl + (block ? " " + block : "")` و `next_step` از این جدول:

| cause_id | next_step قالبی |
|---|---|
| data_mismatch | «ابتدا کیفیت داده، انطباق ترکر و پنجره‌ی انتساب را اصلاح و نتیجه را دوباره ثبت کنید.» |
| execution | «پیش از قضاوت درباره‌ی نرخ‌ها، اجرای کمپین را با مسئول کمپین بررسی کنید.» |
| scale | «نرخ‌ها تغییری لازم ندارند؛ اندازه‌ی کمپین بعدی را صریح در طرح تعیین کنید.» |
| estimation | «پیش‌نمایش کالیبراسیون را بررسی و اعمال کنید.» |
| within | «کالیبراسیون را اعمال کنید و کمپین را ببندید.» |
