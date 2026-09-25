---
task: insight_explain
version: v1
model: fast
temperature: 0.3
max_tokens: 260
timeout_ms: 8000
response_format: json_schema
fallback: none shown (the card's static claim/risk already cover it)
batch: 10 calls in parallel per insight generation; cached per (plan inputs, perspective)
---

# insight_explain — «چرا» و «ریسک» از زبان ذی‌نفع

**کجا:** زیر هر کارت اینسایت، یک بخش کوچک با برچسب «توضیح هوشمند».
**چه چیزی عوض نمی‌شود:** شش فیلد کارت (`claim`، `proposal`، `evidence`، `risk`، `success_metric`) همچنان از قالب قطعی می‌آیند و متنشان عیناً متن نمونه است. این وظیفه فقط دو جمله‌ی کوتاه **اضافه** می‌کند.
**هدف:** کارت را از «جمله‌ی عددی» به «استدلال یک ذی‌نفع» تبدیل کند؛ یعنی بگوید چرا یک مدیر مالی/مدیرعامل/… این تخصیص را انتخاب می‌کند و چه چیزی را فدا می‌کند.

## System

```text
TASK: For ONE insight card, explain in the voice of its stakeholder (1) why this allocation serves that stakeholder's objective and (2) the main risk of choosing it — using only the card's data.

STAKEHOLDER LENSES (perspective_id → what this person optimizes and what they fear):
- cfo      مدیر مالی — unit profit: CAC under the margin cap, POAS. Fears: selling at a loss per order.
- ceo      مدیرعامل — volume and market share. Fears: growing too slowly.
- cmo      مدیر بازاریابی — balance of volume and efficiency across channels. Fears: over-reliance on one channel.
- analyst  تحلیل‌گر داده — predictability (low variance). Fears: a result that teaches nothing.
- ops      مسئول کمپین — operational familiarity (high sample_n). Fears: launch problems and delivery errors.
- value    سگمنت پرارزش — customer value (AOV × CVR, revenue, ROAS). Fears: small, non-scalable audience.
- churn    سگمنت در معرض ریزش — re-acquisition cheaper than new acquisition. Fears: zero absolute growth.
- season   فصلی و مناسبتی — timing and seasonal lift. Fears: peak-season CPI inflation.
- compete  رقابتی — under-used rows with acceptable rates. Fears: thin data, wide range.
- safe     محافظه‌کار — test a fraction of the budget before committing. Fears: a test too small to learn from.

WHAT TO WRITE
- why:  1–2 sentences, max 40 words. Tie the allocation to the stakeholder's objective AND to the merchant's business_goal. Name the top row (channel/segment) and at most two facts.
- risk: 1–2 sentences, max 40 words. The main thing this choice gives up, from the lens above. It must be consistent with `static_risk` (you may rephrase or sharpen it, never contradict it).

MANDATORY CAVEATS (add as a short clause to `risk`, without digits):
- flags.low_sample = true  → «پشت ردیف اصلی این پیشنهاد نمونه‌ی کمی است.»
- flags.benchmark  = true  → «نرخ‌ها از مرجع صنعت است و هنوز با داده‌ی شما کالیبره نشده.»
- flags.poas_negative = true → say the allocation is loss-making on the first order (use the POAS display from facts).
- flags.fits_goal = false → say it does not match the stated business goal.

DO NOT
- repeat the card's claim sentence;
- recommend a different perspective or allocation;
- mention perspectives other than this one;
- use any number not in facts (no "twice", no "half").
```

## User template

```json
{
  "perspective_id": "cfo",
  "perspective_name": "مدیر مالی",
  "objective": "max_unit_profit",
  "business_goal": "سودآوری",
  "risk_appetite": "متعادل",
  "top_row": { "ref": "rates:پوش|پرارزش", "channel": "پوش", "segment": "پرارزش" },
  "allocation": [
    { "ref": "rates:پوش|پرارزش", "label": "پوش/پرارزش", "share_fact": "alloc#0.share" }
  ],
  "flags": { "low_sample": false, "benchmark": false, "poas_negative": false, "fits_goal": true },
  "static_claim": "{{insight.claim}}",
  "static_risk": "{{insight.risk}}",
  "facts": [ ]
}
```

`facts` برای هر کارت: CAC و سقف حاشیه‌ی ردیف اول، CAC کل، POAS کل، نصب و خرید مورد انتظار، درآمد، ROAS، سهم هر ردیف تخصیص، `sample_n` و `variance` ردیف اول، و برای `churn` کمترین CAC کاربر جدید، برای `season` ضریب اوج، برای `safe` بودجه‌ی تست و بودجه‌ی کل.
`flags.fits_goal` = دیدگاه در جدول `fitScore` برای هدف کسب‌وکار امتیاز > ۰ دارد.

## Output schema

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["why", "risk"],
  "properties": {
    "why": { "type": "string", "minLength": 15, "maxLength": 320 },
    "risk": { "type": "string", "minLength": 15, "maxLength": 320 }
  }
}
```

(این وظیفه `source_refs` ندارد؛ سرور refها را از `facts` استفاده‌شده استخراج و کنار متن نمایش می‌دهد.)

## Few-shot

**ورودی (خلاصه):** دیدگاه `cfo`، هدف «سودآوری»، ردیف اول پوش/پرارزش.
```json
"facts": [
  { "ref": "rates:پوش|پرارزش#cac", "label": "CAC ردیف اول", "value": 115648, "display": "۱۱۵٬۶۴۸ تومان" },
  { "ref": "rates:پوش|پرارزش#margin_cap", "label": "سقف حاشیه‌ی ردیف اول", "value": 912000, "display": "۹۱۲٬۰۰۰ تومان" },
  { "ref": "insight:cfo#cac", "label": "CAC کل تخصیص", "value": 282025, "display": "۲۸۲٬۰۲۵ تومان" },
  { "ref": "insight:cfo#poas", "label": "POAS کل", "value": 0.658, "display": "+۶۶٪" },
  { "ref": "insight:cfo#conv", "label": "خرید مورد انتظار", "value": 1773, "display": "۱٬۷۷۳" }
]
```
**خروجی:**
```json
{"why":"برای هدف سودآوری، این تخصیص بودجه را اول سراغ پوش روی «پرارزش» می‌برد که CAC آن ۱۱۵٬۶۴۸ تومان و بسیار پایین‌تر از سقف حاشیه‌ی ۹۱۲٬۰۰۰ تومان است. کل طرح با POAS +۶۶٪ در سفارش اول سودده است.","risk":"ردیف‌های ارزان کانال خودی سقف حجم دارند و بقیه‌ی بودجه به ردیف‌های گران‌تر سرریز می‌شود؛ به همین دلیل CAC کل به ۲۸۲٬۰۲۵ تومان می‌رسد."}
```

**ورودی (خلاصه):** دیدگاه `analyst`، هدف «سودآوری»، ردیف اول یکتانت/فعال، `flags.poas_negative = true`.
```json
"facts": [
  { "ref": "rates:یکتانت|فعال#variance", "label": "نوسان", "value": 0.12, "display": "±۱۲٪" },
  { "ref": "insight:analyst#cac", "label": "CAC کل", "value": 616398, "display": "۶۱۶٬۳۹۸ تومان" },
  { "ref": "insight:analyst#poas", "label": "POAS کل", "value": -0.291, "display": "−۲۹٪" }
]
```
**خروجی:**
```json
{"why":"یکتانت روی سگمنت «فعال» کم‌نوسان‌ترین ردیف جدول است (±۱۲٪)؛ نتیجه‌ی این کمپین هر چه باشد، برای تصمیم بعدی قابل اتکا است.","risk":"پیش‌بینی‌پذیری به قیمت سود تمام می‌شود: CAC کل ۶۱۶٬۳۹۸ تومان است و این تخصیص در سفارش اول با POAS −۲۹٪ ضررده است."}
```

## اعتبارسنجی و جایگزین

zod → دیوار عدد روی `why` و `risk` → واژه‌های ممنوع → اگر `flags.*` درست است، بند الزامی‌اش (بدون رقم) باید در `risk` باشد (تطبیق کلیدواژه: «نمونه‌ی کمی»، «مرجع صنعت»، «ضررده»، «هدف»).
شکست → بخش «توضیح هوشمند» اصلاً نمایش داده نمی‌شود؛ کارت کامل و بی‌نقص باقی می‌ماند.
