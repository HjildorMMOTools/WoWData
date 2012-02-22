<?php
/* wowdata.php -- MyBB plugin for querying Blizzard's World of Warcraft API
  version 0.1.1, February 17th, 2012
  Basic ideas based upon the work of Daniel Major in his wowitem plugin.
  Tooltips powered by DarkTip: https://github.com/darkspotinthecorner/DarkTip
  and by jquery.qtip.min: http://craigsworks.com/projects/qtip/

  Copyright (C) 2012 Luke Rebarchik

  This software is provided 'as-is', without any express or implied
  warranty.  In no event will the authors be held liable for any damages
  arising from the use of this software.

  Permission is granted to anyone to use this software for any purpose,
  including commercial applications, and to alter it and redistribute it
  freely, subject to the following restrictions:

  1. The origin of this software must not be misrepresented; you must not
     claim that you wrote the original software. If you use this software
     in a product, an acknowledgment in the product documentation would be
     appreciated but is not required.
  2. Altered source versions must be plainly marked as such, and must not be
     misrepresented as being the original software.
  3. This notice may not be removed or altered from any source distribution.

  Luke Rebarchik myfavoriteluke@gmail.com

*/

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

//	Detect if the wowhead script is included... if so then don't do the items with this tooltip.
$wowitem_active = wowdata_detectwowitem();

// the MyCode tag(s) to parse for, separated by |
define(WOWDATA_MYCODE, 'arena|character|guild'.((!$wowitem_active)?"|item":""));


define(WOWDATA_FOOTER, '<script type="text/javascript">
		window.___DarkTipSettings = {
			\'resources\': {
				\'extras\': [
					\'jscripts/WoWData.css\',
					\'jscripts/DarkTip/modules/wow.css\',
					\'jscripts/DarkTip/modules/wow.js\',
					\'jscripts/DarkTip/modules/wow.realm.js\',
					\'jscripts/DarkTip/modules/wow.quest.js\',
					\'jscripts/DarkTip/modules/wow.item.js\',
					\'jscripts/DarkTip/modules/wow.item.equipped.js\',
					\'jscripts/DarkTip/modules/wow.character.js\',
					\'jscripts/DarkTip/modules/wow.character.pvp.js\',
					\'jscripts/DarkTip/modules/wow.guild.js\',
					\'jscripts/DarkTip/modules/wow.arena.js\'' . ( (!$wowitem_active) ?
					',
					\'jscripts/DarkTip/modules/wow.wowhead.js\',
					\'jscripts/DarkTip/modules/wow.wowhead.character.js\',
					\'jscripts/DarkTip/modules/wow.wowhead.guild.js\',
					\'jscripts/DarkTip/modules/wow.wowhead.item.js\',
					\'jscripts/DarkTip/modules/wow.wowhead.quest.js\'
					' : '' ) .
					'
				]
			}
		};	</script>
	<script type="text/javascript" src="jscripts/DarkTip/DarkTip.js"></script>');

$plugins -> add_hook('pre_output_page', 'wowdata_footercode');
$plugins -> add_hook("parse_message", "wowdata_parse");

function wowdata_info()
{
	return array(
		"name"          => "WoW Data Links",
		"description"   => "Displays World of Warcraft data from Blizzard`s battle.net API in posts and messages.",
		"website"       => "https://github.com/HjildorMMOTools/WoWData",
		"author"        => "Luke Rebarchik",
		"authorsite"    => "mailto:myfavoriteluke@gmail.com",
		"version"       => "0.1.1",
		"guid"          => "b05e4bcc066b9d7281d5b79e3e29703f",
		"compatibility" => "16*"
	);
}

function wowdata_install()
{
	global $mybb, $db;
		
	// DELETE ALL SETTINGS TO AVOID DUPLICATES
	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN(
		'wowdata_switch'
	)");
	$db->delete_query("settinggroups", "name = 'wowdata'");
	
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows");
	$rows = $db->fetch_field($query, "rows");
	
	$insertarray = array(
		'name' => 'wowdata',
		'title' => 'WoW Data Links',
		'description' => 'Options to configure the WoW Data Links plugin.',
		'disporder' => $rows+1,
		'isdefault' => 0
	);
	$group['gid'] = $db->insert_query("settinggroups", $insertarray);
	$mybb->wowdata_insert_gid = $group['gid'];
	
	$insertarray = array(
		'name' => 'wowdata_switch',
		'title' => 'WoW Data Main Switch',
		'description' => 'Turns on or off WoW Data.',
		'optionscode' => 'onoff',
		'value' => 1,
		'disporder' => 0,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'wowdata_locale',
		'title' => 'Locale',
		'description' => 'Many areas are covered, try yours!  The first part is a 2 letter language code, after the \"_\" is the \"zone\" you want to cover.  To know which one your need simply log into battle.net and look what appears before \".battle.net\"  Example: \"en_us\" is for United States in English, \"de_eu\" is for Europe in German, \"es_us\" is for United States in Spanish...',
		'optionscode' => "text",
		'value' => 'en_us',
		'disporder' => 1,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'wowdata_realm',
		'title' => 'Realm',
		'description' => "Your realm goes here.  Please use no leading or trailing spaces.",
		'optionscode' => "text",
		'value' => 'Thrall',
		'disporder' => 2,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	rebuild_settings();
}

function wowdata_is_installed()
{
	global $mybb, $db;
	
	$query = $db->simple_select("settinggroups", "name", "name = 'wowdata'");
	$wowdata_result = $db->fetch_array($query);
	if($wowdata_result)
		return true;
	else return false;
}

function wowdata_uninstall()
{
	global $mybb, $db;

	// remove settings
	$db->delete_query('settinggroups', "name = 'wowdata'");
	$db->delete_query('settings', "name LIKE 'wowdata%'");
}

function wowdata_activate()
{
	global $db;
	$db -> update_query('settings', array('value' => 1), "name = 'wowdata_switch'");
	//require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	//find_replace_templatesets('headerinclude', '/{\$newpmmsg}/', WOWDATA_HEADER . "\n{\$newpmmsg}");
	//find_replace_templatesets("footer","/(".preg_quote("{\$auto_dst_detection}").")/i", "{WOWDATA_FOOTER}\n$1");
}

function wowdata_deactivate()
{
	global $db;
	$db -> update_query('settings', array('value' => 0), "name = 'wowdata_switch'");
	//require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	//find_replace_templatesets('headerinclude', '/' . preg_quote(WOWDATA_HEADER) . '\n/', '', 0);
	//find_replace_templatesets("footer","/" . preg_quote(WOWDATA_FOOTER) . "\n/i", "\n");
}

function wowdata_parse($message)
{
	global $mybb,$mycodes;
	$mycodes = explode('|', WOWDATA_MYCODE);
	$max_loops = 100;
	foreach ($mycodes as $mycode)
	{
		$loopiterator = 0;
		$data_match = "/\[(".$mycode.")(\s?=?[^=\]]*)(\s?=?[^=\]]*)\]([^\[]+)\[\/".$mycode."\]/ies";
		while(preg_match($data_match, $message, $datas_found) > 0 && $loopiterator < $max_loops) {
			//$message = preg_replace($data_match, "wowdata_data('".$datas_found[1]."')", $message, 1);
			$message = preg_replace($data_match, "wowdata_data('\$4', '\$1', '\$2', '\$3')", $message, 1);
			$loopiterator++;
		}
	}
	return $message;
}
function wowdata_arrayfilter($var) {
	if($var!="")
		return true;
	else
		return false;
}
function wowdata_data($data, $code, $parm1, $parm2) {
	global $mybb;
	$parm1 = explode("=", $parm1);
	$parm2 = explode("=", $parm2);
	if(count($parm1)>1)
		if($parm1[0]=="")
			$parm1 = array($parm1[1]);
	if(count($parm2)>1)
		if($parm2[0]=="")
			$parm2 = array($parm2[1]);
	$zone = substr($mybb -> settings['wowdata_locale'], strpos($mybb -> settings['wowdata_locale'],"_")+1);
	$lang = substr($mybb -> settings['wowdata_locale'], 0, strpos($mybb -> settings['wowdata_locale'],"_"));
	$ret = "[Placeholder]";
	//	WoW battle net prefix...
	$ret = "<a target=\"_blank\" href=\"http://".$zone.".battle.net/wow/".$lang."/";
	//	Request type (currently the "code" must match the intended URL)
	$ret .= urlencode(strtolower($code));
	//	Insert Realm
	$ret .= "/".urlencode($mybb->settings['wowdata_realm'])."/";
	//	if it's an arena team the first parameter will always match 2v2 or 3v3 etc.
	$ret .= ( count($parm1>0 ) ? 
		( ( preg_match("/\dv\d/i", $parm1[0]) ) ? 
			strtolower($parm1[0])."/"
		:
			""
		)
	:
		""
	);
	//	The main part of the [code]This Part[/code] goes in the URL here
	$ret .= preg_replace("/\+/", "%20", urlencode($data))."/";
	//	If the first parameter is pvp the code will have been character (but I didn't check for it because including it in the URL should make no difference.
	$ret .= 
		( count($parm1)>0 ) ?
			( ( strtolower($parm1[0])=="pvp" ) ? 
				"pvp" 
			:
				( ( strtolower($parm1[0])!="" && preg_match("/\dv\d/i", $parm1[0]) == 0 ) ? 
					"".$parm1[0] . ( (count($parm1)>1 ) ? "".$parm1[1] : "" )
				:
					( $parm1[0] == "character" )?"simple":""
				)
				. 
				( ( count($parm2)>0 ) ?
					( ( strtolower($parm2[0])=="pvp" ) ? 
						"pvp" 
					:
						( ( strtolower($parm2[0])!="" && preg_match("/\dv\d/i", $parm2[0]) == 0 ) ? 
							"".$parm2[0].((count($parm2)>1)?"".$parm2[1]:"")
						:
							""
						)
					)
				:
					""
				)
			)
		:
			"";
	//	This part is simple.  The data goes in these [] and is a link...
	$ret .= "\"><span class=\"" . preg_replace("/\s/", "-", $code) . "\">[".$data."]</span></a>";
	return $ret;
}

function wowdata_installtables() {
	global $mybb, $db;

	// create cache table
	$db->write_query("
	CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."wowdata_cache` (
 		`wowdata_id` int(16) NOT NULL AUTO_INCREMENT COMMENT 'Primary key; numeric, auto increment',
		`text_key` varchar(75) NOT NULL COMMENT 'between the [] codes comes text, this is where it is stored',
		`attribute_keys` varchar(75) NOT NULL COMMENT 'sometimes things aren`t 1:1, extended matching criteria go here',
		`external_id` int(16) NOT NULL COMMENT 'items have an ID shared by wowhead and blizzard.  This ID is stored here',
		PRIMARY KEY (`wowdata_id`),
		UNIQUE KEY `external_id` (`external_id`)
	) AUTO_INCREMENT=1");
}

function wowdata_getdata($data) {
}

function wowdata_cachedata($data) {
	$insert_query = "
		INSERT INTO `mybb`.`mybb_wowdata_cache` (
			`wowdata_id` ,
			`text_key` ,
			`attribute_keys` ,
			`external_id`
		)
		VALUES (
			".(($data['local_id']!="")?$data['local_id']:"NULL")." ,
			'Test',
			'Key1=12',
			'1'
	";
}

function wowdata_getdata_cache($data) {
}

function wowdata_getdata_blizzard($data) {
}

function wowdata_getdata_wowhead($data) {
}

function wowdata_detectdatatype($data) {
}

function wowdata_footercode($page) {
	global $db;
	$query = $db->simple_select("settings", "value", "name = 'wowdata_switch'");
	$wowdata_result = $db->fetch_array($query);
	if($wowdata_result)
		if ($wowdata_result['value'] == 1){
			$page = preg_replace('/([\s\n\r\t]*\<\/body\>)/i', WOWDATA_FOOTER . '$1', $page);
		}
	return $page;
}

function wowdata_detectwowitem() {
	global $db;
	$query = $db->query("
		SELECT s.sid, t.template, t.tid 
		FROM ".TABLE_PREFIX."templatesets s 
		LEFT JOIN ".TABLE_PREFIX."templates t ON (t.title='headerinclude' AND t.sid=s.sid)
	");
	$template = $db->fetch_array($query);
	$result = preg_match("/www\.wowhead\.com\/widgets\/power\.js/iU", $template['template']);
	if($result > 0)
		return true;
	else
		return false;
}
?>
