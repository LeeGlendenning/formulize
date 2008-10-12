<?php

###############################################################################
##     Formulize - ad hoc form creation and reporting module for XOOPS       ##
##                    Copyright (c) 2004 Freeform Solutions                  ##
###############################################################################
##                    XOOPS - PHP Content Management System                  ##
##                       Copyright (c) 2000 XOOPS.org                        ##
##                          <http://www.xoops.org/>                          ##
###############################################################################
##  This program is free software; you can redistribute it and/or modify     ##
##  it under the terms of the GNU General Public License as published by     ##
##  the Free Software Foundation; either version 2 of the License, or        ##
##  (at your option) any later version.                                      ##
##                                                                           ##
##  You may not change or alter any portion of this comment or credits       ##
##  of supporting developers from this source code or any supporting         ##
##  source code which is considered copyrighted (c) material of the          ##
##  original comment or credit authors.                                      ##
##                                                                           ##
##  This program is distributed in the hope that it will be useful,          ##
##  but WITHOUT ANY WARRANTY; without even the implied warranty of           ##
##  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            ##
##  GNU General Public License for more details.                             ##
##                                                                           ##
##  You should have received a copy of the GNU General Public License        ##
##  along with this program; if not, write to the Free Software              ##
##  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA ##
###############################################################################
##  Author of this file: Freeform Solutions 					     ##
##  Project: Formulize                                                       ##
###############################################################################

// this file contains the logic for the displayCalendar function and related functions.

global $xoopsConfig;
// load the formulize language constants if they haven't been loaded already
	if ( file_exists(XOOPS_ROOT_PATH."/modules/formulize/language/".$xoopsConfig['language']."/main.php") ) {
		include_once XOOPS_ROOT_PATH."/modules/formulize/language/".$xoopsConfig['language']."/main.php";
	} else {
		include_once XOOPS_ROOT_PATH."/modules/formulize/language/english/main.php";
	}

// main function
// formframes - array of forms and/or frameworks in use
// mainforms - array of mainforms for each framework in use (must have empty places corresponding to any plain form IDs in formframes
// viewHandles - array of the element ids or element handles (if in a framework) corresponding to the element where the data to be printed on the calendar comes from .... can be an array with multiple values.  If it is an array, then the values in all fields are used, separated with commas.
// dateHandles - array similar to viewHandles, but for the element that contains the data information to base the placement of the data in the calendar on .... can be an array with two values.  If it is an array, the first value is the start field and the second value is the end field.  This is for using a range of dates.
// filters -- array of filters that can be passed directly to the data extraction, to support viewing only a subset of the data at any one time.
// viewPrefixs - array of text to prepend to the beginning of the viewHandles text when displaying an entry on the calendar (meant for situations like "Booking Deadline: September Conference")
// hidden -- array of values to be put into hidden elements in the form (so they are preserved when the user changes months)
// $scopes - array of the scopes to use when querying for the data to display in the calendar.  Valid values are: mine, group, all, or list of group ids formatted with starting and ending commas, plus commas in between, ie: ,1,3,
// $type - the kind of calendar to display, ie: month, week, year...??  
// $start - the starting view for the calendar.  if a month, this is the year and month, ie: 2005-07.  (not sure what else it could/should be, only supporting month views to start with.)
//
// The idea behind all these arrays is that you can hook up your calendar to multiple sets of data.
// So, key 0 in each array represents all the settings for that set of data.  
// key 1 and key 2 and so on in each array, represent other sets of data.
// ie: formframes[2] and viewHandles[2]...that's the form or framework used for dataset 2, and the handle for the element in that dataset which should be displayed on the calendar.
// Note about scopes: scopes must be converted to the format described for the $scope param for the getData function

function displayCalendar($formframes, $mainforms="", $viewHandles, $dateHandles, $filters, $viewPrefixes, $scopes, $hidden, $type="month", $start="", $multiPageData="") {



	global $xoopsDB, $xoopsUser;
	include_once XOOPS_ROOT_PATH.'/modules/formulize/include/functions.php';

    global $xoopsTpl;

	// Set some required variables
	$mid = getFormulizeModId();
	for($i=0;$i<count($formframes);$i++) {
		unset($fid);
		unset($frid);
		if($mainforms[$i]) {
			list($fid, $frid) = getFormFramework($formframes[$i], $mainforms[$i]);
		} else {
			list($fid, $frid) = getFormFramework($formframes[$i]);
		}
		$fids[] = $fid;
		$frids[] = $frid;
	}


	$gperm_handler = &xoops_gethandler('groupperm');
	$member_handler =& xoops_gethandler('member');
	$groups = $xoopsUser ? $xoopsUser->getGroups() : array(0=>XOOPS_GROUP_ANONYMOUS);
	$uid = $xoopsUser->getVar('uid');


	foreach($fids as $thisFid) { // check that the user is allowed to see all the fids
		if(!$scheck = security_check($thisFid, "", $uid, "", $groups, $mid, $gperm_handler)) {
			print "<p>" . _NO_PERM . "</p>";
			return;
		}
	}                  

	$currentURL = getCurrentURL();

	// get the current view, ie: the month
	if($_POST['calview']) { // if we're recieving a view from a form submission...
		$settings['calview'] = $_POST['calview'];
	} else {
		if(!$start) {
			// nothing passed from form, and no default value specified, so use current date
			$today = getDate();
			if($today['mon']<10) {$today['mon'] = "0" . $today['mon']; }
			$settings['calview'] = $today['year'] . "-" . $today['mon'];
		} else {
			$settings['calview'] = $start;
		}
	}
	$settings['calfrid'] = $_POST['calfrid'];
	$settings['calfid'] = $_POST['calfid'];
	$settings['calhidden'] = $hidden;

	// check to see if a switch to a form has been requested
	$settings['ventry'] = $_POST['ventry'];
	if($settings['ventry']) {
		if($_POST['ventry'] == "addnew") {
			$this_ent = "";
			$dateOverride = $_POST['adddate'];
		} elseif($_POST['ventry'] == "proxy") { // support for proxies not currently written
			$this_ent = "proxy";
		} else {
			$this_ent = $_POST['ventry'];
		}
		if($_POST['calfrid']) {
			if(isset($multiPageData[$_POST['calfid']])) {
				if(is_numeric($multiPageData[$_POST['calfid']])) { // numeric value indicates a screen id
					$screenData = readScreenId($multiPageData[$_POST['calfid']], $_POST['calfid']);
					if(is_array($screenData)) {
						$multiPageData = $screenData;
					}
				}
				include_once XOOPS_ROOT_PATH . "/modules/formulize/include/formdisplaypages.php";
				displayFormPages($_POST['calfrid'], $this_ent, $_POST['calfid'], $multiPageData[$_POST['calfid']]['pages'], $multiPageData[$_POST['calfid']]['conditions'], $multiPageData[$_POST['calfid']]['introtext'], $multiPageData[$_POST['calfid']]['thankstext'], $currentURL, _formulize_CAL_RETURNFROMMULTI, $settings, $dateOverride, $multiPageData[$_POST['calfid']]['printall']);
			} else {
				displayForm($_POST['calfrid'], $this_ent, $_POST['calfid'], $currentURL, "", $settings, "", $dateOverride, 1, 1); // first "" is the done text, second is the onetoonetitles, last two 1s are the overrides for multi form behaviour 
			}
			return;
		} else {
			if(isset($multiPageData[$_POST['calfid']])) {
				if(is_numeric($multiPageData[$_POST['calfid']])) { // numeric value indicates a screen id
					$screenData = readScreenId($multiPageData[$_POST['calfid']], $_POST['calfid']);
					if(is_array($screenData)) {
						$multiPageData = $screenData;
					}
				}
				include_once XOOPS_ROOT_PATH . "/modules/formulize/include/formdisplaypages.php";
				displayFormPages($_POST['calfid'], $this_ent, "", $multiPageData[$_POST['calfid']]['pages'], $multiPageData[$_POST['calfid']]['conditions'], $multiPageData[$_POST['calfid']]['introtext'], $multiPageData[$_POST['calfid']]['thankstext'], $currentURL, _formulize_CAL_RETURNFROMMULTI, $settings, $dateOverride, $multiPageData[$_POST['calfid']]['printall']);
			} else {
				displayForm($_POST['calfid'], $this_ent, "", $currentURL, "", $settings, "", $dateOverride, 1, 1); // "" is the done text
			}
			return;
		}
	}

	// handle deletion if requested, added sept 18 2005
	if($_POST['delentry']) {
		deleteEntry($_POST['delentry'], $_POST['delfrid'], $_POST['delfid'], $gperm_handler, $member_handler, $mid);
	}

	// get the data for all the fids
	// 1. convert the scopes for each one
	// 2. do the extraction (filter by calview)

	include_once XOOPS_ROOT_PATH . "/modules/formulize/include/extract.php";
	for($i=0;$i<count($fids);$i++) {
		$scope="";
		if($scopes[$i]) {
			$scope = buildScope($scopes[$i], $member_handler, $gperm_handler, $uid, $groups, $fids[$i], $mid);
		}
		if(is_array($dateHandles[$i])) {
			$dateField = $dateHandles[$i][0];
			$dateField2 = $dateHandles[$i][1];
		} else {
			$dateField = $dateHandles[$i];
			$dateField2 = "";
		}
		if(!$frids[$i]) {
/* 			NUMERIC ELE-ID IS ACCEPTED BY EXTRACTION LAYER SO FOR REGULAR FORMS, THIS IS PREFERRED, SINCE LANGUAGE TAGS IN CAPTIONS WILL THROW OFF THE PARSING OF THE FILTER! -- April 7 2006
			$caption = q("SELECT ele_caption FROM " . DBPRE . "formulize WHERE ele_id = '" . $dateField . "'"); 
			$ffcaption = str_replace ("&#039;", "`", $caption[0]['ele_caption']);
			$ffcaption = str_replace ("&quot;", "`", $ffcaption);
			$ffcaption = str_replace ("'", "`", $ffcaption);
			$filterDH = $ffcaption;
			if($dateField2) {
				$caption = q("SELECT ele_caption FROM " . DBPRE . "formulize WHERE ele_id = '" . $dateField2 . "'"); 
				$ffcaption = str_replace ("&#039;", "`", $caption[0]['ele_caption']);
				$ffcaption = str_replace ("&quot;", "`", $ffcaption);
				$ffcaption = str_replace ("'", "`", $ffcaption);
				$filterDH2 = $ffcaption;
			} else {
				$filterDH2 = "";
			}
*/
			$filterDH = $dateField;
			$filterDH2 = $dateField2;
		} else {
			$filterDH = $dateField;
			$filterDH2 = $dateField2;
		}

		// new, complex filter format is:
		// $filter[0][0] -- andor setting for filter 0
		// $filter[0][1] -- filter for filter 0
    $filter = array();
		$filter[0][0] = "OR";
		$filter[0][1] = $filterDH . "/**/" . $settings['calview'];
		if($filterDH2) {
			$filter[0][1] .= "][" . $filterDH2 . "/**/" . $settings['calview'];
		}
		if($filters[$i]) {
			$filter[1][0] = "AND";
			$filter[1][1] = $filters[$i];
		}


		$data[$i] = getData($frids[$i], $fids[$i], $filter, "AND", $scope);
		$data[$i] = resultSort($data[$i], $dateField);
    
   
	}
	 
	// need the formatting magic to go here, to whip it all into a nice calendar
	// basic display of data is below
	// demonstrates linking to a form for updating/viewing that entry
	// demonstrates altering the calview setting to change months

	// need to do something a little more complex for adding a new entry, since we have to know for which fid/frid pair the add operation is being requested.
	// probably best to leave out adding for now and leave it as a future feature.  It can always be custom added within a pageworks page if necessary for a particular calendar


	$rights = $gperm_handler->checkRight("add_own_entry", $fid, $groups, $mid);


	// information to pass to the template
	global $calendarData;
    
	// initialize language constants
	global $arrayMonthNames;
	global $arrayWeekNames;
	global $dateMonthStartDay;

	$arrayMonthNames = array(_formulize_CAL_MONTH_01, _formulize_CAL_MONTH_02, _formulize_CAL_MONTH_03, _formulize_CAL_MONTH_04, _formulize_CAL_MONTH_05, _formulize_CAL_MONTH_06, _formulize_CAL_MONTH_07, _formulize_CAL_MONTH_08, _formulize_CAL_MONTH_09, _formulize_CAL_MONTH_10, _formulize_CAL_MONTH_11, _formulize_CAL_MONTH_12); 
	if($type == "mini_month")
    {    
		$arrayWeekNames = array(_formulize_CAL_WEEK_1_3ABRV, _formulize_CAL_WEEK_2_3ABRV, _formulize_CAL_WEEK_3_3ABRV, _formulize_CAL_WEEK_4_3ABRV, _formulize_CAL_WEEK_5_3ABRV, _formulize_CAL_WEEK_6_3ABRV, _formulize_CAL_WEEK_7_3ABRV);
	}
    else
    {    
	$arrayWeekNames = array(_formulize_CAL_WEEK_1, _formulize_CAL_WEEK_2, _formulize_CAL_WEEK_3, _formulize_CAL_WEEK_4, _formulize_CAL_WEEK_5, _formulize_CAL_WEEK_6, _formulize_CAL_WEEK_7);
	}

	// convert string date into parts 
	$arrayDate = getdate(strtotime($settings['calview'] . "-01"));
	$dateMonth = $arrayDate["mon"]; 
	$dateDay = $arrayDate["mday"];
	$dateYear = $arrayDate["year"];

	// get the number of days in the month.
	$dateMonthDays = days_in_month($dateMonth, $dateYear);

	// get the month's first week start day.
	$dateMonthStartDay = $arrayDate["wday"]; 

    // get the number of weeks.
    $dateMonthWeeks = week_in_month($dateMonthDays) + 1;



    // intialize MONTH template information
    // each cell is an array:
    // [0] - is control information, where each entry is an array:
    //     [0] - day number
    //     [1] - send date
    // [1] - is an array containing all items, where each item is also an array:
    //     [0] - $ids[0]
    //     [1] - $frids[$i]
    //     [2] - $fids[$i]
    //     [3] - $textToDisplay
    //     [4] - true/false based on user's right to delete this item (based on either delete own, or delete others permission)

	if($type == "month"
		|| $type == "mini_month"
		|| $type == "micro_month")
    {    
	    // initialize grid: convert the data set into a grid of 7 columns for  
	    //  days and a row for each week
	    $displayDay = "";
	    for($intWeeks = 0; $intWeeks < $dateMonthWeeks; $intWeeks++)
	    {
	        $calendarData[$intWeeks] = array();
	        for($intDays = 0; $intDays < 7; $intDays++)
	        {
	            // check to see if the processing day is the start day.
	            if($intWeeks == 0
	                && $displayDay == "")
	            {
	                if($intDays == $dateMonthStartDay)
	                {
	                    $displayDay = 1; 
	                }
	            }
	            else
	            {
	                if($displayDay != "")
	                {
	                    $displayDay++;
	                
	                    if($displayDay > $dateMonthDays)
	                    {
	                        $displayDay = "";
	                    }
	                }
	            }

	            $calendarData[$intWeeks][$intDays] = array();
	            $calendarData[$intWeeks][$intDays][0][0] = $displayDay;
	            $calendarData[$intWeeks][$intDays][0][1] = $dateYear . "-" . $dateMonth . "-" . (($displayDay < 10) ? "0" . $displayDay : $displayDay);
	            //$calendarData[$intWeeks][$intDays][1] = array();
	        }
	    }    

		// Initialize template variables
	    $xoopsTpl->assign('previousMonth', ((($dateMonth - 1) < 1) ? ($dateYear - 1) . "-12" : $dateYear . "-" . ((($dateMonth - 1) < 10) ? "0" . ($dateMonth - 1) : ($dateMonth - 1))));
	    $xoopsTpl->assign('nextMonth', ((($dateMonth + 1) > 12) ? ($dateYear + 1) . "-01" : $dateYear . "-" . ((($dateMonth + 1) < 10) ? "0" . ($dateMonth + 1) : ($dateMonth + 1))));

	    $monthSelector = array();
	    $numberOfMonths = count($arrayMonthNames);
	    for($intMonth = 0; $intMonth < $numberOfMonths; $intMonth++)
	    {
	        $monthName = $arrayMonthNames[$intMonth];
	        $monthSelector[((($intMonth + 1) < 10) ? "0" . ($intMonth + 1) : ($intMonth + 1))] = $monthName; 
	    }
	    $xoopsTpl->assign('monthSelector', $monthSelector);

	    $yearSelector = array();
	    $startYear = $dateYear - 4;
	    $endYear = $dateYear + 3;
	    for($intYear = $startYear; $intYear <= $endYear; $intYear++)
	    {
	        $yearSelector[] = $intYear;    
	    }
	    $xoopsTpl->assign('yearSelector', $yearSelector);
	}
    


	// process data set(s)
	for($i=0;$i<count($data);$i++) {

		// determine if the user has the right to delete items in this form -- added September 18 2005
		$delown = 0;
		$delother = 0;
		$delown = $gperm_handler->checkRight("delete_own_entry", $fids[$i], $groups, $mid);
		$delother = $gperm_handler->checkRight("delete_other_entries", $fids[$i], $groups, $mid);

		foreach($data[$i] as $id=>$entry) {
      
      
      
	            if(!$frids[$i]) {
				if(is_array($viewHandles[$i])) {
					$formhandle = getFormHandleFromEntry($entry, $viewHandles[$i][0]);
				} else {
					$formhandle = getFormHandleFromEntry($entry, $viewHandles[$i]);
				}
			} else {
				$formhandle = $mainforms[$i];
			}
			$ids = internalRecordIds($entry, $formhandle);
			
        
            if(is_array($viewHandles[$i])) {
			$needsep = 0;

			// make sure that no data is keep from previous processing
	            $textToDisplay = "";

			foreach($viewHandles[$i] as $thisVH) {
				if($needsep) { $textToDisplay .= ", "; }
				$needsep = 1;
				$textToDisplay .= display($entry, $thisVH);
			}
		} else {
			$textToDisplay = display($entry, $viewHandles[$i]);
		}
		if($viewPrefixes[$i]) {
	            $textToDisplay = $viewPrefixes[$i] . $textToDisplay;
	      } 

            $calendarDataItem = array();
            $calendarDataItem[0] = $ids[0];
            $calendarDataItem[1] = $frids[$i];
            $calendarDataItem[2] = $fids[$i];
            $calendarDataItem[3] = $textToDisplay;
		if($i==0 AND (($uid == display($entry, "uid") AND $delown) OR ($uid != display($entry, "uid") AND $delother))) {
			$calendarDataItem[4] = true;
		} else {
			$calendarDataItem[4] = false;
		}

            // Layout	

	        if($type == "month"
	        	|| $type == "mini_month"
	        	|| $type == "micro_month")
	        {    
	            if(is_array($dateHandles[$i])) 
                {
	                $startValue = display($entry, $dateHandles[$i][0]);
	                $endValue = display($entry, $dateHandles[$i][1]);
                    
                    if($startValue && $endValue)
                    {
                    	//echo "$startValue - $endValue (Both)<br>";
                        
                        $startDate = strtotime($startValue);
                        $endDate = strtotime($endValue);
                        for($x=$startDate;$x<=$endDate;$x=$x+86400) 
                        {
	                        $arrayDate = getdate($x);
                            if($arrayDate["mon"] == $dateMonth)
                            {
	                            $calendarData = assignItem($arrayDate, $calendarDataItem, $calendarData);
                            }
                        }
                    }
					else if($startValue)
                    {
                    	//echo "$startValue (Only start)<br>";

                        $startDate = strtotime($startValue);
	                    $arrayDate = getdate($startDate);
	                    $calendarData = assignItem($arrayDate, $calendarDataItem, $calendarData);
                    }
					else
                    {
                    	//echo "$endValue (Only end)<br>";

                        $endDate = strtotime($endValue);
	                    $arrayDate = getdate($endDate);
	                    $calendarData = assignItem($arrayDate, $calendarDataItem, $calendarData);
                    }
	            } 
                else 
                {
	                $currentDate = display($entry, $dateHandles[$i]);
	                $arrayDate = getdate(strtotime($currentDate));
	                $calendarData = assignItem($arrayDate, $calendarDataItem, $calendarData);
	            }
	        }

			// Layout	
		}
	}

    
	// Layout	

    // Initialize common template variables
	$xoopsTpl->assign('cal_type', $type);

	$xoopsTpl->assign('rights', $rights);    
	$xoopsTpl->assign('frids', $frids[0]);    
	$xoopsTpl->assign('fids', $fids[0]);    

	$xoopsTpl->assign('addItem', _formulize_CAL_ADD_ITEM);

	$xoopsTpl->assign('rowStyleEven', true);
    
	$xoopsTpl->assign('MonthNames', $arrayMonthNames);
	$xoopsTpl->assign('WeekNames', $arrayWeekNames);

	$xoopsTpl->assign('dateMonthZeroIndex', $dateMonth - 1);
	$xoopsTpl->assign('dateMonth', $dateMonth);
	$xoopsTpl->assign('dateYear', $dateYear);

	$xoopsTpl->assign('currentURL', $currentURL);
	$xoopsTpl->assign('hidden', $hidden);
	$xoopsTpl->assign('calview', $settings['calview']);

	$xoopsTpl->assign('calendarData', $calendarData);

	$xoopsTpl->assign('delete', _formulize_DELETE);
	$xoopsTpl->assign('delconf', _formulize_DELCONF);
    
	// force template to be drawn
    $xoopsTpl->display("db:calendar_" . $type . ".html");

    // Layout
}


// THIS FUNCTION ASSIGNS AN ITEM TO THE MASTER ARRAY THAT GETS SENT TO THE TEMPLATE
function assignItem($arrayDate, $calendarDataItem, $calendarData) {
	$dateDay = $arrayDate["mday"];
	$dateMonthWeekDay = $arrayDate["wday"];
	$dateMonthWeek = week_in_month($dateDay);

	// debug
	//print "$dateDay :: $dateMonthWeek :: $dateMonthWeekDay :: $calendarDataItem";

	$calendarData[$dateMonthWeek][$dateMonthWeekDay][1][] = $calendarDataItem;

	return $calendarData;
}



/* 
 * days_in_month($month, $year) 
 * Returns the number of days in a given month and year, taking into account leap years. 
 * 
 * $month: numeric month (integers 1-12) 
 * $year: numeric year (any integer) 
 * 
 * Prec: $month is an integer between 1 and 12, inclusive, and $year is an integer. 
 * Post: none 
 */ 
// corrected by ben at sparkyb dot net 
function days_in_month($month, $year) 
{ 
	// calculate number of days in a month 
	return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31); 
}


function week_in_month($day)
{
    global $dateMonthStartDay;
    
	//$value = (int)((($dateMonthStartDay + 1) + $day) / 7);
	$value = (int)((($dateMonthStartDay - 1) + $day) / 7);

    //echo "<br>" . $dateMonthStartDay . "::" . $value . "<br>";
    
    return $value;    
}




// displayFilter(2, "statusform", "statusvalue", 24, array("Choose a status" => array("Show All", "")));
function displayFilter($page, $name, $id, $ele_id, $overrides = "")
{

	print "\n<p><form name=\"$name\" action=\"" . XOOPS_URL . "/modules/pageworks/index.php?page=$page\" method=\"post\">\n";

	print buildFilter($id, $ele_id, "", $name, $overrides);

	print "<input type=hidden name=calview value=" . $_POST['calview'] . ">\n";
	print "</form></p>\n";
}

// THIS FUNCTION ACTUALLY BUILDS THE SELECT FORM ELEMENT AND RETURNS IT AS A STRING
// Used to create a drop down list that can act as a filter in a user interface
// The dropdown list is made up of the options for the specified ele_id
// If the dropdown list is a linked selectbox, the values can be optionally limited by the "limit" params, based on values in another field in each entry that underlies the link
// ie: build a filter with the names of all activity entries, but limit it to activity entries where the date of the activity is 2007
function buildFilter($id, $ele_id, $defaulttext="", $name="", $overrides=array(0=>""), $subfilter=false, $linked_ele_id = 0, $linked_data_id=0, $limit=false) { 

	// Changes made to allow the linking of one filter to another. This is acheieved as follows:
	// 1. Create a formulize form for managing the Main Filter List (form M)
	// 2. Create a formulize form for managing the Sub Filter list (form S), which includes a linked element to the data in form M, 
	//    so that relation between the Main Filter & Sub Filter data can be specified 
	// 3. Create a formulize form for the data that the Main & SubFilter act upon (form D)
	///
	// In such a case, the parameters have the following meaning:
	//  - $id is the element id of the field to be filtered in Form D
	//  - $ele_id is also the element id of the field to be filtered in Form D
	//  - $subfilter specifies if this filter is a subfilter
	//  - $linked_ele_id specifies the ele_id of the Main Filter field as it appears in Form S
	//  - $linked_data_id specifies the ele_id of the Main Filter field as it appears in Form D

	/* limit params work as follows: (a limit is some property of a field in the source entry in a linked selectbox)
	$limit = false, or if used then it's an array with these params...
	'ele_id' = the id of the element to pay attention to for the limit condition
	'term' = the term used to build the condition
	'operator' = the operator used to build the condition
	*/
	
	// limits are very similar to subfilters in their effect, but subfilters are meant for situations where one filter influences another filter
	// subfilters are kind of like dynamic limits, where the limit condition is not specified until the parent filter is chosen.

	global $xoopsDB;		// required by q
	$filter = "<SELECT name=\"$id\" id=\"$id\"";
	if($name) { $filter .= " onchange='javascript:document.$name.submit();'"; }
	$filter .= ">\n";
	
	if ($subfilter AND !(isset($_POST[$linked_data_id])) AND !(isset($_GET[$linked_data_id])))  { 
		// If its a subfilter and the main filter is unselected, then put in 'Please select from above options first
		$filter .= "<option value=\"none\">Please select a primary filter first</option>\n"; 
		}
	else {
		// Either it is not a subfilter, or it is a subfilter with the linked values set
		$defaulttext = $defaulttext ? $defaulttext: _AM_FORMLINK_PICK;
		$filter .= "<option value=\"none\">".$defaulttext."</option>\n"; 

    $form_element = q("SELECT ele_value, ele_type FROM " . $xoopsDB->prefix("formulize") . " WHERE ele_id = " . $ele_id);
    $element_value = unserialize($form_element[0]["ele_value"]);
	switch($form_element[0]["ele_type"]) {
		case "select":
			$options = $element_value[2];
			break;
		case "radio":
		case "checkbox":
			$options = $element_value;
			break;
	}

	// if the $options is from a linked selectbox, then figure that out and gather the possible values
	// only linked selectboxes have this string in their options field
	if(strstr($options, "#*=:*")) {
		$boxproperties = explode("#*=:*", $options);
		$source_form_id = $boxproperties[0];
		$source_element_handle = $boxproperties[1];
		
		// process the limits
		$limitCondition = "";
		if(is_array($limit)) {
			$limitCondition = $limit['ele_id'] . "/**/" . $limit['term'];
			$limitCondition .= isset($limit['operator']) ? "/**/" . $limit['operator'] : "";
		}
		
			if (!$subfilter) {
		$data = getData("", $source_form_id, $limitCondition);
				}
			else {
				$getDataFilter .= $linked_ele_id . "/**/" . $_POST[$linked_data_id];
				$getDataFilter .= $limitCondition ? "][" . $limitCondition : "";
				$data = getData("", $source_form_id, $getDataFilter);
				}
		unset($options);
		foreach($data as $entry) {
			$option_text = display($entry, $source_element_handle);
			$options[$option_text] = ""; // it's the key that gets used in the loop below
		}
	}
	
	$nametype = "";
	if(key($options) === "{FULLNAMES}" OR key($options) === "{USERNAMES}") { // code copied from elementrender.php to make fullnames work for Drupalcamp demo
		if(key($options) === "{FULLNAMES}") { $nametype = "name"; }
		if(key($options) === "{USERNAMES}") { $nametype = "uname"; }
		$pgroups = array();
		if($element_value[3]) {
			$scopegroups = explode(",",$element_value[3]);
			global $xoopsUser;
			$groups = $xoopsUser ? $xoopsUser->getGroups() : array(0=>XOOPS_GROUP_ANONYMOUS);
			if(!in_array("all", $scopegroups)) {
				if($element_value[4]) { // limit by users's groups
					foreach($groups as $gid) { // want to loop so we can get rid of reg users group simply
						if($gid == XOOPS_GROUP_USERS) { continue; }
						if(in_array($gid, $scopegroups)) {
							$pgroups[] = $gid;
						}
					}
					if(count($pgroups) > 0) { 
						unset($groups);
						$groups = $pgroups;
      				} else {
      					$groups = array();
      				}
      			} else { // don't limit by user's groups
					$groups = $scopegroups;
				}
			} else { // use all
				if(!$element_value[4]) { // really use all (otherwise, we're just going will all user's groups, so existing value of $groups will be okay
					unset($groups);
					global $xoopsDB;
					$allgroupsq = q("SELECT groupid FROM " . $xoopsDB->prefix("groups") . " WHERE groupid != " . XOOPS_GROUP_USERS);
					foreach($allgroupsq as $thisgid) {
						$groups[] = $thisgid['groupid'];
					} 
				}
			}
			$options = array();
			$namelist = gatherNames($groups, $nametype);
			foreach($namelist as $auid=>$aname) {
				$options[$aname] = $auid; // backwards to how elementrenderer.php does it, since logic below to build list is different
			}
		}
	}


        ksort($options);

    foreach($options as $option=>$option_value)
    {

	  if(isset($overrides[$option])) 
        {
		        $selected = ($_POST[$id] == $option OR $_GET[$id] == $option) ? "selected" : ""; 
		        $filter .= "<option value=\"" . $overrides[$option][1] . "\" $selected>" . $overrides[$option][0] . "</option>\n";
        } else {
		  if(preg_match('/\{OTHER\|+[0-9]+\}/', $option)) { $option = str_replace(":", "", _formulize_OPT_OTHER); }
		  $passoption = $nametype ? $option_value : $option; // str_replace(" ", "_", $option); // if a nametype is in effect, then use the value, otherwise, use the key -- also, no longer swapping out spaces for underscores
		  if((isset($_POST[$id]) OR isset($_GET[$id])) AND $overrides !== false) { $selected = ($_POST[$id] == $passoption OR $_GET[$id] == $passoption) ? "selected" : ""; } else { $selected = ""; }
	        $filter .= "<option value=\"$passoption\" $selected>$option</option>\n";
		}            
    }
	}
	$filter .= "</SELECT>\n";

	return $filter;
}

// THIS FUNCTION TAKES A SCREEN id AND RETURNS THE $multiPageData array necessary to get the form to display properly
function readScreenId($id, $fid) {
	include_once XOOPS_ROOT_PATH . "/modules/formulize/class/multiPageScreen.php";
	$screen_handler =& xoops_getmodulehandler('multiPageScreen', 'formulize');
	$screen = $screen_handler->get($id);
	if(is_object($screen)) {
		// same parsing operations as in the screen object itself in the render method
		$pages = $screen->getVar('pages');
		$pagetitles = $screen->getVar('pagetitles');
		array_unshift($pages, ""); // displayFormPages looks for the page array to start with [1] and not [0], for readability when manually using the API.
		array_unshift($pagetitles, ""); 
		$pages['titles'] = $pagetitles;
		unset($pages[0]); // get rid of the part we just unshifted, so the page count is correct
		foreach($screen->getVar('conditions') as $pageid=>$condata) {
			$pagenumber = $pageid+1;
			$conditions[$pagenumber] = array(0=>$condata['details']['elements'], 1=>$condata['details']['ops'], 2=>$condata['details']['terms']);
		}
		$multiPageData = array();
		$multiPageData[$fid]['pages'] = $pages;
		$multiPageData[$fid]['conditions'] = $conditions;
		$multiPageData[$fid]['introtext'] = $screen->getVar('introtext');
		$multiPageData[$fid]['thankstext'] = $screen->getVar('thankstext');
		$multiPageData[$fid]['printall'] = $screen->getVar('printall');
		return $multiPageData;
	} else {
		return false;
	}
}

?>