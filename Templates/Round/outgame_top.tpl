<?php
#################################################################################
##  outgame_top.tpl - shared head + navigation of the round pages              ##
##  (guide.php, playwindow.php, results.php), same layout as spielregeln.php.  ##
##  Expects $rndPageTitle (plain text).                                        ##
#################################################################################
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php echo htmlspecialchars(SERVER_NAME . ' - ' . $rndPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="shortcut icon" href="favicon.ico"/>
	<link rel="stylesheet" type="text/css" href="img/tutorial/main.css"/>
	<link rel="stylesheet" type="text/css" href="img/tutorial/flaggs.css"/>
	<meta name="content-language" content="en"/>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta http-equiv="imagetoolbar" content="no"/>
	<script src="mt-core.js" type="text/javascript"></script>
	<script src="new.js" type="text/javascript"></script>
	<style type="text/css" media="screen">
	body.contentPage #content .rnd-box {margin: 15px 160px; padding: 10px 14px; background: #f5f9ec; border: 1px solid #c9dfa5; border-radius: 4px;}
	body.contentPage #content .rnd-box p {margin: 4px 0;}
	body.contentPage #content .rnd-big {font-size: 18px; font-weight: bold; color: #2A720B;}
	body.contentPage #content .rnd-count {font-size: 22px; font-weight: bold; color: #333; font-family: Verdana, Arial, sans-serif;}
	body.contentPage #content .rnd-center {text-align: center;}
	body.contentPage #content ul.rnd-list {margin: 5px 160px 15px 180px; padding: 0;}
	body.contentPage #content ul.rnd-list li {line-height: 19px; margin-bottom: 3px;}
	body.contentPage #content table.rnd-table {border-collapse: collapse; width: 520px;}
	body.contentPage #content table.rnd-table th, body.contentPage #content table.rnd-table td {border: 1px solid #c0c0c0; padding: 3px 6px; font-size: 12px;}
	body.contentPage #content table.rnd-table th {background: #f5f5f5; text-align: left;}
	body.contentPage #content table.rnd-table td.num, body.contentPage #content table.rnd-table th.num {text-align: center;}
	body.contentPage #content table.rnd-table tr.rnd-first td {background: #fdf6d8; font-weight: bold;}
	body.contentPage #content .rnd-muted {color: #777; font-size: 11px;}
	body.contentPage #content .rnd-new {color: #c00; font-weight: bold;}
	body.contentPage #content #rnd_wait_meanwhile ul {margin: 6px 0 6px 20px; padding: 0;}
	body.contentPage #content #rnd_wait_meanwhile li {margin: 3px 0;}
	</style>
</head>
<body class="webkit contentPage">
<div class="wrapper">
<div id="country_select">
</div>
<div id="header">
	<h1><?php echo PUBLIC_WELCOME_TO; ?> <?php echo SERVER_NAME; ?></h1>
</div>

<div id="navigation">
<a href="index.php" class="home"><img src="img/x.gif" alt="Travian"/></a>
	<table class="menu">
	<tr>
		<td><a href="anleitung.php"><span><?php echo PUBLIC_MANUAL; ?></span></a></td>
		<td><a href="guide.php"><span><?php echo RND_GUIDE; ?></span></a></td>
<?php if (!empty($rndLoggedIn)) { ?>
		<td><a href="logout.php"><span><?php echo LOGOUT; ?></span></a></td>
<?php } else { ?>
		<td><a href="index.php?signup"><span><?php echo PUBLIC_REGISTER; ?></span></a></td>
		<td><a href="index.php?login"><span><?php echo LOGIN; ?></span></a></td>
<?php } ?>
	</tr>
	</table>
</div>

<div id="content">
	<div class="grit">
