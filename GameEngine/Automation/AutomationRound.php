<?php

#################################################################################
##  AutomationRound                                                             ##
## ---------------------------------------------------------------------------  ##
##  Fixed-length round events, run on every automation tick (cron.php):         ##
##    - weekly gold bonus for active players (once per 7-day period)            ##
##    - final artifact snapshot once the round has ended                        ##
##  The logic lives in RoundControl; both steps are idempotent, so repeated or  ##
##  concurrent ticks are harmless.                                              ##
#################################################################################

include_once(dirname(__DIR__) . '/RoundControl.php');

trait AutomationRound
{
    private function roundEvents()
    {
        RoundControl::runAutomation();
    }
}
