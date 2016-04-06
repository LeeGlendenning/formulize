<?php
//  ------------------------------------------------------------------------ //
//                XOOPS - PHP Content Management System                      //
//                    Copyright (c) 2000 XOOPS.org                           //
//                       <http://www.xoops.org/>                             //
//  ------------------------------------------------------------------------ //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //
// Author: Kazumi Ono (AKA onokazu)                                          //
// URL: http://www.myweb.ne.jp/, http://www.xoops.org/, http://jp.xoops.org/ //
// Project: The XOOPS Project                                                //
// ------------------------------------------------------------------------- //
/**
 * Class to create a form field with a date selector
 *
 * @copyright	http://www.impresscms.org/ The ImpressCMS Project
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @category	ICMS
 * @package		Form
 * @subpackage	Elements
 * @version		SVN: $Id: Date.php 12495 2015-06-15 19:43:10Z fiammy $
 **/

defined('ICMS_ROOT_PATH') or die("ImpressCMS root path not defined");

/**
 * A text field with calendar popup
 *
 * @category	ICMS
 * @package     Form
 * @subpackage  Elements
 *
 * @author	    Kazumi Ono	<onokazu@xoops.org>
 */
class icms_form_elements_Date extends icms_form_elements_Text {


    /**
     * Constructor
     *
     * @param string	$caption
     * @param string	$name
     * @param int		$size
     * @param mixed		$value
     */
    public function __construct($caption, $name, $size = 15, $value= 0) {
        // ALTERED BY FREEFORM SOLUTIONS FOR THE DATE DEFAULT CHANGES IN FORMULIZE STANDALONE
		if($value === "") {
			$value = _DATE_DEFAULT;
		} elseif(ereg_replace("[^A-Z{}]","", $value) === "{TODAY}") { // check for {TODAY}, {TODAY-14} etc
			$number = ereg_replace("[^0-9+-]","", $value);
			$value = mktime(0, 0, 0, date("m") , date("d")+$number, date("Y")); 
		} elseif(!is_numeric($value)) {
			$value = time();
		} else {
			$value = intval($value);
		}
        parent::__construct($caption, $name, $size, 25, $value);
    }

    /**
     * Render the Date field
     */
    public function render() {
        global $icmsConfigPersona;
        $ele_name = $this->getName();
		// ALTERED BY FREEFORM SOLUTIONS FOR THE DATE DEFAULT CHANGES IN FORMULIZE STANDALONE
		if($icmsConfigPersona['use_jsjalali']) {
			include_once ICMS_ROOT_PATH . '/include/jalali.php';	
		} 
		if($this->getValue(false) !== _DATE_DEFAULT) {
			$ele_value = date(_SHORTDATESTRING, $this->getValue(false));
			$jstime = formatTimestamp($this->getValue(false), _SHORTDATESTRING);
			if(_CALENDAR_TYPE=='jalali') {
				$jalali_ele_value = icms_conv_nr2local(jdate(_SHORTDATESTRING, $ele_value));
			} else {
				$jalali_ele_value = $ele_value;
			}
		} else {
			$ele_value = $this->getValue(false);
			$jstime = formatTimestamp(time(), _SHORTDATESTRING);
			$jalali_ele_value = $ele_value;
		}

        include_once ICMS_ROOT_PATH . '/include/calendar' . ($icmsConfigPersona['use_jsjalali'] == true ? 'jalali' : '') . 'js.php';

        if (false and $icmsConfigPersona['use_jsjalali']) {
            $dateFormat = dateFormatToStrftime(_SHORTDATESTRING);
			$result = "<input name='".$ele_name."' id='".$ele_name."' class=\"icms-date-box\" size='".$this->getSize()."' maxlength='".$this->getMaxlength()."' value='".$jalali_ele_value."'".$this->getExtra()." />&nbsp;&nbsp;<img src='" . ICMS_URL . "/images/calendar.png' class='no-print' alt='"._CALENDAR."' title='"._CALENDAR."' id='btn_".$ele_name."'><script type='text/javascript'>
				Calendar.setup({
					inputField  : '".$ele_name."',
					ifFormat    : '$dateFormat',
					button      : 'btn_".$ele_name."',
					langNumbers : true,
					dateType    : '"._CALENDAR_TYPE."',
					onUpdate    : function(cal){document.getElementById('".$ele_name."').value = cal.date.print('$dateFormat');}
				});
			</script>";
		} else {
            $result = "<input type='text' name='".$ele_name."' id='".$ele_name.
                "' class=\"icms-date-box\" size='".$this->getSize()."' maxlength='".$this->getMaxlength().
                "' value='".$ele_value."'".$this->getExtra()." />";
            static $output_datepicker_defaults = true;
            if ($output_datepicker_defaults) {
                $ICMS_URL = ICMS_URL;
                // the jQuery datepicker wants a date format such as yy-mm-dd, or yy-m-d.
                // yyyy-mm-dd gives a date like '20142014-10-11', so only yy, not yyyy.
                $dateFormat = dateFormatToStrftime(_SHORTDATESTRING);
                $dateFormat = str_replace(array("%y", "%m", "%d"), array("yy", "m", "d"), strtolower($dateFormat));
                // note: datepicker_defaults is a var so it is available later for date elements with date limits
                $result .= <<<EOF
				<script>
				var datepicker_defaults = {
					dateFormat: "$dateFormat",
					changeMonth: true,
					changeYear: true,
					hideIfNoPrevNext: true, // do not show the prev/next links if they are disabled
					numberOfMonths: 1,
					yearRange: "c-20:c+20",
					showOn: "both",
					buttonImageOnly: true,
					buttonImage: "$ICMS_URL/images/calendar.png",
					buttonText: "Calendar"
				};

				jQuery(document).ready(function() {
					jQuery.datepicker.setDefaults(datepicker_defaults);
					jQuery(function() {
						jQuery(".icms-date-box").datepicker();
					});
				});
				</script>
				EOF;
                $output_datepicker_defaults = false;
            }
        }
        return $result;
    }
}