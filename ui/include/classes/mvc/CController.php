<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


abstract class CController {

	const VALIDATION_OK = 0;
	const VALIDATION_ERROR = 1;
	const VALIDATION_FATAL_ERROR = 2;

	/**
	 * Action name, so that controller knows what action he is executing.
	 *
	 * @var string
	 */
	private $action;

	/**
	 * Response object generated by controller.
	 *
	 * @var CControllerResponse
	 */
	private $response;

	/**
	 * Result of input validation, one of VALIDATION_OK, VALIDATION_ERROR, VALIDATION_FATAL_ERROR.
	 *
	 * @var int
	 */
	private $validationResult;

	/**
	 * Input parameters retrieved from global $_REQUEST after validation.
	 *
	 * @var array
	 */
	public $input = [];

	/**
	 * SID validation flag, if true SID must be validated.
	 *
	 * @var bool
	 */
	private $validateSID = true;

	public function __construct() {
		$this->init();
	}

	/**
	 * Initialization function that can be overridden later.
	 */
	protected function init() {
	}

	/**
	 * Return controller action name.
	 *
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * Set controller action name.
	 *
	 * @param string $action
	 */
	public function setAction($action) {
		$this->action = $action;
	}

	/**
	 * Return controller response object.
	 *
	 * @return CControllerResponse
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * Set controller response.
	 *
	 * @param CControllerResponse $response
	 */
	public function setResponse($response) {
		$this->response = $response;
	}

	/**
	 * Return debug mode.
	 *
	 * @return bool
	 */
	public function getDebugMode() {
		return CWebUser::getDebugMode();
	}

	/**
	 * Return user type.
	 *
	 * @return int
	 */
	public function getUserType() {
		return CWebUser::getType();
	}

	/**
	 * Checks access of current user to specific access rule.
	 *
	 * @param string $rule_name  Rule name.
	 *
	 * @return bool  Returns true if user has access to rule, false - otherwise.
	 */
	public function checkAccess(string $rule_name): bool {
		return CWebUser::checkAccess($rule_name);
	}

	/**
	 * Return user SID, first 16 bytes of session ID.
	 *
	 * @return string
	 */
	public function getUserSID() {
		$sessionid = CSessionHelper::getId();

		if ($sessionid === null || strlen($sessionid) < 16) {
			return null;
		}

		return substr($sessionid, 16, 16);
	}

	/**
	 * Parse form data.
	 *
	 * @return boolean
	 */
	protected function parseFormData(): bool {
		$data = base64_decode(getRequest('data'));
		$sign = base64_decode(getRequest('sign'));
		$request_sign = CEncryptHelper::sign($data);

		if (!CEncryptHelper::checkSign($sign, $request_sign)) {
			info(_('Operation cannot be performed due to unauthorized request.'));
			return false;
		}

		$data = json_decode($data, true);

		$_REQUEST = array_merge($_REQUEST, $data['form']);

		if ($data['messages']) {
			CMessageHelper::setScheduleMessages($data['messages']);
		}

		return true;
	}

	/**
	 * Validate input parameters.
	 *
	 * @param array $validationRules
	 *
	 * @return bool
	 */
	public function validateInput($validationRules) {
		if (hasRequest('formdata')) {
			$this->parseFormData();

			// Replace window.history to avoid resubmission warning dialog.
			zbx_add_post_js("history.replaceState({}, '');");
		}

		$validator = new CNewValidator($_REQUEST, $validationRules);

		foreach ($validator->getAllErrors() as $error) {
			info($error);
		}

		if ($validator->isErrorFatal()) {
			$this->validationResult = self::VALIDATION_FATAL_ERROR;
		}
		else if ($validator->isError()) {
			$this->input = $validator->getValidInput();
			$this->validationResult = self::VALIDATION_ERROR;
		}
		else {
			$this->input = $validator->getValidInput();
			$this->validationResult = self::VALIDATION_OK;
		}

		return ($this->validationResult == self::VALIDATION_OK);
	}

	/**
	 * Validate "from" and "to" parameters for allowed period.
	 *
	 * @return bool
	 */
	public function validateTimeSelectorPeriod() {
		if (!$this->hasInput('from') || !$this->hasInput('to')) {
			return true;
		}

		try {
			$max_period = 'now-'.CSettingsHelper::get(CSettingsHelper::MAX_PERIOD);
		}
		catch (Exception $x) {
			access_deny(ACCESS_DENY_PAGE);

			return false;
		}

		$ts = [];
		$ts['now'] = time();
		$range_time_parser = new CRangeTimeParser();

		foreach (['from', 'to'] as $field) {
			$range_time_parser->parse($this->getInput($field));
			$ts[$field] = $range_time_parser
				->getDateTime($field === 'from')
				->getTimestamp();
		}

		$period = $ts['to'] - $ts['from'] + 1;
		$range_time_parser->parse($max_period);
		$max_period = 1 + $ts['now'] - $range_time_parser
			->getDateTime(true)
			->getTimestamp();

		if ($period < ZBX_MIN_PERIOD) {
			info(_n('Minimum time period to display is %1$s minute.',
				'Minimum time period to display is %1$s minutes.', (int) (ZBX_MIN_PERIOD / SEC_PER_MIN)
			));

			return false;
		}
		elseif ($period > $max_period) {
			info(_n('Maximum time period to display is %1$s day.',
				'Maximum time period to display is %1$s days.', (int) ($max_period / SEC_PER_DAY)
			));

			return false;
		}

		return true;
	}

	/**
	 * Return validation result.
	 *
	 * @return int
	 */
	public function getValidationError() {
		return $this->validationResult;
	}

	/**
	 * Check if input parameter exists.
	 *
	 * @param string $var
	 *
	 * @return bool
	 */
	public function hasInput($var) {
		return array_key_exists($var, $this->input);
	}

	/**
	 * Get single input parameter.
	 *
	 * @param string $var
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function getInput($var, $default = null) {
		if ($default === null) {
			return $this->input[$var];
		}
		else {
			return array_key_exists($var, $this->input) ? $this->input[$var] : $default;
		}
	}

	/**
	 * Get several input parameters.
	 *
	 * @param array $var
	 * @param array $names
	 */
	public function getInputs(&$var, $names) {
		foreach ($names as $name) {
			if ($this->hasInput($name)) {
				$var[$name] = $this->getInput($name);
			}
		}
	}

	/**
	 * Return all input parameters.
	 *
	 * @return array
	 */
	public function getInputAll() {
		return $this->input;
	}

	/**
	 * Check user permissions.
	 *
	 * @abstract
	 *
	 * @return bool
	 */
	abstract protected function checkPermissions();

	/**
	 * Validate input parameters.
	 *
	 * @abstract
	 *
	 * @return bool
	 */
	abstract protected function checkInput();

	/**
	 * Validate session ID (SID).
	 */
	public function disableSIDvalidation() {
		$this->validateSID = false;
	}

	/**
	 * Validate session ID (SID).
	 *
	 * @return bool
	 */
	protected function checkSID() {
		$sessionid = CSessionHelper::getId();

		if ($sessionid === null || !isset($_REQUEST['sid'])) {
			return false;
		}

		return ($_REQUEST['sid'] === substr($sessionid, 16, 16));
	}

	/**
	 * Execute action and generate response object.
	 *
	 * @abstract
	 */
	abstract protected function doAction();

	/**
	 * Main controller processing routine. Returns response object: data, redirect or fatal redirect.
	 *
	 * @return CControllerResponse
	 */
	final public function run() {
		if ($this->validateSID && !$this->checkSID()) {
			access_deny(ACCESS_DENY_PAGE);
		}

		if ($this->checkInput()) {
			if ($this->checkPermissions() !== true) {
				access_deny(ACCESS_DENY_PAGE);
			}
			$this->doAction();
		}

		return $this->getResponse();
	}
}
