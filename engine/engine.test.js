// Run: node --test engine/
const test = require('node:test');
const assert = require('node:assert');
const { createEngine } = require('./engine.js');
const DEF = { execTh: 0.15, estTh: 0.25, scaleLo: 0.8, scaleHi: 1.2, inflation: 0.035, attrWindow: 7, fraudTh: 0.08 };
const verify = (o, cfg) => { const e = createEngine({ cfg: Object.assign({}, DEF, cfg || {}) }); return e.verify(Object.assign({}, e.blankVI(), o)); };
const cases = [
  ['T1 estimation error', { pb: 500e6, pi: 10000, pc: 800, ab: 520e6, ai: 6800, ac: 590 }, 'خطای برآورد', true],
  ['T2 execution deviation', { pb: 300e6, pi: 4800, pc: 360, ab: 195e6, ai: 3100, ac: 230 }, 'انحراف اجرا', false],
  ['T3 scale not quality', { pb: 100e6, pi: 11000, pc: 680, ab: 112e6, ai: 12300, ac: 762 }, 'مقیاس، نه کیفیت', false],
  ['T4 data mismatch (incomplete)', { pb: 200e6, pi: 5200, pc: 250, ab: 210e6, ai: 2900, ac: 140, complete: 'خیر' }, 'ناسازگاری داده', false],
  ['T5 within expectation', { pb: 120e6, pi: 9200, pc: 875, ab: 124e6, ai: 8900, ac: 845 }, 'در دامنه‌ی انتظار', true],
  ['T6 attribution window mismatch', { window: 14 }, 'ناسازگاری داده', false],
  ['T7 fraud over threshold', { fraud: 12 }, 'ناسازگاری داده', false],
  ['T8 CVR drop blocks scale branch', { pb: 100e6, pi: 10000, pc: 800, ab: 110e6, ai: 11000, ac: 600 }, 'خطای برآورد', true],
  ['T9 configurable exec threshold', { pb: 500e6, pi: 10000, pc: 800, ab: 520e6, ai: 6800, ac: 590 }, 'انحراف اجرا', false, { execTh: 0.02 }]
];
for (const [name, o, cause, calib, cfg] of cases) {
  test(name, () => { const r = verify(o, cfg); assert.strictEqual(r.cause, cause); assert.strictEqual(r.calib, calib); });
}
test('incrementality lift', () => { const r = verify({ reach: 50000, ac: 590, holdout: 0.4 }); assert.ok(Math.abs(r.lift - 0.661) < 0.01); });
test('diminishing returns at ceiling = 1/1.5', () => { const e = createEngine(); const row = e.seedRates()[0]; const B = row.ceiling * e.cpiAdj(row); assert.ok(Math.abs(e.installsAt(row, B) / (B / e.cpiAdj(row)) - 2 / 3) < 1e-9); });
test('ten insights, each with evidence', () => { const e = createEngine(); const ins = e.buildInsights(); assert.strictEqual(ins.length, 10); ins.forEach(i => assert.ok(/rates:/.test(i.evidence))); });
test('plan ids are sequential', () => { const e = createEngine(); assert.strictEqual(e.state.seqNo, 101); });
