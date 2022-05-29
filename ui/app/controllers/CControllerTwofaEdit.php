<?php

class CControllerTwofaEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	/**
	 * Validate user input.
	 *
	 * @return bool
	 */
	protected function checkInput() {
		$fields = [
			'twofa_type' =>			'db config.twofa_type',
			'form_refresh' =>		'string',
			// actions
			'update' =>			'string',
			// DUO 2FA
			'twofa_duo_api_hostname' =>	'db config.twofa_duo_api_hostname',
			'twofa_duo_integration_key' => 	'db config.twofa_duo_integration_key',
			'twofa_duo_secret_key' =>	'db config.twofa_duo_secret_key',
			'twofa_duo_a_key' =>		'db config.twofa_duo_a_key'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * Validate is user allowed to change configuration.
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction() {
		$data = [
			'action_submit' => 'twofa.update',
			'form_refresh' => 0
		];

		$twofa_params = [
			CTwofaHelper::TWOFA_TYPE,
			CTwofaHelper::TWOFA_DUO_API_HOSTNAME,
			CTwofaHelper::TWOFA_DUO_INTEGRATION_KEY,
			CTwofaHelper::TWOFA_DUO_SECRET_KEY,
			CTwofaHelper::TWOFA_DUO_A_KEY
		];

		$twofa = [];
		foreach ($twofa_params as $param) {
			$twofa[$param] = CTwofaHelper::get($param);
		}

		if ($this->hasInput('form_refresh')) {
			$this->getInputs($data, [
				'form_refresh',
				'twofa_type',
				'twofa_duo_api_hostname',
				'twofa_duo_integration_key',
				'twofa_duo_secret_key',
				'twofa_duo_a_key',
			]);

			$data += $twofa;
		}
		else {
			$data += $twofa;
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(twofa2str($data['twofa_type']));
		$this->setResponse($response);
	}
}
