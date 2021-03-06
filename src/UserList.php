<?php
/*******************************************************************************
 *
 *  filename    : UserList.php
 *  last change : 2003-01-07
 *  description : displays a list of all users
 *
 *  http://www.ecclesiacrm.com/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *  Cpoyright 2018 Philippe Logel
 *
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\UserQuery;
use EcclesiaCRM\dto\SystemURLs;

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['user']->isAdmin()) {
    Redirect('Menu.php');
    exit;
}

// Get all the User records
$rsUsers = UserQuery::create()->find();

// Set the page title and include HTML header
$sPageTitle = gettext('User Listing');
require 'Include/Header.php';

?>
<!-- Default box -->
<div class="box">
    <div class="box-header">
        <a href="UserEditor.php" class="btn btn-app"><i class="fa fa-user-plus"></i><?= gettext('New User') ?></a>
        <a href="SettingsUser.php" class="btn btn-app"><i class="fa fa-wrench"></i><?= gettext('User Settings') ?></a>
    </div>
</div>
<div class="box">
    <div class="box-body">
        <table class="table table-hover dt-responsive" id="user-listing-table" style="width:100%;">
            <thead>
            <tr>
                <th><?= gettext('Actions') ?></th>
                <th><?= gettext('Name') ?></th>
                <th><?= gettext('First Name') ?></th>
                <th align="center"><?= gettext('Last Login') ?></th>
                <th align="center"><?= gettext('Total Logins') ?></th>
                <th align="center"><?= gettext('Failed Logins') ?></th>
                <th align="center"><?= gettext('Password') ?></th>

            </tr>
            </thead>
            <tbody>
            <?php foreach ($rsUsers as $user) { //Loop through the person?>
                <tr>
                    <td>
                        <?php 
                           if ( $user->getPersonId() != 1 || $user->getId() == $_SESSION['user']->getId() && $user->getPersonId() == 1) {
                        ?>
                            <a href="UserEditor.php?PersonID=<?= $user->getId() ?>"><i class="fa fa-pencil"
                                                                                   aria-hidden="true"></i></a>&nbsp;&nbsp;
                        <?php
                            } else {
                        ?>
                           <span style="color:red"><?= gettext("Not modifiable") ?></span>
                        <?php
                            }
                        ?>
                            <?php 
                           if ( $user->getId() != $_SESSION['user']->getId() && $user->getPersonId() != 1 ) {
                        ?>
                            <a onclick="deleteUser(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')"><i
                                        class="fa fa-trash-o" aria-hidden="true"></i></a>
                        <?php
                            } 
                        ?>
                    </td>
                    <td>
                        <a href="PersonView.php?PersonID=<?= $user->getId() ?>"> <?= $user->getPerson()->getLastName() ?></a>
                    </td>
                    <td>
                        <a href="PersonView.php?PersonID=<?= $user->getId() ?>"> <?= $user->getPerson()->getFirstName() ?></a>
                    </td>
                    <td align="center"><?= $user->getLastLogin(SystemConfig::getValue('sDateFormatLong')) ?></td>
                    <td align="center"><?= $user->getLoginCount() ?></td>
                    <td align="center">
                        <?php if ($user->isLocked()) {
        ?>
                            <span class="text-red"><?= $user->getFailedLogins() ?></span>
                            <?php
    } else {
        echo $user->getFailedLogins();
    }
    if ($user->getFailedLogins() > 0) {
        ?>
                            <a onclick="restUserLoginCount(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')"><i
                                        class="fa fa-eraser" aria-hidden="true"></i></a>
                            <?php
    } ?>
                    </td>
                    <td>
                        <a href="UserPasswordChange.php?PersonID=<?= $user->getId() ?>&FromUserList=True"><i
                                    class="fa fa-wrench" aria-hidden="true"></i></a>&nbsp;&nbsp;
                        <?php if ($user->getId() != $_SESSION['user']->getId() && !empty($user->getEmail())) {
        ?>
                            <a onclick="resetUserPassword(<?= $user->getId() ?>, '<?= $user->getPerson()->getFullName() ?>')"><i
                                        class="fa fa-send-o" aria-hidden="true"></i></a>
                            <?php
    } ?>
                    </td>

                </tr>
                <?php
} ?>
            </tbody>
        </table>
    </div>
    <!-- /.box-body -->
</div>
<!-- /.box -->

<?php require 'Include/Footer.php' ?>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/UserList.js" ></script>