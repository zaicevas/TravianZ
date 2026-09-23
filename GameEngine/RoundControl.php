<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : RoundControl.php                                          ##
##  Type           : Round settings, play window, weekly gold, round end       ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ (Traviancikas fork)                              ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

/**
 * RoundControl
 * -------------------------------------------------------------------------
 * Everything that makes this server a fixed-length "artifact race" round:
 *
 *   - round settings (play window, start quadrant, weekly gold, round length),
 *     stored in the small `round_settings` table (name => value). The table is
 *     created lazily on the first write, so an already installed server needs
 *     no migration and no new config.php constants. Missing rows fall back to
 *     the defaults below.
 *   - the daily play window: outside it, non-staff players cannot load in-game
 *     pages (Session.php) or call ajax.php. The world itself keeps running.
 *   - the weekly gold bonus, granted from the automation tick.
 *   - the hard round end: START_DATE + START_TIME + round_days. At the end a
 *     snapshot of the artifact holders is stored once, so later troop
 *     movements cannot change the result (results.php).
 *
 * Self-contained (static, resolves the DB link from globals) so the in-game
 * pages, ajax.php, cron.php/Automation and the admin panel can all use it.
 * Settings are read with one query per request and cached.
 */
class RoundControl
{
    /** Defaults, used whenever a setting row does not exist (yet). */
    const DEFAULTS = [
        'window_enabled' => '1',
        'window_start'   => '18:30',
        'window_end'     => '19:00',
        'start_quadrant' => '2',   // 0 = players choose, 1 = NW (-|+), 2 = NE (+|+), 3 = SW (-|-), 4 = SE (+|-)
        'weekly_gold'    => '50',  // 0 = off
        'round_days'     => '42',
    ];

    /** Internal (non-editable) keys. */
    const KEY_GOLD_PERIOD = 'weekly_gold_period';
    const KEY_SNAPSHOT    = 'final_snapshot';

    /** Artifact score per size: 1 = small, 2 = great, 3 = unique. */
    const SCORE = [1 => 1, 2 => 2, 3 => 3];

    /** Artifact type of the WW construction plans (not part of the race). */
    const TYPE_WW_PLAN = 11;

    /** Access level from which an account counts as staff (MH, Support, admin). */
    const STAFF_ACCESS = 8;

    /** Quadrant id => coordinates label (same numbering as registration). */
    const QUADRANTS = [1 => '(-|+)', 2 => '(+|+)', 3 => '(-|-)', 4 => '(+|-)'];

    /** Pages a closed play window never blocks (outgame / session plumbing). */
    const WINDOW_FREE_PAGES = [
        'index.php', 'login.php', 'logout.php', 'anmelden.php', 'activate.php',
        'playwindow.php', 'results.php', 'guide.php', 'rules.php',
        'banned.php', 'maintenance.php', 'winner.php',
    ];

    /** In-game pages that stay viewable (GET only) after the round has ended. */
    const ENDED_VIEW_PAGES = [
        'berichte.php', 'nachrichten.php', 'statistiken.php', 'karte.php',
        'karte2.php', 'spieler.php',
    ];

    /** GET parameters that trigger game actions (Building::procBuild()). */
    const ACTION_PARAMS = ['a', 'master', 'buildingFinish'];

    /** ajax.php calls still allowed after the round has ended (view / chat). */
    const ENDED_AJAX_OK = [
        'k7', 'gchat_poll', 'gchat_updates', 'gchat_send', 'gchat_edit',
        'gchat_delete', 'gchat_poll_create', 'gchat_poll_vote',
        'gchat_search_users', 'gchat_share_report', 'gchat_mute',
        'gchat_block', 'gchat_unmute',
    ];

    private static $settings = null;
    private static $snapshot = false;

    /* ---- Storage -------------------------------------------------------- */

    private static function link()
    {
        if (isset($GLOBALS['link']) && $GLOBALS['link']) {
            return $GLOBALS['link'];
        }
        if (isset($GLOBALS['database']) && isset($GLOBALS['database']->dblink)) {
            return $GLOBALS['database']->dblink;
        }
        return null;
    }

    private static function table()
    {
        return '`' . TB_PREFIX . 'round_settings`';
    }

    /** Create the round_settings table if missing (called before writes). */
    public static function ensureSchema()
    {
        $link = self::link();
        if (!$link) {
            return false;
        }
        try {
            return (bool) mysqli_query($link, "CREATE TABLE IF NOT EXISTS " . self::table() . " (
                `name`    varchar(64) NOT NULL,
                `value`   mediumtext NOT NULL,
                `updated` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Forget the per-request cache (long running cron loops, after writes). */
    public static function reload()
    {
        self::$settings = null;
        self::$snapshot = false;
    }

    /** All editable settings, validated, defaults filled in. Cached per request. */
    public static function all()
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $values = self::DEFAULTS;
        $link   = self::link();

        if ($link) {
            try {
                $names = "'" . implode("','", array_keys(self::DEFAULTS)) . "'";
                $res   = @mysqli_query($link, "SELECT `name`, `value` FROM " . self::table() . " WHERE `name` IN (" . $names . ")");
                if ($res) {
                    while ($row = mysqli_fetch_assoc($res)) {
                        $values[$row['name']] = (string) $row['value'];
                    }
                }
            } catch (\Throwable $e) {
                // table not created yet: defaults
            }
        }

        // a malformed stored value must never break the game: fall back
        foreach ($values as $name => $value) {
            if (self::validate($name, $value) !== true) {
                $values[$name] = self::DEFAULTS[$name];
            }
        }

        return self::$settings = $values;
    }

    public static function get($name)
    {
        $all = self::all();
        return $all[$name] ?? null;
    }

    /**
     * Validate one editable setting. Returns true or an error message.
     */
    public static function validate($name, $value)
    {
        $value = trim((string) $value);

        switch ($name) {
            case 'window_enabled':
                return in_array($value, ['0', '1'], true) ? true : 'Play window must be on or off.';

            case 'window_start':
            case 'window_end':
                return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value)
                    ? true : 'Times must use the HH:MM format (00:00 - 23:59).';

            case 'start_quadrant':
                return preg_match('/^[0-4]$/', $value) ? true : 'Unknown starting quadrant.';

            case 'weekly_gold':
                return (preg_match('/^[0-9]{1,5}$/', $value) && (int) $value <= 10000)
                    ? true : 'Weekly gold must be a whole number between 0 and 10000.';

            case 'round_days':
                return (preg_match('/^[0-9]{1,3}$/', $value) && (int) $value >= 1 && (int) $value <= 365)
                    ? true : 'Round length must be between 1 and 365 days.';
        }

        return 'Unknown setting.';
    }

    /** Store one raw value (no validation - callers validate). */
    public static function set($name, $value)
    {
        $link = self::link();
        if (!$link || !self::ensureSchema()) {
            return false;
        }

        $name  = (string) $name;
        $value = (string) $value;
        $now   = time();

        $stmt = mysqli_prepare($link, "INSERT INTO " . self::table() . " (`name`, `value`, `updated`) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated` = VALUES(`updated`)");
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $name, $value, $now);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        self::reload();
        return $ok;
    }

    /** Raw value of an internal key (not cached), or null. */
    private static function getRaw($name)
    {
        $link = self::link();
        if (!$link) {
            return null;
        }
        try {
            $stmt = mysqli_prepare($link, "SELECT `value` FROM " . self::table() . " WHERE `name` = ? LIMIT 1");
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 's', $name);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            return $row ? (string) $row['value'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ---- Round dates ---------------------------------------------------- */

    /** Round start (START_DATE + START_TIME, server timezone). */
    public static function roundStart()
    {
        if (!defined('START_DATE') || !defined('START_TIME')) {
            return 0;
        }
        $ts = strtotime(START_DATE . ' ' . START_TIME);
        return $ts ? (int) $ts : 0;
    }

    /** $ts + $days calendar days, keeping the wall-clock time across DST. */
    public static function addDays($ts, $days)
    {
        $date = new DateTime('@' . (int) $ts);
        $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $date->modify(((int) $days >= 0 ? '+' : '') . (int) $days . ' days');
        return $date->getTimestamp();
    }

    /** Hard round end, or 0 when the start is unknown. */
    public static function roundEnd($days = null)
    {
        $start = self::roundStart();
        if (!$start) {
            return 0;
        }
        return self::addDays($start, $days === null ? (int) self::get('round_days') : (int) $days);
    }

    public static function isRoundOver($now = null)
    {
        $end = self::roundEnd();
        return $end > 0 && ($now === null ? time() : (int) $now) >= $end;
    }

    /** When the artifacts spawn - same formula as AutomationNatarsWW::spawnNatars(). */
    public static function artifactsDate()
    {
        if (!defined('START_DATE') || !defined('NATARS_SPAWN_TIME')) {
            return 0;
        }
        return (int) (strtotime(START_DATE) + ((int) NATARS_SPAWN_TIME * 86400));
    }

    /* ---- Starting quadrant ---------------------------------------------- */

    /** Configured fixed quadrant (1-4), or 0 when players may choose. */
    public static function fixedQuadrant()
    {
        return (int) self::get('start_quadrant');
    }

    /**
     * The quadrant a new player really gets. A fixed quadrant always wins over
     * whatever was posted; otherwise the posted value is sanitised and
     * 0 ("random") is kept for generateBase() to roll.
     */
    public static function quadrantFor($requested)
    {
        $fixed = self::fixedQuadrant();
        if ($fixed >= 1 && $fixed <= 4) {
            return $fixed;
        }
        $requested = (int) $requested;
        return ($requested >= 1 && $requested <= 4) ? $requested : 0;
    }

    /* ---- Play window ---------------------------------------------------- */

    public static function windowEnabled()
    {
        return self::get('window_enabled') === '1';
    }

    private static function minutesOf($hhmm)
    {
        list($h, $m) = explode(':', $hhmm);
        return (int) $h * 60 + (int) $m;
    }

    /** Timestamp of the given HH:MM on the day of $ts, shifted by $dayOffset days. */
    private static function timeOnDay($hhmm, $ts, $dayOffset = 0)
    {
        list($h, $m) = explode(':', $hhmm);
        return mktime((int) $h, (int) $m, 0, (int) date('n', $ts), (int) date('j', $ts) + $dayOffset, (int) date('Y', $ts));
    }

    /** Is the daily play window open at $now? (always true when disabled) */
    public static function isWindowOpen($now = null)
    {
        if (!self::windowEnabled()) {
            return true;
        }
        $now   = $now === null ? time() : (int) $now;
        $start = self::minutesOf(self::get('window_start'));
        $end   = self::minutesOf(self::get('window_end'));
        $t     = (int) date('G', $now) * 60 + (int) date('i', $now);

        if ($start === $end) {
            return true;
        }
        if ($start < $end) {
            return $t >= $start && $t < $end;
        }
        // the window crosses midnight, e.g. 23:30 - 00:30
        return $t >= $start || $t < $end;
    }

    /** Next time the window opens after $now (or when the current one opened). */
    public static function nextWindowStart($now = null)
    {
        $now   = $now === null ? time() : (int) $now;
        $start = self::get('window_start');
        $today = self::timeOnDay($start, $now);

        if (self::isWindowOpen($now)) {
            return $today <= $now ? $today : self::timeOnDay($start, $now, -1);
        }
        return $today > $now ? $today : self::timeOnDay($start, $now, 1);
    }

    /** Next time the window closes after $now. */
    public static function nextWindowEnd($now = null)
    {
        $now   = $now === null ? time() : (int) $now;
        $end   = self::get('window_end');
        $today = self::timeOnDay($end, $now);
        return $today > $now ? $today : self::timeOnDay($end, $now, 1);
    }

    /** "18:30 - 19:00" */
    public static function windowLabel()
    {
        return self::get('window_start') . ' - ' . self::get('window_end');
    }

    /* ---- Access control (Session.php / ajax.php) ------------------------ */

    public static function isStaff($access, $username = '')
    {
        return (int) $access >= self::STAFF_ACCESS || in_array($username, ['Support', 'Multihunter'], true);
    }

    private static function isAjaxRequest()
    {
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return stripos($accept, 'application/json') !== false && stripos($accept, 'text/html') === false;
    }

    private static function deny($reason, $location)
    {
        if (self::isAjaxRequest()) {
            self::denyAjax($reason);
        }
        header('Location: ' . $location, true, 303);
        exit;
    }

    /** Short machine-readable refusal for AJAX / non-page requests. */
    public static function denyAjax($reason)
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json');
            header('Cache-Control: no-store');
        }
        $body = ['ok' => 0, 'reason' => $reason];
        if ($reason === 'play_window_closed') {
            $body['message'] = 'The play window is closed. It opens again at ' . date('d.m.Y H:i', self::nextWindowStart()) . ' (server time).';
            $body['next'] = self::nextWindowStart();
        } else {
            $body['message'] = 'The round has ended.';
        }
        echo json_encode($body);
        exit;
    }

    /**
     * Called from Session::__construct() for every logged-in, non-admin-panel
     * request. Staff always passes. After the round end players are sent to
     * the results (they may still browse a few view-only pages); outside the
     * play window they are sent to the waiting page.
     *
     * @param string $prefix path back to the web root ($autoprefix)
     */
    public static function enforceSession($access, $username, $prefix = '')
    {
        if (self::isStaff($access, $username)) {
            return;
        }

        $page = basename($_SERVER['PHP_SELF'] ?? '');
        if (in_array($page, self::WINDOW_FREE_PAGES, true)) {
            return;
        }

        // the World Wonder ending keeps its own flow (Session::isWinner / winner.php)
        if (isset($GLOBALS['database']) && $GLOBALS['database']->isThereAWinner()) {
            return;
        }

        if (self::isRoundOver()) {
            $isGet   = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
            $isAction = false;
            foreach (self::ACTION_PARAMS as $param) {
                if (isset($_GET[$param])) {
                    $isAction = true;
                }
            }
            if ($isGet && !$isAction && in_array($page, self::ENDED_VIEW_PAGES, true)) {
                return;
            }
            self::deny('round_over', $prefix . 'results.php');
        }

        if (!self::isWindowOpen()) {
            self::deny('play_window_closed', $prefix . 'playwindow.php');
        }
    }

    /** Same rules for ajax.php, which does not bootstrap Session.php. */
    public static function enforceAjax($database, $action)
    {
        $uid = (int) ($_SESSION['id_user'] ?? 0);
        if ($uid <= 0) {
            return; // not logged in: the endpoints refuse on their own
        }

        $over   = self::isRoundOver();
        $closed = !$over && !self::isWindowOpen();
        if (!$over && !$closed) {
            return;
        }
        if ($over && in_array($action, self::ENDED_AJAX_OK, true)) {
            return;
        }
        if ($database->isThereAWinner()) {
            return;
        }

        $access = (int) $database->getUserField($uid, 'access', 0);
        $name   = (string) ($_SESSION['username'] ?? '');
        if (self::isStaff($access, $name)) {
            return;
        }
        self::denyAjax($over ? 'round_over' : 'play_window_closed');
    }

    /* ---- Automation ----------------------------------------------------- */

    /** Called from every automation tick (cron.php, or Village.php when cron is not running). */
    public static function runAutomation($now = null)
    {
        $now = $now === null ? time() : (int) $now;
        self::reload();
        self::grantWeeklyGold($now);
        self::takeFinalSnapshot($now);
    }

    /**
     * Weekly period that has most recently started at $now: [number, time].
     * Period k starts at round start + 7k days (k >= 1) and must start before
     * the round end. [0, 0] when none has started yet.
     */
    public static function weeklyPeriodAt($now)
    {
        $start = self::roundStart();
        $now   = (int) $now;
        if (!$start || $now < $start) {
            return [0, 0];
        }

        $end = self::roundEnd();
        $k   = (int) floor(($now - $start) / 604800) + 1;
        while ($k > 0 && (self::addDays($start, 7 * $k) > $now || self::addDays($start, 7 * $k) >= $end)) {
            $k--;
        }
        return [$k, $k > 0 ? self::addDays($start, 7 * $k) : 0];
    }

    /** Last weekly period that has been processed (0 = none yet). */
    public static function lastGoldPeriod()
    {
        return (int) self::getRaw(self::KEY_GOLD_PERIOD);
    }

    /** First weekly grant after $now (for display), or 0. */
    public static function nextWeeklyGold($now = null)
    {
        $now   = $now === null ? time() : (int) $now;
        $start = self::roundStart();
        if (!$start) {
            return 0;
        }
        list($k) = self::weeklyPeriodAt($now);
        $next = self::addDays($start, 7 * ($k + 1));
        return $next < self::roundEnd() ? $next : 0;
    }

    /**
     * Grant the weekly gold for the current period, exactly once.
     *
     * The last processed period is stored in round_settings and read with
     * SELECT ... FOR UPDATE inside a transaction, so concurrent or repeated
     * runs cannot grant a period twice. A period is also marked as processed
     * when the bonus is off (amount 0), so switching it on later does not pay
     * out a period retroactively. Only the most recent period is paid (no
     * catch-up for missed periods). Recipients: real players (not system,
     * staff or banned accounts) active within the 7 days before the period.
     *
     * @return int number of players who received gold
     */
    public static function grantWeeklyGold($now = null)
    {
        $now  = $now === null ? time() : (int) $now;
        $link = self::link();
        if (!$link) {
            return 0;
        }

        list($period, $periodTime) = self::weeklyPeriodAt($now);
        if ($period < 1) {
            return 0;
        }

        $amount = (int) self::get('weekly_gold');

        if (!self::ensureSchema()) {
            return 0;
        }

        $key = self::KEY_GOLD_PERIOD;
        $recipients = [];

        try {
            $stmt = mysqli_prepare($link, "INSERT IGNORE INTO " . self::table() . " (`name`, `value`, `updated`) VALUES (?, '0', ?)");
            mysqli_stmt_bind_param($stmt, 'si', $key, $now);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_begin_transaction($link);

            $stmt = mysqli_prepare($link, "SELECT `value` FROM " . self::table() . " WHERE `name` = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 's', $key);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($row && (int) $row['value'] >= $period) {
                mysqli_commit($link);
                return 0;
            }

            if ($amount > 0) {
                $activeSince = $periodTime - 7 * 86400;
                $stmt = mysqli_prepare($link, "SELECT id FROM `" . TB_PREFIX . "users`
                    WHERE id > 5 AND tribe IN (1,2,3,6,7,8,9) AND access > 0 AND access < " . self::STAFF_ACCESS . "
                    AND timestamp >= ? ORDER BY id");
                mysqli_stmt_bind_param($stmt, 'i', $activeSince);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($user = mysqli_fetch_assoc($res)) {
                    $recipients[] = (int) $user['id'];
                }
                mysqli_stmt_close($stmt);

                if ($recipients) {
                    $update = mysqli_prepare($link, "UPDATE `" . TB_PREFIX . "users` SET gold = gold + ? WHERE id = ?");
                    $log    = mysqli_prepare($link, "INSERT INTO `" . TB_PREFIX . "gold_fin_log` (wid, uid, action, gold, time, details) VALUES (0, ?, 'Weekly gold', ?, ?, ?)");
                    $details = 'Weekly activity bonus, week ' . $period;
                    foreach ($recipients as $uid) {
                        mysqli_stmt_bind_param($update, 'ii', $amount, $uid);
                        mysqli_stmt_execute($update);
                        mysqli_stmt_bind_param($log, 'iiis', $uid, $amount, $now, $details);
                        mysqli_stmt_execute($log);
                    }
                    mysqli_stmt_close($update);
                    mysqli_stmt_close($log);
                }
            }

            $value = (string) $period;
            $stmt = mysqli_prepare($link, "UPDATE " . self::table() . " SET `value` = ?, `updated` = ? WHERE `name` = ?");
            mysqli_stmt_bind_param($stmt, 'sis', $value, $now, $key);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($link);
        } catch (\Throwable $e) {
            try { mysqli_rollback($link); } catch (\Throwable $ignored) {}
            error_log('RoundControl: weekly gold failed: ' . $e->getMessage());
            return 0;
        }

        // in-game notice (queued, flushed by Database.php at shutdown)
        if ($recipients && isset($GLOBALS['database'])) {
            $topic = 'Weekly gold: +' . $amount . ' gold';
            $text  = 'You received ' . $amount . ' gold as the weekly activity bonus (week ' . $period . ' of the round).'
                . "\n\n" . 'Every 7 days all players who played during the previous week get ' . $amount . ' gold.';
            foreach ($recipients as $uid) {
                $GLOBALS['database']->sendMessage($uid, 1, $topic, $text, 0, 0, 0, 0, 0);
            }
        }

        return count($recipients);
    }

    /* ---- Round end snapshot / results ----------------------------------- */

    /** The final snapshot (array) or null when not taken yet. Cached. */
    public static function getSnapshot()
    {
        if (self::$snapshot !== false) {
            return self::$snapshot;
        }
        $raw  = self::getRaw(self::KEY_SNAPSHOT);
        $data = $raw !== null ? json_decode($raw, true) : null;
        return self::$snapshot = (is_array($data) ? $data : null);
    }

    /**
     * Store the final artifact holders once, at/after the round end.
     * INSERT IGNORE on the primary key: the first writer wins, later calls are
     * no-ops even when they race.
     *
     * @return bool true when this call took the snapshot
     */
    public static function takeFinalSnapshot($now = null)
    {
        $now = $now === null ? time() : (int) $now;
        if (!self::isRoundOver($now) || self::getSnapshot() !== null) {
            return false;
        }
        $link = self::link();
        if (!$link || !self::ensureSchema()) {
            return false;
        }

        $data = self::collectStandings();
        $data['taken_at']  = $now;
        $data['round_end'] = self::roundEnd();
        $json = json_encode($data);
        $key  = self::KEY_SNAPSHOT;

        try {
            $stmt = mysqli_prepare($link, "INSERT IGNORE INTO " . self::table() . " (`name`, `value`, `updated`) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssi', $key, $json, $now);
            mysqli_stmt_execute($stmt);
            $taken = mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
        } catch (\Throwable $e) {
            error_log('RoundControl: snapshot failed: ' . $e->getMessage());
            return false;
        }

        self::$snapshot = false;
        return $taken;
    }

    /** Current artifact holders and top population (live data). */
    public static function collectStandings()
    {
        $link = self::link();
        $out  = ['artifacts' => [], 'population' => []];
        if (!$link) {
            return $out;
        }

        $q = "SELECT a.id, a.name, a.type, a.size, a.vref, a.owner,
                v.name AS village, w.x, w.y,
                u.username, u.tribe, u.alliance AS alliance_id,
                al.tag AS alliance_tag, al.name AS alliance_name
            FROM `" . TB_PREFIX . "artefacts` a
            LEFT JOIN `" . TB_PREFIX . "vdata` v ON v.wref = a.vref
            LEFT JOIN `" . TB_PREFIX . "wdata` w ON w.id = a.vref
            LEFT JOIN `" . TB_PREFIX . "users` u ON u.id = a.owner
            LEFT JOIN `" . TB_PREFIX . "alidata` al ON al.id = u.alliance
            WHERE a.del = 0 AND a.type != " . self::TYPE_WW_PLAN . "
            ORDER BY a.size DESC, a.id ASC";
        try {
            $res = mysqli_query($link, $q);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $out['artifacts'][] = [
                    'id'            => (int) $row['id'],
                    'name'          => (string) $row['name'],
                    'type'          => (int) $row['type'],
                    'size'          => (int) $row['size'],
                    'vref'          => (int) $row['vref'],
                    'village'       => (string) $row['village'],
                    'x'             => (int) $row['x'],
                    'y'             => (int) $row['y'],
                    'owner'         => (int) $row['owner'],
                    'username'      => (string) $row['username'],
                    'tribe'         => (int) $row['tribe'],
                    'alliance_id'   => (int) $row['alliance_id'],
                    'alliance_tag'  => (string) $row['alliance_tag'],
                    'alliance_name' => (string) $row['alliance_name'],
                ];
            }
        } catch (\Throwable $e) {
            // artefacts table always exists on an installed server; stay quiet anyway
        }

        $maxAccess = (defined('INCLUDE_ADMIN') && INCLUDE_ADMIN) ? 10 : self::STAFF_ACCESS;
        $q = "SELECT u.id, u.username, al.tag AS alliance_tag,
                SUM(v.pop) AS pop, COUNT(v.wref) AS villages
            FROM `" . TB_PREFIX . "users` u
            JOIN `" . TB_PREFIX . "vdata` v ON v.owner = u.id
            LEFT JOIN `" . TB_PREFIX . "alidata` al ON al.id = u.alliance
            WHERE u.id > 5 AND u.access > 0 AND u.access < " . (int) $maxAccess . " AND u.tribe IN (1,2,3,6,7,8,9)
            GROUP BY u.id, u.username, al.tag
            ORDER BY pop DESC, villages DESC, u.username ASC
            LIMIT 10";
        try {
            $res = mysqli_query($link, $q);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $out['population'][] = [
                    'uid'          => (int) $row['id'],
                    'username'     => (string) $row['username'],
                    'alliance_tag' => (string) $row['alliance_tag'],
                    'pop'          => (int) $row['pop'],
                    'villages'     => (int) $row['villages'],
                ];
            }
        } catch (\Throwable $e) {
        }

        return $out;
    }

    /**
     * Player and alliance rankings from a standings array (snapshot or live).
     * Artifacts still held by the Natars or other system accounts score for
     * nobody. Order: score, then unique/great/small counts, then name.
     */
    public static function rank(array $standings)
    {
        $players = [];
        $alliances = [];

        foreach ($standings['artifacts'] ?? [] as $art) {
            $owner = (int) $art['owner'];
            if ($owner <= 5) {
                continue;
            }
            $points = self::SCORE[(int) $art['size']] ?? 0;

            if (!isset($players[$owner])) {
                $players[$owner] = [
                    'uid' => $owner, 'username' => $art['username'],
                    'alliance_tag' => $art['alliance_tag'], 'score' => 0,
                    'counts' => [1 => 0, 2 => 0, 3 => 0], 'artifacts' => [],
                ];
            }
            $players[$owner]['score'] += $points;
            $players[$owner]['counts'][(int) $art['size']] = ($players[$owner]['counts'][(int) $art['size']] ?? 0) + 1;
            $players[$owner]['artifacts'][] = $art;

            $aid = (int) $art['alliance_id'];
            if ($aid > 0) {
                if (!isset($alliances[$aid])) {
                    $alliances[$aid] = [
                        'aid' => $aid, 'tag' => $art['alliance_tag'], 'name' => $art['alliance_name'],
                        'score' => 0, 'counts' => [1 => 0, 2 => 0, 3 => 0], 'members' => [],
                    ];
                }
                $alliances[$aid]['score'] += $points;
                $alliances[$aid]['counts'][(int) $art['size']] = ($alliances[$aid]['counts'][(int) $art['size']] ?? 0) + 1;
                $alliances[$aid]['members'][$owner] = $art['username'];
            }
        }

        $order = function ($nameKey) {
            return function ($a, $b) use ($nameKey) {
                foreach (['score', 3, 2, 1] as $k) {
                    $x = $k === 'score' ? $a['score'] : ($a['counts'][$k] ?? 0);
                    $y = $k === 'score' ? $b['score'] : ($b['counts'][$k] ?? 0);
                    if ($x !== $y) {
                        return $y <=> $x;
                    }
                }
                return strcasecmp((string) $a[$nameKey], (string) $b[$nameKey]);
            };
        };

        usort($players, $order('username'));
        usort($alliances, $order('tag'));

        return ['players' => $players, 'alliances' => $alliances];
    }
}
