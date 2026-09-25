<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : guide.php                                                 ##
##  Type           : One-page server guide (round rules, artifact race)        ##
## --------------------------------------------------------------------------- ##
##  Project        : TravianZ (Traviancikas fork)                              ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

use App\Utils\AccessLogger;

if(!file_exists('var/installed') && @opendir('install')) {
	header("Location: install/");
	exit;
}

include_once("GameEngine/config.php");
include_once("GameEngine/Database.php");
include_once("GameEngine/RoundControl.php");
require_once __DIR__ . "/GameEngine/Lang/loader.php";
tz_load_language(LANG);
AccessLogger::logRequest();

$now        = time();
$fmt        = RoundControl::DATE_FMT;
$start      = RoundControl::roundStart();
$end        = RoundControl::roundEnd();
$artifacts  = RoundControl::artifactsDate();
$ended      = RoundControl::isRoundOver($now);
$window     = RoundControl::windowEnabled();
$windowOpen = RoundControl::isWindowOpen($now);
$nextOpen   = RoundControl::nextWindowStart($now);
$cd         = RoundControl::countdownTarget($now);
$nextClose  = RoundControl::nextWindowEnd($now);
$weekly     = (int) RoundControl::get('weekly_gold');
$startGold  = (defined('NEW_FUNCTION_REGISTRATION_GOLD') && NEW_FUNCTION_REGISTRATION_GOLD) ? (int) NEW_FUNCTION_REGISTRATION_GOLD_VALUE : 0;
$quadrant   = RoundControl::fixedQuadrant();
$protection = round(PROTECTION / 3600);
$days       = (int) RoundControl::get('round_days');
$tz         = htmlspecialchars(date_default_timezone_get(), ENT_QUOTES, 'UTF-8');
$wStart     = htmlspecialchars(RoundControl::get('window_start'), ENT_QUOTES, 'UTF-8');
$wEnd       = htmlspecialchars(RoundControl::get('window_end'), ENT_QUOTES, 'UTF-8');

// all weekly gold dates of the round
$goldDates = [];
if ($weekly > 0 && $start) {
	for ($k = 1; ($t = RoundControl::addDays($start, 7 * $k)) < $end; $k++) {
		$goldDates[] = date($fmt, $t);
	}
}

$rndPageTitle = RND_GUIDE;
$rndLoggedIn = RoundControl::hasPlayerSession();
include("Templates/Round/outgame_top.tpl");
?>
<h1><?php echo htmlspecialchars(SERVER_NAME, ENT_QUOTES, 'UTF-8'); ?> - <?php echo RND_GUIDE; ?></h1>

<p>This page explains how this server works. It is a small private world with a fixed end:
the round runs for <b><?php echo $days; ?> days</b>, from <b><?php echo date($fmt, $start); ?></b> to
<b><?php echo date($fmt, $end); ?></b>, and it is decided by an <b>artifact race</b>. All times are server
time (<?php echo $tz; ?>).</p>

<h2 id="window">1. The daily play window</h2>
<?php if ($window) { ?>
<p>You can only play during the daily play window: <b><?php echo $wStart; ?> - <?php echo $wEnd; ?></b>.
Outside of it the game pages are closed for players (you will see a waiting page), but <b>the world keeps running</b>:
troops march and fight, buildings and training finish and your villages keep producing resources.</p>
<div class="rnd-box rnd-center" id="rnd_window_box">
<?php     if ($ended || !$cd) { ?>
	<p class="rnd-big">The round is over - see the <a href="results.php">results</a>.</p>
<?php     } elseif ($cd['mode'] === 'closes') { ?>
	<p class="rnd-big">The play window is open now!</p>
	<p>It closes at <b><?php echo date('H:i', $cd['at']); ?></b>, in</p>
	<p class="rnd-count"><span class="rnd-countdown" data-left="<?php echo (int) ($cd['at'] - $now); ?>" data-done-url="guide.php#window"></span></p>
<?php     } elseif ($cd['mode'] === 'end') { ?>
	<p>There is no play window left before the round end on <b><?php echo date($fmt, $cd['at']); ?></b>, in</p>
	<p class="rnd-count"><span class="rnd-countdown" data-left="<?php echo (int) ($cd['at'] - $now); ?>" data-done-url="guide.php#window"></span></p>
<?php     } else { ?>
<?php         if ($cd['mode'] === 'first') { ?>
	<p id="rnd_guide_prestart">The round has not started yet: it starts on <b><?php echo date($fmt, $start); ?></b>.</p>
<?php         } ?>
	<p><?php echo $cd['mode'] === 'first' ? 'The first play window opens on' : 'The next play window opens on'; ?> <b id="rnd_next_open"><?php echo date($fmt, $cd['at']); ?></b>, in</p>
	<p class="rnd-count"><span class="rnd-countdown" data-left="<?php echo (int) ($cd['at'] - $now); ?>" data-done-url="guide.php#window"></span></p>
<?php     } ?>
</div>
<?php } else { ?>
<p>There is currently no play window: the game is open around the clock.</p>
<?php } ?>

<h2 id="artifacts">2. The artifact race</h2>
<p>Artifacts are treasures guarded by the Natars. They give their holder a bonus (faster building, faster
troops, bigger storage, cheaper crop, ...) and on this server they also decide who wins the round.
They appear on <b><?php echo date($fmt, $artifacts); ?></b> in Natar villages spread over the map.</p>
<ul class="rnd-list">
	<li><b>Sizes:</b> small artifacts affect one village, great artifacts your whole account, unique artifacts are the strongest.</li>
	<li><b>Treasury:</b> to hold a small artifact your village needs a Treasury of level 10; great and unique artifacts need level 20.
		The Treasury must be empty - one artifact per village.</li>
	<li><b>How to capture one:</b> send a <b>normal attack</b> (not a raid) with your <b>hero</b> from a village whose Treasury is
		empty and high enough. The defenders have to be defeated and the Treasury of the artifact village must be destroyed
		(bring catapults aimed at it, in the same or an earlier attack). If your hero survives, he takes the artifact home.</li>
	<li><b>Limits:</b> an account can hold at most 3 artifacts, and at most one of them can be great or unique.</li>
	<li><b>They can be stolen back:</b> other players capture artifacts from you exactly the same way, so defend the villages that hold them.</li>
</ul>
<div class="rnd-box">
	<p><b>Scoring:</b> small artifact = <b>1 point</b>, great artifact = <b>2 points</b>, unique artifact = <b>3 points</b>.</p>
	<p>At the round end (<b><?php echo date($fmt, $end); ?></b>) the artifact holders are frozen. The player with the highest
		score wins; alliances are ranked by the total score of their members. Population is shown as secondary information.
		The holders are not published while the round runs; the <a href="results.php">results</a> appear right after the end.
		The game processes arriving troops once a minute, so <b>an attack must land at least one minute before the end</b>
		for its capture to count.</p>
	<p><?php echo RND_RESULTS_TIEBREAK; ?></p>
</div>

<h2 id="dates">3. Key dates</h2>
<table class="rnd-table">
	<tr><th>Round start</th><td><?php echo date($fmt, $start); ?></td></tr>
	<tr><th>Artifacts appear</th><td><?php echo date($fmt, $artifacts); ?></td></tr>
<?php if ($goldDates) { ?>
	<tr><th>Weekly gold</th><td><?php echo implode('<br />', $goldDates); ?></td></tr>
<?php } ?>
	<tr><th>Round end</th><td><?php echo date($fmt, $end); ?></td></tr>
</table>

<h2 id="gold">4. Gold</h2>
<ul class="rnd-list">
	<li>Every new account starts with <b><?php echo $startGold; ?> gold</b>.</li>
<?php if ($weekly > 0) { ?>
	<li>Every 7 days (counted from the round start) every player who played during the previous 7 days receives
		<b><?php echo $weekly; ?> gold</b>, together with an in-game message.</li>
<?php } else { ?>
	<li>There is no weekly gold bonus at the moment.</li>
<?php } ?>
	<li>Use gold for Travian Plus, the resource bonuses, the NPC merchant or to finish constructions instantly.</li>
</ul>

<h2 id="world">5. Speed and world</h2>
<table class="rnd-table">
	<tr><th>Game speed</th><td><?php echo SPEED; ?>x</td></tr>
	<tr><th>Troop speed</th><td><?php echo INCREASE_SPEED; ?>x</td></tr>
	<tr><th>Trader capacity</th><td><?php echo TRADER_CAPACITY; ?>x</td></tr>
	<tr><th>Map</th><td><?php echo sprintf(RND_INFO_MAP_SIZE_VALUE, (int) WORLD_MAX, 2 * (int) WORLD_MAX + 1); ?></td></tr>
	<tr><th>Starting region</th><td><?php echo $quadrant ? 'Everybody starts in the ' . RoundControl::QUADRANTS[$quadrant] . ' region' : 'you choose it when you register'; ?></td></tr>
	<tr><th>Beginner protection</th><td><?php echo $protection; ?> hours</td></tr>
</table>

<h2 id="tips">6. Your first sessions</h2>
<ul class="rnd-list">
	<li><b>Queue long builds right before the window closes.</b> Whatever you start finishes while you are away, so use the
		last minutes of every session for the longest constructions and training orders.</li>
	<li><b>Your troops train and your buildings finish while you are away</b> - plan your session so that the next queue is ready to go.</li>
	<li><b>Build crannies.</b> Attacks can land while nobody can log in; crannies hide part of your resources from raiders.</li>
	<li><b>Beginner protection</b> lasts <?php echo $protection; ?> hours after the start (or after you register). Use it to grow your resource fields.</li>
	<li><b>Prepare for the artifacts:</b> a Main Building of level 10 unlocks the Treasury; start it early so it reaches level 10 (or 20) when the artifacts appear.</li>
	<li><b>Team up.</b> Alliances are ranked too, and holding artifacts is much easier with friends who send reinforcements.</li>
</ul>

<h2 id="fair">7. Fair play</h2>
<p>One account per player. Sitting, pushing and multi-accounts are handled as described in the <a href="spielregeln.php">game rules</a>.</p>
<?php
include("Templates/Round/outgame_bottom.tpl");
?>
