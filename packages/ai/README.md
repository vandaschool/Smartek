# packages/ai — لایه‌ی ۲ با متیس

راهنمای پیاده‌سازی بک‌اند هوش مصنوعی. مرجع محصول: `product/PRD-Campaign-Loop-v6.md` بخش ۱۱ و ۱۲؛ مرجع فنی: `docs/AI-INTEGRATION-METIS.md`.

## ساختار

```
packages/ai/
  prompts/
    _shared.v1.md            قواعد مشترک (ابتدای system همه‌ی وظیفه‌ها)
    ask_intent.v1.md         ۱ · fast  · تشخیص نیت پرسش
    ask_phrase.v1.md         ۲ · fast  · نوشتن پاسخ از روی ردیف‌ها
    insight_explain.v1.md    ۳ · fast  · «چرا» و «ریسک» کارت اینسایت
    verify_narrative.v1.md   ۴ · smart · روایت علت انحراف + قدم بعدی
    csv_map.v1.md            ۵ · fast  · نگاشت سرستون‌های CSV
    monthly_summary.v1.md    ۶ · smart · خلاصه‌ی ماهانه
    plan_reason_hint.v1.md   ۷ · fast  · راهنمای دلیل انتخاب دیدگاه
  (در M7 ساخته می‌شود)
  src/provider.ts            AIProvider: MockProvider | MetisProvider
  src/run-task.ts            اجرای وظیفه: کش → قطع‌کن مدار → مدل → zod → دیوار عدد → جایگزین
  src/firewall.ts            دیوار عدد و بررسی source_refs
  src/context/*.ts           ساختن ورودی هر وظیفه از خروجی موتور (facts با display)
  src/schemas.ts             zod هم‌ارز «Output schema» هر فایل پرامپت
  evals/                     ai:eval و ai:smoke
```

## بارگذاری پرامپت

هر فایل `prompts/<task>.v1.md` یک سرآیند YAML (مدل، دما، زمان) و یک بلوک ```` ```text ```` زیر عنوان `## System` دارد.

```ts
const system = loadBlock('_shared.v1.md', 'System (shared)') + '\n\n' + loadBlock(`${task}.v1.md`, 'System');
const messages = [
  { role: 'system', content: system },
  ...fewShot(task),                                  // جفت‌های user/assistant بخش Few-shot
  { role: 'user', content: JSON.stringify(context) } // فقط JSON، هرگز متن آزاد کنار آن
];
```

`prompt_version` = نام فایل (`ask_intent.v1`). تغییر متن پرامپت یعنی فایل `v2` تازه، نه ویرایش `v1`؛ کش و `llm_call` بر اساس همین نسخه جدا می‌شوند.

## فراخوانی متیس

```ts
import OpenAI from 'openai';
const client = new OpenAI({ apiKey: env.METIS_API_KEY, baseURL: env.METIS_BASE_URL });

const res = await client.chat.completions.create({
  model: tier === 'smart' ? env.METIS_MODEL_SMART : env.METIS_MODEL_FAST,
  temperature, max_tokens,
  messages,
  response_format: supportsJsonSchema
    ? { type: 'json_schema', json_schema: { name: task, strict: true, schema } }
    : { type: 'json_object' },
}, { timeout: tier === 'smart' ? env.AI_TIMEOUT_SMART_MS : env.AI_TIMEOUT_FAST_MS });
```

`supportsJsonSchema` در `pnpm ai:smoke` برای هر مدل یک بار آزموده و در کش نگه داشته می‌شود.

## دیوار عدد (`firewall.ts`)

```
normalize(s):
  ارقام ۰-۹ و ٠-٩ → 0-9 ؛ «٬» و «,» → حذف ؛ «٫» → «.» ؛ «−» → «-» ؛ «٪» → «%»

extractNumbers(text):
  همه‌ی الگوهای  [-+]?\d+(\.\d+)?  پس از normalize
  + واحد بعدی اگر «م» یا «میلیون» (×1e6) یا «میلیارد» (×1e9) یا «هزار» (×1e3) یا «%» (÷100) باشد

allowed(context):
  برای هر fact: value و همه‌ی اعداد داخل display (با همان قاعده‌ی واحد)
  + برای csv_map: اعداد headers و sample_rows

check(output, context):
  برای هر عدد n در همه‌ی رشته‌های خروجی:
      ok اگر  ∃ a ∈ allowed:  |n − a| ≤ 0.005 × max(|a|, 1)
  هر عدد ناموفق → violation
  برای هر ref در source_refs: باید در facts[].ref باشد
  واژه‌های ممنوع (_shared قاعده‌ی ۴) → violation
```

خروجی رد‌شده هرگز به کاربر نمی‌رسد. `llm_call.status = 'violation'` و متن قالبی جایگزین نمایش داده می‌شود.

## زنجیره‌ی اجرا (`run-task.ts`)

```
workspace.ai_enabled = false یا provider = mock  → جایگزین قطعی
بودجه‌ی توکن ماه تمام شده                      → جایگزین + یک اعلان
کش (sha256(task, prompt_version, model, context))  → status = cached
قطع‌کن مدار باز                                   → جایگزین
فراخوانی مدل (۱ تلاش دوباره با jitter)            → timeout/error → جایگزین
zod ناموفق → ۱ تلاش دوباره با متن خطا → ناموفق → جایگزین
دیوار عدد + قواعد اختصاصی وظیفه                  → violation → جایگزین
موفق → ذخیره در کش، llm_call(status = ok)
پاسخ API همیشه: {..., ai: { provider, grounded, fallback }}
```

## جایی که کلید متیس لازم است

فقط در **M7** و فقط برای این سه چیز:

| متغیر | کجا |
|---|---|
| `METIS_API_KEY` | تنظیمات محیط (environment variable)، هرگز در کد یا چت |
| `METIS_MODEL_FAST` · `METIS_MODEL_SMART` | نام دقیق مدل‌ها از پنل متیس |
| `METIS_BASE_URL` | تأیید آدرس پایه (پیش‌فرض `https://api.metisai.ir/openai/v1`) |

تا آن زمان `AI_PROVIDER=mock` و همه‌ی آزمون‌ها روی خروجی‌های Few-shot همین فایل‌ها اجرا می‌شوند.

## ارزیابی

- `fixtures/ai-evals.jsonl`: ۳۶ پرسش برای `ask_intent`.
- دروازه‌ی CI: دقت intent ≥ ۹۰٪ · **تخلف عدد = ۰** · شکست schema < ۲٪.
- `pnpm ai:smoke`: هر وظیفه یک بار با ورودی Few-shot؛ تأخیر، توکن و قبول/رد چاپ می‌شود.
