<?php
//	Edited and committed
/* wowdata.php -- MyBB plugin for querying Blizzard's World of Warcraft API
  version 0.1.0, February 17th, 2012
  Basic ideas based upon the work of Daniel Major in his wowitem plugin.
  Tooltips powered by DarkTip: https://github.com/darkspotinthecorner/DarkTip

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

// the MyCode tag(s) to parse for, separated by |
define(WOWDATA_MYCODE, 'arena|character|guild');

/*define(WOWDATA_HEADER, '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>');*/
define(WOWDATA_FOOTER, '<script type="text/javascript">
		window.___DarkTipSettings = {
			\'resources\': {
				\'extras\': [
					\'inc/plugins/DarkTip/modules/wow.css\',
					\'inc/plugins/DarkTip/modules/wow.js\',
					\'inc/plugins/DarkTip/modules/wow.realm.js\',
					\'inc/plugins/DarkTip/modules/wow.quest.js\',
					\'inc/plugins/DarkTip/modules/wow.item.js\',
					\'inc/plugins/DarkTip/modules/wow.item.equipped.js\',
					\'inc/plugins/DarkTip/modules/wow.character.js\',
					\'inc/plugins/DarkTip/modules/wow.character.pvp.js\',
					\'inc/plugins/DarkTip/modules/wow.guild.js\',
					\'inc/plugins/DarkTip/modules/wow.arena.js\',
					\'inc/plugins/DarkTip/modules/wow.wowhead.js\',
					\'inc/plugins/DarkTip/modules/wow.wowhead.character.js\',
					\'inc/plugins/DarkTip/modules/wow.wowhead.guild.js\',
					\'inc/plugins/DarkTip/modules/wow.wowhead.item.js\',
					\'inc/plugins/DarkTip/modules/wow.wowhead.quest.js\'
				]
			}
		};	</script>
	<script type="text/javascript" src="inc/plugins/DarkTip/DarkTip.js"></script>');

$plugins -> add_hook('pre_output_page', 'wowdata_footercode');
$plugins -> add_hook("parse_message", "wowdata_parse");

function wowdata_info()
{
	return array(
		"name"          => "WoW Data Links",
		"description"   => "Displays World of Warcraft data from Blizzard`s battle.net API in posts and messages.",
		"website"       => "http://www.rebarchik.com",
		"author"        => "Luke",
		"authorsite"    => "mailto:myfavoriteluke@gmail.com",
		"version"       => "0.0.1",
		"guid"          => "",
		"compatibility" => "16*"
	);
}

function wowdata_install()
{
	global $mybb, $db;

	// delete settings that were not uninstalled cleanly
	$db->delete_query('settings', "name LIKE 'wowdata_%'");
	$db->delete_query('settinggroups', "name = 'wowdata'");

	// add settings group
	$query = $db->simple_select('settinggroups', "MAX(disporder) AS max_disporder");
	$disporder = $db->fetch_field($query, 'max_disporder') + 1;
	$gid = $db->insert_query('settinggroups', array(
		'name' => 'wowdata',
		'title' => 'WoW Data Links',
		'description' => $db->escape_string("Options to configure the WoW Data Links plugin."),
		'disporder' => $disporder,
		'isdefault' => 0
	));

	// add settings
	$db->insert_query('settings', array(
		'name' => 'wowdata_locale',
		'title' => 'Locale',
		'description' => $db->escape_string("Many areas are covered, try yours!  The first part is a 2 letter language code, after the \"_\" is the \"zone\" you want to cover.  To know which one your need simply log into battle.net and look what appears before \".battle.net\"  Example: \"en_us\" is for United States in English, \"de_eu\" is for Europe in German, \"sp_us\" is for United States in Spanish..."),
		'optionscode' => "text",
		'value' => 'en_us',
		'disporder' => 1,
		'gid' => $gid
	));
	$db->insert_query('settings', array(
		'name' => 'wowdata_realm',
		'title' => 'Realm',
		'description' => $db->escape_string("Your realm goes here.  Please use no leading or trailing spaces."),
		'optionscode' => "text",
		'value' => 'Thrall',
		'disporder' => 2,
		'gid' => $gid
	));
	rebuild_settings();
}

function wowdata_is_installed()
{
	global $mybb, $db;

	/*return $db->table_exists('wowdata');*/
	return true;
}

function wowdata_uninstall()
{
	global $mybb, $db;

	// remove settings
	$db->delete_query('settings', "name LIKE 'wowdata_%'");
	$db->delete_query('settinggroups', "name = 'wowdata'");

	rebuild_settings();
}

function wowdata_activate()
{
	//require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	//find_replace_templatesets('headerinclude', '/{\$newpmmsg}/', WOWDATA_HEADER . "\n{\$newpmmsg}");
	//find_replace_templatesets("footer","/(".preg_quote("{\$auto_dst_detection}").")/i", "{WOWDATA_FOOTER}\n$1");
}

function wowdata_deactivate()
{
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
			//	This internal loop isn't strictly needed.  I made the replace only do one because I wasn't sure the eval code was working properly.
			//	Either version of the following will work: (I commented out my version)
			//	$message = preg_replace($data_match, "wowdata_data('".$datas_found[1]."')", $message, 1);
			/*echo "<pre>\n";
			print_r($datas_found);
			echo "</pre>\n";*/
			$message = preg_replace($data_match, "wowdata_data('\$4', '\$1', '\$2', '\$3')", $message, 1);
			//	Please note that if you remove the loop you need to remove the 1 in the 4th parameter above.
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
	$parm1 = explode("=", $parm1);
	$parm2 = explode("=", $parm2);
	if(count($parm1)>1)
		if($parm1[0]=="")
			$parm1 = array($parm1[1]);
	if(count($parm2)>1)
		if($parm2[0]=="")
			$parm2 = array($parm2[1]);
	/*array_filter($parm1,"wowdata_arrayfilter");
	array_filter($parm2,"wowdata_arrayfilter");
	echo "Func<pre>\n";
	print_r($parm1);
	print_r($parm2);
	echo "</pre>tion\n<br/>";*/
	$ret = "[Placeholder]";
	//	WoW battle net prefix...
	$ret = "<a href=\"http://us.battle.net/wow/en/";
	//	Request type (currently the "code" must match the intended URL)
	$ret .= strtolower($code);
	//	Insert Realm
	$ret .= "/thrall/";
	//	if it's an arena team the first parameter will always match 2v2 or 3v3 etc.
	$ret .= ( count($parm1>0 ) ? 
		( ( preg_match("/\dv\d/i", $parm1[0]) ) ? 
			strtolower($parm1[0])
		:
			""
		)
	:
		""
	);
	//	The main part of the [code]This Part[/code] goes in the URL here
	$ret .= $data."/";
	//	If the first parameter is pvp the code will have been character (but I didn't check for it because including it in the URL should make no difference.
	$ret .= 
		( count($parm1)>0 ) ?
			( ( strtolower($parm1[0])=="pvp" ) ? 
				"pvp" 
			:
				( ( strtolower($parm1[0])!="" ) ? 
					"".$parm1[0] . ( (count($parm1)>1 ) ? "".$parm1[1] : "" )
				:
					"simple"
				)
				. 
				( ( count($parm2)>0 ) ?
					( ( strtolower($parm2[0])=="pvp" ) ? 
						"pvp" 
					:
						( ( strtolower($parm2[0])!="" ) ? 
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
	$ret .= "\">[".$data."]</a>";
	return $ret;
}
function wowdata_footercode($page) {
	//global $mybb;
	//if ($mybb -> settings['wowdata_realm'] != ""){
		$page = preg_replace('/([\s\n\r\t]*\<\/body\>)/i', WOWDATA_FOOTER . '$1', $page);
	//}
	return $page;
}
?>
