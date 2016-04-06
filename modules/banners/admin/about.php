<?php
/**
* About page of the module
*
* @copyright	The ImpressCMS Project
* @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
* @since		1.0
* @author		Phoenyx <phoenyx@impresscms.de>
* @package		banners
* @version		$Id$
*/

include_once "admin_header.php";

$aboutObj = new icms_ipf_About();
$aboutObj->render();