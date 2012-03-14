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
if(!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

//	Detect if the wowhead script is included... if so then don't do [item] with this tooltip.
$wowitem_active = wowdata_detect_wowitem();

// the MyCode tag(s) to parse for, separated by |
define(WOWDATA_MYCODE, 'arena|character|guild'.((!$wowitem_active || $mybb->settings['wowdata_doitem'] == 1)?"|item":""));


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
					\'jscripts/DarkTip/modules/wow.arena.js\'' . ( (wowdata_itemtooltipsource() == "darktip" ) ?
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
	<script type="text/javascript" src="jscripts/DarkTip/DarkTip.js"></script>' . ( (wowdata_itemtooltipsource() == "wowhead") ? '
	<script tyle="text/javascript" src="http://www.wowhead.com/widgets/power.js"></script>' : '' ) . '
');
echo $mybb->settings['wowdata_itemtooltipsource'];
$plugins -> add_hook('pre_output_page', 'wowdata_footercode');
$plugins -> add_hook("parse_message", "wowdata_parse");

function wowdata_info() {
	return array(
		"name"          => "WoW Data Links",
		"description"   => "Displays World of Warcraft data from Blizzard`s battle.net API in posts and messages using [character], [guild] and [arena] style tags.",
		"website"       => "https://github.com/HjildorMMOTools/WoWData",
		"author"        => "Luke Rebarchik",
		"authorsite"    => "mailto:myfavoriteluke@gmail.com",
		"version"       => "1.0.0",
		"guid"          => "b05e4bcc066b9d7281d5b79e3e29703f",
		"compatibility" => "16*"
	);
}

function wowdata_install() {
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
		'description' => "Your realm goes here.  Please use no leading or trailing spaces.  To link to something other than this realm simply add it to the tag like this: [character realm=Thrall]Character Name[/character].  The same code also works for other tags.",
		'optionscode' => "text",
		'value' => 'Thrall',
		'disporder' => 2,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'wowdata_doitem',
		'title' => '[item] Active',
		'description' => "[item] codes are a powerful addition to WoW Data, but they can also be handled by wowitem plugin.  " . (($wowitem_active) ? "If you want to use them from WoW Data, first you'll have to uninstall wowitem." : ""),
		'optionscode' => "onoff",
		'value' => (($wowitem_active)?0:1),
		'disporder' => 3,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'wowdata_defaultitemmatch',
		'title' => '[item] Default Match Difficulty',
		'description' => "Sometimes items have the same name in WoW, but different item levels.  These are different versions of the item based on dungeon or raid difficulty.  Please select the level you want [item] codes to match when there is no version speficied.",
		'optionscode' => "radio \nLFR=LFR\nNormal=Normal\nHeroic=Heroic",
		'value' => 'Normal',
		'disporder' => 4,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'wowdata_itemtooltipdestination',
		'title' => '[item] Link Target',
		'description' => "Do you want your [item] tooltips to link to battle.net or wowhead.com?",
		'optionscode' => "radio \nblizzard=battle.net\nwowhead=wowhead.com",
		'value' => 'wowhead',
		'disporder' => 5,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	$insertarray = array(
		'name' => 'wowdata_itemtooltipsource',
		'title' => '[item] Tooltip Style',
		'description' => "Do you want your [item] tooltips to look like DarkTip or like wowhead.com?  Note: if you are linking to battle.net and using wowhead style tooltips you won\'t get [item] tooltips at all.  Use wowhead style tooltips with wowhead linked items.",
		'optionscode' => "radio \ndarktip=DarkTip\nwowhead=wowhead.com",
		'value' => 'wowhead',
		'disporder' => 6,
		'gid' => $group['gid']
	);
	$db->insert_query("settings", $insertarray);
	
	rebuild_settings();
	
	wowdata_installtables();
}

function wowdata_is_installed() {
	global $mybb, $db;
	
	$query = $db->simple_select("settinggroups", "name", "name = 'wowdata'");
	$wowdata_result = $db->fetch_array($query);
	if($wowdata_result)
		return true;
	else return false;
}

function wowdata_uninstall() {
	global $mybb, $db;

	// remove settings
	$db->delete_query('settinggroups', "name = 'wowdata'");
	$db->delete_query('settings', "name LIKE 'wowdata%'");
	$db->drop_table('wowdata_cache');
}

function wowdata_activate() {
	global $db;
	$db -> update_query('settings', array('value' => 1), "name = 'wowdata_switch'");
	//require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	//find_replace_templatesets('headerinclude', '/{\$newpmmsg}/', WOWDATA_HEADER . "\n{\$newpmmsg}");
	//find_replace_templatesets("footer","/(".preg_quote("{\$auto_dst_detection}").")/i", "{WOWDATA_FOOTER}\n$1");
}

function wowdata_deactivate() {
	global $db;
	$db -> update_query('settings', array('value' => 0), "name = 'wowdata_switch'");
	//require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	//find_replace_templatesets('headerinclude', '/' . preg_quote(WOWDATA_HEADER) . '\n/', '', 0);
	//find_replace_templatesets("footer","/" . preg_quote(WOWDATA_FOOTER) . "\n/i", "\n");
}

function wowdata_parse($message) {
	global $mybb,$mycodes;
	$mycodes = explode('|', WOWDATA_MYCODE);
	$max_loops = 100;
	foreach ($mycodes as $mycode)
	{
		$loopiterator = 0;
		$data_match = "/\[(".$mycode.")(\s?=[^=\]]*)?(\s[^=]+=[^=\]]+)?\]([^\[]+)\[\/".$mycode."\]/ies";
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
	$zone = substr($mybb -> settings['wowdata_locale'], strpos($mybb -> settings['wowdata_locale'],"_")+1);
	$lang = substr($mybb -> settings['wowdata_locale'], 0, strpos($mybb -> settings['wowdata_locale'],"_"));
	$ret = "[Placeholder]";
	if($mybb -> settings['wowdata_itemtooltipdestination'] == "blizzard" || strtolower($code) != "item") {
		//	WoW battle net prefix...
		$ret = "<a target=\"_blank\" href=\"http://".$zone.".battle.net/wow/".$lang."/";
	} else {
		//	WoW battle net prefix...
		$ret = "<a target=\"_blank\" href=\"http://www.wowhead.com/";
	}
	//	Request type (currently the "code" must match the intended URL)
	$ret .= urlencode(strtolower($code));
	if($code != "item") {
		//	Insert Realm
		$default_realm = $mybb->settings['wowdata_realm'];
		if(strtolower(trim($parm1[0])) == "realm")
			$realm = trim($parm1[1]);
		else if(strtolower(trim($parm2[0])) == "realm")
			$realm = trim($parm2[1]);
		else
			$realm = $default_realm;
		$ret .= "/".urlencode($realm)."/";
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
		//	Likewise if there is a realm parameter don't output it, it has already been captured above.
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
							( ( strtolower($parm2[0])!="" && preg_match("/\dv\d/i", $parm2[0]) == 0 && preg_match("/realm/i", $parm2[0]) == 0) ? 
								"/".$parm2[0]."/".((count($parm2)>1)?"".$parm2[1]:"")
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
		if(strtolower($code) == "character") {
			//$cache_data = wowdata_build_character_data($ret);
			$cache_data = wowdata_getdata(array("code"=>"character","string"=>$ret));
			if(is_array($cache_data)) {
				// For language support
				$warrior =              'warrior';
				$paladin =              'paladin';
				$hunter =               'hunter';
				$rogue =                'rogue';
				$priest =               'priest';
				$death_knight = 		'death-knight';
				$shaman =               'shaman';
				$mage =                 'mage';
				$warlock =              'warlock';
				$druid =                'druid';
				
				// Blizzard and wowhead have different order for the classes witch result in different ids
				$blizzard_classes = array(null,$warrior,$paladin,$hunter,$rogue,$priest,$death_knight,$shaman,$mage,$warlock,null,$druid);
				//$wowhead_classes = array($druid,$hunter,$mage,$paladin,$priest,$rogue,$shaman,$warlock,$warrior,$death_knight);
				if(isset($cache_data['data']['class'])) {
					//	echo "WoWData code reset for class.<br/>\n";
					if(!is_array($cache_data['data']))
						$cache_data['data'] = json_decode($cache_data['data'], true);
					$code = $blizzard_classes[$cache_data['data']['class']];
				} else {
					//echo "Strange, no class found in: <pre>";
					//print_r($cache_data['data']);
					//echo "</pre>\n";
				}
			}
		}
	} else {
		//	This is specific for [item] searches...
		$items_array = wowdata_getdata(array('code'=>$code,'search'=>$data));
		if(count($items_array) == 1) {
			$item = $items_array[0];
		} else {
			if(in_array(trim(strtolower($parm1[0])), array("ilevel","ilev","ilvl"))) {
				$ilevel = $parm1[1];
			}
			if(in_array(trim(strtolower($parm2[0])), array("ilevel","ilev","ilvl"))) {
				$ilevel = $parm2[1];
			}
			if(isset($ilevel)) {
				foreach($items_array as $item_i) {
					if(preg_match('/ilevel\='.$ilevel.'\&/i', $item_i['attribute_keys']) > 0) {
						$item = $item_i; //$ret .= "/".$item_i['external_id'];
						break;
					} else {
						//echo '/ilevel\='.$ilevel.'\&/i'." not found in ".$item_i['attribute_keys']."<br/>\n";
					}
				}
				
			}
			if(!isset($item)) {
				//echo "Item not yet found...\n";
				$sort_by_ilevel = array();
				foreach($items_array as $item_i) {
					preg_match('/ilevel\=(\d+)\&/i', $item_i['attribute_keys'], $matches);
					$sort_by_ilevel[$matches[1]] = $item_i;
				}
				ksort($sort_by_ilevel,SORT_NUMERIC);
				$ilevels = array_keys($sort_by_ilevel);
				//$default_difficulty_item_matchings = array("Heroic","Normal","LFR");
				$selected_difficulty = strtolower($mybb -> settings['wowdata_defaultitemmatch']);
				if(in_array(strtolower($parm1[0]),array("rf","lfr","normal","heroic")))
					$selected_difficulty = strtolower($parm1[0]);
				if(in_array(strtolower($parm2[0]),array("rf","lfr","normal","heroic")))
					$selected_difficulty = strtolower($parm2[0]);
				if(in_array(strtolower($parm1[1]),array("rf","lfr","normal","heroic")))
					$selected_difficulty = strtolower($parm1[1]);
				if(in_array(strtolower($parm2[1]),array("rf","lfr","normal","heroic")))
					$selected_difficulty = strtolower($parm2[1]);
				if(count($items_array) == 2) {
					if($selected_difficulty == "lfr" || $selected_difficulty == "rf" || $selected_difficulty == "normal")
						$item = $sort_by_ilevel[$ilevels[0]];
					else
						$item = $sort_by_ilevel[$ilevels[1]];
				}
				if(count($items_array) == 3) {
					if($selected_difficulty == "lfr" || $selected_difficulty == "rf")
						$item = $sort_by_ilevel[$ilevels[0]];
					else if($selected_difficulty == "normal")
						$item = $sort_by_ilevel[$ilevels[1]];
					else
						$item = $sort_by_ilevel[$ilevels[2]];
				} else {
					//	4 versions of the same item means it is Wrath level... and there were no duplicate names then... so this part is superfluous.
					preg_match('/(\d+) \-\> (\d+) \-\> (\d+) \-\> (\d+)/i', $selected_groupsize, $matches);
					$group_size_priorities = array($matches[1],$matches[2],$matches[3],$matches[4]);
					if($group_size_priorities[0] == 25)
						$item = $sort_by_ilevel[$ilevels[3]];
					if($group_size_priorities[0] == 10)
						$item = $sort_by_ilevel[$ilevels[2]];
					if($group_size_priorities[0] == 5)
						$item = $sort_by_ilevel[$ilevels[1]];
					if($group_size_priorities[0] == 40)
						$item = $sort_by_ilevel[$ilevels[0]];
				}
			}
		}
		$ret .= (($mybb -> settings['wowdata_itemtooltipdestination'] == "blizzard")?"/":"=").(($item['external_id'] != "")?$item['external_id']:$item['item_id']);
		$item_data = (!is_array($item['data']))?json_decode($item['data'], true):$item['data'];
		if(count($item_data) == 0)
			$item_data = $item;
		$qualities = array("poor","common","uncommon","rare","epic","legendary","artifact","heirloom");
		$code = $qualities[$item_data['quality']];
	}
	//	This part is simple.  The data goes in these [] and is a link...
	$ret .= "\"><span class=\"" . strtolower(preg_replace("/\s/", "-", $code)) . "\">[".$data."]</span></a>";
	return $ret;
}

function wowdata_installtables() {
	global $mybb, $db;

	// create cache table
	$db->write_query("DROP TABLE IF EXISTS `".TABLE_PREFIX."wowdata_cache`");
	$db->write_query("
	CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."wowdata_cache` (
 		`wowdata_id` int(16) NOT NULL AUTO_INCREMENT COMMENT 'Primary key; numeric, auto increment',
		`code` varchar(75) NOT NULL COMMENT 'there are many [thing] codes which can be handled, they are stored here',
		`text_key` varchar(75) NOT NULL COMMENT 'between the [thing] codes comes text, this is where it is stored',
		`attribute_keys` varchar(75) NOT NULL COMMENT 'sometimes things aren`t 1:1, extended matching criteria go here',
		`external_id` int(16) DEFAULT NULL COMMENT 'items have an ID shared by wowhead and blizzard.  This ID is stored here',
		`url` varchar(255) DEFAULT NULL COMMENT 'URL requested',
		`data` text COMMENT 'Cached Response',
		PRIMARY KEY (`wowdata_id`),
		UNIQUE KEY `code_keys` (`code`,`text_key`,`attribute_keys`,`external_id`),
		FULLTEXT KEY `data` (`data`)
	) AUTO_INCREMENT=1");
}

function wowdata_getdata($cache_data) {
	if(strtolower($cache_data['code']) == "character") {
		$cache_data = wowdata_build_character_data($cache_data['string']);
		return $cache_data;
	} else if (strtolower($cache_data['code']) == "item") {
		//	Cache format for items:
		//	code			=	'item'
		//	text_key		=	SEARCH_TERM (passed inside [item]here[/item]
		//	attribute_keys	=	any extra =something
		//	external_id		=	there will be multiple rows added per search (if there is more than one match) for each single match there will be a value here, for the general search there will be nothing.
		//	url				=	this contains the search url or the url which is picked up by darktip...
		//	data			=	this is either the result of the search (for the match) or nothing when there is an external_id (it should be one or the other)
		$cache_search = wowdata_getdata_cache(array('code'=>"item", 'text_key'=>$cache_data['search'],'attribute_keys'=>"ilevel"));
		if(count($cache_search) == 0 || $cache_search == -1 || $cache_search == false) {
			wowdata_getdata_wowhead($cache_data['search']);
			$search_wowhead = wowdata_getdata_cache(array('code'=>"item", 'text_key'=>$cache_data['search'],'attribute_keys'=>"ilevel"));
		} else
			$search_wowhead = $cache_search;
		return $search_wowhead;
	}
}

function wowdata_cachedata($cache_data) {
	global $db;
	$insert_query = "
		INSERT INTO `".TABLE_PREFIX."wowdata_cache` (
			`wowdata_id` ,
			`code`,
			`text_key` ,
			`attribute_keys` ,
			`external_id`,
			`url`,
			`data`
		)
		VALUES (
			".(($cache_data['local_id']!="")?mysql_escape_string($cache_data['local_id']):"NULL")." ,
			'".mysql_escape_string($cache_data['code'])."',
			'".mysql_escape_string($cache_data['text_key'])."',
			'".mysql_escape_string(wowdata_implode_r(array("&","="),$cache_data['attribute_keys']))."',
			".(($cache_data['external_id'] != "null")?"'".mysql_escape_string($cache_data['external_id'])."'":"-1").",
			".(($cache_data['url'] != "null")?"'".mysql_escape_string($cache_data['url'])."'":"null").",
			".(($cache_data['data'] != "null")?"'".mysql_escape_string(((is_string($cache_data['data']))?$cache_data['data']:json_encode($cache_data['data'])))."'":"null")."
		) ON DUPLICATE KEY UPDATE
			`code` = '".mysql_escape_string($cache_data['code'])."',
			`text_key` = '".mysql_escape_string($cache_data['text_key'])."',
			`attribute_keys` = '".mysql_escape_string(wowdata_implode_r(array("&","="),$cache_data['attribute_keys']))."',
			`external_id` = ".(($cache_data['external_id'] != "null")?"'".mysql_escape_string($cache_data['external_id'])."'":"-1").",
			`url` = ".(($cache_data['url'] != "null")?"'".mysql_escape_string($cache_data['url'])."'":"null").",
			`data` = ".(($cache_data['data'] != "null")?"'".mysql_escape_string(((is_string($cache_data['data']))?$cache_data['data']:json_encode($cache_data['data'])))."'":"null")."
	";
	$db->write_query($insert_query);
}

function wowdata_build_character_data($url) {
	$cache_data = array(
		"wowdata_id" => "null",
		"code" => "null",
		"text_key" => "null",
		"attribute_keys" => "null",
		"external_id" => "null",
		"url" => $url,
		"data" => "null"
	);
	preg_match('/https?:\/\/(\w+)\.battle\.net\/wow\/([^\/]+)\/([^\/]+)\/([^\/]+)\/?([^\/]*)\/?([^\/]*)\/?/i', $url, $matches);
	$cache_data['code'] = $matches[3];
	$cache_data['attribute_keys'] = "realm=".$matches[4];
	$cache_data['text_key'] = $matches[5];
	if(strtolower($matches[3]) == "character") {
		$cache_check = wowdata_getdata_cache(array('code'=>"character", 'text_key'=>$matches[5], 'attribute_keys'=>"realm=".$matches[4]));
		if($cache_check === false || $cache_check === -1) {
			$new_url = "http://".$matches[1].".battle.net/api/wow/".strtolower($matches[3])."/".$matches[4]."/".$matches[5]."";	//?jsonp=_jqjsp
			$cache_data['data'] = wowdata_getdata_blizzard($new_url);
			wowdata_cachedata($cache_data);
			$cache_data['url'] = $new_url;
			//	echo "New data requested from battle.net!!!<br/>\n";
		} else {
			$cache_data = $cache_check;
			if(is_string($cache_data['data']))
				$cache_data['data'] = json_decode($cache_data['data']);
			//	echo "Data loaded from cache!\n<br/>\n";
		}
	}
	$matches[1] = "";
	$matches[2] = "";
	$matches[3] = "";
	$matches[4] = "";
	$matches[5] = "";
	foreach($matches as $i => $match) {
		if(strpos("|".strtolower(WOWDATA_MYCODE)."|", strtolower("|".$match."|")) !== false) {
			$cache_data['code'] = $match;
			$matches[$i] = "";
		} else if($match != "") {
				$cache_data['attribute_keys'] .= "&".$match;
		}
	}
	$cache_data['url'] = $new_url;
	return $cache_data;
}

function wowdata_getdata_cache($cache_data) {
	global $db;
	$sql_where = "`code` = '".mysql_escape_string($cache_data['code'])."' AND `text_key` = '".mysql_escape_string($cache_data['text_key'])."' AND `attribute_keys` LIKE '".mysql_escape_string($cache_data['attribute_keys'])."%'";
	$query = $db->simple_select('wowdata_cache', '`wowdata_id` ,`code`, `text_key`, `attribute_keys`, `external_id`, `url`, `data`', $sql_where);
	$cache_data = $db->fetch_array($query);
	if(isset($cache_data['wowdata_id'])) {
		$json_parsed = json_decode($cache_data['data'],true);
		if(($json_parsed['status'] != "nok" && is_array($json_parsed)) || $cache_data['code'] == "item") {
			$cache_data['data'] = $json_parsed;
			if($cache_data['code'] == "item") {
				$ret = array($cache_data);
				while($cache_data = $db->fetch_array($query)) {
					$cache_data['data'] = json_decode($cache_data['data'],true);
					$ret[] = $cache_data;
				}
				return $ret;
			} else {
				return $cache_data;
			}
		} else
			return -1;
	} else
		return false;
}

function wowdata_getdata_blizzard($url) {
	$ch = curl_init();
	//	set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1 );
	
	//	make the request...
	$curl_req = curl_exec($ch);
	
	//	a little debug...
	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	//echo "<div>Status: ".$http_status."</div>\n";
	//echo "<div>Response:\n".$curl_req."</div>\n";
	
	//	close cURL resource, and free up system resources
	curl_close($ch);
	return $curl_req;
}

function wowdata_getdata_wowhead($search_term) {
	$ch = curl_init();
	$wowhead_url = "http://www.wowhead.com/search?q=".preg_replace('/\+/', '%20', urlencode($search_term))."&opensearch";
	//	set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, $wowhead_url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1 );
	
	//	make the request...
	$curl_req = curl_exec($ch);
	
	//	a little debug...
	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	//echo "<div>Status: ".$http_status."</div>\n";
	//echo "<div>Response:\n".$curl_req."</div>\n";
	
	//	close cURL resource, and free up system resources
	curl_close($ch);
	$ret = json_decode($curl_req);
	$items = array();
	foreach($ret[7] as $index => $match) {
		if($match[0] == 3) {
			$name = preg_replace('/\s+\([^\)]+\)$/', "", $match[1][$index]);
			$items_index = count($items);
			$items[$items_index]['type'] = $match[0];
			$items[$items_index]['item_id'] = $match[1];
			$items[$items_index]['icon'] = $match[2];
			$items[$items_index]['quality'] = $match[3];
			$blizzard_url = "http://us.battle.net/api/wow/item/".$match[1];
			$blizzard_data = json_decode(wowdata_getdata_blizzard($blizzard_url), true);
			$cache_data = array(
				"wowdata_id" => "null",
				"code" => "item",
				"text_key" => $blizzard_data['name'],
				"attribute_keys" => "ilevel=".$blizzard_data['itemLevel']."&requiredLevel=".$blizzard_data['requiredLevel']."&itemClass=".$blizzard_data['itemClass'],
				"external_id" => $match[1],
				"url" => $blizzard_url,
				"data" => $blizzard_data
			);
			wowdata_cachedata($cache_data);
		}
	}
	if(count($items) > 0) {
		$cache_data = array(
			"wowdata_id" => "null",
			"code" => "item",
			"text_key" => $search_term,
			"attribute_keys" => "",
			"external_id" => "null",
			"url" => $wowhead_url,
			"data" => $items
		);
		wowdata_cachedata($cache_data);
		return $items;
	}else
		return array();
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

function wowdata_detect_wowhead() {
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

function wowdata_detect_wowitem() {
	global $db;
	$query = $db->simple_select("settinggroups", "name", "name = 'wowitem'");
	$row = $db->fetch_array($query);
	if($row['name'] == "")
		return false;
	else
		return true;
}

function wowdata_implode_r($glue, $pieces){
	$return = "";
	
	if(!is_array($glue)){
		$glue = array($glue);
	}
	
	$thisLevelGlue = array_shift($glue);
	
	if(!count($glue)) $glue = array($thisLevelGlue);
	
	if(!is_array($pieces)) {
		return (string) $pieces;
	}
	
	foreach($pieces as $sub) {
		$return .= implode_r($glue, $sub) . $thisLevelGlue;
	}
	
	if(count($pieces)) $return = substr($return, 0, strlen($return) -strlen($thisLevelGlue));
	
	return $return;
}
function wowdata_itemtooltipsource() {
	global $db;
	$query = $db->simple_select("settings", "name,value", "name = 'wowdata_itemtooltipsource'");
	$row = $db->fetch_array($query);
	if($row['name'] == "")
		return false;
	else
		return $row['value'];
}
?>
