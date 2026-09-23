<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : roundSettings.tpl                                         ##
##  Type           : Admin Panel Frontend for the round settings              ##
##                   (play window, start quadrant, weekly gold, round length) ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

if (!isset($_SESSION['access']) || $_SESSION['access'] < ADMIN) {
    echo '<p style="color:#f87171;padding:16px;">Access denied.</p>';
    return;
}

$rs      = RoundControl::all();
$msg     = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
$err     = isset($_GET['err']) ? (string)$_GET['err'] : '';
$now     = time();
$fmt     = 'd.m.Y H:i';
$start   = RoundControl::roundStart();
$end     = RoundControl::roundEnd();
$over    = RoundControl::isRoundOver($now);
$snap    = RoundControl::getSnapshot();
list($goldPeriod) = RoundControl::weeklyPeriodAt($now);
$nextGold = RoundControl::nextWeeklyGold($now);

$quadrants = [
    0 => 'Players choose (stock behaviour)',
    1 => 'North-West (-|+)',
    2 => 'North-East (+|+)',
    3 => 'South-West (-|-)',
    4 => 'South-East (+|-)',
];
$sizeNames = [1 => 'small', 2 => 'great', 3 => 'unique'];
?>
<style>
.rs-wrap{color:#e2e8f0;font-family:Verdana,Arial,sans-serif;font-size:12px;padding:6px 4px 26px;}
.rs-wrap h2{font-size:18px;margin:0 0 4px;color:#fff;}
.rs-wrap h2 span{color:#f59e0b;}
.rs-intro{color:#94a3b8;font-size:11px;margin:0 0 14px;max-width:820px;line-height:1.5;}
.rs-msg{background:#14532d;border:1px solid #166534;color:#bbf7d0;border-radius:6px;padding:8px 12px;font-size:11px;margin-bottom:14px;}
.rs-err{background:#7f1d1d;border:1px solid #991b1b;color:#fecaca;border-radius:6px;padding:8px 12px;font-size:11px;margin-bottom:14px;}
.rs-card{background:#111827;border:1px solid #1f2937;border-radius:8px;padding:14px 16px;margin-bottom:18px;}
.rs-card h3{margin:0 0 10px;font-size:13px;color:#fff;font-weight:bold;}
.rs-row{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;margin-bottom:10px;}
.rs-row label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:4px;}
.rs-row select,.rs-row input{background:#0b1220;border:1px solid #334155;border-radius:6px;color:#e2e8f0;padding:7px 9px;}
.rs-row input.short{width:80px;}
.rs-hint{color:#64748b;font-size:10px;margin-top:4px;line-height:1.5;}
.rs-val{font-family:monospace;color:#fbbf24;}
.rs-save{background:#f59e0b;color:#111827;font-weight:bold;border:0;border-radius:6px;padding:9px 18px;cursor:pointer;}
.rs-table{width:100%;border-collapse:collapse;background:#0b1220;border:1px solid #1f2937;}
.rs-table th{background:#111827;text-align:left;padding:7px 10px;font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;border-bottom:1px solid #1f2937;}
.rs-table td{padding:7px 10px;border-bottom:1px solid #14203a;}
</style>

<div class="rs-wrap">
    <h2><?php echo ADMIN_ROUND_SETTINGS; ?> <span>Traviancikas</span></h2>
    <p class="rs-intro">Settings of the fixed-length artifact race. They are stored in the database
        (<span class="rs-val"><?php echo e(TB_PREFIX); ?>round_settings</span>), take effect immediately and never touch config.php.
        All times are server time (<span class="rs-val"><?php echo e(date_default_timezone_get()); ?></span>, now <?php echo date($fmt, $now); ?>).</p>

    <?php if ($msg !== ''): ?>
        <div class="rs-msg" id="rs_msg"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($err !== ''): ?>
        <div class="rs-err" id="rs_err"><?php echo e($err); ?></div>
    <?php endif; ?>

    <form method="post" action="../GameEngine/Admin/Mods/roundSettings.php">
        <?php echo csrf_field(); ?>

        <div class="rs-card">
            <h3>Daily play window</h3>
            <div class="rs-row">
                <div>
                    <label>Play window</label>
                    <select name="window_enabled">
                        <option value="1"<?php echo $rs['window_enabled'] === '1' ? ' selected' : ''; ?>>On</option>
                        <option value="0"<?php echo $rs['window_enabled'] === '0' ? ' selected' : ''; ?>>Off (always open)</option>
                    </select>
                </div>
                <div>
                    <label>Opens (HH:MM)</label>
                    <input class="short" type="text" name="window_start" maxlength="5" value="<?php echo e($rs['window_start']); ?>" placeholder="18:30">
                </div>
                <div>
                    <label>Closes (HH:MM)</label>
                    <input class="short" type="text" name="window_end" maxlength="5" value="<?php echo e($rs['window_end']); ?>" placeholder="19:00">
                </div>
            </div>
            <div class="rs-hint">Outside the window players (not admins, Multihunter or Support) only see a waiting page; the world keeps running.
                A window may cross midnight (e.g. 23:30 - 00:30).
                Right now the window is <b><?php echo RoundControl::isWindowOpen($now) ? 'open' : 'closed'; ?></b>;
                next opening: <?php echo date($fmt, RoundControl::nextWindowStart($now)); ?>.</div>
        </div>

        <div class="rs-card">
            <h3>Registration</h3>
            <div class="rs-row">
                <div>
                    <label>Starting quadrant</label>
                    <select name="start_quadrant">
                        <?php foreach ($quadrants as $id => $label): ?>
                            <option value="<?php echo (int) $id; ?>"<?php echo (int) $rs['start_quadrant'] === $id ? ' selected' : ''; ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="rs-hint">A fixed quadrant is enforced by the server, whatever the registration form posts.</div>
        </div>

        <div class="rs-card">
            <h3>Weekly gold</h3>
            <div class="rs-row">
                <div>
                    <label>Gold per week (0 = off)</label>
                    <input class="short" type="text" name="weekly_gold" maxlength="5" value="<?php echo e($rs['weekly_gold']); ?>">
                </div>
            </div>
            <div class="rs-hint">Granted every 7 days from the round start (by cron) to every real player active during the previous 7 days.
                Last processed week: <b><?php echo e((string) RoundControl::lastGoldPeriod()); ?></b>
                (current week: <?php echo (int) $goldPeriod; ?>)<?php if ($nextGold): ?>; next grant: <b><?php echo date($fmt, $nextGold); ?></b><?php endif; ?>.</div>
        </div>

        <div class="rs-card">
            <h3>Round length</h3>
            <div class="rs-row">
                <div>
                    <label>Round length (days)</label>
                    <input class="short" type="text" name="round_days" maxlength="3" value="<?php echo e($rs['round_days']); ?>">
                </div>
            </div>
            <div class="rs-hint">Round start (config.php START_DATE / START_TIME): <b><?php echo date($fmt, $start); ?></b>.
                Round end = start + length: <b id="rs_end"><?php echo date($fmt, $end); ?></b>
                <?php echo $over ? '<span style="color:#f87171;">(ended)</span>' : ''; ?>.
                The length can be changed (e.g. to extend the round) as long as the end has not passed.</div>
        </div>

        <button type="submit" class="rs-save">Save round settings</button>
    </form>

    <div class="rs-card" style="margin-top:18px;">
        <h3>Final artifact snapshot</h3>
        <?php if ($snap === null): ?>
            <div class="rs-hint">Not taken yet. It is stored automatically by the automation at the round end (<?php echo date($fmt, $end); ?>).</div>
        <?php else: ?>
            <div class="rs-hint">Taken on <b><?php echo date($fmt, (int) $snap['taken_at']); ?></b> (round end <?php echo date($fmt, (int) $snap['round_end']); ?>). Read-only.</div>
            <table class="rs-table" style="margin-top:8px;">
                <thead><tr><th>Artifact</th><th>Size</th><th>Holder</th><th>Alliance</th><th>Village</th></tr></thead>
                <tbody>
                <?php foreach ($snap['artifacts'] as $art): ?>
                    <tr>
                        <td><?php echo e($art['name']); ?></td>
                        <td><?php echo e($sizeNames[(int) $art['size']] ?? '?'); ?></td>
                        <td><?php echo e($art['username'] !== '' ? $art['username'] : '#' . (int) $art['owner']); ?></td>
                        <td><?php echo e($art['alliance_tag'] !== '' ? $art['alliance_tag'] : '-'); ?></td>
                        <td><?php echo e($art['village']); ?> (<?php echo (int) $art['x']; ?>|<?php echo (int) $art['y']; ?>)</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="rs-hint">Public results: <a href="../results.php" style="color:#fbbf24;">results.php</a></div>
        <?php endif; ?>
    </div>
</div>
