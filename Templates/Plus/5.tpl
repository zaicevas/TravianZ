<?php

#################################################################################
##                -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-               ##
## --------------------------------------------------------------------------- ##
##  Filename       : 5.tpl                                                     ##
##  Type           : Plus - Payment Result                                     ##
## --------------------------------------------------------------------------- ##
##  Developed by   : Shadow                                                    ##
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

include("Templates/Plus/pmenu.tpl");

$refLink = rtrim(HOMEPAGE, '/') . '/anmelden.php?uid=ref_' . $session->uid;
$invited = $database->getInvitedUser($session->uid);
?>
<h2><?php echo RND_INVITE_TITLE; ?></h2>
<p><?php echo RND_INVITE_TEXT; ?></p>

<h3><?php echo RND_INVITE_LINK; ?></h3>
<span class="link" onclick="navigator.clipboard.writeText('<?= $refLink ?>'); this.style.color='#0a0';" title="<?php echo TZ_CLICK_TO_COPY; ?>"><?= $refLink ?></span>

<table id="brought_in" cellpadding="1" cellspacing="1">
    <thead>
        <tr><th colspan="4"><?php echo TZ_PLAYERS_BROUGHT_IN; ?></th></tr>
        <tr><td>UID</td><td><?php echo TZ_MEMBER_SINCE; ?></td><td><?php echo INHABITANTS; ?></td><td><?php echo VILLAGES; ?></td></tr>
    </thead>
    <tbody>
    <?php if(!empty($invited)): ?>
        <?php foreach($invited as $inv): 
            $villages = $database->getProfileVillages($inv['id']);
            $totalPop = array_sum(array_column($villages, 'pop'));
            $vilCount = count($villages);
        ?>
        <tr>
            <td><?= $inv['id'] ?></td>
            <td><?= date('j.m.y', $inv['regtime']) ?></td>
            <td><?= $totalPop ?></td>
            <td><?= $vilCount ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td class="none" colspan="4"><?php echo TZ_YOU_HAVE_NOT_BROUGHT_IN_ANY_NEW_PL; ?></td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>