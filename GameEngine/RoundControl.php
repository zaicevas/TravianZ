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
 *     snapshot of the artifact holders is stored once (first automation tick
 *     after the end, before troop movements are processed), so later troop
 *     movements cannot change the result (results.php).
 *
 * The bookkeeping rows (last weekly gold period, final snapshot) record the
 * round start they belong to; a value from another round (reinstall, reset,
 * new START_DATE) counts as "none".
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

    /** How round dates are shown to players and staff: 2026.09.27 18:30. */
    const DATE_FMT = 'Y.m.d H:i';

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

    /** A timestamp in DATE_FMT, server timezone. */
    public static function fmt($ts)
    {
        return date(self::DATE_FMT, (int) $ts);
    }

    /** "3 days 4 hours", "5 hours 20 minutes" or "12 minutes" (rounded down). */
    public static function durationText($seconds)
    {
        $m = max(0, (int) floor($seconds / 60));
        $d = intdiv($m, 1440);
        $h = intdiv($m % 1440, 60);
        $m = $m % 60;
        // English fallback when no language file is loaded (CLI, cron)
        $unit = function ($n, $word) {
            $c = 'RND_' . strtoupper($word) . ($n === 1 ? '' : 'S');
            return $n . ' ' . (defined($c) ? constant($c) : $word . ($n === 1 ? '' : 's'));
        };
        if ($d > 0) {
            return $unit($d, 'day') . ($h > 0 ? ' ' . $unit($h, 'hour') : '');
        }
        if ($h > 0) {
            return $unit($h, 'hour') . ($m > 0 ? ' ' . $unit($m, 'minute') : '');
        }
        return $unit($m, 'minute');
    }

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

    /**
     * Whether the visitor has a game session, for the top navigation of the
     * out-of-game pages (results.php, guide.php) that do not load Session.php.
     * Display only - never use it for access decisions. Reads an existing
     * session and closes it at once; visitors without the cookie get none.
     */
    public static function hasPlayerSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (empty($_COOKIE[session_name()]) || headers_sent()) {
                return false;
            }
            @session_start(['read_and_close' => true]);
        }
        return !empty($_SESSION['username']) && !empty($_SESSION['sessid']);
    }

    /**
     * File name of the script that is really executing, e.g. "dorf1.php", or
     * '' when it is not a file in the web root.
     *
     * Uses SCRIPT_FILENAME (set by the web server, never contains PATH_INFO):
     * basename($_SERVER['PHP_SELF']) is attacker-controlled through PATH_INFO,
     * /dorf1.php/index.php runs dorf1.php but PHP_SELF ends in "index.php".
     */
    public static function currentPage()
    {
        static $page = null;
        if ($page !== null) {
            return $page;
        }
        $file = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $real = $file !== '' ? realpath($file) : false;
        $root = realpath(dirname(__DIR__));
        if ($real === false || $root === false || dirname($real) !== $root) {
            return $page = '';
        }
        return $page = basename($real);
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
            $body['message'] = 'The play window is closed. It opens again at ' . self::fmt(self::nextWindowStart()) . ' (server time).';
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

        $page = self::currentPage();
        if (in_array($page, self::WINDOW_FREE_PAGES, true)) {
            return;
        }

        // A World Wonder winner ends the round its own way (Session::isWinner /
        // winner.php): no results redirect then, but the play window still applies.
        $over = self::isRoundOver()
            && !(isset($GLOBALS['database']) && $GLOBALS['database']->isThereAWinner());

        if ($over) {
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
        $closed = !self::isWindowOpen();
        if (!$over && !$closed) {
            return;
        }
        // same rules as enforceSession(): a World Wonder winner only lifts the
        // round-over block, the play window still applies
        if ($over && $database->isThereAWinner()) {
            $over = false;
        }
        if ($over) {
            if (in_array($action, self::ENDED_AJAX_OK, true)) {
                return;
            }
            $reason = 'round_over';
        } elseif ($closed) {
            $reason = 'play_window_closed';
        } else {
            return;
        }

        $access = (int) $database->getUserField($uid, 'access', 0);
        $name   = (string) ($_SESSION['username'] ?? '');
        if (self::isStaff($access, $name)) {
            return;
        }
        self::denyAjax($reason);
    }

    /* ---- Automation ----------------------------------------------------- */

    /**
     * Start of every automation tick (cron.php, or Village.php when cron is not
     * running): freeze the artifact holders BEFORE troop movements of this tick
     * are processed, so battles landing after the end cannot count.
     */
    public static function runSnapshot($now = null)
    {
        self::reload();
        self::takeFinalSnapshot($now === null ? time() : (int) $now);
    }

    /** End of every automation tick: weekly gold. */
    public static function runWeeklyGold($now = null)
    {
        self::reload();
        self::grantWeeklyGold($now === null ? time() : (int) $now);
    }

    /** Decode a bookkeeping value; null unless it belongs to the current round. */
    private static function roundValue($raw)
    {
        $data = ($raw !== null && $raw !== '') ? json_decode($raw, true) : null;
        if (!is_array($data) || (int) ($data['round_start'] ?? -1) !== self::roundStart()) {
            return null;
        }
        return $data;
    }

    /** Remove the bookkeeping rows (not the settings), e.g. on a server reset. */
    public static function clearRoundState()
    {
        $link = self::link();
        if (!$link) {
            return false;
        }
        try {
            mysqli_query($link, "DELETE FROM " . self::table() . " WHERE `name` IN ('" . self::KEY_GOLD_PERIOD . "', '" . self::KEY_SNAPSHOT . "')");
        } catch (\Throwable $e) {
            // table does not exist yet: nothing to clear
        }
        self::reload();
        return true;
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

    /** Last weekly period of the current round that has been processed (0 = none yet). */
    public static function lastGoldPeriod()
    {
        $data = self::roundValue(self::getRaw(self::KEY_GOLD_PERIOD));
        return $data ? (int) ($data['period'] ?? 0) : 0;
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
     * The last processed period (with the round start it belongs to) is stored
     * in round_settings and read with SELECT ... FOR UPDATE inside a
     * transaction, so concurrent or repeated runs cannot grant a period twice;
     * a counter of another round counts as 0. A period is also marked as processed
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

            $stored = $row ? self::roundValue((string) $row['value']) : null;
            if ($stored && (int) ($stored['period'] ?? 0) >= $period) {
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

            $value = json_encode(['round_start' => self::roundStart(), 'period' => $period]);
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

    /** The final snapshot of the current round (array), or null when not taken yet. Cached. */
    public static function getSnapshot()
    {
        if (self::$snapshot !== false) {
            return self::$snapshot;
        }
        return self::$snapshot = self::roundValue(self::getRaw(self::KEY_SNAPSHOT));
    }

    /** JSON for the snapshot; invalid UTF-8 (e.g. in a village name) is replaced, never fatal. */
    public static function encodeSnapshot(array $data)
    {
        $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($json) || $json === '' || !is_array(json_decode($json, true))) {
            return false;
        }
        return $json;
    }

    /**
     * Store the final artifact holders once, at/after the round end.
     * Row-locked (INSERT IGNORE + SELECT ... FOR UPDATE): the first writer
     * wins, later or racing calls are no-ops. A snapshot of another round is
     * replaced; an empty/undecodable one is never stored.
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
        $data['taken_at']    = $now;
        $data['round_end']   = self::roundEnd();
        $data['round_start'] = self::roundStart();
        $json = self::encodeSnapshot($data);
        if ($json === false) {
            error_log('RoundControl: snapshot not stored, JSON encoding failed: ' . json_last_error_msg());
            return false;
        }
        $key = self::KEY_SNAPSHOT;

        try {
            mysqli_begin_transaction($link);

            $stmt = mysqli_prepare($link, "INSERT IGNORE INTO " . self::table() . " (`name`, `value`, `updated`) VALUES (?, '', ?)");
            mysqli_stmt_bind_param($stmt, 'si', $key, $now);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($link, "SELECT `value` FROM " . self::table() . " WHERE `name` = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 's', $key);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($row && self::roundValue((string) $row['value']) !== null) {
                mysqli_commit($link);   // someone else was faster
                self::$snapshot = false;
                return false;
            }

            $stmt = mysqli_prepare($link, "UPDATE " . self::table() . " SET `value` = ?, `updated` = ? WHERE `name` = ?");
            mysqli_stmt_bind_param($stmt, 'sis', $json, $now, $key);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($link);
        } catch (\Throwable $e) {
            try { mysqli_rollback($link); } catch (\Throwable $ignored) {}
            error_log('RoundControl: snapshot failed: ' . $e->getMessage());
            return false;
        }

        self::$snapshot = false;
        return true;
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
                u.username, u.tribe, u.access, u.alliance AS alliance_id,
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
                    'access'        => (int) $row['access'],
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
     * Quadrant of a tile: 1 (-|+), 2 (+|+), 3 (-|-), 4 (+|-), 0 for the centre
     * tile. Same bounds as the registration sectors (DatabaseVillageQueries).
     */
    public static function quadrantOf($x, $y)
    {
        $x = (int) $x;
        $y = (int) $y;
        if ($x < 0 && $y >= 0) return 1;
        if ($x >= 0 && $y > 0) return 2;
        if ($x <= 0 && $y < 0) return 3;
        if ($x > 0 && $y <= 0) return 4;
        return 0;
    }

    /**
     * Normal attacks and raids (attack_type 3, 4) that players (not system
     * accounts) sent since $since. Reinforcements are not counted.
     */
    public static function attacksSince($since)
    {
        $link = self::link();
        if (!$link) {
            return 0;
        }
        $q = "SELECT COUNT(*) AS n
            FROM `" . TB_PREFIX . "movement` m
            JOIN `" . TB_PREFIX . "attacks` a ON a.id = m.ref
            JOIN `" . TB_PREFIX . "vdata` v ON v.wref = m.`from`
            WHERE m.sort_type = 3 AND a.attack_type IN (3, 4) AND v.owner > 5 AND m.starttime >= " . (int) $since;
        try {
            $res = mysqli_query($link, $q);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            return $row ? (int) $row['n'] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Players for the homepage list, by name: username, tribe, how many
     * accounts they invited, quadrant of their capital (0 = none/centre).
     * Same accounts as the population ranking: no system, staff or banned.
     */
    public static function registeredPlayers()
    {
        $link = self::link();
        if (!$link) {
            return [];
        }
        $maxAccess = (defined('INCLUDE_ADMIN') && INCLUDE_ADMIN) ? 10 : self::STAFF_ACCESS;
        $q = "SELECT u.username, u.tribe, w.x, w.y,
                (SELECT COUNT(*) FROM `" . TB_PREFIX . "users` i WHERE i.invited IN (u.id, -u.id)) AS invited
            FROM `" . TB_PREFIX . "users` u
            LEFT JOIN `" . TB_PREFIX . "vdata` v ON v.owner = u.id AND v.capital = 1
            LEFT JOIN `" . TB_PREFIX . "wdata` w ON w.id = v.wref
            WHERE u.id > 5 AND u.access > 0 AND u.access < " . (int) $maxAccess . " AND u.tribe IN (1,2,3,6,7,8,9)";
        $out = [];
        try {
            $res = mysqli_query($link, $q);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $out[] = [
                    'username' => (string) $row['username'],
                    'tribe'    => (int) $row['tribe'],
                    'invited'  => (int) $row['invited'],
                    'quadrant' => $row['x'] === null ? 0 : self::quadrantOf($row['x'], $row['y']),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }
        usort($out, function ($a, $b) {
            return strcasecmp($a['username'], $b['username']) ?: strcmp($a['username'], $b['username']);
        });
        return $out;
    }

    /**
     * Does this artifact count for the race? Not when it is held by the Natars
     * or another system account (id <= 5) or by staff (access >= 8, Support,
     * Multihunter - the same accounts that get no weekly gold).
     */
    public static function isScored(array $art)
    {
        return (int) $art['owner'] > 5 && !self::isStaff((int) ($art['access'] ?? 0), (string) ($art['username'] ?? ''));
    }

    /**
     * Player and alliance rankings from a standings array (snapshot or live),
     * scored artifacts only (isScored). Order, as stated on the results page
     * (RND_RESULTS_TIEBREAK): score, then more unique, more great, more small
     * artifacts, then name (alphabetical).
     */
    public static function rank(array $standings)
    {
        $players = [];
        $alliances = [];

        foreach ($standings['artifacts'] ?? [] as $art) {
            $owner = (int) $art['owner'];
            if (!self::isScored($art)) {
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
