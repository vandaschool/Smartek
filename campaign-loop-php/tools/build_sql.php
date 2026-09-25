<?php
// Builds database/database.sql = schema + global seed + default admin + demo workspace.
// Usage: php tools/build_sql.php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $c): void {
    $p = APP_ROOT . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (str_starts_with($c, 'App\\') && is_file($p)) {
        require $p;
    }
});

use App\Engine\Seed;

$q = static function ($v): string {
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v) . "'";
};

$out = [];
$out[] = "-- Campaign Loop — full database (schema + reference data + admin + demo workspace)";
$out[] = "-- Import this file into an EMPTY MySQL/MariaDB database (phpMyAdmin → Import).";
$out[] = "-- Default login: admin@example.com / ChangeMe123 (you must change it on first login).";
$out[] = '';
$out[] = file_get_contents(APP_ROOT . '/database/schema.sql');
$out[] = file_get_contents(APP_ROOT . '/database/seed-global.sql');

$hash = password_hash('ChangeMe123', PASSWORD_BCRYPT, ['cost' => 11]);
$out[] = "INSERT INTO `users` (`id`,`email`,`name`,`company`,`password_hash`,`email_verified_at`,`is_admin`,`must_change_password`,`last_workspace_id`,`prefs`,`created_at`) VALUES (1,'admin@example.com','مدیر سامانه','مرچنت نمونه'," . $q($hash) . ",NOW(),1,1,1,'{\"welcome\":1}',NOW());";
$out[] = "INSERT INTO `workspaces` (`id`,`name`,`tier`,`is_demo`,`created_at`) VALUES (1,'فضای کاری مرچنت نمونه','enterprise',1,NOW());";
$out[] = "INSERT INTO `memberships` (`workspace_id`,`user_id`,`role`,`created_at`) VALUES (1,1,'owner',NOW());";
$out[] = "INSERT INTO `rules` (`workspace_id`) VALUES (1);";
$p = Seed::profile();
$out[] = sprintf("INSERT INTO `merchant_profiles` (`workspace_id`,`monthly_budget`,`gross_margin`,`target_cac`,`ltv`,`business_goal`,`season_note`,`blocked_channels`,`updated_at`) VALUES (1,%d,%s,%d,%d,%s,%s,'',NOW());", $p['budget'], $p['margin'], $p['targetCac'], $p['ltv'], $q($p['goal']), $q($p['season']));
foreach (Seed::rates() as $r) {
    $out[] = sprintf(
        "INSERT INTO `rates` (`workspace_id`,`channel`,`segment`,`type`,`unit_cost`,`cvr`,`aov`,`variance`,`sample_n`,`ceiling`,`seasonal_lift`,`d7`,`d30`,`fraud`,`observed_at`,`source`,`updated_at`) VALUES (1,%s,%s,%s,%s,%s,%d,%s,%d,%d,%s,%s,%s,%s,DATE_SUB(CURDATE(), INTERVAL %d MONTH),'history',NOW());",
        $q($r['ch']), $q($r['seg']), $q($r['type']), $r['cpi'], $r['cvr'], $r['aov'], $r['variance'], $r['n'], $r['ceiling'], $r['lift'], $r['d7'], $r['d30'], $r['fraud'], $r['age']
    );
}
$row = static function (string $table, array $data) use ($q): string {
    return 'INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($data)) . '`,`workspace_id`,`created_at`) VALUES ('
        . implode(',', array_map($q, array_values($data))) . ',1,NOW());';
};
// identical to Ws::seedDemo(): every key of the seed arrays is a column
foreach (Seed::history() as $h) {
    $out[] = $row('campaign_history', $h);
}
foreach (Seed::perspectiveLog() as $l) {
    $out[] = $row('perspective_log', $l);
}
$out[] = "INSERT INTO `settings` (`k`,`v`) VALUES ('installed_at', NOW()) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);";
file_put_contents(APP_ROOT . '/database/database.sql', implode("\n", $out) . "\n");
echo "database/database.sql written (" . count($out) . " blocks)\n";
