<?php

$this->includeJsFile('administration.twofa.edit.js.php');

$widget = (new CWidget())->setTitle(_('Two factor authentication'));

// create form
$twofaForm = (new CForm())->setName('twofaForm');

// create form list
$twofaFormList = new CFormList('twofaList');

// append twofa_type radio buttons to form list
$twofaFormList->addRow(_('Two factor authentication'),
	(new CRadioButtonList('twofa_type', (int) $data['twofa_type']))
		->setAttribute('autofocus', 'autofocus')
		->addValue(_('None'), ZBX_AUTH_2FA_NONE, null, 'submit()')
		->addValue(_('DUO'), ZBX_AUTH_2FA_DUO, null, 'submit()')
		->addValue(_('Google Authenticator'), ZBX_AUTH_2FA_GGL, null, 'submit()')
		->setModern(true)
);

// Add current value of twofa_type
$twofaForm->addVar('action', $data['action_submit']);

// append DUO fields to form list
if ($data['twofa_type'] == ZBX_AUTH_2FA_DUO) {
	$twofaFormList->addRow(
		_('API hostname'),
		(new CTextBox('twofa_duo_api_hostname', $data['twofa_duo_api_hostname']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$twofaFormList->addRow(
		_('Integration key'),
		(new CTextBox('twofa_duo_integration_key', $data['twofa_duo_integration_key']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$twofaFormList->addRow(
		_('Secret key'),
		(new CPassBox('twofa_duo_secret_key', $data['twofa_duo_secret_key']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$twofaFormList->addRow(
		_('40 characters long custom key'),
		(new CPassBox('twofa_duo_a_key', $data['twofa_duo_a_key'], 40))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
} else if ($data['twofa_type'] == ZBX_AUTH_2FA_GGL) {
        $twofaFormList->addRow(
		_('All users will be required to use Google Authenticator application on their devices')
	);
}

// append form list to tab
$twofaTab = new CTabView();
$twofaTab->addTab('twofaTab', '2FA', $twofaFormList);

// create save button
$saveButton = new CSubmit('update', _('Update'));

$twofaTab->setFooter(makeFormFooter($saveButton));

// append tab to form
$twofaForm->addItem($twofaTab);

// append form to widget
$widget->addItem($twofaForm);

$widget->show();
