<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : resetServer.php 			                               ##
##  Type           : Admin Panel Frontend                                      ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Dzoki (Original)                                          ##
##  Refactored by  : Shadow                                                    ##
##  Redesign by    : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2025. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

include_once("../../GameEngine/config.php");
include_once("../../GameEngine/Database.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['access']) || (int)$_SESSION['access'] < ADMIN) {
    die("<h1><font color=\"red\">Access Denied: You are not Admin!</font></h1>");
}
set_time_limit(0);

// 1. Salvăm adminul dacă e bifat
$keepAdmin = isset($_POST['keep_admin']) && $_POST['keep_admin'] == '1';
$adminData = null;
if($keepAdmin){
    $admin_id = (int)$_SESSION['id'];
    $res = mysqli_query($GLOBALS["link"], "SELECT username, password, email, access, tribe FROM `".TB_PREFIX."users` WHERE id = $admin_id LIMIT 1");
    $adminData = mysqli_fetch_assoc($res);
}

// Multihunter keeps its current password (the stock reset set "12345" with admin
// access, i.e. a public admin login). No existing account: an unguessable one.
$res = mysqli_query($GLOBALS["link"], "SELECT password FROM `".TB_PREFIX."users` WHERE id = 5 LIMIT 1");
$mhPass = ($row = mysqli_fetch_assoc($res)) && $row['password'] !== '' ? $row['password']
    : password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);

// Round bookkeeping (last weekly gold period, final artifact snapshot) belongs
// to the old round; the admin's round settings are kept.
include_once("../../GameEngine/RoundControl.php");
RoundControl::clearRoundState();

// 2. Wipe every round table (the stock hard-coded list missed newer ones - hero items,
// global chat, statistics - and named ones that no longer exist). Admin configuration
// that is not round data is kept.
$keep = ['round_settings', 'quest_config', 'reg_block', 'gold_promo', 'banlist_ip'];
mysqli_query($GLOBALS["link"], "SET FOREIGN_KEY_CHECKS=0");
$res = mysqli_query($GLOBALS["link"], "SHOW TABLES");
while ($row = mysqli_fetch_row($res)) {
    $t = $row[0];
    if (strpos($t, TB_PREFIX) === 0 && !in_array(substr($t, strlen(TB_PREFIX)), $keep, true)) {
        mysqli_query($GLOBALS["link"], "TRUNCATE TABLE `$t`");
    }
}
mysqli_query($GLOBALS["link"], "SET FOREIGN_KEY_CHECKS=1");

// 3. Same path as the installer: struct.sql re-seeds the system accounts (Support,
// Nature, Taskmaster, Multihunter) and config rows, then the map is generated.
$autoprefix = '../../';
$database->createDbStructure();
$database->populateWorldData();

// 4. Multihunter: its previous password (the seed has none)
$passw = mysqli_real_escape_string($GLOBALS["link"], $mhPass);
mysqli_query($GLOBALS["link"], "UPDATE `".TB_PREFIX."users` SET password = '$passw' WHERE id = 5");

// 5. Reintroducem adminul
if($keepAdmin && $adminData && $admin_id == 5){
    // the admin is Multihunter itself: already back as id 5, just restore its access
    mysqli_query($GLOBALS["link"], "UPDATE `".TB_PREFIX."users` SET access = ".(int)$adminData['access']." WHERE id = 5");
} elseif($keepAdmin && $adminData){
    $u = mysqli_real_escape_string($GLOBALS["link"], $adminData['username']);
    $p = mysqli_real_escape_string($GLOBALS["link"], $adminData['password']);
    $e = mysqli_real_escape_string($GLOBALS["link"], $adminData['email']);
    $a = (int)$adminData['access'];
    $t = (int)$adminData['tribe'];
    // ID 6 ca să nu se calce cu sistemele
    mysqli_query($GLOBALS["link"], "INSERT INTO `".TB_PREFIX."users` (id, username, password, email, tribe, access, gold, plus, protect) VALUES (6, '$u', '$p', '$e', $t, $a, 0, 0, 0)");
    $_SESSION['id'] = 6; // actualizăm sesiunea
}

// 6. Log (proxy-aware, issue #185)
$resetIp = \App\Utils\IpResolver::getClientIp() ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
mysqli_query($GLOBALS["link"], "INSERT INTO `".TB_PREFIX."admin_log` (user, log, time) VALUES (".(int)$_SESSION['id'].", '".mysqli_real_escape_string($GLOBALS["link"], "Server reset".($keepAdmin ? ' (admin kept)' : '')." from ".$resetIp)."', ".time().")");

header("Location: ../admin.php?p=resetdone");
exit;
?>