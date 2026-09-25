# Integrations — Adtrace (attribution) & Intrack (CRM / push / SMS)

Both are Smartech products. Real API documentation and keys will be provided later; build against the adapter interface with mocks first (`CONNECTOR_MODE=mock`).

## Adapter interface
```ts
interface Connector {
  kind: 'adtrace' | 'intrack';
  testConnection(creds): Promise<{ ok: boolean; error?: string }>;
  listCampaigns(creds, since: Date): Promise<ExternalCampaign[]>;
  fetchDaily(creds, externalId: string, day: string /* ISO */): Promise<{
    spend: number; installs: number; conversions: number; revenue: number;
    fraud_installs?: number; reach?: number; tracker_id?: string; attribution_window_days?: number;
  }>;
  verifyWebhook?(headers, rawBody): boolean;
}
```
- Adtrace → paid channels (گوگل، تپسل، یکتانت، اینستاگرام): installs, fraud, attribution window, revenue events.
- Intrack → owned channels (پوش، پیامک): reach (sent/delivered), conversions, revenue, holdout group size.

## Tables
```
connector_account(id, workspace_id, kind, status[connected|error|revoked], creds_encrypted, last_sync_at, created_by)
connector_sync(id, connector_account_id, campaign_id, day, payload_json, status, error, created_at)  -- unique(connector_account_id, campaign_id, day)
campaign_link(campaign_id, connector_account_id, external_id)
```

## Jobs
- `sync-daily` — 06:00 Asia/Tehran for every live campaign: fetch yesterday, upsert pace snapshot (cumulative), run pace alerts.
- `backfill` — on link: fetch every day since `date_from`.
- Webhook receiver `POST /webhooks/:kind` — verify signature, enqueue, idempotent by (external_id, day).
- On campaign end + 7 days (attribution window): auto-draft the run result from synced totals; user confirms → verifier.

## UI (already in prototype «اتصال داده» page)
Connect form (API key), status, last sync, error message, disconnect. Mapped fields feed the verify form so users confirm instead of typing.

## Rules
- Credentials encrypted (libsodium / KMS). Never logged.
- Retries with exponential backoff (max 5); after that `status=error` + notification to owner.
- Data from connectors is labeled `source: adtrace|intrack` in history rows (source chip in UI).
