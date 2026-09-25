# Engine test cases (Verifier + Calibration)

Default rules: exec_th 0.15 · est_th 0.25 · scale 0.8–1.2 · attr_window 7 · fraud_th 0.08.
Δ = (actual − planned) / planned.

| # | planned (B / I / C) | actual (B / I / C) | flags | expected cause | calibrate |
|---|---|---|---|---|---|
| T1 | 500M / 10000 / 800 | 520M / 6800 / 590 | complete, matched, window 7, fraud 3% | خطای برآورد (ΔI −32%) | yes |
| T2 | 300M / 4800 / 360 | 195M / 3100 / 230 | ok | انحراف اجرا (ΔB −35%) | no |
| T3 | 100M / 11000 / 680 | 112M / 12300 / 762 | ok | مقیاس، نه کیفیت (ΔI/ΔB ≈ 0.98, ΔCVR ≈ 0) | no |
| T4 | 200M / 5200 / 250 | 210M / 2900 / 140 | complete = no | ناسازگاری داده | no |
| T5 | 120M / 9200 / 875 | 124M / 8900 / 845 | ok | در دامنه‌ی انتظار | yes |
| T6 | any | any | window 14 ≠ 7 | ناسازگاری داده | no |
| T7 | any | any | fraud 12% | ناسازگاری داده | no |
| T8 | 100M / 10000 / 800 | 110M / 11000 / 600 | ok | خطای برآورد (ΔI/ΔB = 1 but ΔCVR −32% → not scale) | yes |
| T9 | T1 with exec_th = 0.02 | — | ok | انحراف اجرا (ΔB +4% > 2%) | no |

## Incrementality
- reach 50000, conversions 590, holdout 0.4% → treated 1.18%, lift = (1.18−0.4)/1.18 ≈ 66%.
- holdout 0 → "not measured" text.

## Calibration weights
Row یکتانت|فعال: cpi 50000, n 7, age 3, ceiling 9000.
- typical = 0.4 × 9000 × 50000 = 180M; spend 520M → w_new = clamp(2.89, .3, 3) = 2.89.
- w_old = 7 × 0.9³ = 5.10.
- observed cpi de-inflated by (1.035)³ before merge; age resets to 0; n → 8.

## Forecast
- diminishing returns: B = ceiling × cpi → installs = B/cpi / 1.5.
- POAS = (rev × margin − spend) / spend; negative must render as bad verdict.
- Band width multipliers: narrow 0.7, normal 1.0, cautious 1.4.

## Pacing
- day 12/30, spend 52% of forecast → expected 40% → +30% → spend alert.
- conversions 29% vs 40% → −27% → conversion alert + reallocation suggestion (if ≥2 rows).

## IDs
- Two consecutive plans → plan-1405-101, plan-1405-102. Editing an unverified plan keeps seq and bumps version.
