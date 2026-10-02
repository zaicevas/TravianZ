<?php
#################################################################################
##  vacation.php - books / cancels a skipped play window (RoundControl).       ##
##  Exempt from the play-window gate (it is used off-hours); every rule lives  ##
##  in RoundControl::vacationBlockers().                                       ##
#################################################################################

include_once("GameEngine/Session.php"); // not logged in: sent to login.php

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_SESSION['csrf_vac'])
    && hash_equals($_SESSION['csrf_vac'], (string) ($_POST['csrf'] ?? ''))) {
    if (($_POST['do'] ?? '') === 'cancel') {
        RoundControl::cancelVacation($session->uid);
    } elseif (!RoundControl::isStaff($session->access, $session->username)) {
        $_SESSION['vac_flash'] = RoundControl::bookVacation($session->uid);
    }
}
header("Location: berichte.php");
exit;
