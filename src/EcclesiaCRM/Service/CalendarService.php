<?php
/*******************************************************************************
 *
 *  filename    : CalendarService.php
 *  last change : 2018-05-04
 *  This code is under copyright not under MIT Licence
 *  copyright   : 2018 Philippe Logel all right reserved not MIT licence
 *                This code can't be incoprorated in another software without any authorizaion
 *
 ******************************************************************************/
 
namespace EcclesiaCRM\Service;

use EcclesiaCRM\EventQuery;
use EcclesiaCRM\EventTypesQuery;
use EcclesiaCRM\FamilyQuery;
use EcclesiaCRM\PersonQuery;
use EcclesiaCRM\Person;
use Propel\Runtime\ActiveQuery\Criteria;
use EcclesiaCRM\EventCountsQuery;
use EcclesiaCRM\Utils\MiscUtils;
use Sabre\CalDAV;
use Sabre\DAV;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Sharing;
use Sabre\DAV\Xml\Element\Sharee;
use Sabre\VObject;
use EcclesiaCRM\MyVCalendar;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL;
use EcclesiaCRM\MyPDO\CalDavPDO;
use EcclesiaCRM\MyPDO\PrincipalPDO;
use Propel\Runtime\Propel;

class CalendarService
{
    public function getEventTypes()
    {
        $eventTypes = [];
        array_push($eventTypes, ['Name' => gettext('Event'), 'backgroundColor' =>'#f39c12']);
        array_push($eventTypes, ['Name' => gettext('Birthday'), 'backgroundColor' =>'#f56954']);
        array_push($eventTypes, ['Name' => gettext('Anniversary'), 'backgroundColor' =>'#0000ff']);
        return $eventTypes;
    }
    public function getEvents($start, $end)
    {
        $origStart = $start;
        $origEnd   = $end;
        
        $events = [];
        $startDate = date_create($start);
        $endDate = date_create($end);
        $startYear = $endYear = '1900';
        $endsNextYear = false;
        if ($endDate->format('Y') > $startDate->format('Y')) {
            $endYear = '1901';
            $endsNextYear = true;
        }
        $firstYear = $startDate->format('Y');
        
        
        if ($_SESSION['bSeePrivacyData'] || $_SESSION['user']->isAdmin()) {
          $peopleWithBirthDays = PersonQuery::create()
            ->JoinWithFamily();
          
          // get the first and the last month
          $firstMonth = $startDate->format('m');
          $endMonth = $endDate->format('m');
        
          $month = $firstMonth;
          
          $peopleWithBirthDays->filterByBirthMonth($firstMonth);// the event aren't more than a month
        
          while ($month != $endMonth) {// we loop to have all the months from the first in the start to the end
            $month += 1;
            if ($month == 13) {
                $month = 1;
            }
            if ($month == 0) {
              $month = 1;
            }
            $peopleWithBirthDays->_or()->filterByBirthMonth($month);// the event aren't more than a month
          }
        
          $peopleWithBirthDays->find();
          foreach ($peopleWithBirthDays as $person) {
              $year = $firstYear;
              if ($person->getBirthMonth() == 1 && $endsNextYear) {
                  $year = $firstYear + 1;
              }
              $start = date_create($year.'-'.$person->getBirthMonth().'-'.$person->getBirthDay());
              $event = $this->createCalendarItem('birthday','<i class="fa fa-birthday-cake"></i>',
              $person->getFullName()." ".$person->getAge(), $start->format(DATE_ATOM), '', $person->getViewURI());
              array_push($events, $event);
          }
        
          // we search the Anniversaries
          $Anniversaries = FamilyQuery::create()
            ->filterByWeddingDate(['min' => '0001-00-00']) // a Wedding Date
            ->filterByDateDeactivated(null, Criteria::EQUAL) //Date Deactivated is null (active)
            ->find();
      
          $curYear = date('Y');
          $curMonth = date('m');
          foreach ($Anniversaries as $anniversary) {
              $year = $curYear;
              if ($anniversary->getWeddingMonth() < $curMonth) {
                  $year = $year + 1;
              }
              $start = $year.'-'.$anniversary->getWeddingMonth().'-'.$anniversary->getWeddingDay();
              $event = $this->createCalendarItem('anniversary', '<i class="fa fa-birthday-cake"></i>', $anniversary->getName(), $start, '', $anniversary->getViewURI());
              array_push($events, $event);
          }
        }
        
        
        // new way to manage events
        // we get the PDO for the Sabre connection from the Propel connection
        $pdo = Propel::getConnection();         
        
        // We set the BackEnd for sabre Backends
        $calendarBackend = new CalDavPDO($pdo->getWrappedConnection());
        $principalBackend = new PrincipalPDO($pdo->getWrappedConnection());
        // get all the calendars for the current user
        
        $calendars = $calendarBackend->getCalendarsForUser('principals/'.strtolower($_SESSION['user']->getUserName()),"displayname",false);
        
        foreach ($calendars as $calendar) {
          $calendarName        = $calendar['{DAV:}displayname'];
          $calendarColor       = $calendar['{http://apple.com/ns/ical/}calendar-color'];
          $writeable           = ($calendar['share-access'] == 1 || $calendar['share-access'] == 3)?true:false;
          $calendarUri         = $calendar['uri'];
          $calendarID          = $calendar['id'];
          $groupID             = $calendar['grpid'];
          
          $icon = "";

          if ($writeable) {
            $icon = '<i class="fa fa-pencil"></i>';
          }

          if ($groupID > 0) {
            $icon .= '<i class="fa fa-users"></i>';
          } 
          
          if ($calendar['share-access'] == 2 || $calendar['share-access'] == 3) {
            $icon .= '<i class="fa  fa-share"></i>';
          } else if ($calendar['share-access'] == 1 && $groupID == 0) {
            $icon .= '<i class="fa fa-user"></i>';
          }          
          
          if ($calendar['present'] == 0 || $calendar['visible'] == 0) {// this ensure the calendars are present or not
            continue;
          }
          
          // we get all the events for the Cal
          $eventsForCal = $calendarBackend->getCalendarObjects($calendar['id']);
          
          foreach ($eventsForCal as $eventForCal) {
            $evnt = EventQuery::Create()->filterByInActive('false')->findOneById($eventForCal['id']);
            
            if ($evnt != null) {
            
              $calObj = $calendarBackend->getCalendarObject($calendar['id'],$eventForCal['uri']);
      
              $freqEvents = $calendarBackend->extractCalendarData($calObj['calendardata'],$origStart,$origEnd);
              
              if ($freqEvents == null) {
                continue;
              }
            
              $title = $evnt->getTitle();
              $desc  = $evnt->getDesc();
              $start = $evnt->getStart('Y-m-d H:i:s');
              $end   = $evnt->getEnd('Y-m-d H:i:s');
              $id    = $evnt->getID();
              $subid = 1;
              $type  = $evnt->getType();
              $desc  = $evnt->getDesc();
              $grpID = $evnt->getGroupId();
              $loc   = "";
              $text  = $evnt->getText();
              $calID = $calendar['id'];
              $fEvnt = false;
    
              foreach ($freqEvents as $key => $value) {      
                if ($key == 'freq' && $value != 'none') {        
                  $fEvnt = true;              
                } elseif ($key == 'freqEvents' && $fEvnt == true) { // we are in front of a recurrence event !!!
                  foreach ($value as $freqValue) {
                    $title        = $freqValue['SUMMARY'];
                    $start        = $freqValue['DTSTART'];
                    $end          = $freqValue['DTEND'];
                    $reccurenceID   = $freqValue['RECURRENCE-ID'];
                  
                    $event = $this->createCalendarItem('event',$icon,
                      $title, $start, $end, 
                     '',$id,$type,$grpID,
                      $desc,$text,$calID,$calendarColor,$subid++,1,$reccurenceID,$writeable);// only the event id sould be edited and moved and have custom color
            
                    array_push($events, $event);
                  }
                }
              }
            
              if ($fEvnt == false) {
                $event = $this->createCalendarItem('event',$icon,
                  $title, $start, $end, 
                 '',$id,$type,$grpID,
                  $desc,$text,$calID,$calendarColor,0,0,0,$writeable);// only the event id sould be edited and moved and have custom color
            
                array_push($events, $event);
              }
              
            }            
          }
        }
        return $events;
    }
    
    public function createCalendarItem($type, $icon, $title, $start, $end, $uri,$eventID=0,$eventTypeID=0,$groupID=0,$desc="",$text="",$calendarid=null,$backgroundColor = null,$subid = 0,$recurrent=0,$reccurenceID = '',$writeable=false)
    {
        $event = [];
        switch ($type) {
          case 'birthday':
            $event['backgroundColor'] = '#f56954';
            break;
          case 'anniversary':
            $event['backgroundColor'] = '#0000ff';
            break;
          default:
            $event['backgroundColor'] = '#eeeeee';
        }
        
        $event['title'] = $title;
        $event['start'] = $start;
        $event['icon'] = $icon;        
        $event['type'] = $type;
        
        if ($end != '') {
            $event['end'] = $end;
            $event['allDay'] = false;
        } else {
            $event['allDay'] = true;
        }
        if ($uri != '') {
            $event['url'] = $uri;
        }

        if ($type == 'event') {
          $event['eventID'] = $eventID;
          $event['eventTypeID'] = $eventTypeID;
          $event['groupID'] = $groupID;
          $event['Desc'] = $desc;
          $event['Text'] = $text;   
          $event['recurrent'] = $recurrent;
          $event['writeable'] = $writeable;
          
          if ($calendarid != null) {
            $event['calendarID'] = $calendarid;//[$calendarid[0],$calendarid[1]];//$calendarid;   
          }
          
          if ($backgroundColor != null) {
            $event['backgroundColor'] = $backgroundColor;
          }
          
          $event['subID'] = $subid; 
          
          $event['reccurenceID'] = '';
          if (!empty($reccurenceID) ) {  
            $event['reccurenceID'] = $reccurenceID; 
          }
          
          
          $eventCounts = EventCountsQuery::Create()->findByEvtcntEventid($eventID);
          
          $event['EventCounts'] = $eventCounts->toArray();
        }
        
        
        return $event;
    }
}