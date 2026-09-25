<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : playwindow.php                                            ##
##  Type           : Waiting page shown outside the daily play window          ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ (Traviancikas fork)                              ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

use App\Utils\AccessLogger;

// Session.php sends visitors who are not logged in to login.php.
include_once("GameEngine/Session.php");
AccessLogger::logRequest();

if (RoundControl::isRoundOver() && !$database->isThereAWinner()) {
	header("Location: results.php");
	exit;
}

// Nothing to wait for: the window is open, disabled, or this is a staff account.
if (RoundControl::isPlayable() || RoundControl::isStaff($session->access, $session->username)) {
	header("Location: dorf1.php");
	exit;
}

// Once the round runs, the reports page (with the global chat) is the waiting page.
if (RoundControl::hasStarted()) {
	header("Location: berichte.php");
	exit;
}

$now         = time();
$nextOpen    = RoundControl::nextPlayStart($now);
$preStart    = !RoundControl::hasStarted($now);
$rndPageTitle = $preStart ? RND_WAIT_TITLE_PRESTART : RND_WAIT_TITLE;
$rndLoggedIn  = true;   // top nav: Guide / Logout instead of Register / Login

include("Templates/Round/outgame_top.tpl");
?>
<h1><?php echo $rndPageTitle; ?></h1>

<?php if ($preStart) { ?>
<p id="rnd_wait_prestart"><?php echo sprintf(RND_WAIT_INTRO_PRESTART, htmlspecialchars((string) $session->username, ENT_QUOTES, 'UTF-8'), '<b>' . RoundControl::fmt(RoundControl::roundStart()) . '</b>'); ?></p>
<?php } ?>
<p><?php echo sprintf(RND_WAIT_INTRO, htmlspecialchars((string) $session->username, ENT_QUOTES, 'UTF-8')); ?></p>

<div class="rnd-box rnd-center">
	<p class="rnd-big"><?php echo htmlspecialchars(RoundControl::windowEnabled() ? RoundControl::windowLabel() : RND_INFO_ALWAYS_OPEN, ENT_QUOTES, 'UTF-8'); ?> <span class="rnd-muted">(<?php echo RND_SERVER_TIME . ', ' . htmlspecialchars(date_default_timezone_get(), ENT_QUOTES, 'UTF-8'); ?>)</span></p>
	<p><?php echo RND_WAIT_NEXT; ?> <b id="rnd_next_open"><?php echo RoundControl::fmt($nextOpen); ?></b> <?php echo RND_WAIT_IN; ?></p>
	<p class="rnd-count"><span class="rnd-countdown" id="rnd_countdown" data-left="<?php echo (int) ($nextOpen - $now); ?>" data-done-url="dorf1.php"><?php echo gmdate('H:i:s', max(0, $nextOpen - $now)); ?></span></p>
	<p class="rnd-muted"><?php echo SERVER_TIME; ?> <?php echo date('H:i:s', $now); ?></p>
</div>

<p><?php echo RND_WAIT_WORLD_RUNS; ?></p>

<?php
// global chat, embedded in the page (the in-game pages have the floating button)
$gchatInline = true;
include("Templates/GlobalChat/widget.tpl");
?>

<?php
$wpReports  = (int) $database->getUnreadNoticesCount($session->uid);
$wpMessages = (int) $database->getUnreadMessagesCount($session->uid);
$wpNew      = function ($n) { return $n > 0 ? ' <span class="rnd-new">(' . sprintf(RND_WAIT_NEW, $n) . ')</span>' : ''; };
?>
<div class="rnd-box" id="rnd_wait_meanwhile">
	<h3><?php echo RND_WAIT_MEANWHILE; ?></h3>
	<ul>
		<li><a href="berichte.php"><?php echo RND_WAIT_REPORTS; ?></a><?php echo $wpNew($wpReports); ?></li>
		<li><a href="nachrichten.php"><?php echo RND_WAIT_MESSAGES; ?></a><?php echo $wpNew($wpMessages); ?></li>
<?php if ((int) $session->alliance > 0) { ?>
		<li><a href="allianz.php?s=6"><?php echo RND_WAIT_ALLY_CHAT; ?></a></li>
<?php } ?>
		<li><?php echo RND_WAIT_GLOBAL_CHAT; ?></li>
	</ul>
	<p class="rnd-muted"><?php echo RND_WAIT_READ_ONLY; ?></p>
</div>

<p class="rnd-center"><a href="guide.php"><?php echo RND_INFO_READ_GUIDE; ?></a> &nbsp;|&nbsp; <a href="logout.php"><?php echo LOGOUT; ?></a></p>
<?php
include("Templates/Round/outgame_bottom.tpl");
?>
