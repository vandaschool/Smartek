<?php
/** @var array $v @var array $breaker @var array $llm @var array $llmErr @var array $stats @var array $occasions @var array $leads @var array $tickets @var array $workspaces @var array $cron @var ?array $aiResult */
$sel = static function (string $name, array $opts, string $cur): string {
    $h = '<select class="inp sm" style="width:220px" name="' . e($name) . '" id="f-' . e($name) . '">';
    foreach ($opts as $k => $l) {
        $h .= '<option value="' . e($k) . '"' . ($cur === (string) $k ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $h . '</select>';
};
$inp = static fn (string $name, string $val, string $extra = '') => '<input class="inp sm ltr" style="width:220px" name="' . e($name) . '" id="f-' . e($name) . '" value="' . e($val) . '"' . $extra . '>';
$sec = static fn (string $name, string $val) => '<div class="col" style="gap:4px;width:220px"><input class="inp sm ltr" type="password" autocomplete="new-password" name="' . e($name) . '" id="f-' . e($name) . '" placeholder="' . ($val !== '' ? 'ذخیره شده — برای تغییر وارد کنید' : 'وارد نشده') . '">'
    . ($val !== '' ? '<label class="check xs"><input type="checkbox" name="' . e($name) . '__clear" value="1"> پاک شود</label>' : '') . '</div>';
$chk = static fn (string $name, string $val, string $label) => '<input type="hidden" name="' . e($name) . '__present" value="1"><label class="check small"><input type="checkbox" name="' . e($name) . '" value="1"' . ($val === '1' ? ' checked' : '') . '> ' . e($label) . '</label>';
$row = static fn (string $label, string $ctl, string $hint = '', string $for = '') => '<div class="frow" style="align-items:flex-start;border-bottom:1px solid var(--bd3);padding-bottom:9px"><div class="k col" style="gap:2px"><label' . ($for ? ' for="f-' . e($for) . '"' : '') . '>' . e($label) . '</label>' . ($hint ? '<span class="hint">' . e($hint) . '</span>' : '') . '</div>' . $ctl . '</div>';
$bOpen = !empty($breaker['open_until']) && $breaker['open_until'] > time();
$aiLive = $v['ai_provider'] === 'metis' && $v['metis_api_key'] !== '';
?>
<div class="col gap20">
  <div class="head">
    <h1 class="title">مدیریت سامانه</h1>
    <p class="lead">تنظیمات سراسری: هوش مصنوعی (متیس)، ایمیل، درگاه پرداخت، اتصال‌ها و مناسبت‌ها. کلیدها رمزگذاری‌شده در پایگاه‌داده ذخیره می‌شوند و هرگز دوباره نمایش داده نمی‌شوند.</p>
  </div>

  <div class="grid" style="--min:160px;gap:12px">
    <div class="card" style="gap:4px"><div class="kpi-l">کاربران</div><div class="kpi-v"><?= fa($stats['users']) ?></div></div>
    <div class="card" style="gap:4px"><div class="kpi-l">فضای کاری</div><div class="kpi-v"><?= fa($stats['ws']) ?></div></div>
    <div class="card" style="gap:4px"><div class="kpi-l">پلن پولی فعال</div><div class="kpi-v"><?= fa($stats['paid']) ?></div></div>
    <div class="card" style="gap:4px"><div class="kpi-l">حلقه‌ی بسته‌شده</div><div class="kpi-v"><?= fa($stats['loops']) ?></div></div>
    <div class="card" style="gap:4px"><div class="kpi-l">حساب دموی موقت</div><div class="kpi-v"><?= fa($stats['demo']) ?></div></div>
  </div>

  <nav class="row gap8 small no-print"><a href="#ai">هوش مصنوعی</a> · <a href="#mail">ایمیل</a> · <a href="#pay">پرداخت</a> · <a href="#conn">اتصال‌ها</a> · <a href="#occasions">مناسبت‌ها</a> · <a href="#tickets">پشتیبانی</a> · <a href="#leads">سرنخ‌ها</a> · <a href="#cron">زمان‌بندی</a></nav>

  <!-- AI -->
  <form method="post" action="<?= e(url('/admin/settings')) ?>" class="card" id="ai" style="max-width:860px"><?= csrf_field() ?><input type="hidden" name="tab" value="ai">
    <div class="row"><div class="h2 grow">هوش مصنوعی — متیس (ChatGPT)</div>
      <?php if ($aiLive && !$bOpen): ?><span class="badge teal">فعال</span><?php elseif ($bOpen): ?><span class="badge bad">قطع موقت (مدارشکن)</span><?php else: ?><span class="badge gray">حالت قالبی</span><?php endif; ?></div>
    <div class="callout info small">لایه‌ی ۲ فقط روایت می‌کند؛ همه‌ی اعداد از موتور می‌آیند و فایروال عدد هر پاسخی را که عدد تازه بسازد رد می‌کند. در حالت «قالبی» یا هنگام خطا، متن از قالب‌های قطعی ساخته می‌شود و محصول بدون وقفه کار می‌کند.</div>
    <?= $row('ارائه‌دهنده', $sel('ai_provider', ['mock' => 'قالبی (بدون هوش مصنوعی)', 'metis' => 'متیس — OpenAI-compatible'], $v['ai_provider']), 'برای فعال‌سازی، «متیس» را انتخاب و کلید را وارد کنید.', 'ai_provider') ?>
    <?= $row('کلید API متیس', $sec('metis_api_key', $v['metis_api_key']), 'از پنل metisai.ir ← API Keys', 'metis_api_key') ?>
    <?= $row('آدرس پایه', $inp('metis_base_url', $v['metis_base_url'], ' placeholder="https://api.metisai.ir/openai/v1"'), 'POST {base}/chat/completions', 'metis_base_url') ?>
    <?= $row('مدل سریع', $inp('metis_model_fast', $v['metis_model_fast']), 'دسته‌بندی پرسش، نگاشت ستون CSV، راهنمای دلیل', 'metis_model_fast') ?>
    <?= $row('مدل دقیق', $inp('metis_model_smart', $v['metis_model_smart']), 'توضیح اینسایت، روایت راستی‌آزمایی، خلاصه‌ی ماهانه', 'metis_model_smart') ?>
    <?= $row('مهلت پاسخ مدل سریع (ms)', $inp('ai_timeout_fast_ms', $v['ai_timeout_fast_ms'], ' inputmode="numeric"'), '', 'ai_timeout_fast_ms') ?>
    <?= $row('مهلت پاسخ مدل دقیق (ms)', $inp('ai_timeout_smart_ms', $v['ai_timeout_smart_ms'], ' inputmode="numeric"'), '', 'ai_timeout_smart_ms') ?>
    <?= $row('سقف توکن ماهانه · آزمایشی', $inp('ai_budget_trial', $v['ai_budget_trial'], ' inputmode="numeric"'), 'به ازای هر فضای کاری؛ پس از سقف، حالت قالبی', 'ai_budget_trial') ?>
    <?= $row('سقف توکن ماهانه · رشد', $inp('ai_budget_growth', $v['ai_budget_growth'], ' inputmode="numeric"'), '', 'ai_budget_growth') ?>
    <?= $row('سقف توکن ماهانه · سازمانی', $inp('ai_budget_enterprise', $v['ai_budget_enterprise'], ' inputmode="numeric"'), '', 'ai_budget_enterprise') ?>
    <?= $chk('ai_json_schema', $v['ai_json_schema'], 'ارسال response_format از نوع json_schema (در صورت پشتیبانی نشدن، خودکار به json_object برمی‌گردد)') ?>
    <?= $chk('ai_debug', $v['ai_debug'], 'ذخیره‌ی متن درخواست/پاسخ در llm_calls برای عیب‌یابی (در محیط اصلی خاموش باشد)') ?>
    <div class="row gap8"><button class="btn primary">ذخیره</button></div>
  </form>
  <div class="card" style="max-width:860px">
    <div class="row"><div class="h2 grow">آزمون اتصال و مصرف ۷ روز اخیر</div>
      <form method="post" action="<?= e(url('/admin/ai-test')) ?>"><?= csrf_field() ?><button class="btn sm outline">آزمون اتصال متیس</button></form></div>
    <?php if ($aiResult): ?><div class="callout <?= $aiResult['ok'] ? 'ok' : 'bad' ?> small"><?= e($aiResult['message']) ?><?= $aiResult['ms'] ? ' · ' . fa($aiResult['ms']) . ' ms' : '' ?></div><?php endif; ?>
    <?php if ($bOpen): ?><div class="callout warn small">مدارشکن باز است تا <?= e(jdt(date('Y-m-d H:i:s', (int) $breaker['open_until']))) ?>؛ تا آن زمان پاسخ‌ها قالبی‌اند. آزمون اتصال آن را بازنشانی می‌کند.</div><?php endif; ?>
    <?php if (!$llm): ?><div class="hint">هنوز فراخوانی ثبت نشده.</div><?php else: ?>
      <div class="tbl-wrap"><table class="tbl" data-cards>
        <thead><tr><th>وظیفه</th><th>کل</th><th>موفق</th><th>کش</th><th>قالبی</th><th>ردشده</th><th>میانگین تأخیر</th><th>توکن</th></tr></thead>
        <tbody><?php foreach ($llm as $l): ?><tr><td data-label="وظیفه" class="mono"><?= e($l['task']) ?></td><td data-label="کل"><?= fa((int) $l['n']) ?></td><td data-label="موفق"><?= fa((int) $l['ok']) ?></td><td data-label="کش"><?= fa((int) $l['cached']) ?></td><td data-label="قالبی"><?= fa((int) $l['fb']) ?></td><td data-label="ردشده"><?= fa((int) $l['viol']) ?></td><td data-label="میانگین تأخیر"><?= $l['ms'] ? fa((int) $l['ms']) . ' ms' : '—' ?></td><td data-label="توکن"><?= e(num((int) $l['tok'])) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
    <?php foreach ($llmErr as $l): ?><div class="xs muted"><span class="mono"><?= e($l['task']) ?> · <?= e($l['status']) ?></span> · <?= e(jdt($l['created_at'])) ?> · <span class="ltr"><?= e($l['error_code']) ?></span></div><?php endforeach; ?>
  </div>

  <!-- Mail -->
  <form method="post" action="<?= e(url('/admin/settings')) ?>" class="card" id="mail" style="max-width:860px"><?= csrf_field() ?><input type="hidden" name="tab" value="mail">
    <div class="h2">ایمیل (SMTP)</div>
    <div class="hint">اگر میزبان SMTP خالی باشد، از تابع mail() خود سرور استفاده می‌شود. برای تحویل مطمئن، SMTP هاست یا سرویس ایمیل را وارد کنید.</div>
    <?= $row('میزبان SMTP', $inp('smtp_host', $v['smtp_host'], ' placeholder="mail.example.com"'), '', 'smtp_host') ?>
    <?= $row('پورت', $inp('smtp_port', $v['smtp_port'], ' inputmode="numeric"'), '۵۸۷ برای TLS · ۴۶۵ برای SSL', 'smtp_port') ?>
    <?= $row('رمزنگاری', $sel('smtp_secure', ['tls' => 'STARTTLS', 'ssl' => 'SSL', 'none' => 'بدون رمزنگاری'], $v['smtp_secure'] ?: 'tls'), '', 'smtp_secure') ?>
    <?= $row('نام کاربری', $inp('smtp_user', $v['smtp_user']), '', 'smtp_user') ?>
    <?= $row('رمز عبور', $sec('smtp_pass', $v['smtp_pass']), '', 'smtp_pass') ?>
    <?= $row('فرستنده (ایمیل)', $inp('mail_from', $v['mail_from'], ' placeholder="no-reply@example.com"'), '', 'mail_from') ?>
    <?= $row('فرستنده (نام)', '<input class="inp sm" style="width:220px" name="mail_from_name" id="f-mail_from_name" value="' . e($v['mail_from_name']) . '">', '', 'mail_from_name') ?>
    <div class="row gap8"><button class="btn primary">ذخیره</button><button class="btn" formaction="<?= e(url('/admin/mail-test')) ?>">ارسال ایمیل آزمایشی به من</button></div>
  </form>

  <!-- Payment -->
  <form method="post" action="<?= e(url('/admin/settings')) ?>" class="card" id="pay" style="max-width:860px"><?= csrf_field() ?><input type="hidden" name="tab" value="pay">
    <div class="h2">درگاه پرداخت</div>
    <?= $row('درگاه', $sel('payment_provider', ['sandbox' => 'آزمایشی (بدون پرداخت واقعی)', 'zarinpal_sandbox' => 'زرین‌پال — محیط تست', 'zarinpal' => 'زرین‌پال — واقعی'], $v['payment_provider']), '', 'payment_provider') ?>
    <?= $row('مرچنت کد زرین‌پال', $sec('zarinpal_merchant_id', $v['zarinpal_merchant_id']), '۳۶ کاراکتری، از پنل زرین‌پال', 'zarinpal_merchant_id') ?>
    <?= $row('قیمت پلن رشد (ریال)', $inp('price_growth_rial', $v['price_growth_rial'], ' inputmode="numeric"'), 'مبلغی که به درگاه ارسال می‌شود', 'price_growth_rial') ?>
    <?= $row('برچسب قیمت', '<input class="inp sm" style="width:220px" name="price_growth_label" id="f-price_growth_label" value="' . e($v['price_growth_label']) . '">', 'متنی که در صفحه‌ی پلن نمایش داده می‌شود', 'price_growth_label') ?>
    <div class="hint ltr" style="text-align:right">Callback: <?= e(url('/billing/callback', [], true)) ?></div>
    <div class="row gap8"><button class="btn primary">ذخیره</button></div>
  </form>

  <!-- Connectors -->
  <form method="post" action="<?= e(url('/admin/settings')) ?>" class="card" id="conn" style="max-width:860px"><?= csrf_field() ?><input type="hidden" name="tab" value="conn">
    <div class="h2">اتصال‌ها و دمو</div>
    <?= $row('حالت اتصال ادتریس/اینترک', $sel('connector_mode', ['mock' => 'آزمایشی (داده‌ی ساختگی قطعی)', 'live' => 'واقعی (فراخوانی API)'], $v['connector_mode']), 'قرارداد API در docs/INTEGRATIONS.md', 'connector_mode') ?>
    <?= $row('آدرس API ادتریس', $inp('adtrace_base_url', $v['adtrace_base_url'], ' placeholder="https://…"'), '', 'adtrace_base_url') ?>
    <?= $row('آدرس API اینترک', $inp('intrack_base_url', $v['intrack_base_url'], ' placeholder="https://…"'), '', 'intrack_base_url') ?>
    <?= $chk('demo_enabled', $v['demo_enabled'], 'دکمه‌ی «ورود با حساب دمو» در صفحه‌ی ورود فعال باشد (حساب موقت ۷ روزه)') ?>
    <div class="row gap8"><button class="btn primary">ذخیره</button></div>
  </form>

  <!-- Occasions -->
  <form method="post" action="<?= e(url('/admin/occasions')) ?>" class="card flush" id="occasions"><?= csrf_field() ?>
    <div style="padding:14px 18px;border-bottom:1px solid var(--bd2)"><div class="h2">مناسبت‌ها</div><div class="hint">اثر مناسبت روی نرخ خرید و هزینه‌ی واحد (کسری، مثلاً ۰٫۲۵ یعنی ٪۲۵). ماه‌ها شمسی‌اند (۱ تا ۱۲؛ ۰ یعنی بدون ماه مشخص).</div></div>
    <div class="tbl-wrap"><table class="tbl" data-cards>
      <thead><tr><th>نام</th><th>اثر خرید</th><th>اثر هزینه</th><th>از ماه</th><th>تا ماه</th><th>حذف</th></tr></thead>
      <tbody>
      <?php foreach (array_merge($occasions, [['id' => 0, 'name' => '', 'purchase_lift' => '', 'cpi_delta' => '', 'month_from' => '', 'month_to' => '']]) as $o): ?>
        <tr>
          <td data-label="نام"><input type="hidden" name="id[]" value="<?= (int) $o['id'] ?>"><input class="inp" style="width:170px" name="name[]" value="<?= e($o['name']) ?>" placeholder="<?= $o['id'] ? '' : 'مناسبت جدید' ?>"></td>
          <td data-label="اثر خرید"><input class="inp num" name="lift[]" value="<?= e($o['purchase_lift']) ?>"></td>
          <td data-label="اثر هزینه"><input class="inp num" name="cpi[]" value="<?= e($o['cpi_delta']) ?>"></td>
          <td data-label="از ماه"><input class="inp num" style="width:60px" name="mf[]" value="<?= e($o['month_from']) ?>"></td>
          <td data-label="تا ماه"><input class="inp num" style="width:60px" name="mt[]" value="<?= e($o['month_to']) ?>"></td>
          <td data-label="حذف"><?php if ($o['id']): ?><input type="checkbox" name="del[]" value="<?= (int) $o['id'] ?>" aria-label="حذف"><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="padding:14px 18px"><button class="btn primary">ذخیره‌ی مناسبت‌ها</button></div>
  </form>

  <!-- Tickets -->
  <div class="card flush" id="tickets">
    <div class="h2" style="padding:14px 18px;border-bottom:1px solid var(--bd2)">درخواست‌های پشتیبانی</div>
    <?php if (!$tickets): ?><div class="hint" style="padding:14px 18px">درخواستی نیست.</div><?php endif; ?>
    <?php foreach ($tickets as $t): ?>
      <form method="post" action="<?= e(url('/admin/ticket/' . $t['id'])) ?>" class="col" style="padding:12px 18px;border-bottom:1px solid var(--bd3);gap:8px"><?= csrf_field() ?>
        <div class="row gap8 small"><b><?= e($t['name'] ?? '—') ?></b><span class="ltr muted"><?= e($t['email'] ?? '') ?></span><span class="muted"><?= e($t['ws'] ?? '') ?></span><span class="xs muted"><?= e(jdt($t['created_at'])) ?></span>
          <span class="badge sm <?= $t['status'] === 'answered' ? 'teal' : 'warn' ?>"><?= $t['status'] === 'answered' ? 'پاسخ داده شد' : 'باز' ?></span></div>
        <div class="small t2" style="white-space:pre-line"><?= e($t['body']) ?></div>
        <div class="row gap8"><textarea class="inp grow" name="reply" rows="2" placeholder="پاسخ" style="min-width:220px"><?= e($t['reply'] ?? '') ?></textarea><button class="btn sm">ثبت پاسخ</button></div>
      </form>
    <?php endforeach; ?>
  </div>

  <div class="grid" style="--min:380px">
    <div class="card flush" id="leads">
      <div class="h2" style="padding:14px 18px;border-bottom:1px solid var(--bd2)">سرنخ‌های صفحه‌ی فرود</div>
      <?php if (!$leads): ?><div class="hint" style="padding:14px 18px">هنوز سرنخی ثبت نشده.</div><?php endif; ?>
      <?php foreach ($leads as $l): ?>
        <div class="list-row"><div class="grow"><b><?= e($l['name']) ?></b> · <?= e($l['company']) ?> <span class="xs muted"><?= e($l['spend_band']) ?></span></div><div class="ltr small"><?= e($l['phone'] ?: $l['email']) ?></div><div class="xs muted"><?= e(jdt($l['created_at'])) ?><?= $l['utm_source'] ? ' · ' . e($l['utm_source']) : '' ?></div></div>
      <?php endforeach; ?>
    </div>
    <div class="card flush">
      <div class="h2" style="padding:14px 18px;border-bottom:1px solid var(--bd2)">فضاهای کاری اخیر</div>
      <?php foreach ($workspaces as $w): ?>
        <div class="list-row"><div class="grow"><b><?= e($w['name']) ?></b> <span class="xs muted">#<?= fa((int) $w['id']) ?></span></div><div class="small"><?= e(['trial' => 'آزمایشی', 'growth' => 'رشد', 'enterprise' => 'سازمانی'][$w['tier']] ?? $w['tier']) ?><?= $w['tier_until'] ? ' تا ' . e(jdate($w['tier_until'])) : '' ?></div><div class="xs muted"><?= fa((int) $w['members']) ?> عضو · <?= fa((int) $w['camps']) ?> کمپین</div></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card" id="cron" style="max-width:860px">
    <div class="h2">کارهای زمان‌بندی‌شده (cron)</div>
    <div class="hint">هر ۱۵ دقیقه اجرا شود: <span class="mono">php <?= e(APP_ROOT) ?>/cron.php</span> — یا از طریق وب با توکن config.php.</div>
    <?php if (!$cron): ?><div class="callout warn small">cron هنوز اجرا نشده است. بدون آن همگام‌سازی، یادآورها و گزارش ماهانه ارسال نمی‌شود.</div><?php endif; ?>
    <?php foreach ($cron as $c): ?><div class="row small" style="border-top:1px solid var(--bd3);padding-top:6px"><span class="mono grow"><?= e($c['job']) ?></span><span class="muted"><?= e(jdt($c['last_run_at'])) ?></span><span class="xs"><?= e($c['last_status']) ?></span></div><?php endforeach; ?>
  </div>
</div>
