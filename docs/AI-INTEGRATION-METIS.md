# AI layer via Metis

## 1. Principle
- **Layer 1 (engine)** computes every number deterministically.
- **Layer 2 (LLM via Metis)** only: understands the question, phrases explanations in Persian, maps CSV headers, summarizes. It never produces a number that is not already in the context it was given. The server enforces this.

## 2. Provider
Metis exposes OpenAI-compatible endpoints. Use the official `openai` npm SDK with a custom `baseURL`.

```ts
// packages/ai/provider.ts
export interface AIProvider {
  complete<T>(req: { task: TaskName; system: string; user: string; schema: ZodSchema<T>; model: 'fast' | 'smart'; timeoutMs?: number }): Promise<{ data: T; usage: Usage; model: string }>;
}
// providers: MockProvider (default; returns fixtures) | MetisProvider (when AI_PROVIDER=metis and METIS_API_KEY set)
```
Env (see `env.example`):
- `AI_PROVIDER=mock|metis`
- `METIS_API_KEY=`
- `METIS_BASE_URL=https://api.metisai.ir/openai/v1` — **confirm the exact URL and model names in your Metis dashboard**
- `METIS_MODEL_FAST=` (e.g. a small GPT-class model) · `METIS_MODEL_SMART=`
- `AI_TIMEOUT_FAST_MS=8000` · `AI_TIMEOUT_SMART_MS=20000` · `AI_DEBUG=false`

## 3. Tasks
Each task has a versioned prompt file `packages/ai/prompts/<task>.v1.md` (Persian output, formal-concise), a zod output schema, and a deterministic fallback.

| # | task | model | input context | output | fallback |
|---|---|---|---|---|---|
| 1 | `ask_intent` | fast | question + allowed intents + channel/segment vocabulary | `{intent, channel?, segment?, metric?}` | regex intent matcher from prototype `answer()` |
| 2 | `ask_phrase` | fast | engine query result rows with ids | `{text, source_refs[]}` | template sentence from prototype |
| 3 | `insight_explain` | fast | one perspective card (allocation, KPIs, evidence field) | `{why, risk}` ≤ 2 sentences each | card's static text |
| 4 | `verify_narrative` | smart | verifier output (cause branch, deltas, thresholds) | `{summary, next_step}` | branch text from prototype |
| 5 | `csv_map` | fast | uploaded headers + 3 sample rows + target schema | `{mapping: {target: source or null}, confidence}` | exact-name match; user confirms in preview |
| 6 | `monthly_summary` | smart | month KPIs JSON | `{headline, bullets[3]}` | KPI list template |
| 7 | `plan_reason_hint` | fast | plan reason text + chosen perspective | `{is_specific, hint}` (never blocks) | none (no hint) |

Flow for **Ask** (the only task that answers user questions):
`question → ask_intent → server runs a deterministic query on rate/history rows → ask_phrase(rows) → firewall → UI`.
If the intent isn't recognized: `grounded:false`, no number, show suggested questions.

## 4. Guardrails (server-side, mandatory)
1. **Structured output**: `response_format: { type: 'json_schema' }` when the model supports it, otherwise JSON mode. Validate with zod; on failure 1 retry with the validation error, then fallback.
2. **Number firewall**: normalize Persian/Arabic digits, `٬` `٫` `%` `٪`; extract every number in the output text; each must match a number in the context (±0.5% for rounding, or a documented unit conversion such as میلیون). Any unmatched number → reject → fallback; write `llm_call.status='violation'`.
3. **source_refs** must all exist in the context payload.
4. **No PII** in prompts: aggregates, channel/segment names, ids only. No emails, names, API keys.
5. **Timeouts + retry**: 1 retry with jitter. **Circuit breaker**: 5 failures in 60 s → open for 120 s → fallback.
6. **Cache**: key = sha256(task, prompt_version, model, context) → Redis, TTL 24 h.
7. **Budgets**: monthly tokens per workspace by tier (trial 200k, growth 2M, enterprise custom); over budget → fallback + one in-app notice.
8. **Rate limit**: ask 30/min/user (BACKEND-SPEC §8).
9. **Feature flag** `workspace.ai_enabled` (default on). UI shows a small «توضیح هوشمند» label on AI-phrased text and always shows the source chip.

## 5. Logging
```
llm_call(id, workspace_id, user_id, task, prompt_version, provider, model, latency_ms,
         tokens_in, tokens_out, cost_toman, status[ok|cached|fallback|violation|error|timeout],
         error_code, created_at)
```
Store raw prompt/response only when `AI_DEBUG=true` (dev only). Admin view: calls/day, fallback rate, violation rate, p95 latency, cost.

## 6. Evals & smoke
- `fixtures/ai-evals.jsonl` — question → expected intent / channel / segment. Extend to ≥ 30 cases.
- `pnpm ai:eval` — runs against mock and (if key) Metis. CI gate: intent accuracy ≥ 90%, number violations = 0, schema failures < 2%.
- `pnpm ai:smoke` — calls every task once with seed context, prints latency, tokens, pass/fail.

## 7. Endpoints touched
`POST /ask` · `GET /insights/:id/explain` · `GET /runs/:id/narrative` · `POST /imports/:id/map` · monthly report job · `POST /plans/validate-reason`.
All return `{..., ai: {provider, grounded, fallback: boolean}}` so the UI can label the text.
