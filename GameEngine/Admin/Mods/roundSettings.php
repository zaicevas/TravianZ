<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       roundSettings.php                                           ##
##  Type           BACKEND (Round settings: play window, quadrant, weekly     ##
##                 gold, round length)                                         ##
##  License:       TravianZ Project                                           ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.               ##
#################################################################################

require_once(__DIR__ . '/../csrf.php');
if (!isset($_SESSION)) session_start();
if (($_SESSION['access'] ?? 0) < 9) {
    admin_deny('You must be signed in as an administrator to do this. '
        . 'Your session may have expired — please return to the admin panel and sign in again.');
}

csrf_verify();

include_once("../../config.php");

$autoprefix = '';
for ($i = 0; $i < 5; $i++) {
    $autoprefix = str_repeat('../', $i);
    if (file_exists($autoprefix . 'autoloader.php')) break;
}
include_once($autoprefix . "GameEngine/Database.php");
include_once($autoprefix . "GameEngine/RoundControl.php");

$admid = (int)($_SESSION['id'] ?? 0);

// Defence in depth: confirm the acting account really is an admin.
$check = mysqli_query($GLOBALS['link'],
    "SELECT access FROM " . TB_PREFIX . "users WHERE id = " . $admid);
$acc = $check ? mysqli_fetch_assoc($check) : null;
if (!$acc || (int)$acc['access'] < ADMIN) {
    admin_deny('Your session may have expired — please sign in again.');
}

function roundSettings_back($ok, $msg)
{
    header("Location: ../../../Admin/admin.php?p=roundSettings&" . ($ok ? 'msg' : 'err') . "=" . urlencode($msg));
    exit;
}

// ---- collect + validate everything first (all or nothing) -----------------
$new = [
    'window_enabled' => isset($_POST['window_enabled']) && $_POST['window_enabled'] === '1' ? '1' : '0',
    'window_start'   => trim((string)($_POST['window_start'] ?? '')),
    'window_end'     => trim((string)($_POST['window_end'] ?? '')),
    'start_quadrant' => trim((string)($_POST['start_quadrant'] ?? '')),
    'weekly_gold'    => trim((string)($_POST['weekly_gold'] ?? '')),
    'round_days'     => trim((string)($_POST['round_days'] ?? '')),
];

$errors = [];
foreach ($new as $name => $value) {
    $valid = RoundControl::validate($name, $value);
    if ($valid !== true) {
        $errors[] = $valid;
    }
}

if (!$errors && $new['window_start'] === $new['window_end']) {
    $errors[] = 'The play window must end at a different time than it starts.';
}

$old = RoundControl::all();

if (!$errors && $new['round_days'] !== $old['round_days']) {
    $now    = time();
    $newEnd = RoundControl::roundEnd((int) $new['round_days']);

    if (RoundControl::isRoundOver($now)) {
        $errors[] = 'The round has already ended on ' . date('d.m.Y H:i', RoundControl::roundEnd())
            . '; its result is final and the length can no longer be changed.';
    } elseif ($newEnd <= $now) {
        $errors[] = 'With ' . (int) $new['round_days'] . ' days the round would end on ' . date('d.m.Y H:i', $newEnd)
            . ', which is in the past. Choose a later end.';
    } elseif ($newEnd <= RoundControl::roundStart()) {
        $errors[] = 'The round end must be after the round start.';
    }
}

if ($errors) {
    roundSettings_back(false, implode(' ', array_unique($errors)));
}

// ---- save --------------------------------------------------------------------
$changed = [];
foreach ($new as $name => $value) {
    if ($value !== $old[$name]) {
        if (!RoundControl::set($name, $value)) {
            roundSettings_back(false, 'Could not save the settings (database error).');
        }
        $changed[] = $name . ': ' . $old[$name] . ' -> ' . $value;
    }
}

if ($changed) {
    $logMsg = mysqli_real_escape_string($GLOBALS['link'], 'Round settings changed (' . implode(', ', $changed) . ')');
    mysqli_query($GLOBALS['link'],
        "INSERT INTO " . TB_PREFIX . "admin_log VALUES (0, " . $admid . ", '" . $logMsg . "', " . time() . ")");
}

roundSettings_back(true, $changed ? 'Round settings saved.' : 'Nothing changed.');
