<?php
// Off-hours page: book the next play window off (RoundControl vacation), or see / undo a booking.
$vac = RoundControl::vacation($session->uid);
$vacErrors = $_SESSION['vac_flash'] ?? [];
unset($_SESSION['vac_flash']);
if (!$vac && RoundControl::vacationBlockers($session->uid) === [VAC_ERR_NO_ROUND]) return;
if (empty($_SESSION['csrf_vac'])) $_SESSION['csrf_vac'] = bin2hex(random_bytes(16));
$vacLeft = RoundControl::VACATIONS_PER_ROUND - RoundControl::vacationsUsed($session->uid);
$vacH = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<form id="rnd_vac" method="post" action="vacation.php">
<input type="hidden" name="csrf" value="<?php echo $vacH($_SESSION['csrf_vac']); ?>" />
<?php if ($vac && $vac['starts'] > time()) { ?>
	<p><b><?php echo VAC_TITLE; ?>:</b> <?php echo sprintf(VAC_BOOKED, '<b>' . RoundControl::fmt($vac['starts']) . '</b>', '<b>' . RoundControl::fmt($vac['ends']) . '</b>'); ?></p>
	<button type="submit" name="do" value="cancel"><?php echo VAC_CANCEL; ?></button>
<?php } elseif ($vac) { ?>
	<p><b><?php echo VAC_TITLE; ?>:</b> <?php echo sprintf(VAC_RUNNING, '<b>' . RoundControl::fmt($vac['ends']) . '</b>'); ?></p>
<?php } else {
	$vacBlock = RoundControl::vacationBlockers($session->uid); ?>
	<p><b><?php echo VAC_TITLE; ?>:</b> <?php echo sprintf(VAC_OFFER, '<b>' . RoundControl::fmt(RoundControl::nextWindowStart()) . '</b>', max(0, $vacLeft), RoundControl::VACATIONS_PER_ROUND); ?></p>
	<?php foreach (array_unique(array_merge($vacErrors, $vacBlock)) as $e) { ?><p class="rnd-vac-err"><?php echo $vacH($e); ?></p><?php } ?>
	<button type="submit" name="do" value="book"<?php echo $vacBlock ? ' disabled="disabled"' : ''; ?> onclick="return confirm(<?php echo $vacH(json_encode(VAC_CONFIRM)); ?>);"><?php echo VAC_BOOK; ?></button>
<?php } ?>
</form>
<style>
#rnd_vac { border: 1px solid #c9c9c9; background: #f7f7f2; padding: 8px 10px; margin: 0 0 14px; font-size: 11px; }
#rnd_vac p { margin: 0 0 6px; }
#rnd_vac .rnd-vac-err { color: #c00; }
</style>
