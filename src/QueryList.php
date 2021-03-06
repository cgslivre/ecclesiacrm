<?php
/*******************************************************************************
 *
 *  filename    : QueryList.php
 *  last change : 2003-01-07
 *  website     : http://www.ecclesiacrm.com
 *  copyright   : Copyright 2001, 2002 Deane Barker
 *  Copyright   : 2018 Philippe Logel
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

//Set the page title
$sPageTitle = gettext('Query Listing');

$sSQL = 'SELECT * FROM query_qry LEFT JOIN query_type ON query_qry.qry_Type_ID = query_type.qry_type_id ORDER BY query_qry.qry_Type_ID, query_qry.qry_Name';
$rsQueries = RunQuery($sSQL);

$aFinanceQueries = explode(',', $aFinanceQueries);

require 'Include/Header.php';

?>
<div class="box box-primary">
    <div class="box-body">
        <p class="text-right">
            <?php
                if ($_SESSION['user']->isAdmin()) {
                    echo '<a href="QuerySQL.php" class="text-red">'.gettext('Run a Free-Text Query').'</a>';
                }
            ?>
        </p>
        
        <ul>
            <?php 
                $query_type = 0;
                $first_time = true;
                $open_ul = false;
                
                while ($aRow = mysqli_fetch_array($rsQueries)) {?>            
                <?php
                    extract($aRow);
                    
                    if ($qry_Type_ID != $query_type) {
                      if ($first_time == false) { ?>
                        </ul></li>
                      <?php
                      }
                      ?>
                      <li><b><?= mb_convert_case(gettext($qry_type_Category), MB_CASE_UPPER, "UTF-8") ?></b><br>
                      <ul>
                      <?php
                      $query_type = $qry_Type_ID;
                      $first_time = false;
                    }
                    ?>
                    <li>
                    <?php
                    // Filter out finance-related queries if the user doesn't have finance permissions
                    if ($_SESSION['user']->isFinanceEnabled() || !in_array($qry_ID, $aFinanceQueries)) {
                        // Display the query name and description
                        ?>
                        <a href="QueryView.php?QueryID=<?= $qry_ID ?>"><?= gettext($qry_Name) ?></a>:
                        <br>
                        <?= gettext($qry_Description) ?>
                    <?php
                    }
                    ?>
                    </li>
            <?php } ?>
        </ul>
    </div>
    
</div>
<?php

require 'Include/Footer.php';