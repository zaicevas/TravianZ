<?php // .rnd-countdown spans: data-left="SECONDS", optional data-done-url to open at 0 ?>
<script type="text/javascript">
(function () {
	var nodes = document.querySelectorAll ? document.querySelectorAll('.rnd-countdown') : [];
	if (!nodes.length) { return; }
	var loaded = Date.now();
	function pad(n) { return n < 10 ? '0' + n : '' + n; }
	function tick() {
		for (var i = 0; i < nodes.length; i++) {
			var node = nodes[i];
			var left = parseInt(node.getAttribute('data-left'), 10) - Math.floor((Date.now() - loaded) / 1000);
			if (left <= 0) {
				node.innerHTML = '00:00:00';
				var url = node.getAttribute('data-done-url');
				if (url && !node.getAttribute('data-fired')) {
					node.setAttribute('data-fired', '1');
					window.location.href = url;
				}
				continue;
			}
			var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600),
			    m = Math.floor(left % 3600 / 60), s = left % 60;
			node.innerHTML = (d > 0 ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
		}
	}
	tick();
	setInterval(tick, 1000);
})();
</script>
