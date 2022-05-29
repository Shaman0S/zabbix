<?php
require_once dirname(__FILE__).'/include/classes/duo/CDuoWeb.php';
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('ZABBIX');
$page['file'] = 'duo.php';

if (isset($_POST['sig_response'])) {
	/*
	* Verify sig response and log in user. Make sure that verifyResponse
	* returns the username we logged in with. You can then set any
	* cookies/session data for that username and complete the login process.
	*/
	$resp = CDuoWeb::verifyResponse($_POST['sig_response'], $_POST['name']);
	if ($resp === true) {
		API::getWrapper()->auth = $_POST['sessionid'];
		CSessionHelper::set('sessionid', $_POST['sessionid']);
		// 2FA successfull
		redirect('index.php');
		exit;
	}
	// login failed, fall back to a guest account
	else {
		CWebUser::logout();
		redirect('index.php');
		exit;
	}
}

$name = CWebUser::$data['username'];
if (!$name || $name == ZBX_GUEST_USER) {
  // User is not authenticated
  redirect('index.php');
}
// Authentication is not complete yet so reset cookie
// to make it impossible to visit other pages until 2FA complete
$data = [
	'sessionid' => CWebUser::$data['sessionid'],
	'sig_request' => CDuoWeb::signRequest($name),
	'name' => $name
];
CSessionHelper::clear();

echo (new CView('general.duo', $data))
	->getOutput();
?>
