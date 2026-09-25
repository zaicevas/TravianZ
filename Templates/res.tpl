<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       res.tpl                                                     ##
##  Developed by:  Dzoki                                                       ##
##  Refactored by: Shadow Incremental Refactor 			                       ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2026. All rights reserved.                ##
##                                                                             ##
##  Incremental Refactor Notes:                                                ##
##  - Preserved original functionality                                         ##
##  - Added safety checks for legacy PHP                                       ##
##  - Reduced repeated property access                                         ##
##  - Improved readability                                                     ##
##  - Kept UI structure unchanged                                              ##
##                                                                             ##
#################################################################################

/**
 * ---------------------------------------------------------
 * Safety check (avoid undefined village context)
 * ---------------------------------------------------------
 */
if (!empty($village)) {

    /**
     * -----------------------------------------------------
     * Production values (rounded)
     * -----------------------------------------------------
     */
    $wood = round($village->getProd("wood"));
    $clay = round($village->getProd("clay"));
    $iron = round($village->getProd("iron"));
    $crop = round($village->getProd("crop"));

    /**
     * Total crop production capacity
     */
    $totalproduction = $village->allcrop;

    /**
     * Safely cache values to reduce repeated access
     */
    $woodStore = round($village->awood);
    $clayStore = round($village->aclay);
    $ironStore = round($village->airon);
    $cropStore = round($village->acrop);

    $maxStore  = $village->maxstore;
    $maxCrop   = $village->maxcrop;
?>

<div id="res">
<div id="resWrap">

    <!-- ================= RESOURCES ================= -->
    <table cellpadding="1" cellspacing="1">
        <tr>

            <!-- Wood -->
            <td>
                <img src="img/x.gif" class="r1" alt="<?php echo LUMBER; ?>" title="<?php echo LUMBER; ?>" />
            </td>

            <td id="l4" title="<?php echo $wood; ?>">
                <?php echo $woodStore . "/" . $maxStore; ?>
            </td>

            <!-- Clay -->
            <td>
                <img src="img/x.gif" class="r2" alt="<?php echo CLAY; ?>" title="<?php echo CLAY; ?>" />
            </td>

            <td id="l3" title="<?php echo $clay; ?>">
                <?php echo $clayStore . "/" . $maxStore; ?>
            </td>

            <!-- Iron -->
            <td>
                <img src="img/x.gif" class="r3" alt="<?php echo IRON; ?>" title="<?php echo IRON; ?>" />
            </td>

            <td id="l2" title="<?php echo $iron; ?>">
                <?php echo $ironStore . "/" . $maxStore; ?>
            </td>

            <!-- Crop -->
            <td>
                <img src="img/x.gif" class="r4" alt="<?php echo CROP; ?>" title="<?php echo CROP; ?>" />
            </td>

            <?php if ($village->acrop > 0) { ?>
                <td id="l1" title="<?php echo $crop; ?>">
                    <?php echo $cropStore . "/" . $maxCrop; ?>
                </td>
            <?php } else { ?>
                <td title="<?php echo $crop; ?>">
                    0/<?php echo $maxCrop; ?>
                </td>
            <?php } ?>

            <!-- Crop consumption -->
            <td>
                <img src="img/x.gif" class="r5" alt="<?php echo CROP_COM; ?>" title="<?php echo CROP_COM; ?>" />
            </td>

            <td>
                <?php echo ($village->pop + $technology->getUpkeep($village->unitall, 0)) . "/" . $totalproduction; ?>
            </td>

        </tr>
        <tr class="resTimers">
            <?php
            // Seconds until each store is full (or, when production is negative, empty).
            $resTimers = [[$wood, $woodStore, $maxStore], [$clay, $clayStore, $maxStore],
                          [$iron, $ironStore, $maxStore], [$crop, $cropStore, $maxCrop]];
            foreach ($resTimers as [$prod, $stock, $cap]) {
                echo '<td></td><td>';
                if ($prod > 0) {
                    echo $stock >= $cap ? RND_RES_FULL
                        : RND_RES_FULL_IN . ' <span data-left="' . (int) ceil(($cap - $stock) / $prod * 3600) . '"></span>';
                } elseif ($prod < 0) {
                    echo '<span class="neg">' . ($stock <= 0 ? RND_RES_EMPTY
                        : RND_RES_EMPTY_IN . ' <span data-left="' . (int) ceil($stock / -$prod * 3600) . '"></span>') . '</span>';
                }
                echo '</td>';
            }
            ?>
            <td></td><td></td>
        </tr>
    </table>
<style>
div#res tr.resTimers td { font-size: 10px; color: #777; padding-top: 0; white-space: nowrap; }
div#res tr.resTimers .neg { color: #c00; }
</style>
<script>
(function () {
    var els = document.querySelectorAll('#res tr.resTimers span[data-left]'), t0 = Date.now();
    function tick() {
        for (var i = 0; i < els.length; i++) {
            var s = Math.max(0, els[i].getAttribute('data-left') - Math.floor((Date.now() - t0) / 1000));
            var h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, x = s % 60;
            els[i].textContent = h + ':' + (m < 10 ? '0' : '') + m + ':' + (x < 10 ? '0' : '') + x;
        }
    }
    tick(); setInterval(tick, 1000);
})();
</script>

</div>
</div>

<?php } ?>