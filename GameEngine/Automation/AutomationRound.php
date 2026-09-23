<?php

#################################################################################
##  AutomationRound                                                             ##
## ---------------------------------------------------------------------------  ##
##  Fixed-length round events, run on every automation tick (cron.php):         ##
##    - roundSnapshot(): FIRST step of the tick - freezes the artifact holders  ##
##      once the round has ended, before this tick processes troop movements   ##
##    - roundEvents(): weekly gold bonus for active players (once per period)   ##
##  The logic lives in RoundControl; both steps are idempotent, so repeated or  ##
##  concurrent ticks are harmless.                                              ##
#################################################################################

include_once(dirname(__DIR__) . '/RoundControl.php');

trait AutomationRound
{
    private function roundSnapshot()
    {
        RoundControl::runSnapshot();
    }

    private function roundEvents()
    {
        RoundControl::runWeeklyGold();
    }
}
