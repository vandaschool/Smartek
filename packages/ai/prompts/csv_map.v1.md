---
task: csv_map
version: v1
model: fast
temperature: 0
max_tokens: 400
timeout_ms: 8000
response_format: json_schema
fallback: exact header-name match (lowercased, trimmed) as in prototype `readImport`
---

# csv_map — نگاشت ستون‌های فایل CSV مرچنت به قالب سیستم

**کجا:** صفحه‌ی «داده‌ها»، بعد از بارگذاری CSV و پیش از پیش‌نمایش (`POST /imports/:id/map`).
**مشکلی که حل می‌کند:** نمونه فقط وقتی فایل را می‌پذیرد که سرستون‌ها دقیقاً `name, channel, spend…` باشند. خروجی واقعی ادتریس یا اکسل مرچنت سرستون فارسی و متفاوت دارد («هزینه (تومان)»، «تعداد نصب»، «Conv.»).
**مرز:** مدل **فقط نام ستون** را نگاشت می‌کند. تبدیل مقدار، اعتبارسنجی سطری و ثبت با موتور قطعی (`readImport`) است و کاربر پیش‌نمایش را تأیید می‌کند.

## System

```text
TASK: Map the columns of an uploaded CSV file to the system's target schema. Output only which source header feeds which target field.

INPUT
- kind: "history" (past campaigns) or "rates" (rate table).
- target_schema: the fields to fill, each with key, meaning, type, required flag and known synonyms.
- headers: the CSV header row exactly as uploaded (Persian, English or mixed).
- sample_rows: up to 3 data rows, aligned with headers. Use them to disambiguate meaning (e.g. money vs count vs fraction), not to transform anything.

RULES
1. mapping must contain EVERY target key. Value = one header string copied exactly from `headers`, or null.
2. Each source header may be used for at most one target key.
3. Map by meaning, not by position. Consider Persian/English synonyms, abbreviations, typos, units in parentheses.
4. Use null when no header clearly fits. A wrong mapping is worse than a missing one: the user can fix a null in the preview, but may miss a wrong match.
5. Never map a column whose samples contradict the type (e.g. text where a number is expected; values like 3–12 in a column you'd map to spend in Toman).
6. warnings (Persian, max 3, each ≤ 20 words): report problems the user must fix before committing, quoting the header in «». Typical:
   - unit mismatch: header says «میلیون» or «هزار» or «ریال» while the target expects Toman;
   - percent vs fraction: target expects 0–1 (cvr, variance, d30, fraud) but samples look like 0–100;
   - date instead of Persian month name for `month`;
   - channel/segment values that are not in the allowed vocabulary (quote one example value from samples).
   Numbers in warnings are allowed only if they appear verbatim in headers or sample_rows.
7. confidence: 0–1 for the whole mapping. Below 0.6 if any required field is null or any mapping is a guess.
```

## User template

```json
{
  "kind": "history",
  "target_schema": [
    { "key": "name",        "meaning": "نام کمپین",                       "type": "text",   "required": true,  "synonyms": ["کمپین", "عنوان", "campaign"] },
    { "key": "channel",     "meaning": "کانال (گوگل، تپسل، یکتانت، اینستاگرام، پوش، پیامک)", "type": "enum", "required": true, "synonyms": ["کانال", "منبع", "source", "network", "media"] },
    { "key": "segment",     "meaning": "سگمنت (کاربر جدید، فعال، در معرض ریزش، پرارزش، بازگشتی)", "type": "enum", "required": true, "synonyms": ["سگمنت", "مخاطب", "audience"] },
    { "key": "spend",       "meaning": "هزینه به تومان",                   "type": "money_toman", "required": true, "synonyms": ["هزینه", "بودجه‌ی مصرف‌شده", "cost", "spend"] },
    { "key": "installs",    "meaning": "تعداد نصب (برای پوش/پیامک: دریافت‌کننده)", "type": "count", "required": true, "synonyms": ["نصب", "installs", "reach", "دریافت"] },
    { "key": "conversions", "meaning": "تعداد خرید",                      "type": "count",  "required": true,  "synonyms": ["خرید", "تبدیل", "سفارش", "conv", "purchases", "orders"] },
    { "key": "revenue",     "meaning": "درآمد به تومان",                   "type": "money_toman", "required": true, "synonyms": ["درآمد", "فروش", "revenue", "GMV"] },
    { "key": "month",       "meaning": "نام ماه جلالی (مهر، آبان، …)",      "type": "text",   "required": true,  "synonyms": ["ماه", "month", "دوره"] }
  ],
  "headers": ["{{h1}}", "{{h2}}", "…"],
  "sample_rows": [["…"], ["…"], ["…"]]
}
```

برای `kind = "rates"` کلیدها: `channel`، `segment`، `unit_cost` (تومان)، `cvr` (کسری)، `aov` (تومان)، `variance` (کسری)، `sample_n` (عدد)، `ceiling` (عدد)، `d30` (کسری)، `fraud` (کسری). سرور `target_schema` را از `importSpec()` می‌سازد.
**حریم خصوصی:** اگر ستونی شبیه ایمیل یا شماره‌ی تلفن باشد، سرور پیش از ارسال مقادیر آن را با `«حذف‌شده»` جایگزین می‌کند.

## Output schema

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["mapping", "confidence", "warnings"],
  "properties": {
    "mapping": {
      "type": "object",
      "additionalProperties": { "type": ["string", "null"] }
    },
    "confidence": { "type": "number", "minimum": 0, "maximum": 1 },
    "warnings": { "type": "array", "maxItems": 3, "items": { "type": "string", "maxLength": 160 } }
  }
}
```

## Few-shot

**ورودی:**
```json
{
  "kind": "history",
  "headers": ["عنوان کمپین", "Network", "مخاطب", "هزینه (میلیون تومان)", "تعداد نصب", "Conv.", "فروش", "تاریخ شروع"],
  "sample_rows": [
    ["نصب پاییزه", "گوگل", "کاربر جدید", "180", "2790", "205", "174000000", "1404/07/03"],
    ["پوش هفتگی", "Push", "فعال", "24", "2600", "158", "181000000", "1404/07/10"],
    ["ریتارگت", "تپسل", "جدید", "135", "2810", "148", "125000000", "1404/07/15"]
  ]
}
```
**خروجی:**
```json
{"mapping":{"name":"عنوان کمپین","channel":"Network","segment":"مخاطب","spend":"هزینه (میلیون تومان)","installs":"تعداد نصب","conversions":"Conv.","revenue":"فروش","month":null},"confidence":0.62,"warnings":["ستون «هزینه (میلیون تومان)» به میلیون است؛ سیستم هزینه را به تومان می‌خواهد.","ستون ماه پیدا نشد؛ «تاریخ شروع» تاریخ است، نه نام ماه جلالی.","مقدار «Push» و «جدید» در واژگان کانال و سگمنت نیست."]}
```

## اعتبارسنجی و جایگزین

- هر مقدار `mapping` باید یا `null` باشد یا دقیقاً یکی از `headers`؛ تکرار یک سرستون → رد.
- کلیدهای `mapping` دقیقاً برابر کلیدهای `target_schema`.
- دیوار عدد روی `warnings`: هر عدد باید در `headers` یا `sample_rows` باشد.
- **هرگز خودکار ثبت نمی‌شود.** رابط نگاشت را در پیش‌نمایش با منوهای کشویی قابل‌ویرایش نشان می‌دهد؛ ستون‌های `null` قرمز؛ `warnings` بالای جدول. دکمه‌ی «ثبت ردیف‌های معتبر» پس از تأیید کاربر.
- واحدها را **موتور** تبدیل نمی‌کند مگر کاربر در پیش‌نمایش «ضریب واحد» را انتخاب کند (میلیون ×۱٬۰۰۰٬۰۰۰، ریال ÷۱۰، درصد ÷۱۰۰).
- جایگزین: تطبیق دقیق نام ستون مثل نمونه (`head.indexOf(c)`)؛ اگر ستون لازم نبود: «ستون‌های لازم پیدا نشد: …».
