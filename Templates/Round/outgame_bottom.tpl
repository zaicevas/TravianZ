<?php
#################################################################################
##  outgame_bottom.tpl - closes the layout opened by outgame_top.tpl and adds  ##
##  the countdown helper: <span class="rnd-countdown" data-left="SECONDS"      ##
##  data-done-url="...">. Counts with the browser clock from the moment the   ##
##  page was served, so the viewer's own time zone does not matter.           ##
#################################################################################
?>
	</div>
	<div class="footer"></div>
</div>
</div>
<?php include(__DIR__ . "/countdown_js.tpl"); ?>
</body>
</html>
