<?php
/*******************************************************************************
 *
 *  filename    : Include/Header-functions.php
 *  website     : http://www.ecclesiacrm.com
 *  description : page header used for most pages
 *
 *  Copyright 2001-2004 Phillip Hullquist, Deane Barker, Chris Gebhardt, Michael Wilt
 *  Update 2018 Philippe Logel
 *
 *
 ******************************************************************************/

require_once 'Functions.php';

use EcclesiaCRM\Service\SystemService;
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\Service\NotificationService;
use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\UserConfigQuery;

use EcclesiaCRM\MenuBar\MenuBar;

function Header_system_notifications()
{
    if (NotificationService::hasActiveNotifications()) {
        ?>
        <div class="systemNotificationBar">
            <?php
            foreach (NotificationService::getNotifications() as $notification) {
                echo "<a href=\"" . $notification->link . "\">" . $notification->title . "</a>";
            } ?>
        </div>
        <?php
    }
}

function Header_head_metatag()
{
    global $sMetaRefresh, $sPageTitle;

    if (strlen($sMetaRefresh) > 0) {
        echo $sMetaRefresh;
    } ?>
    <title>EcclesiaCRM: <?= $sPageTitle ?></title>
    <?php
}

function Header_modals()
{
    ?>
    <!-- Issue Report Modal -->
    <div id="IssueReportModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
              <div id="submitDiaglogStart">
                  <form name="issueReport">
                      <input type="hidden" name="pageName" value="<?= $_SERVER['SCRIPT_NAME'] ?>"/>
                      <div class="modal-header">
                          <button type="button" class="close" data-dismiss="modal">&times;</button>
                          <h4 class="modal-title"><?= gettext('Issue Report!') ?></h4>
                      </div>
                      <div class="modal-body">
                          <div class="container-fluid">
                              <div class="row">
                                  <div class="col-xl-3">
                                      <label
                                              for="issueTitle"><?= gettext('Enter a Title for your bug / feature report') ?>
                                          : </label>
                                  </div>
                                  <div class="col-xl-3">
                                      <input type="text" name="issueTitle"  style="min-width: 100%;max-width: 100%;">
                                  </div>
                              </div>
                              <div class="row">
                                  <div class="col-xl-3">
                                      <label
                                              for="issueDescription"><?= gettext('What were you doing when you noticed the bug / feature opportunity?') ?></label>
                                  </div>
                                  <div class="col-xl-3">
                                      <textarea rows="10" name="issueDescription" style="min-width: 100%;max-width: 100%;"></textarea>
                                  </div>
                              </div>
                          </div>
                          <ul>
                              <li><?= gettext("When you click \"submit,\" an error report will be posted to the EcclesiaCRM GitHub Issue tracker.") ?></li>
                              <li><?= gettext('Please do not include any confidential information.') ?></li>
                              <li><?= gettext('Some general information about your system will be submitted along with the request such as Server version and browser headers.') ?></li>
                              <li><?= gettext('No personally identifiable information will be submitted unless you purposefully include it.') ?></li>
                          </ul>
                      </div>
                      <div class="modal-footer">
                          <button type="button" class="btn btn-primary" id="submitIssue"><?= gettext('Submit') ?></button>
                      </div>
                  </form>
              </div>
              <div id="submitDiaglogFinish">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><?= gettext('Issue Report done!') ?></h4>
                </div>
                <div class="modal-body"><h2><?= _("Successfully submitted Issue") ?> <span id="issueSubmitSucces"></span></h2>
                <a href="" target="_blank" id="issueSubmitSuccesLink"><?= _("View Issue on GitHub")." : #" ?> <span id="issueSubmitSuccesLinkText"></span></a>
                <div class="modal-footer">
                          <button type="button" class="btn btn-primary" id="submitIssueDone"><?= gettext('OK') ?></button>
                </div>
                </div>              
              </div>
            </div>

        </div>
    </div>
    <!-- End Issue Report Modal -->

    <?php
}

function Header_body_scripts()
{
    global $localeInfo;
    $systemService = new SystemService(); ?>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        window.CRM = {
            root: "<?= SystemURLs::getRootPath() ?>",
            lang: "<?= $localeInfo->getLanguageCode() ?>",
            locale: "<?= $localeInfo->getLocale() ?>",
            shortLocale: "<?= $localeInfo->getShortLocale() ?>",
            currency: "<?= SystemConfig::getValue('sCurrency') ?>",
            maxUploadSize: "<?= $systemService->getMaxUploadFileSize(true) ?>",
            maxUploadSizeBytes: "<?= $systemService->getMaxUploadFileSize(false) ?>",
            datePickerformat:"<?= SystemConfig::getValue('sDatePickerPlaceHolder') ?>",
            timeEnglish:<?= (SystemConfig::getValue("sTimeEnglish"))?"true":"false" ?>,
            iDasbhoardServiceIntervalTime:"<?= SystemConfig::getValue('iDasbhoardServiceIntervalTime') ?>",
            showTooltip:<?= ($_SESSION['bShowTooltip'])?"true":"false" ?>,
            showCart:<?= ($_SESSION['user']->isShowCartEnabled())?"true":"false" ?>,
            bSidebarExpandOnHover:<?= ($_SESSION['bSidebarExpandOnHover'])?"true":"false" ?>,
            bSidebarCollapse:<?= ($_SESSION['bSidebarCollapse'])?"true":"false" ?>,
            iPersonId:<?= $_SESSION['user']->getPersonId() ?>,
            plugin: {
                dataTable : {
                   "language": {
                        "url": "<?= SystemURLs::getRootPath() ?>/locale/datatables/<?= $localeInfo->getDataTables() ?>.json"
                    },
                    responsive: true,
                    "dom": 'T<"clear">lfrtip',
                    "tableTools": {
                        "sSwfPath": "<?= SystemURLs::getRootPath() ?>/skin/adminlte/plugins/datatables/extensions/TableTools/swf/copy_csv_xls.swf"
                    }
                }
            },
            PageName:"<?= $_SERVER['PHP_SELF']?>"
        };
    </script>
    <script src="<?= SystemURLs::getRootPath() ?>/skin/js/CRMJSOM.js"></script>
    <?php
}

function addMenu($menu)
{
    $menubar = new MenuBar("MainMenuBar");    
    $menubar->renderMenu();
}

?>