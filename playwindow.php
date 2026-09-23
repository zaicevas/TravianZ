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
if (RoundControl::isWindowOpen() || RoundControl::isStaff($session->access, $session->username)) {
	header("Location: dorf1.php");
	exit;
}

$now         = time();
$nextOpen    = RoundControl::nextWindowStart($now);
$rndPageTitle = RND_WAIT_TITLE;

include("Templates/Round/outgame_top.tpl");
?>
<h1><?php echo RND_WAIT_TITLE; ?></h1>

<p><?php echo sprintf(RND_WAIT_INTRO, htmlspecialchars((string) $session->username, ENT_QUOTES, 'UTF-8')); ?></p>

<div class="rnd-box rnd-center">
	<p class="rnd-big"><?php echo htmlspecialchars(RoundControl::windowLabel(), ENT_QUOTES, 'UTF-8'); ?> <span class="rnd-muted">(<?php echo RND_SERVER_TIME . ', ' . htmlspecialchars(date_default_timezone_get(), ENT_QUOTES, 'UTF-8'); ?>)</span></p>
	<p><?php echo RND_WAIT_NEXT; ?> <b id="rnd_next_open"><?php echo date('d.m.Y H:i', $nextOpen); ?></b> <?php echo RND_WAIT_IN; ?></p>
	<p class="rnd-count"><span class="rnd-countdown" id="rnd_countdown" data-left="<?php echo (int) ($nextOpen - $now); ?>" data-done-url="dorf1.php"><?php echo gmdate('H:i:s', max(0, $nextOpen - $now)); ?></span></p>
	<p class="rnd-muted"><?php echo SERVER_TIME; ?> <?php echo date('H:i:s', $now); ?></p>
</div>

<p><?php echo RND_WAIT_WORLD_RUNS; ?></p>

<p class="rnd-center"><a href="guide.php"><?php echo RND_INFO_READ_GUIDE; ?></a> &nbsp;|&nbsp; <a href="logout.php"><?php echo LOGOUT; ?></a></p>
<?php
include("Templates/Round/outgame_bottom.tpl");
?>
