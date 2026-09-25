<?php use App\Core\Settings; ?>
<main class="pub-wrap">
  <section class="hero">
    <div class="col gap18">
      <span class="badge teal" style="align-self:start">Campaign Loop · محصول اسمارتک</span>
      <h1>کمپین را طراحی کن، پیش‌بینی کن، و بعد <span style="color:var(--teal)">ثابت کن کجا اشتباه شد</span>.</h1>
      <p>سه ایستگاه روی یک زنجیره‌ی شناسه: مشاوری که ده دیدگاه می‌دهد، پیش‌بینی‌ای که بازه دارد، و راستی‌آزمایی‌ای که انحراف را به علت نسبت می‌دهد و نرخ‌های شما را اصلاح می‌کند.</p>
      <div class="row gap12">
        <a class="btn primary lg" href="<?= e(url('/signup', array_filter(['utm_source' => $_GET['utm_source'] ?? null, 'utm_medium' => $_GET['utm_medium'] ?? null, 'utm_campaign' => $_GET['utm_campaign'] ?? null]))) ?>">ساختن حساب و شروع</a>
        <?php if (Settings::bool('demo_enabled', true)): ?>
          <form method="post" action="<?= e(url('/demo')) ?>" class="inline"><?= csrf_field() ?><button class="btn lg">دیدن نمونه با داده‌ی دمو</button></form>
        <?php endif; ?>
      </div>
      <div class="small muted">بدون نیاز به اتصال داده برای شروع · مسیر دستی مستقل کار می‌کند</div>
    </div>
    <div class="card p22" style="gap:14px">
      <div class="row gap8"><span class="badge teal">plan-۱۴۰۵-۱۰۱</span><span class="badge blue">sim-۱۴۰۵-۱۰۱</span><span class="badge soft">run-۱۴۰۵-۱۰۱</span></div>
      <div class="h2">از دید مدیر مالی</div>
      <div style="font-size:14.5px;line-height:1.95">از دید سود: روی پوش و سگمنت «پرارزش» تمرکز کن. CAC آنجا ۱۱۵٬۶۴۸ تومان است در برابر سقف حاشیه‌ی ۹۱۲٬۰۰۰ تومان.</div>
      <div class="ins-fields"><div><div class="k teal">شاهد</div><div class="v">ردیف rates: پوش|پرارزش · ۶ کمپین پشت این ردیف</div></div><div><div class="k warn">ریسک</div><div class="v">سقف حجم این ردیف ۳٬۰۰۰ نصب است.</div></div></div>
      <div class="verdict ok"><div class="k">حکم سودآوری (POAS)</div><div class="v">سود ناخالص از هزینه بیشتر است (POAS +۶۶٪).</div></div>
    </div>
  </section>
  <div class="small muted" style="text-align:center">بخشی از سبد محصولات اسمارتک — داده‌ی ادتریس و اینترک ورودی این حلقه است</div>

  <section class="section">
    <div class="eyebrow">مسئله</div>
    <h2>کمپین تمام می‌شود، ولی هیچ‌کس نمی‌داند چه چیزی یاد گرفتیم.</h2>
    <div class="grid" style="--min:260px">
      <?php foreach ([
          ['پیش‌بینی مکتوب نیست', 'وقتی عدد انتظار جایی ثبت نشده، بعد از کمپین هر روایتی درست به‌نظر می‌رسد. اولین کاری که این ابزار می‌کند، مکتوب‌کردن همان عدد است.'],
          ['انحراف قابل انتساب نیست', 'خروجی کم شد — از اجرا بود، از مقیاس، یا از خطای برآورد؟ بدون جواب این سؤال، اصلاح نرخ‌ها فقط نویز اضافه می‌کند.'],
          ['اتوماسیون به‌جای مشورت', 'ابزاری که خودش کمپین را اجرا می‌کند اتوماسیون است. دستیار واقعی مثل یک تیم مشاور، چند دیدگاه می‌دهد و انتخاب را به شما می‌سپارد.'],
      ] as [$t, $x]): ?>
        <div class="card"><div class="h3"><?= e($t) ?></div><div class="small t2" style="line-height:1.9"><?= e($x) ?></div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" id="how">
    <div class="eyebrow">چطور کار می‌کند</div>
    <h2>چهار قدم، و هر قدم یک شناسه که به قدم بعد می‌چسبد.</h2>
    <div class="grid" style="--min:230px">
      <?php foreach ([
          ['۱', 'مشاور چنددیدگاهی', 'ده تابع هدف روی یک داده: از دید مدیر مالی، مدیرعامل، تحلیل‌گر داده، سگمنت پرارزش و شش زاویه‌ی دیگر. هر اینسایت شاهد عددی دارد.', 'plan_id'],
          ['۲', 'پیش‌بینی با بازه', 'قیف نصب، خرید و درآمد با کران بالا و پایین — بازه از نوسان تاریخی همان کانال می‌آید، نه از یک مدل. به‌همراه حکم ریسک و سودآوری.', 'sim_id'],
          ['۳', 'راستی‌آزمایی نتیجه', 'انحراف به یکی از پنج علت نسبت داده می‌شود: ناسازگاری داده، انحراف اجرا، مقیاس، خطای برآورد، یا در دامنه‌ی انتظار.', 'run_id'],
          ['۴', 'اصلاح حافظه‌ی سیستم', 'فقط در شاخه‌های معتبر، نرخ کانال با وزن نمونه به‌روز می‌شود. کمپین بعدی با نرخ اصلاح‌شده طراحی می‌شود — حلقه بسته شد.', 'calibration_id'],
      ] as [$n, $t, $x, $id]): ?>
        <div class="card"><div class="row gap8"><span class="stepnum"><?= $n ?></span><div class="h3"><?= e($t) ?></div></div><div class="small t2" style="line-height:1.9"><?= e($x) ?></div><div class="src"><?= e($id) ?></div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section">
    <div class="eyebrow">سه ایستگاه</div>
    <h2>هر ایستگاه یک تصمیم را ممکن می‌کند، نه یک گزارش.</h2>
    <div class="grid" style="--min:300px">
      <div class="card"><div class="src">DESIGNER</div><div class="h3">ده دیدگاه، ده پیشنهاد متفاوت</div><div class="small t2" style="line-height:1.9">هوش مصنوعی دیدگاه اختراع نمی‌کند: هر دیدگاه یک تابع هدف متفاوت روی همان جدول نرخ است. کارت اینسایت شش فیلد دارد — ادعا، پیشنهاد، شاهد، ریسک، معیار موفقیت — و <b>شاهد اجباری است</b>.</div><div class="small t3">· انتخاب یا ترکیب دو دیدگاه با سهم بودجه<br>· ثبت دلیل انتخاب، به‌عنوان داده‌ی یادگیری</div></div>
      <div class="card"><div class="src">SIMULATOR</div><div class="h3">پیش‌بینی‌ای که حدش را می‌داند</div><div class="small t2" style="line-height:1.9">قیف با کران بالا و پایین، و دو حکم صریح: آیا هدف پوشش داده می‌شود، و آیا CAC از حاشیه‌ی هر سفارش بیشتر است. روی خود صفحه نوشته شده که این برون‌یابی نرخ تاریخی است، نه شبیه‌سازی.</div></div>
      <div class="card"><div class="src">VERIFIER</div><div class="h3">کارت انحراف با علت، نه با بهانه</div><div class="small t2" style="line-height:1.9">درخت تصمیم، اولین شرط برقرار را برنده می‌کند و علت را می‌نویسد. ظریف‌ترین قاعده‌ی سیستم همین‌جاست: وقتی انحراف از اجرا یا مقیاس آمده، نرخ کانال غلط نبوده — پس کالیبره نمی‌شود.</div><div class="small t3">مسیر دستی بدون هیچ اتصالی کار می‌کند: پیش‌بینی خودتان را وارد کنید، نتیجه را ثبت کنید، علت را بگیرید.</div></div>
    </div>
  </section>

  <section class="section" id="arch">
    <div class="eyebrow">معماری دو لایه</div>
    <h2>لایه‌ی هوش مصنوعی حق ساختن عدد ندارد.</h2>
    <p class="lead" style="margin-bottom:16px">زیر، یک موتور قطعی و تکرارپذیر مسئله‌ی تخصیص را تحت قیود حل می‌کند. رو، یک لایه‌ی روایت (قالب یا مدل زبانی از طریق متیس) که همان عدد را از دید ذی‌نفع ترجمه می‌کند.</p>
    <div class="col gap8" style="max-width:720px">
      <div class="card blue"><div class="h3">لایه‌ی ۲ — هوش مصنوعی: تفسیر و ارائه</div><div class="small t2">اینسایت از دید ذی‌نفع · داشبورد · پاسخ به پرسش</div><div class="small" style="color:var(--bad-s);font-weight:600">⛔ حق ساختن عدد ندارد</div></div>
      <div class="small muted" style="text-align:center">▲ فقط عدد، با شناسه‌ی منبع</div>
      <div class="card"><div class="h3">لایه‌ی ۱ — موتور بهینه‌سازی: قطعی و تکرارپذیر</div><div class="small t2">حل مسئله‌ی تخصیص تحت قیود · یک بهینه به ازای هر تابع هدف</div></div>
    </div>
  </section>

  <section class="section">
    <div class="eyebrow">ده دیدگاه</div>
    <h2>همان داده، ده زاویه — انسان انتخاب می‌کند.</h2>
    <div class="grid" style="--min:200px;gap:10px">
      <?php foreach ([['مدیر مالی', 'max unit profit · cac ≤ margin × aov'], ['مدیرعامل', 'max output volume'], ['مدیر بازاریابی', 'max composite rank'], ['تحلیل‌گر داده', 'min variance'], ['مسئول کمپین', 'max sample_n'], ['سگمنت پرارزش', 'max aov × cvr'], ['سگمنت در معرض ریزش', 'min reacquisition cost'], ['فصلی و مناسبتی', 'max seasonal lift'], ['رقابتی', 'min sample_n, acceptable cac'], ['محافظه‌کار', 'top objective × 0.2 budget']] as [$t, $o]): ?>
        <div class="card" style="gap:4px;padding:14px"><div class="h4"><?= e($t) ?></div><div class="src"><?= e($o) ?></div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" id="pricing">
    <div class="eyebrow">قیمت‌گذاری</div>
    <h2>با داده‌ی دستی رایگان شروع کنید؛ با اتصال خودکار رشد کنید.</h2>
    <div class="grid" style="--min:260px">
      <div class="card p20"><div style="font-size:17px;font-weight:700">آزمایشی</div><div style="font-size:15px;color:var(--primary);font-weight:600">رایگان</div><div class="small t2">برای آزمودن حلقه روی داده‌ی خودتان.</div><div class="small t3" style="line-height:2">✓ ۳ کمپین در ماه<br>✓ ۱ کاربر<br>✓ ورود دستی و CSV<br>✓ ده دیدگاه، شبیه‌سازی، راستی‌آزمایی</div><a class="btn primary" href="<?= e(url('/signup')) ?>">شروع رایگان</a></div>
      <div class="card p20" style="border-color:var(--primary)"><div style="font-size:17px;font-weight:700">رشد</div><div style="font-size:15px;color:var(--primary);font-weight:600"><?= e(Settings::get('price_growth_label', '۴٫۹ میلیون تومان / ماه')) ?></div><div class="small t2">برای تیم مارکتینگ که هر ماه چند کمپین اجرا می‌کند.</div><div class="small t3" style="line-height:2">✓ کمپین نامحدود<br>✓ ۵ کاربر با نقش<br>✓ اتصال ادتریس و اینترک<br>✓ پایش خودکار و اعلان<br>✓ گزارش ماهانه برای مدیر</div><a class="btn primary" href="<?= e(url('/signup')) ?>">شروع ۱۴ روز آزمایشی</a></div>
      <div class="card p20"><div style="font-size:17px;font-weight:700">سازمانی</div><div style="font-size:15px;color:var(--primary);font-weight:600">تماس با فروش</div><div class="small t2">برای آژانس‌ها و اکانت‌منیجرهای چند مرچنت.</div><div class="small t3" style="line-height:2">✓ چند فضای کاری<br>✓ ورود یکپارچه (SSO)<br>✓ قواعد و آستانه‌ی اختصاصی<br>✓ API کامل و SLA</div><a class="btn" href="#demo">درخواست دمو</a></div>
    </div>
  </section>

  <section class="section" id="demo">
    <div class="grid" style="--min:320px">
      <div class="col gap12"><div class="eyebrow">درخواست دمو</div><h2 style="margin:0">درخواست دموی اختصاصی</h2><p class="lead">۳۰ دقیقه با تیم محصول: یک حلقه‌ی کامل روی داده‌ی نمونه‌ی کسب‌وکار شما. پاسخ ظرف یک روز کاری.</p></div>
      <div class="card p20">
        <?php if ($lead): ?>
          <div class="callout ok">درخواست <?= e($lead['name']) ?> ثبت شد. همکاران ما با <span class="ltr"><?= e($lead['contact']) ?></span> تماس می‌گیرند.</div>
        <?php else: ?>
          <form method="post" action="<?= e(url('/lead')) ?>" class="col gap12"><?= csrf_field() ?>
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            <?php foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $u): ?><input type="hidden" name="<?= $u ?>" value="<?= e($_GET[$u] ?? '') ?>"><?php endforeach; ?>
            <input type="hidden" name="referrer" value="<?= e($_SERVER['HTTP_REFERER'] ?? '') ?>">
            <div class="grid" style="--min:180px;gap:10px">
              <div class="field"><label>نام</label><input class="inp" name="name" required></div>
              <div class="field"><label>شرکت</label><input class="inp" name="company"></div>
              <div class="field"><label>ایمیل کاری</label><input class="inp ltr" type="email" name="email"></div>
              <div class="field"><label>تلفن</label><input class="inp ltr" name="phone" inputmode="tel"></div>
            </div>
            <div class="field"><label>بودجه‌ی ماهانه‌ی تبلیغات</label><select class="inp" name="spend_band"><option>زیر ۵۰۰ میلیون تومان</option><option>۵۰۰ میلیون تا ۲ میلیارد</option><option>بالای ۲ میلیارد</option></select></div>
            <?php if ($leadErr): ?><div class="ferr"><?= e($leadErr) ?></div><?php endif; ?>
            <button class="btn primary md">ثبت درخواست</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section" id="faq">
    <div class="eyebrow">سؤال‌های متداول</div>
    <div class="card flush">
      <?php foreach ([
          ['پس چه چیزی اینجا هوشمند است؟', 'هوش در فهم پرسش و روایت است، نه در ساختن عدد — و این عمدی است. چیزی که ساخته شده زنجیره‌ی شناسه و مرز دو لایه است. مدل زبانی (از طریق متیس) روی همین زیرساخت سوار شده؛ هر عددی که بگوید با ورودی‌اش مقایسه می‌شود و اگر نخواند، دور ریخته می‌شود.'],
          ['برای شروع چه داده‌ای لازم است؟', '۱۵ تا ۲۰ کمپین گذشته، حاشیه‌ی سود ناخالص و هدف کسب‌وکار. صفحه‌ی آمادگی داده قبل از طراحی همین‌ها را بررسی می‌کند و می‌گوید کجا کم است.'],
          ['چرا همیشه کالیبره نمی‌کند؟', 'چون اگر انحراف از اجرا یا مقیاس آمده باشد، نرخ کانال غلط نبوده و به‌روزرسانی آن حافظه‌ی سیستم را با نویز خراب می‌کند. در سه شاخه از پنج شاخه، کالیبراسیون عمداً متوقف می‌شود.'],
          ['جای انسان کجاست؟', 'انتخاب دیدگاه و ثبت دلیلش. همین یک ستون، بعد از بیست کمپین می‌گوید کدام زاویه در عمل درست از آب درآمده — و آن هسته‌ی واقعی یادگیری این محصول است.'],
      ] as [$q, $a]): ?>
        <div class="list-row" style="flex-direction:column;align-items:stretch;gap:6px;padding:14px 18px"><div class="h4" style="font-size:14px"><?= e($q) ?></div><div class="small t2" style="line-height:1.9"><?= e($a) ?></div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" style="text-align:center">
    <h2>یک دور کامل حلقه، زیر پنج دقیقه.</h2>
    <p class="lead" style="margin:0 auto 16px">با داده‌ی دموی آماده شروع کنید و بعد داده‌ی خودتان را وارد کنید.</p>
    <a class="btn primary lg" href="<?= e(url('/signup')) ?>">شروع رایگان</a>
  </section>
</main>
