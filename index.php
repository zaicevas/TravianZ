<?php
use App\Utils\AccessLogger;

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       : index.php                      	                       ##
##  Type           : In Game Index Page                                        ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Dzoki 						                               ##
##  Refactored by  : Shadow                                                    ##
##  Redesign by    : Shadow                                                    ##
## --------------------------------------------------------------------------- ##
##  Contact        : cata7007@gmail.com                                        ##
##  Project        : TravianZ                                                  ##
##  URLs:          : https://travianz.org                                      ##
##  GitHub         : https://github.com/Shadowss/TravianZ                      ##
## --------------------------------------------------------------------------- ##
##  License        : TravianZ Project                                          ##
##  Copyright      : TravianZ (c) 2010-2026. All rights reserved.              ##
## --------------------------------------------------------------------------- ##
#################################################################################

if(!file_exists('var/installed') && @opendir('install')) {
    header("Location: install/");
    exit;
}

include_once("GameEngine/config.php");
/*
if($_SERVER['HTTP_HOST'] != '.SERVER.')
{
    header('location: '.SERVER.'');
    exit;
}
*/

// delete the /* and the */ if you not use localhost.

error_reporting(E_ALL || E_NOTICE);

if(file_exists('Security/Security.class.php'))
{
    require 'Security/Security.class.php';
    Security::instance();
}
else
{
    die('Security: Please activate security class!');
}

include_once "GameEngine/Database.php";
include_once "GameEngine/RoundControl.php";
require_once __DIR__ . "/GameEngine/Lang/loader.php";
tz_load_language(LANG);

AccessLogger::logRequest();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo SERVER_NAME; ?></title>
	<link rel="shortcut icon" href="favicon.ico" />
	<link rel="stylesheet" type="text/css" href="gpack/travian/main.css" />
	<link rel="stylesheet" type="text/css" href="gpack/travian/flaggs.css" />
	<link rel="stylesheet" type="text/css" href="gpack/travian/main_en.css" />
	<meta name="content-language" content="<?php echo LANG; ?>" />
	<meta http-equiv="imagetoolbar" content="no" />
	<script src="mt-core.js" type="text/javascript"></script>
	<script src="new.js?22102017" type="text/javascript"></script>
	<script src="new2.js?22102017" type="text/javascript"></script>
	<style type="text/css">
		<!-- li.c4 {background-image:url('img/en/welten/en1_big.jpg');} -->
		<!-- li.c3 {background-image:url('img/en/welten/en1_big_g.jpg');} -->
		div.c2 {left:237px;}
		ul.c1 {position:absolute; left:0px; width: 686px;}
		#server_info {padding-top:10px; padding-bottom:6px;}
		#server_info h2 {font-size:15px; line-height:20px; margin-bottom:5px;}
		#server_info table {border-spacing:0; width:100%;}
		#server_info th, #server_info td {padding:1px 0; font-size:11px; line-height:16px; vertical-align:top;}
		#server_info th {text-align:left; font-weight:normal; color:#555; width:48%;}
		#server_info td {font-weight:bold; color:#333;}
		#server_info .sep th {padding-top:6px; color:#71d000; font-weight:bold;}
		#server_info p {margin:6px 0 0; font-size:11px;}
		#rnd_players {background:#efefef; padding:14px 15px 12px;}
		#rnd_players h2 {color:#71d000; font-size:15px; font-weight:bold; margin:0 0 8px; text-align:center;}
		#rnd_players table {background:#fff; border-collapse:collapse; width:100%;}
		#rnd_players th, #rnd_players td {border:1px solid #c8c8c8; font-size:11px; line-height:16px; padding:2px 6px;}
		#rnd_players th {background:#f7f7f7; font-weight:normal; text-align:center;}
		#rnd_players td.rp-num {text-align:center; white-space:nowrap;}
		#rnd_players tfoot td {background:#f7f7f7; color:#444; text-align:center;}
		#rnd_players .rp-none {color:#555; font-size:11px; text-align:center;}
		#rnd_key_dates {background:#fff8e1; border:2px solid #e2b33c; border-radius:8px; margin:0 0 12px; padding:10px 14px 8px; box-shadow:0 1px 4px rgba(0,0,0,.15);}
		#rnd_key_dates .kd-block {display:inline-block; vertical-align:top; width:49%;}
		#rnd_key_dates .kd-label {color:#71a000; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px;}
		#rnd_key_dates .kd-big {color:#9c0f19; font-size:19px; font-weight:bold; line-height:26px; white-space:nowrap;}
		#rnd_key_dates .kd-sub {color:#555; font-size:11px; line-height:14px; min-height:14px;}
		#rnd_key_dates .kd-open {background:#71d000; border-radius:3px; color:#fff; font-weight:bold; padding:0 4px;}
		#rnd_key_dates .kd-more {border-spacing:0; border-top:1px solid #ecd9a0; margin-top:7px; padding-top:5px; width:100%;}
		#rnd_key_dates .kd-more th {color:#555; font-size:11px; font-weight:normal; text-align:left; width:48%;}
		#rnd_key_dates .kd-more td {color:#333; font-size:11px; font-weight:bold;}
	</style>
</head>

<body class="presto indexPage">
	<div class="wrapper">
		<div id="country_select">
			<div id="flags"></div>
			<script src="flaggen.js?a" type="text/javascript"></script>
			<script type="text/javascript">
			var region_list = new Array('Europe','America','Asia','Middle East','Africa','Oceania');
			show_flags('', '', region_list);
			</script>
		</div>
		<div id="header"><h1><?php echo $lang['index'][0][1]; ?></h1></div>
		<div id="navigation">
			<a href="index.php" class="home"><img src="img/x.gif" alt="Travian" /></a>
			<table class="menu">
				<tr>
					<td><a href="anleitung.php"><span><?php echo $lang['index'][0][2]; ?></span></a></td>
					<td><a href="guide.php"><span><?php echo RND_GUIDE; ?></span></a></td>
					<td><a href="?signup" class="signup_link mark"><span><?php echo $lang['register']; ?></span></a></td>
					<td><a href="?login" class="login_link"><span><?php echo LOGIN; ?></span></a></td>
				</tr>
			</table>
		</div>
		<?php
		if(T4_COMING==true){
		?>
		<div id="t4play">
		<a href="notification/">
		<img src="img/t4n/Teaser_Prelandingpage_EN.png" alt="Travian 4" />
		</a>
		</div>
		<?php } ?>
		<div id="register_now">
			<a href="?signup" class="signup_link"><?php echo $lang['register']; ?></a>
			<span><?php echo PLAY_NOW; ?></span>
		</div>
		<div id="content">
			<div class="grit">
				<div class="infobox">
					<div id="what_is_travian">
						<h2><?php echo $lang['index'][0][4]; ?></h2>
						<p><?php echo $lang['index'][0][5]; ?></p>
						<p class="play_now"><a href="?signup" class="signup_link"><?php echo $lang['index'][0][6]; ?></a></p>
					</div>
					<div id="player_counter">
						<table>
							<tbody>
								<tr>
									<th><?php

										   echo $lang['index'][0][7];

									?>:</th>

									<td><?php

											$return = mysqli_query($link, "SELECT Count(*) as Total FROM " . TB_PREFIX . "users WHERE tribe IN(1, 2, 3, 6, 7, 8, 9)");
											echo ($users = !empty($return) ? mysqli_fetch_assoc($return)['Total'] : 0);
									?></td>
								</tr>

								<tr>
									<th><?php

										   echo $lang['index'][0][8];

									?>:</th>

									<td><?php

									       $return = mysqli_query($link,"SELECT Count(*) as Total FROM " . TB_PREFIX . "users WHERE timestamp > ".(time() - (3600*24))." AND tribe IN(1, 2, 3, 6, 7, 8, 9)");
									       echo !empty($return) ? mysqli_fetch_assoc($return)['Total'] : 0;

									?></td>
								</tr>

								<tr>
									<th><?php

										   echo $lang['index'][0][9];

									?>:</th>

									<td><?php

										   $return = mysqli_query($link,"SELECT Count(*) as Total FROM " . TB_PREFIX . "users WHERE timestamp > ".(time() - (60*10))." AND tribe IN(1, 2, 3, 6, 7, 8, 9)");
										   echo ($online = !empty($return) ? mysqli_fetch_assoc($return)['Total'] : 0);

									?></td>
								</tr>
							</tbody>
						</table>
					</div>
					<?php
					// Server information box (round settings from RoundControl, speeds from config.php)
					$rcStats = ['villages' => 0, 'pop' => 0];
					$return = mysqli_query($link, "SELECT COUNT(v.wref) AS villages, IFNULL(SUM(v.pop), 0) AS pop FROM " . TB_PREFIX . "vdata v JOIN " . TB_PREFIX . "users u ON u.id = v.owner WHERE u.id > 5 AND u.access > 0 AND u.access < " . ((defined('INCLUDE_ADMIN') && INCLUDE_ADMIN) ? 10 : RoundControl::STAFF_ACCESS) . " AND u.tribe IN(1, 2, 3, 6, 7, 8, 9)");
					if (!empty($return)) {
						$rcStats = mysqli_fetch_assoc($return);
					}
					$rcGoldStart  = (defined('NEW_FUNCTION_REGISTRATION_GOLD') && NEW_FUNCTION_REGISTRATION_GOLD) ? (int) NEW_FUNCTION_REGISTRATION_GOLD_VALUE : 0;
					$rcGoldWeekly = (int) RoundControl::get('weekly_gold');
					$rcQuadrant   = RoundControl::fixedQuadrant();
					$rpList       = RoundControl::registeredPlayers();
					$rcTribes     = [1 => 0, 2 => 0, 3 => 0];
					foreach ($rpList as $rp) {
						$rcTribes[$rp['tribe']] = ($rcTribes[$rp['tribe']] ?? 0) + 1;
					}
					ksort($rcTribes);
					$rcRows = [
						[RND_INFO_SPEED_HEAD, null],
						[RND_INFO_GAME_SPEED, SPEED . 'x'],
						[RND_INFO_TROOP_SPEED, INCREASE_SPEED . 'x'],
						[RND_INFO_TRADER_CAPACITY, TRADER_CAPACITY . 'x'],
						[RND_INFO_WORLD_HEAD, null],
						[RND_INFO_MAP_SIZE, sprintf(RND_INFO_MAP_SIZE_VALUE, (int) WORLD_MAX, 2 * (int) WORLD_MAX + 1)],
						[RND_INFO_START_REGION, $rcQuadrant ? RoundControl::QUADRANTS[$rcQuadrant] : RND_INFO_START_REGION_FREE],
						[RND_INFO_PROTECTION, round(PROTECTION / 3600) . ' ' . RND_HOURS],
						[RND_INFO_GOLD_HEAD, null],
						[RND_INFO_START_GOLD, $rcGoldStart],
						[RND_INFO_WEEKLY_GOLD, $rcGoldWeekly > 0 ? sprintf(RND_INFO_WEEKLY_GOLD_VALUE, $rcGoldWeekly) : RND_OFF],
						[RND_INFO_STATS_HEAD, null],
						[RND_INFO_VILLAGES, (int) $rcStats['villages']],
						[RND_INFO_POPULATION, number_format((int) $rcStats['pop'])],
						[RND_INFO_ATTACKS_TODAY, RoundControl::attacksSince(mktime(0, 0, 0))],
						[RND_INFO_TRIBES_HEAD, null],
					];
					foreach ($rcTribes as $rcTribe => $rcN) {
						$rcRows[] = [defined('TRIBE' . $rcTribe) ? constant('TRIBE' . $rcTribe) : '?',
							sprintf(RND_INFO_TRIBE_VALUE, $rcN, $rpList ? number_format(100 * $rcN / count($rpList), 1) : '0')];
					}
					?>
					<div id="server_info">
						<h2><?php echo RND_INFO_TITLE; ?></h2>
						<table>
							<tbody>
<?php foreach ($rcRows as $rcRow) { ?>
<?php     if ($rcRow[1] === null) { ?>
								<tr class="sep"><th colspan="2"><?php echo htmlspecialchars($rcRow[0], ENT_QUOTES, 'UTF-8'); ?></th></tr>
<?php     } else { ?>
								<tr><th><?php echo htmlspecialchars($rcRow[0], ENT_QUOTES, 'UTF-8'); ?>:</th><td><?php echo htmlspecialchars((string) $rcRow[1], ENT_QUOTES, 'UTF-8'); ?></td></tr>
<?php     } ?>
<?php } ?>
							</tbody>
						</table>
						<p><a href="guide.php"><?php echo RND_INFO_READ_GUIDE; ?></a><?php if (RoundControl::isRoundOver()) { ?> | <a href="results.php"><?php echo RND_RESULTS_TITLE; ?></a><?php } ?></p>
					</div>
					<div id="about_the_game">
						<h2><?php echo $lang['index'][0][10]; ?>:</h2>
						<ul>
							<li><?php echo $lang['index'][0][11]; ?></li>
							<li><?php echo $lang['index'][0][12]; ?></li>
							<li><?php echo $lang['index'][0][13]; ?></li>
						</ul>
					</div>
				</div>
				<div class="secondarybox">
					<?php
					// Key dates panel: round start and play window first, the rest below
					$kdNow     = time();
					$kdStart   = RoundControl::roundStart();
					$kdEnd     = RoundControl::roundEnd();
					$kdStarted = $kdStart && $kdNow >= $kdStart;
					$kdEnded   = RoundControl::isRoundOver($kdNow);
					if ($kdEnded) {
						$kdLabel = RND_KEY_ENDED;
						$kdSub   = '<a href="results.php">' . RND_RESULTS_TITLE . '</a>';
					} elseif ($kdStarted) {
						$kdLabel = RND_KEY_STARTED;
						$kdSub   = htmlspecialchars(sprintf(RND_KEY_RUNNING, RoundControl::durationText($kdEnd - $kdNow)), ENT_QUOTES, 'UTF-8');
					} else {
						$kdLabel = RND_KEY_STARTS;
						$kdSub   = htmlspecialchars(sprintf(RND_KEY_IN, RoundControl::durationText($kdStart - $kdNow)), ENT_QUOTES, 'UTF-8');
					}
					$kdWindow = RoundControl::windowEnabled();
					?>
					<div id="rnd_key_dates">
						<div class="kd-block">
							<div class="kd-label"><?php echo htmlspecialchars($kdLabel, ENT_QUOTES, 'UTF-8'); ?></div>
							<div class="kd-big" id="rnd_key_start"><?php echo RoundControl::fmt($kdStart); ?></div>
							<div class="kd-sub" id="rnd_key_start_sub"><?php echo $kdSub; ?></div>
						</div>
						<div class="kd-block">
							<div class="kd-label"><?php echo RND_KEY_WINDOW; ?></div>
							<div class="kd-big" id="rnd_key_window"><?php echo htmlspecialchars($kdWindow ? str_replace(' - ', ' &ndash; ', RoundControl::windowLabel()) : RND_INFO_ALWAYS_OPEN, ENT_QUOTES, 'UTF-8', false); ?></div>
							<div class="kd-sub"><?php if ($kdWindow) { echo htmlspecialchars(sprintf(RND_KEY_WINDOW_DAILY, date_default_timezone_get()), ENT_QUOTES, 'UTF-8'); if ($kdStarted && !$kdEnded && RoundControl::isWindowOpen($kdNow)) { ?> <span class="kd-open" id="rnd_key_window_open"><?php echo RND_KEY_WINDOW_OPEN; ?></span><?php } } ?></div>
						</div>
						<table class="kd-more">
							<tr><th><?php echo RND_INFO_ARTIFACTS; ?>:</th><td id="rnd_key_artifacts"><?php echo RoundControl::fmt(RoundControl::artifactsDate()); ?></td></tr>
							<tr><th><?php echo RND_INFO_ROUND_END; ?>:</th><td id="rnd_key_end"><?php echo RoundControl::fmt($kdEnd); ?></td></tr>
						</table>
					</div>
					<div id="screenshots">
						<h2><?php echo SCREENSHOTS; ?></h2>
						<a href="#last" class="navi prev dynamic_btn"><img class="dynamic_btn" src="img/x.gif" alt="previous" /></a>
						<div id="screenshots_preview">
							<ul id="screenshot_list" class="c1">
								<li><a href="#"><img src="img/un/s/s1s.jpg" alt="Screenshot" /></a></li>
								<li><a href="#"><img src="img/un/s/s2s.jpg" alt="Screenshot" /></a></li>
								<li><a href="#"><img src="img/un/s/s4s.jpg" alt="Screenshot" /></a></li>
								<li><a href="#"><img src="img/un/s/s3s.jpg" alt="Screenshot" /></a></li>
								<li><a href="#"><img src="img/un/s/s5s.jpg" alt="Screenshot" /></a></li>
								<li><a href="#"><img src="img/un/s/s7s.jpg" alt="Screenshot" /></a></li>
								<li><a href="#"><img src="img/un/s/s8s.jpg" alt="Screenshot" /></a></li>
							</ul>
						</div><a href="#next" class="navi next"><img class="dynamic_btn" src="img/x.gif" alt="next" /></a>
					</div>
					<?php
					$rpCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
					foreach ($rpList as $rp) {
						if ($rp['quadrant']) $rpCounts[$rp['quadrant']]++;
					}
					$rpH = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
					$rpShowQuadrant = !RoundControl::fixedQuadrant();   // everyone starts in the same quadrant otherwise
					?>
					<div id="rnd_players">
						<h2><?php echo RND_PLAYERS_TITLE; ?></h2>
<?php if (!$rpList) { ?>
						<p class="rp-none"><?php echo RND_PLAYERS_NONE; ?></p>
<?php } else { ?>
						<table id="rnd_players_table">
							<thead><tr><th><?php echo RND_PLAYERS_PLAYER; ?></th><th><?php echo RND_PLAYERS_INVITED; ?></th><?php if ($rpShowQuadrant) { ?><th><?php echo RND_PLAYERS_QUADRANT; ?></th><?php } ?></tr></thead>
							<tbody>
<?php     foreach ($rpList as $rp) { ?>
								<tr><td class="rp-name"><?php echo $rpH($rp['username']); ?> (<?php echo $rpH(defined('TRIBE' . $rp['tribe']) ? constant('TRIBE' . $rp['tribe']) : '?'); ?>)</td><td class="rp-num"><?php echo (int) $rp['invited']; ?></td><?php if ($rpShowQuadrant) { ?><td class="rp-num"><?php echo $rp['quadrant'] ? trim(RoundControl::QUADRANTS[$rp['quadrant']], '()') : '-'; ?></td><?php } ?></tr>
<?php     } ?>
							</tbody>
<?php     if ($rpShowQuadrant) { ?>
							<tfoot><tr><td colspan="3" id="rnd_players_quadrants"><?php
								$rpParts = [];
								foreach ($rpCounts as $qid => $n) {
									$rpParts[] = sprintf(RND_PLAYERS_AT, $n, trim(RoundControl::QUADRANTS[$qid], '()'));
								}
								echo $rpH(implode(' · ', $rpParts));
							?></td></tr></tfoot>
<?php     } ?>
						</table>
<?php } ?>
					</div>
					<div id="newsbox">
						<h2><?php echo NEWS; ?></h2>
						<div class="news"><?php include ("Templates/indexnews.tpl"); ?></div>
					</div>
				</div>
			</div>
			<div class="clear"></div>
		</div>
		<div id="footer">
			<div class="container">
				<ul class="menu">
					<li><a href="anleitung.php?s=3"><?php echo FAQ; ?></a>|</li>
					<li><a href="index.php?screenshots"><?php echo SCREENSHOTS; ?></a>|</li>
					<li><a href="spielregeln.php"><?php echo SPIELREGELN; ?></a>|</li>
					<li><a href="agb.php"><?php echo AGB; ?></a>|</li>
					<li><a href="impressum.php"><?php echo IMPRINT; ?></a></li>
					<li class="copyright">&copy; 2011-<?php echo date('Y'); ?> - TravianZ - All rights reserved</li>
				</ul>
			</div>
		</div>
	</div>
	<div id="login_layer" class="overlay">
		<div class="mask closer"></div>
		<div id="login_list" class="overlay_content">
			<h2><?php echo CHOOSE; ?></h2>
			<a href="#" class="closer"><img class="dynamic_img" alt="Close" src="img/un/x.gif" /></a>
			<ul class="world_list">
				<li class="w_big c3" style="background-image:url('img/en/welten/en1_big.jpg');">
					<a href="login.php"><img class="w_button" src="img/un/x.gif" alt="World" title="<?php echo $users; echo "&nbsp;"; echo PLAYERS; echo "&nbsp;|&nbsp;"; echo $active; echo "&nbsp;"; echo ACTIVE; echo "&nbsp;|&nbsp;"; echo $online; echo "&nbsp;"; echo ONLINE; ?>" /></a>
					<div class="label_players c0"><?php echo PLAYERS; ?>:</div>
					<div class="label_online c0"><?php echo ONLINE; ?>:</div>
					<div class="players c1"><?php echo $users; ?></div>
					<div class="online c1"><?php echo $online; ?></div>
				</li>
			</ul>
			<div class="footer"></div>
		</div>
	</div>
	<div id="signup_layer" class="overlay">
		<div class="mask closer"></div>
		<div id="signup_list" class="overlay_content">
			<h2><?php echo CHOOSE; ?></h2>
			<a href="#" class="closer"><img class="dynamic_img" alt="Close" src="img/un/x.gif" /></a>
			<ul class="world_list">
				<li class="w_big c4" style="background-image:url('img/en/welten/en1_big.jpg');">
					<a href="anmelden.php"><img class="w_button" src="img/un/x.gif" alt="World" title="<?php echo $users; echo "&nbsp;"; echo PLAYERS; echo "&nbsp;|&nbsp;"; echo $active; echo "&nbsp;"; echo ACTIVE; echo "&nbsp;|&nbsp;"; echo $online; echo "&nbsp;"; echo ONLINE; ?>" /></a>
					<div class="label_players c0"><?php echo PLAYERS; ?>:</div>
					<div class="label_online c0"><?php echo ONLINE; ?>:</div>
					<div class="players c1"><?php echo $users; ?></div>
					<div class="online c1"><?php echo $online; ?></div>
				</li>
			</ul>
			<div class="footer"></div>
		</div>
	</div>
	<div id="iframe_layer" class="overlay">
		<div class="mask closer"></div>
		<div class="overlay_content">
			<a href="#" class="closer"><img class="dynamic_img" alt="Close" src="img/un/x.gif" /></a>
			<h2><?php echo $lang['index'][0][2]; ?></h2>
			<div id="frame_box"></div>
			<div class="footer"></div>
		</div>
	</div>
	<div id="screenshot_layer" class="overlay">
		<div class="mask closer"></div>
		<div class="overlay_content">
			<h3><?php echo SCREENSHOTS; ?></h3>
			<a href="#" class="closer"><img class="dynamic_img" alt="Close" src="img/x.gif" /></a>
			<div class="screenshot_view">
				<h4 id="screen_hl"></h4>
				<img id="screen_view" src="img/x.gif" alt="Screenshot" name="screen_view" />
				<div id="screen_desc"></div>
			</div>
			<a href="#prev" class="navi prev" onclick="galarie.showPrev();"><img class="dynamic_img" src="img/x.gif" alt="previous" /></a>
			<a href="#next" class="navi next" onclick="galarie.showNext();"><img class="dynamic_img" src="img/x.gif" alt="next" /></a>
			<div class="footer"></div>
		</div>
	</div>
	<script type="text/javascript">
		var screenshots = [
			{'img':'img/en/s/s1.png','hl':"<?php echo $lang['screenshots']['title1']; ?>", 'desc':"<?php echo $lang['screenshots']['desc1']; ?>"},{'img':'img/en/s/s2.png','hl':"<?php echo $lang['screenshots']['title2']; ?>", 'desc':"<?php echo $lang['screenshots']['desc2']; ?>"},{'img':'img/en/s/s4.png','hl':"<?php echo $lang['screenshots']['title3']; ?>", 'desc':"<?php echo $lang['screenshots']['desc3']; ?>"},{'img':'img/en/s/s3.png','hl':"<?php echo $lang['screenshots']['title4']; ?>", 'desc':"<?php echo $lang['screenshots']['desc4']; ?>"},{'img':'img/en/s/s5.png','hl':"<?php echo $lang['screenshots']['title5']; ?>", 'desc':"<?php echo $lang['screenshots']['desc5']; ?>"},{'img':'img/en/s/s7.png','hl':"<?php echo $lang['screenshots']['title6']; ?>", 'desc':"<?php echo $lang['screenshots']['desc6']; ?>"},{'img':'img/en/s/s8.png','hl':"<?php echo $lang['screenshots']['title7']; ?>", 'desc':"<?php echo $lang['screenshots']['desc7']; ?>"}
		];
		var galarie = new Fx.Screenshots('screen_view', 'screen_hl', 'screen_desc', screenshots);
	<?php
	    if (isset($_GET['signup'])) {
	?>
		window.addEvent('domready', function() {
			$$('.signup_link').fireEvent('click');
		});
	<?php
	   }
	?>

	<?php
    	if (isset($_GET['login'])) {
	?>
		window.addEvent('domready', function() {
    		$$('.login_link').fireEvent('click');
    	});
	<?php
	   }
	?>
	</script>
</body>
</html>
