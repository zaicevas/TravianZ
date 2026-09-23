<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : results.php                                               ##
##  Type           : Artifact race results (fixed-length round)                ##
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

$now     = time();
$ended   = RoundControl::isRoundOver($now);
$roundEnd = RoundControl::roundEnd();

// Only the automation takes the snapshot (first tick after the end, before
// troop movements are processed). Nothing is shown before it exists: while
// the round runs the artifact holders stay secret, and right after the end
// the page says the results are being finalised.
$snapshot  = $ended ? RoundControl::getSnapshot() : null;
$standings = $snapshot !== null ? $snapshot : ['artifacts' => [], 'population' => []];
$ranking   = RoundControl::rank($standings);

$unscored = 0;
foreach ($standings['artifacts'] as $art) {
	if (!RoundControl::isScored($art)) {
		$unscored++;
	}
}

$sizeNames = [1 => RND_RESULTS_SMALL, 2 => RND_RESULTS_GREAT, 3 => RND_RESULTS_UNIQUE];

function rnd_h($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$rndPageTitle = $ended ? RND_RESULTS_TITLE : RND_RESULTS_LIVE_TITLE;
$rndLoggedIn = RoundControl::hasPlayerSession();
include("Templates/Round/outgame_top.tpl");
?>
<h1><?php echo rnd_h($rndPageTitle); ?></h1>

<div class="rnd-box">
<?php if (!$ended) { ?>
	<p id="rnd_in_progress"><?php echo sprintf(RND_RESULTS_RUNNING, '<b>' . date('d.m.Y H:i', $roundEnd) . '</b>'); ?>
	(<span class="rnd-countdown" data-left="<?php echo (int) max(0, $roundEnd - $now); ?>" data-done-url="results.php"></span>)</p>
<?php } elseif ($snapshot === null) { ?>
	<p id="rnd_finalising"><?php echo sprintf(RND_RESULTS_FINALISING, '<b>' . date('d.m.Y H:i', $roundEnd) . '</b>'); ?></p>
<?php } else { ?>
	<p><?php echo sprintf(RND_RESULTS_ENDED, '<b>' . date('d.m.Y H:i', $roundEnd) . '</b>'); ?></p>
<?php     if (!empty($ranking['players'])) { ?>
	<p class="rnd-big" id="rnd_winner"><?php echo sprintf(RND_RESULTS_WINNER, rnd_h($ranking['players'][0]['username'])); ?></p>
<?php     } ?>
<?php     if (!empty($ranking['alliances'])) { ?>
	<p><b><?php echo sprintf(RND_RESULTS_WINNER_ALLY, rnd_h($ranking['alliances'][0]['tag'])); ?></b></p>
<?php     } ?>
<?php } ?>
	<p><?php echo RND_RESULTS_SCORING; ?></p>
	<p id="rnd_tiebreak"><?php echo RND_RESULTS_TIEBREAK; ?></p>
</div>

<?php if ($snapshot !== null) { ?>
<h2><?php echo RND_RESULTS_PLAYERS; ?></h2>
<?php if (empty($ranking['players'])) { ?>
<p><?php echo RND_RESULTS_NONE; ?></p>
<?php } else { ?>
<table class="rnd-table" id="rnd_players">
	<tr>
		<th class="num"><?php echo RND_RESULTS_RANK; ?></th>
		<th><?php echo RND_RESULTS_PLAYER; ?></th>
		<th><?php echo RND_RESULTS_ALLIANCE; ?></th>
		<th class="num"><?php echo RND_RESULTS_SMALL; ?></th>
		<th class="num"><?php echo RND_RESULTS_GREAT; ?></th>
		<th class="num"><?php echo RND_RESULTS_UNIQUE; ?></th>
		<th class="num"><?php echo RND_RESULTS_SCORE; ?></th>
	</tr>
<?php     foreach ($ranking['players'] as $i => $player) { ?>
	<tr<?php echo $i === 0 ? ' class="rnd-first"' : ''; ?>>
		<td class="num"><?php echo $i + 1; ?>.</td>
		<td class="rnd-player"><?php echo rnd_h($player['username']); ?></td>
		<td><?php echo rnd_h($player['alliance_tag'] !== '' ? $player['alliance_tag'] : '-'); ?></td>
		<td class="num"><?php echo (int) $player['counts'][1]; ?></td>
		<td class="num"><?php echo (int) $player['counts'][2]; ?></td>
		<td class="num"><?php echo (int) $player['counts'][3]; ?></td>
		<td class="num rnd-score"><?php echo (int) $player['score']; ?></td>
	</tr>
<?php     } ?>
</table>

<h3><?php echo RND_RESULTS_ARTIFACTS; ?></h3>
<table class="rnd-table" id="rnd_artifacts">
	<tr>
		<th><?php echo RND_RESULTS_ARTIFACTS; ?></th>
		<th class="num"><?php echo RND_RESULTS_SIZE; ?></th>
		<th><?php echo RND_RESULTS_HOLDER; ?></th>
		<th><?php echo RND_RESULTS_VILLAGE; ?></th>
	</tr>
<?php     foreach ($ranking['players'] as $player) { ?>
<?php         foreach ($player['artifacts'] as $art) { ?>
	<tr>
		<td><?php echo rnd_h($art['name']); ?></td>
		<td class="num"><?php echo rnd_h($sizeNames[(int) $art['size']] ?? '?'); ?></td>
		<td><?php echo rnd_h($player['username']); ?></td>
		<td><?php echo rnd_h($art['village']); ?> (<?php echo (int) $art['x']; ?>|<?php echo (int) $art['y']; ?>)</td>
	</tr>
<?php         } ?>
<?php     } ?>
</table>
<?php } ?>
<?php if ($unscored > 0) { ?>
<p class="rnd-muted" id="rnd_unscored"><?php echo sprintf(RND_RESULTS_NATARS, $unscored); ?></p>
<?php } ?>

<?php if (!empty($ranking['alliances'])) { ?>
<h2><?php echo RND_RESULTS_ALLIANCES; ?></h2>
<table class="rnd-table" id="rnd_alliances">
	<tr>
		<th class="num"><?php echo RND_RESULTS_RANK; ?></th>
		<th><?php echo RND_RESULTS_ALLIANCE; ?></th>
		<th><?php echo RND_RESULTS_MEMBERS; ?></th>
		<th class="num"><?php echo RND_RESULTS_SMALL; ?></th>
		<th class="num"><?php echo RND_RESULTS_GREAT; ?></th>
		<th class="num"><?php echo RND_RESULTS_UNIQUE; ?></th>
		<th class="num"><?php echo RND_RESULTS_SCORE; ?></th>
	</tr>
<?php     foreach ($ranking['alliances'] as $i => $ally) { ?>
	<tr<?php echo $i === 0 ? ' class="rnd-first"' : ''; ?>>
		<td class="num"><?php echo $i + 1; ?>.</td>
		<td><?php echo rnd_h($ally['tag']); ?></td>
		<td><?php echo rnd_h(implode(', ', $ally['members'])); ?></td>
		<td class="num"><?php echo (int) $ally['counts'][1]; ?></td>
		<td class="num"><?php echo (int) $ally['counts'][2]; ?></td>
		<td class="num"><?php echo (int) $ally['counts'][3]; ?></td>
		<td class="num"><?php echo (int) $ally['score']; ?></td>
	</tr>
<?php     } ?>
</table>
<?php } ?>

<?php if (!empty($standings['population'])) { ?>
<h2><?php echo RND_RESULTS_POPULATION; ?></h2>
<table class="rnd-table" id="rnd_population">
	<tr>
		<th class="num"><?php echo RND_RESULTS_RANK; ?></th>
		<th><?php echo RND_RESULTS_PLAYER; ?></th>
		<th><?php echo RND_RESULTS_ALLIANCE; ?></th>
		<th class="num"><?php echo RND_RESULTS_VILLAGES; ?></th>
		<th class="num"><?php echo RND_RESULTS_POP; ?></th>
	</tr>
<?php     foreach ($standings['population'] as $i => $row) { ?>
	<tr>
		<td class="num"><?php echo $i + 1; ?>.</td>
		<td><?php echo rnd_h($row['username']); ?></td>
		<td><?php echo rnd_h($row['alliance_tag'] !== '' ? $row['alliance_tag'] : '-'); ?></td>
		<td class="num"><?php echo (int) $row['villages']; ?></td>
		<td class="num"><?php echo (int) $row['pop']; ?></td>
	</tr>
<?php     } ?>
</table>
<?php } ?>

<p class="rnd-muted"><?php echo sprintf(RND_RESULTS_SNAPSHOT, date('d.m.Y H:i', (int) $snapshot['taken_at'])); ?></p>
<?php } // snapshot ?>
<?php if ($ended) { ?>
<p><?php echo RND_RESULTS_VIEW_ONLY; ?>
	<a href="karte.php"><?php echo MAP; ?></a> |
	<a href="statistiken.php"><?php echo STATISTICS; ?></a> |
	<a href="berichte.php"><?php echo REPORTS; ?></a> |
	<a href="nachrichten.php"><?php echo MESSAGES; ?></a> |
	<a href="logout.php"><?php echo LOGOUT; ?></a></p>
<?php } ?>
<?php
include("Templates/Round/outgame_bottom.tpl");
?>
