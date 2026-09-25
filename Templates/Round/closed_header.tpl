<?php
// Play window closed: the reports page becomes the waiting page (global chat with
// the countdown to the next session on top, then the reports list).
$rndNext = RoundControl::nextPlayStart(time());
$rndLeft = max(0, $rndNext - time());
$gchatInline = true;
$gchatHeadRight = RND_CLOSED_SESSION_IN . ' <b class="rnd-countdown" id="rnd_countdown" data-left="' . $rndLeft
    . '" data-done-url="dorf1.php">' . RoundControl::countdownText($rndLeft) . '</b>';
?>
<div id="rnd_closed">
<?php include("Templates/GlobalChat/widget.tpl"); ?>
<p class="rnd-closed-note"><?php echo sprintf(RND_CLOSED_NEXT, '<b id="rnd_next_open">' . RoundControl::fmt($rndNext) . '</b>',
    htmlspecialchars(RoundControl::windowLabel() . ', ' . date_default_timezone_get(), ENT_QUOTES, 'UTF-8')); ?><br />
<?php echo RND_WAIT_READ_ONLY; ?>
<?php if ((int) $session->alliance > 0) { ?> <a href="allianz.php?s=6"><?php echo RND_WAIT_ALLY_CHAT; ?></a><?php } ?></p>
<h1><?php echo RND_CLOSED_REPORTS_TITLE; ?></h1>
</div>
<style>#rnd_closed .rnd-closed-note { color: #777; font-size: 11px; margin: 0 0 14px; } #rnd_closed h1 { font-size: 18px; }</style>
<?php include("Templates/Round/countdown_js.tpl"); ?>
