<?php
/*******************************************************************************
 *
 *  filename    : FamilyView.php
 *  last change : 2013-02-02
 *  website     : http://www.ecclesiacrm.com
 *  copyright   : Copyright 2001, 2002 Deane Barker, 2003 Chris Gebhardt, 2004-2005 Michael Wilt
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\FamilyQuery;
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\PropertyQuery;
use EcclesiaCRM\Service\MailChimpService;
use EcclesiaCRM\Service\TimelineService;
use EcclesiaCRM\Utils\GeoUtils;
use EcclesiaCRM\Utils\InputUtils;
use EcclesiaCRM\Utils\OutputUtils;
use EcclesiaCRM\dto\Cart;


use EcclesiaCRM\AutoPaymentQuery;
use EcclesiaCRM\PledgeQuery;
use EcclesiaCRM\ListOptionQuery;

$timelineService = new TimelineService();
$mailchimp = new MailChimpService();
$curYear = (new DateTime)->format("Y");

//Set the page title
$sPageTitle = gettext("Family View");
require "Include/Header.php";

//Get the FamilyID out of the querystring
if (!empty($_GET['FamilyID'])) {
    $iFamilyID = InputUtils::LegacyFilterInput($_GET['FamilyID'], 'int');
}

//Deactivate/Activate Family
if ($_SESSION['bDeleteRecords'] && !empty($_POST['FID']) && !empty($_POST['Action'])) {
    $family = FamilyQuery::create()->findOneById($_POST['FID']);
    if ($_POST['Action'] == "Deactivate") {
        $family->deactivate();
    } elseif ($_POST['Action'] == "Activate") {
        $family->activate();
    }
    $family->save();
    Redirect("FamilyView.php?FamilyID=" . $_POST['FID']);
    exit;
}
// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
$rsFunds = RunQuery($sSQL);

if (isset($_POST["UpdatePledgeTable"]) && $_SESSION['bFinance']) {
    $_SESSION['sshowPledges'] = isset($_POST["ShowPledges"]);
    $_SESSION['sshowPayments'] = isset($_POST["ShowPayments"]);
    $_SESSION['sshowSince'] = DateTime::createFromFormat("Y-m-d", InputUtils::FilterDate($_POST["ShowSinceDate"]));
}

$dSQL = "SELECT fam_ID FROM family_fam order by fam_Name";
$dResults = RunQuery($dSQL);

$last_id = 0;
$next_id = 0;
$capture_next = 0;
while ($myrow = mysqli_fetch_row($dResults)) {
    $fid = $myrow[0];
    if ($capture_next == 1) {
        $next_id = $fid;
        break;
    }
    if ($fid == $iFamilyID) {
        $previous_id = $last_id;
        $capture_next = 1;
    }
    $last_id = $fid;
}

//Get the information for this family
$sSQL = "SELECT *, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName, a.per_ID AS EnteredId,
      b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName, b.per_ID AS EditedId
    FROM family_fam
    LEFT JOIN person_per a ON fam_EnteredBy = a.per_ID
    LEFT JOIN person_per b ON fam_EditedBy = b.per_ID
    WHERE fam_ID = " . $iFamilyID;
    
$rsFamily = RunQuery($sSQL);
extract(mysqli_fetch_array($rsFamily));

// Get the lists of custom person fields
$sSQL = "SELECT family_custom_master.* FROM family_custom_master ORDER BY fam_custom_Order";
$rsFamCustomFields = RunQuery($sSQL);

// Get the custom field data for this person.
$sSQL = "SELECT * FROM family_custom WHERE fam_ID = " . $iFamilyID;
$rsFamCustomData = RunQuery($sSQL);
$aFamCustomData = mysqli_fetch_array($rsFamCustomData, MYSQLI_BOTH);

$family = FamilyQuery::create()->findPk($iFamilyID);

if (empty($family)) {
    Redirect('members/404.php');
    exit;
}

//Get the pledges for this family
$ormPledges = PledgeQuery::Create()
            ->leftJoinPerson()
            ->withColumn('Person.FirstName', 'EnteredFirstName')
            ->withColumn('Person.LastName', 'EnteredLastName')
            ->leftJoinDonationFund()
            ->withColumn('DonationFund.Name', 'fundName')
            ->findByFamId($iFamilyID);

//Get the automatic payments for this family
$ormAutoPayments = AutoPaymentQuery::create()
           ->leftJoinPerson()
             ->withColumn('Person.FirstName','EnteredFirstName')
             ->withColumn('Person.LastName','EnteredLastName')
             ->withColumn('Person.FirstName','EnteredFirstName')
             ->withColumn('Person.LastName','EnteredLastName')
           ->leftJoinDonationFund()
             ->withColumn('DonationFund.Name','fundName')
           ->orderByNextPayDate()
           ->findByFamilyid($iFamilyID);

//Get the Properties assigned to this Family
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
    FROM record2property_r2p
    LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
    LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
    WHERE pro_Class = 'f' AND r2p_record_ID = " . $iFamilyID .
" ORDER BY prt_Name, pro_Name";
$rsAssignedProperties = RunQuery($sSQL);


//Get all the properties
$ormProperties = PropertyQuery::Create()
                  ->filterByProClass('f')
                  ->orderByProName()
                  ->find();

//Get classifications
$ormClassifications = ListOptionQuery::Create()
              ->orderByOptionSequence()
              ->findById(1);


// Get Field Security List Matrix
$securityListOptions = ListOptionQuery::Create()
              ->orderByOptionSequence()
              ->findById(5);
              
foreach ($securityListOptions as $securityListOption) {
    $aSecurityType[$securityListOption->getOptionId()] = $securityListOption->getOptionName();
}


//Set the spacer cell width
$iTableSpacerWidth = 10;

// Format the phone numbers
$sHomePhone = ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy);
$sWorkPhone = ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy);
$sCellPhone = ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy);

$sFamilyEmails = array();

$bOkToEdit = ($_SESSION['bEditRecords'] || ($_SESSION['bEditSelf'] && ($iFamilyID == $_SESSION['iFamID'])));

?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentFamily = <?= $iFamilyID ?>;
</script>

<?php if (!empty($fam_DateDeactivated)) {
    ?>
    <div class="alert alert-warning">
        <strong><?= gettext(" This Family is Deactivated") ?> </strong>
    </div>
    <?php
} ?>
<div class="row">
    <div class="col-lg-3 col-md-3 col-sm-3">
        <div class="box box-primary">
            <div class="box-body">
                <div class="image-container">
                    <img src="<?= SystemURLs::getRootPath() ?>/api/families/<?= $family->getId() ?>/photo" class="initials-image img-rounded img-responsive profile-user-img profile-family-img"/>
                    <?php if ($bOkToEdit): ?>
                        <div class="after">
                            <div class="buttons">
                                <a class="hide" id="view-larger-image-btn" href="#"
                                   title="<?= gettext("View Photo") ?>">
                                    <i class="fa fa-search-plus"></i>
                                </a>&nbsp;
                                <a href="#" data-toggle="modal" data-target="#upload-image"
                                   title="<?= gettext("Upload Photo") ?>">
                                    <i class="fa fa-camera"></i>
                                </a>&nbsp;
                                <a href="#" data-toggle="modal" data-target="#confirm-delete-image"
                                   title="<?= gettext("Delete Photo") ?>">
                                    <i class="fa fa-trash-o"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="profile-username text-center"><?= gettext('Family') . ': ' . $fam_Name ?></h3>
                <?php if ($bOkToEdit) {
        ?>
                    <a href="FamilyEditor.php?FamilyID=<?= $fam_ID ?>"
                       class="btn btn-primary btn-block"><b><?= gettext("Edit") ?></b></a>
                    <?php
    } ?>
                <hr/>
                <ul class="fa-ul">
                    <li><i class="fa-li fa fa-home"></i><?= gettext("Address") ?>:<span>
          <a
                            href="http://maps.google.com/?q=<?= $family->getAddress() ?>"
                            target="_blank"><?= $family->getAddress() ?></a></span><br>

                        <?php if ($fam_Latitude && $fam_Longitude) {
        if (SystemConfig::getValue("iChurchLatitude") && SystemConfig::getValue("iChurchLongitude")) {
            $sDistance = GeoUtils::LatLonDistance(SystemConfig::getValue("iChurchLatitude"), SystemConfig::getValue("iChurchLongitude"), $fam_Latitude, $fam_Longitude);
            $sDirection = GeoUtils::LatLonBearing(SystemConfig::getValue("iChurchLatitude"), SystemConfig::getValue("iChurchLongitude"), $fam_Latitude, $fam_Longitude);
            echo $sDistance . " " . strtolower(SystemConfig::getValue("sDistanceUnit")) . " " . $sDirection . " " . gettext(" of church<br>");
        }
    } else {
        $bHideLatLon = true;
    } ?>
                        <?php if (!$bHideLatLon) { /* Lat/Lon can be hidden - General Settings */ ?>
                    <li><i class="fa-li fa fa-compass"></i><?= gettext("Latitude/Longitude") ?>
                        <span><?= $fam_Latitude . " / " . $fam_Longitude ?></span></li>
                    <?php
    }
    if (!SystemConfig::getValue("bHideFamilyNewsletter")) { /* Newsletter can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa fa-hacker-news"></i><?= gettext("Send Newsletter") ?>:
                            <span style="color:<?= ($fam_SendNewsLetter == "TRUE" ? "green" : "red") ?>"><i
                                        class="fa fa-<?= ($fam_SendNewsLetter == "TRUE" ? "check" : "times") ?>"></i></span>
                        </li>
                        <?php
    }
    if (!SystemConfig::getValue("bHideWeddingDate") && $fam_WeddingDate != "") { /* Wedding Date can be hidden - General Settings */ ?>
                        <li><i class="fa-li fa fa-magic"></i><?= gettext("Wedding Date") ?>:
                            <span><?= OutputUtils::FormatDate($fam_WeddingDate, false) ?></span></li>
                        <?php
    }
    if (SystemConfig::getValue("bUseDonationEnvelopes")) {
        ?>
                        <li><i class="fa-li fa fa-phone"></i><?= gettext("Envelope Number") ?>
                            <span><?= $fam_Envelope ?></span>
                        </li>
                        <?php
    }
    if ($sHomePhone != "") {
        ?>
                        <li><i class="fa-li fa fa-phone"></i><?= gettext("Home Phone") ?>: <span><a
                                        href="tel:<?= $sHomePhone ?>"><?= $sHomePhone ?></a></span></li>
                        <?php
    }
    if ($sWorkPhone != "") {
        ?>
                        <li><i class="fa-li fa fa-building"></i><?= gettext("Work Phone") ?>: <span><a
                                        href="tel:<?= $sWorkPhone ?>"><?= $sWorkPhone ?></a></span></li>
                        <?php
    }
    if ($sCellPhone != "") {
        ?>
                        <li><i class="fa-li fa fa-mobile"></i><?= gettext("Mobile Phone") ?>: <span><a
                                        href="tel:<?= $sCellPhone ?>"><?= $sCellPhone ?></a></span></li>
                        <li><i class="fa-li fa fa-mobile-phone"></i><?= gettext('Text Message') ?>: <span><a 
                                        href="sms:<?= $sCellPhone ?>&body=<?= gettext("EcclesiaCRM text message") ?>"><?= $sCellPhone ?></a></span></li>

                        <?php
    }
    if ($fam_Email != "") {
        ?>
                        <li><i class="fa-li fa fa-envelope"></i><?= gettext("Email") ?>:<a
                                    href="mailto:<?= $fam_Email ?>">
                                <span><?= $fam_Email ?></span></a></li>
                        <?php if ($mailchimp->isActive()) {
            ?>
                            <li><i class="fa-li fa fa-send"></i><?= gettext("Email") ?>:
                                <span><?= $mailchimp->isEmailInMailChimp($fam_Email) ?></span>
                                </a></li>
                            <?php
        }
    }
    // Display the left-side custom fields
    while ($Row = mysqli_fetch_array($rsFamCustomFields)) {
        extract($Row);
        if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || ($_SESSION[$aSecurityType[$fam_custom_FieldSec]])) {
            $currentData = trim($aFamCustomData[$fam_custom_Field]);
            if ($type_ID == 11) {
                $fam_custom_Special = $sPhoneCountry;
            }
            echo "<li><i class=\"fa-li fa fa-tag\"></i>" . $fam_custom_Name . ": <span>" . displayCustomField($type_ID, $currentData, $fam_custom_Special) . "</span></li>";
        }
    } ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-9 col-md-9 col-sm-9">
            <div class="box"><br/>
                <a class="btn btn-app" href="#" data-toggle="modal" data-target="#confirm-verify"><i class="fa fa-check-square"></i> <?= gettext("Verify Info") ?></a>
                
                <?php
                  if (Cart::FamilyInCart($iFamilyID)) {
                ?>
                  <a class="btn btn-app RemoveFromFamilyCart" id="AddToFamilyCart" data-cartfamilyid="<?= $iFamilyID ?>"> <i class="fa fa-remove"></i> <span class="cartActionDescription"><?= gettext("Remove from Cart") ?></span></a>
                <?php 
                  } else {
                ?>
                  <a class="btn btn-app AddToFamilyCart" id="AddToFamilyCart" data-cartfamilyid="<?= $iFamilyID ?>"> <i class="fa fa-cart-plus"></i> <span class="cartActionDescription"><?= gettext("Add to Cart") ?></span></a>
                <?php 
                 }
                ?>
                
                <a class="btn btn-app bg-olive" href="PersonEditor.php?FamilyID=<?= $iFamilyID ?>"><i class="fa fa-plus-square"></i> <?= gettext('Add New Member') ?></a>
                <?php if (($previous_id > 0)) {
        ?>
                    <a class="btn btn-app" href="FamilyView.php?FamilyID=<?= $previous_id ?>"><i class="fa fa-hand-o-left"></i><?= gettext('Previous Family') ?></a>
                    <?php
    } ?>
                <a class="btn btn-app btn-danger" role="button" href="FamilyList.php"><i class="fa fa-list-ul"></i><?= gettext('Family List') ?></a>
                <?php if (($next_id > 0)) {
        ?>
                    <a class="btn btn-app" role="button" href="FamilyView.php?FamilyID=<?= $next_id ?>"><i class="fa fa-hand-o-right"></i><?= gettext('Next Family') ?> </a>
                    <?php
    } ?>
                <?php if ($_SESSION['bDeleteRecords']) {
        ?>
                    <a class="btn btn-app bg-maroon" href="SelectDelete.php?FamilyID=<?= $iFamilyID ?>"><i class="fa fa-trash-o"></i><?= gettext('Delete this Family') ?></a>
                    <?php
    } ?>
                <?php
                if ($_SESSION['bNotes']) {
                    ?>
                    <a class="btn btn-app" href="NoteEditor.php?FamilyID=<?= $iFamilyID ?>"><i class="fa fa-sticky-note"></i><?= gettext("Add a Document") ?></a>
                    <?php
                } ?>
                        

                <?php if ($bOkToEdit) {
                    ?>
                    <button class="btn btn-app bg-orange" id="activateDeactivate">
                        <i class="fa <?= (empty($fam_DateDeactivated) ? 'fa-times-circle-o' : 'fa-check-circle-o') ?> "></i><?php echo((empty($fam_DateDeactivated) ? _('Deactivate') : _('Activate')) . _(' this Family')); ?>
                    </button>
                    <?php
                } ?>
            </div>
    </div>

    <div class="col-lg-9 col-md-9 col-sm-9">
            <div class="box box-solid">
                <div class="box-body table-responsive clearfix">
                    <table class="table user-list table-hover data-person" width="100%">
                        <thead>
                        <tr>
                            <th><span><?= gettext("Family Members") ?></span></th>
                            <th class="text-center"><span><?= gettext("Role") ?></span></th>
                            <th><span><?= gettext("Birthday") ?></span></th>
                            <th><span><?= gettext("Email") ?></span></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($family->getPeople() as $person) {
                    ?>
                            <tr>
                                <td>
                                    <img src="<?= SystemURLs::getRootPath() ?>/api/persons/<?= $person->getId() ?>/thumbnail"
                                         width="40" height="40"
                                         class="initials-image img-circle"/>
                                    <a href="<?= $person->getViewURI() ?>"
                                       class="user-link"><?= $person->getFullName() ?> </a>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $famRole = $person->getFamilyRoleName();
                    $labelColor = 'label-default';
                    if ($famRole == gettext('Head of Household')) {
                    } elseif ($famRole == gettext('Spouse')) {
                        $labelColor = 'label-info';
                    } elseif ($famRole == gettext('Child')) {
                        $labelColor = 'label-warning';
                    } ?>
                                    <span class='label <?= $labelColor ?>'> <?= $famRole ?></span>
                                </td>
                                <td>
                                    <?= OutputUtils::FormatBirthDate($person->getBirthYear(),
                                        $person->getBirthMonth(), $person->getBirthDay(), "-", $person->getFlags()) ?>
                                </td>
                                <td>
                                    <?php $tmpEmail = $person->getEmail();
                    if ($tmpEmail != "") {
                        array_push($sFamilyEmails, $tmpEmail); ?>
                                        <a href="#"><a href="mailto:<?= $tmpEmail ?>"><?= $tmpEmail ?></a></a>
                                        <?php
                    } ?>
                                </td>
                                <td style="width: 20%;">
                                    <a class="AddToPeopleCart" data-cartpersonid="<?= $person->getId() ?>">
                                        <span class="fa-stack">
                                            <i class="fa fa-square fa-stack-2x"></i>
                                            <i class="fa fa-cart-plus fa-stack-1x fa-inverse"></i>
                                        </span>
                                    </a>
                                    <?php if ($bOkToEdit) {
                        ?>
                                        <a href="PersonEditor.php?PersonID=<?= $person->getId() ?>" class="table-link">
                                    <span class="fa-stack">
                                        <i class="fa fa-square fa-stack-2x"></i>
                                        <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                                    </span>
                                        </a>
                                        <a class="delete-person" data-person_name="<?= $person->getFullName() ?>"
                                           data-person_id="<?= $person->getId() ?>" data-view="family">
                                    <span class="fa-stack">
                                        <i class="fa fa-square fa-stack-2x"></i>
                                        <i class="fa fa-trash-o fa-stack-1x fa-inverse"></i>
                                    </span>
                                        </a>
                                        <?php
                    } ?>
                                </td>
                            </tr>
                            <?php
                } ?>
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="nav-tabs-custom">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active"><a href="#timeline" aria-controls="timeline" role="tab"
                                                          data-toggle="tab"><?= gettext("Timeline") ?></a></li>
                <li role="presentation"><a href="#properties" aria-controls="properties" role="tab"
                                           data-toggle="tab"><?= gettext("Assigned Properties") ?></a></li>
                <?php if ($_SESSION['bFinance']) {
                    ?>
                    <li role="presentation"><a href="#finance" aria-controls="finance" role="tab"
                                               data-toggle="tab"><?= gettext("Automatic Payments") ?></a></li>
                    <li role="presentation"><a href="#pledges" aria-controls="pledges" role="tab"
                                               data-toggle="tab"><?= gettext("Pledges and Payments") ?></a></li>
                    <?php
                } ?>
                    <li role="presentation"><a href="#notes" aria-controls="notes" role="tab"
                                               data-toggle="tab"><?= gettext("Your Documents") ?></a></li>

            </ul>

            <!-- Tab panes -->
            <div class="tab-content">
                <div role="tab-pane fade" class="tab-pane active" id="timeline">
                    <ul class="timeline">
                        <!-- timeline time label -->
                        <li class="time-label">
                            <span class="bg-red">
                                <?= $curYear ?>
                            </span>
                        </li>
                        <!-- /.timeline-label -->

                        <!-- timeline item -->
                        <?php foreach ($timelineService->getForFamily($iFamilyID) as $item) {
                    if ($curYear != $item['year']) {
                        $curYear = $item['year']; ?>
                                <li class="time-label">
                                    <span class="bg-gray">
                                        <?= $curYear ?>
                                    </span>
                                </li>
                                <?php
                    } ?>
                            <li>
                                <!-- timeline icon -->
                                <i class="fa <?= $item['style'] ?>"></i>

                                <div class="timeline-item">
                                    <span class="time"><i class="fa fa-clock-o"></i><?= $item['datetime'] ?> 
                                     <?php if (($_SESSION['bNotes']) && (isset($item["editLink"]) || isset($item["deleteLink"])) && $item['slim']) {
                                     ?>
                                     &nbsp;
                                     <?php if (isset($item["editLink"])) {
                            ?>
                                                <a href="<?= $item["editLink"] ?>">
                                                    <button type="button" class="btn-xs btn-primary"><i
                                                                class="fa fa-edit"></i></button>
                                                </a>
                                                <?php
                        }
                        if (isset($item["deleteLink"])) {
                            ?>
                                                <a href="<?= $item["deleteLink"] ?>">
                                                    <button type="button" class="btn-xs btn-danger"><i
                                                                class="fa fa-trash"></i></button>
                                                </a>
                                                <?php
                        } ?>
                        <?php } ?>

                                    </span>

                                    <h3 class="timeline-header">
                                        <?php if (in_array('headerlink', $item)) {
                        ?>
                                            <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                                            <?php
                    } else {
                        ?>
                                            <?= gettext($item['header']) ?>
                                            <?php
                    } ?>
                                    </h3>

                                    <div class="timeline-body">
                                        <pre><?= $item['text'] ?></pre>
                                    </div>

                                    <?php if (($_SESSION['bNotes']) && (isset($item["editLink"]) || isset($item["deleteLink"])) && !$item['slim']) {
                        ?>
                                        <div class="timeline-footer">
                                            <?php if (isset($item["editLink"])) {
                            ?>
                                                <a href="<?= $item["editLink"] ?>">
                                                    <button type="button" class="btn btn-primary"><i
                                                                class="fa fa-edit"></i></button>
                                                </a>
                                                <?php
                        }
                        if (isset($item["deleteLink"])) {
                            ?>
                                                <a href="<?= $item["deleteLink"] ?>">
                                                    <button type="button" class="btn btn-danger"><i
                                                                class="fa fa-trash"></i></button>
                                                </a>
                                                <?php
                        } ?>
                                        </div>
                                        <?php
                    } ?>
                                </div>
                            </li>
                            <?php
                } ?>
                        <!-- END timeline item -->
                    </ul>
                </div>
                <div role="tab-pane fade" class="tab-pane" id="properties">
                    <div class="main-box clearfix">
                        <div class="main-box-body clearfix">
                            <?php
                            $sAssignedProperties = ",";

    if (mysqli_num_rows($rsAssignedProperties) == 0) {
        ?>
                                <br>
                                <div class="alert alert-warning">
                                    <i class="fa fa-question-circle fa-fw fa-lg"></i>
                                    <span><?= gettext("No property assignments.") ?></span>
                                </div>
                                <?php
    } else {
    ?>
        <table width="100%" cellpadding="4" class="table table-condensed dt-responsive dataTable no-footer dtr-inline">
        <tr class="TableHeader">
        <td width="10%" valign="top"><b><?= gettext("Type") ?></b></td>
        <td width="15%" valign="top"><b><?= gettext("Name") ?></b></td>
        <td valign="top"><b><?= gettext("Value") ?></b></td>
        <?php
        if ($bOkToEdit) {
            ?>
            <td width="10%" valign="top"><b><?= gettext("Edit Value") ?> </td>
            <td valign="top"><b><?= gettext("Remove") ?></td>
      <?php
        }
      ?>
        </tr>
      <?php
        $last_pro_prt_ID = "";
        $bIsFirst = true;

        //Loop through the rows
        while ($aRow = mysqli_fetch_array($rsAssignedProperties)) {
            $pro_Prompt = "";
            $r2p_Value = "";

            extract($aRow);

            if ($pro_prt_ID != $last_pro_prt_ID) {
                if ($bIsFirst) {
                    $rowColor = "RowColorB";
                } else {
                    $rowColor = "RowColorC";
                }                
              ?>
                <tr class="<?= $rowColor ?>">
              
                <td><b><?= $prt_Name ?></b></td>
              <?php
                $bIsFirst = false;
                $last_pro_prt_ID = $pro_prt_ID;
                $sRowClass = "RowColorB";
            } else {
            ?>
                <tr class="<?= $sRowClass ?>">
                <td valign="top">&nbsp;</td>
            <?php
            }
            ?>
            <td valign="center"><?=  $pro_Name ?></td>
            <td valign="center"><?=  $r2p_Value ?>&nbsp</td>
            <?php

            if ($bOkToEdit) {
                if (strlen($pro_Prompt) > 0) {
                ?>
                    <td valign="top"><a data-family_id="<?= $iFamilyID ?>" data-property_id="<?= $pro_ID ?>" data-property_Name="<?= $r2p_Value ?>" class="edit-property-btn btn btn-success"><?= gettext('Edit Value') ?></a></td>
                <?php
                } else {
                ?>
                    <td>&nbsp;</td>
                <?php
                }
                ?>

                <td valign="top"><a data-family_id="<?= $iFamilyID ?>" data-property_id=" <?= $pro_ID ?>" class="remove-property-btn btn btn-danger"><?= gettext('Remove') ?></a>
            
            <?php
            }
            ?>            

            </tr>
            
            <?php
            //Alternate the row style
            $sRowClass = AlternateRowStyle($sRowClass);

            $sAssignedProperties .= $pro_ID . ",";
        }
        
        //Close the table
        ?>
        </table>
    <?php
    }
    if ($bOkToEdit) {
        ?>
                                <div class="alert alert-info">
                                    <div>
                                        <h4><strong><?= gettext("Assign a New Property") ?>:</strong></h4>

                                        <form method="post" action="<?= SystemURLs::getRootPath(). '/api/properties/families/assign' ?>" id="assign-property-form">
                                            <input type="hidden" name="FamilyId" value="<?= $iFamilyID ?>" >
                                            <div class="row">
                                                <div class="form-group col-xs-12 col-md-7">
                                                    <select name="PropertyId" class="input-family-properties form-control select2"
                                                             style="width:100%" data-placeholder="<?= gettext("Select") ?> ...">
                                                        <option selected disabled> -- <?= gettext('select an option') ?>
                                                            --
                                                        </option>
                                                        <?php
                                                        foreach ($ormProperties as $ormProperty) {
                                                            //If the property doesn't already exist for this Person, write the <OPTION> tag
                                                            if (strlen(strstr($sAssignedProperties, "," . $ormProperty->getProId() . ",")) == 0) {
                                                            ?>
                                                                <option value="<?= $ormProperty->getProId() ?>" data-pro_Prompt="<?= $ormProperty->getProPrompt() ?>" data-pro_Value=""><?= $ormProperty->getProName() ?></option>*/
                                                            <?php
                                                            }
                                                        } ?>
                                                    </select>
                                                </div>
                                                <div id="prompt-box" class="col-xs-12 col-md-7">
                                                </div>
                                                <div class="form-group col-xs-12 col-md-7">
                                                    <input type="submit" class="btn btn-primary"
                                                           value="<?= gettext("Assign") ?>" name="Submit2">
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php
    } ?>
                        </div>
                    </div>
                </div>
                <?php if ($_SESSION['bFinance']) {
        ?>
                <div role="tab-pane fade" class="tab-pane" id="finance">
                    <div class="main-box clearfix">
                        <div class="main-box-body clearfix">
                            <?php if ($ormAutoPayments->count() > 0) {
            ?>
            
                                <table class="table table-striped table-bordered" id="automaticPaymentsTable" cellpadding="5" cellspacing="0"  width="100%"></table>

                                <?php
        } ?>
                            <p align="center">
                                <a class="btn btn-primary"
                                   href="AutoPaymentEditor.php?AutID=-1&FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= gettext("Add a new automatic payment") ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <div role="tab-pane fade" class="tab-pane" id="pledges">
                    <div class="main-box clearfix">
                        <div class="main-box-body clearfix">
                                <input type="checkbox" name="ShowPledges" id="ShowPledges"
                                       value="1" <?php if ($_SESSION['sshowPledges']) {
                                      echo " checked";
                                  } ?>><?= gettext("Show Pledges") ?>
                                                          <input type="checkbox" name="ShowPayments" id="ShowPayments"
                                                                 value="1" <?php if ($_SESSION['sshowPayments']) {
                                      echo " checked";
                                  } ?>><?= gettext("Show Payments") ?>
                                  <label for="ShowSinceDate"><?= gettext("From") ?>:</label>
                                                          <?php
                                                          $showSince = "";
                                  if ($_SESSION['sshowSince'] != null) {
                                      $showSince = $_SESSION['sshowSince']->format('Y-m-d');
                                  } ?>
                                <input type="text" Name="Min" id="Min"
                                       value="<?= date("Y") ?>" maxlength="10" id="ShowSinceDate" size="15">
                                       
                                <label for="ShowSinceDate"><?= gettext("To") ?>:</label>
                                
                                <input type="text" Name="Max" id="Max"
                                       value="<?= date("Y") ?>" maxlength="10" id="ShowSinceDate" size="15">
                                <?php
                                $tog = 0;

        if ($_SESSION['sshowPledges'] || $_SESSION['sshowPayments']) {
        
        ?>
        
        <table id="pledgePaymentTable" class="table table-striped table-bordered"  cellspacing="0" width="100%"></table>

				<?php
        } // if bShowPledges

                                ?>
                            
                            <p align="center">
                                <a class="btn btn-primary"
                                   href="PledgeEditor.php?FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>&amp;PledgeOrPayment=Pledge"><?= gettext("Add a new pledge") ?></a>
                                <a class="btn btn-default"
                                   href="PledgeEditor.php?FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>&amp;PledgeOrPayment=Payment"><?= gettext("Add a new payment") ?></a>
                            </p>

                            <?php
    } ?>

                            <?php if ($_SESSION['bCanvasser']) {
        ?>

                            <p align="center">
                                <a class="btn btn-default"
                                   href="CanvassEditor.php?FamilyID=<?= $fam_ID ?>&amp;FYID=<?= $_SESSION['idefaultFY'] ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= MakeFYString($_SESSION['idefaultFY']) . gettext(" Canvass Entry") ?></a>
                            </p>
                        </div>
                    </div>
                </div>
            <?php
    } ?>
                <div role="tab-pane fade" class="tab-pane" id="notes">
          <ul class="timeline">
            <!-- note time label -->
            <li class="time-label">
              <span class="bg-yellow">
                <?php echo date_create()->format(SystemConfig::getValue('sDateFormatLong')) ?>
              </span>
            </li>
            <!-- /.note-label -->

            <!-- note item -->
            <?php foreach ($timelineService->getNotesForFamily($iFamilyID) as $item) {
                                        ?>
              <li>
                <!-- timeline icon -->
                <i class="fa <?= $item['style'] ?>"></i>

                <div class="timeline-item">
                  <span class="time">
                     <i class="fa fa-clock-o"></i> <?= $item['datetime'] ?>
                                          &nbsp;

                     <?php 
                     
                     if ($item['slim']) {
                       if ($item['editLink'] != '') {
                                                ?>
                        <a href="<?= $item['editLink'] ?>">
                          <button type="button" class="btn-xs btn-primary"><i class="fa fa-edit"></i></button>
                        </a>
                      <?php
                                            }
                                            if ($item['deleteLink'] != '') {
                                                ?>
                        <a href="<?= $item['deleteLink'] ?>">
                          <button type="button" class="btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                        </a>
                      <?php
                          }
                      } ?>                     
                     </span>
                  
                  <h3 class="timeline-header">
                    <?php if (in_array('headerlink', $item)) {
                                            ?>
                      <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                    <?php
                                        } else {
                                            ?>
                      <?= $item['header'] ?>
                    <?php
                                        } ?>
                  </h3>

                  <div class="timeline-body">
                    <?= $item['text'] ?>
                  </div>

                  <?php if (($_SESSION['bNotes']) && ($item['editLink'] != '' || $item['deleteLink'] != '')) {
                                            ?>
                    <div class="timeline-footer">
                    <?php if (!$item['slim']) {
                    ?>
                      <?php if ($item['editLink'] != '') {
                                                ?>
                        <a href="<?= $item['editLink'] ?>">
                          <button type="button" class="btn btn-primary"><i class="fa fa-edit"></i></button>
                        </a>
                      <?php
                                            }
                                            if ($item['deleteLink'] != '') {
                                                ?>
                        <a href="<?= $item['deleteLink'] ?>">
                          <button type="button" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                        </a>
                      <?php
                                            } ?>
                    </div>
                  <?php
                                        } ?>
                  <?php
                                  } ?>
                </div>
              </li>
            <?php
                                    } ?>
            <!-- END timeline item -->
          </ul>
        </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="photoUploader"></div>

<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog" aria-labelledby="delete-Image-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm Delete") ?></h4>
            </div>

            <div class="modal-body">
                <p><?= gettext("You are about to delete the profile photo, this procedure is irreversible.") ?></p>

                <p><?= gettext("Do you want to proceed?") ?></p>
            </div>


            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
                <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirm-verify" tabindex="-1" role="dialog" aria-labelledby="confirm-verify-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"
                    id="confirm-verify-label"><?= gettext("Request Family Info Verification") ?></h4>
            </div>
            <div class="modal-body">
                <b><?= gettext("Select how do you want to request the family information to be verified") ?></b>
                <p>
                    <?php if (count($sFamilyEmails) > 0) {
        ?>
                <p><?= gettext("You are about to email copy of the family information in pdf to the following emails") ?>
                <ul>
                    <?php foreach ($sFamilyEmails as $tmpEmail) {
            ?>
                        <li><?= $tmpEmail ?></li>
                        <?php
        } ?>
                </ul>
                </p>
            </div>
            <?php
    } ?>
            <div class="modal-footer text-center">
                <?php if (count($sFamilyEmails) > 0 && !empty(SystemConfig::getValue('sSMTPHost'))) {
        ?>
                    <button type="button" id="onlineVerify"
                            class="btn btn-warning warning"><i
                                class="fa fa-envelope"></i> <?= gettext("Online Verification") ?>
                    </button>
                    <?php
    } ?>
                <button type="button" id="verifyDownloadPDF"
                        class="btn btn-info"><i class="fa fa-download"></i> <?= gettext("PDF Report") ?></button>
                <button type="button" id="verifyNow"
                        class="btn btn-success"><i class="fa fa-check"></i> <?= gettext("Verified In Person") ?>
                </button>
            </div>
        </div>
    </div>

    <script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery-photo-uploader/PhotoUploader.js"></script>
    <script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyView.js" ></script>
    <script src="<?= SystemURLs::getRootPath() ?>/skin/js/MemberView.js" ></script>
        
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        window.CRM.currentActive = <?= (empty($fam_DateDeactivated) ? 'true' : 'false') ?>;
        var dataT = 0;
        var dataPaymentTable = 0;
        var pledgePaymentTable = 0;
        
        $(document).ready(function () {
            $("#activateDeactivate").click(function () {
                console.log("click activateDeactivate");
                popupTitle = (window.CRM.currentActive == true ? "<?= gettext('Confirm Deactivation') ?>" : "<?= gettext('Confirm Activation') ?>" );
                if (window.CRM.currentActive == true) {
                    popupMessage = "<?= gettext('Please confirm deactivation of family') . ': ' . $fam_Name ?>";
                }
                else {
                    popupMessage = "<?= gettext('Please confirm activation of family') . ': ' . $fam_Name  ?>";
                }

                bootbox.confirm({
                    title: popupTitle,
                    message: '<p style="color: red">' + popupMessage + '</p>',
                    callback: function (result) {
                        if (result) {
                            $.ajax({
                                method: "POST",
                                url: window.CRM.root + "/api/families/" + window.CRM.currentFamily + "/activate/" + !window.CRM.currentActive,
                                dataType: "json",
                                encode: true
                            }).done(function (data) {
                                if (data.success == true)
                                    window.location.href = window.CRM.root + "/FamilyView.php?FamilyID=" + window.CRM.currentFamily;

                            });
                        }
                    }
                });
            });

            $("#deletePhoto").click(function () {
                $.ajax({
                    type: "POST",
                    url: window.CRM.root + "/api/families/" + window.CRM.currentFamily + "/photo",
                    encode: true,
                    dataType: 'json',
                    data: {
                        "_METHOD": "DELETE"
                    }
                }).done(function (data) {
                    location.reload();
                });
            });

            window.CRM.photoUploader = $("#photoUploader").PhotoUploader({
                url: window.CRM.root + "/api/families/" + window.CRM.currentFamily + "/photo",
                maxPhotoSize: window.CRM.maxUploadSize,
                photoHeight: <?= SystemConfig::getValue("iPhotoHeight") ?>,
                photoWidth: <?= SystemConfig::getValue("iPhotoWidth") ?>,
                done: function (e) {
                    location.reload();
                }
            });

            contentExists(window.CRM.root + "/api/families/" + window.CRM.currentFamily + "/photo", function (success) {
                if (success) {
                    $("#view-larger-image-btn").removeClass('hide');

                    $("#view-larger-image-btn").click(function () {
                        bootbox.alert({
                            title: "<?= gettext('Family Photo') ?>",
                            message: '<img class="img-rounded img-responsive center-block" src="<?= SystemURLs::getRootPath() ?>/api/families/' + window.CRM.currentFamily + '/photo" />',
                            backdrop: true
                        });
                    });
                }
            });
            
            $(".input-family-properties").select2({ 
                  language: window.CRM.shortLocale
            });
        });
    </script>

    <?php require "Include/Footer.php" ?>
