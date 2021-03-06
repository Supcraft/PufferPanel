<?php
/*
	PufferPanel - A Minecraft Server Management Panel
	Copyright (c) 2013 Dane Everitt

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see http://www.gnu.org/licenses/.
 */
namespace PufferPanel\Core;
use \ORM as ORM;

require_once('../../../../../src/core/core.php');

if($core->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->auth->getCookie('pp_auth_token'), null, true) !== true){
	Components\Page::redirect('../../../index.php');
}

//Cookies :3
setcookie("__TMP_pp_admin_newnode", json_encode($_POST), time() + 30, '/');

/*
 * Agree Warning
 */
if(!isset($_POST['read_warning'])) {
	Components\Page::redirect('../../add.php?error=agree_warn&disp=agree_warn');
}

/*
 * Are they all Posted?
 */
if(!isset($_POST['node_name'], $_POST['location'], $_POST['allocate_memory'], $_POST['allocate_disk'], $_POST['disk_selector'], $_POST['mem_selector'], $_POST['fqdn'], $_POST['ip'], $_POST['ip_port'], $_POST['gsd_listen'], $_POST['gsd_console'], $_POST['gsd_server_dir'])) {
	Components\Page::redirect('../../add.php?disp=missing_args');
}

/*
 * Validate Ports
 */
if(!is_numeric($_POST['gsd_listen']) || !is_numeric($_POST['gsd_console']) || !is_numeric($_POST['allocate_memory']) || !is_numeric($_POST['allocate_disk'])) {
	Components\Page::redirect('../../add.php?error=gsd_listen|gsd_console|allocate_memory|allocate_disk&disp=port_fail');
}

/*
 * Validate Directory
 */
if(!preg_match('/^([\/][\d\w.\-\/]+[\/])$/', $_POST['gsd_server_dir'])) {
	Components\Page::redirect('../../add.php?error=gsd_server_dir&disp=dir_fail');
}

/*
 * Validate Node Name
 */
if(!preg_match('/^[\w.-]{1,15}$/', $_POST['node_name'])) {
	Components\Page::redirect('../../add.php?error=node_name&disp=n_fail');
}

/*
 * Validate FQDN & IP
 */
if(!filter_var(gethostbyname($_POST['fqdn']), FILTER_VALIDATE_IP) || !filter_var($_POST['ip'] , FILTER_VALIDATE_IP)) {
	Components\Page::redirect('../../add.php?error=fqdn|ip&disp=ip_fail');
}

/*
 * Process IPs and Ports
 */
$IPP = array();
$IPA = array();

/*
 * Clean Inputs
 */
$_POST['ip_port'] = str_replace(" ", "", $_POST['ip_port']);

$lines = explode("\r\n", $_POST['ip_port']);
foreach($lines as $id => $values) {

	list($ip, $ports) = explode('|', $values);

	$IPA = array_merge($IPA, array($ip => array()));
	$IPP = array_merge($IPP, array($ip => array()));

	$portList = array();
	$portList = Components\Functions::processPorts($ports);

	for($l=0; $l<count($portList); $l++) {

			$IPP[$ip][$portList[$l]] = 1;

	}

	/*
	 * Make sure Ports are in the array
	 */
	if(count($IPP[$ip]) > 0) {

		$IPA[$ip] = array_merge($IPA[$ip], array("ports_free" => count($IPP[$ip])));

	} else {

		Components\Page::redirect('../../view.php?id='.$_POST['nid'].'&error=ip_port&disp=ip_port_space');

	}

}

/*
 * Add Node to Database
 */
$node = ORM::forTable('nodes')->create();
$node->set(array(
	'node' => $_POST['node_name'],
	'location' => $_POST['location'],
	'allocate_memory' => ($_POST['mem_selector'] == 1) ? ($_POST['allocate_memory'] * 1024) : $_POST['allocate_memory'],
	'allocate_disk' => ($_POST['disk_selector'] == 1) ? ($_POST['allocate_disk'] * 1024) : $_POST['allocate_disk'],
	'fqdn' => $_POST['fqdn'],
	'ip' => $_POST['ip'],
	'gsd_secret' => $core->auth->generateUniqueUUID('nodes', 'gsd_secret'),
	'gsd_listen' => $_POST['gsd_listen'],
	'gsd_console' => $_POST['gsd_console'],
	'gsd_server_dir' => $_POST['gsd_server_dir'],
	'ips' => json_encode($IPA),
	'ports' => json_encode($IPP),
	'public' => (isset($_POST['is_public'])) ? 0 : 1
));
$node->save();

Components\Page::redirect('../../view.php?id='.$node->id());
?>
