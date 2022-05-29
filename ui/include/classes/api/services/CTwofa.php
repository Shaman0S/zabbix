<?php declare(strict_types = 1);

/**
 * Class containing methods for operations with 2FA parameters.
 */
class CTwofa extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	/**
	 * @var string
	 */
	protected $tableName = 'config';

	/**
	 * @var string
	 */
	protected $tableAlias = 'c';

	/**
	 * @var array
	 */
	private $output_fields = [
		'twofa_type', 'twofa_duo_api_hostname', 'twofa_duo_integration_key',
		'twofa_duo_secret_key', 'twofa_duo_a_key'
	];

	/**
	 * Get 2FA parameters.
	 *
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function get(array $options): array {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields),
				'default' => API_OUTPUT_EXTEND]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
		}

		$db_twofa = [];

		$result = DBselect($this->createSelectQuery($this->tableName(), $options));

		while ($row = DBfetch($result)) {
			$db_twofa[] = $row;
		}
		$db_twofa = $this->unsetExtraFields($db_twofa, ['configid'], []);

		return $db_twofa[0];
	}

	/**
	 * Update 2FA parameters.
	 *
	 * @param array  $twofa
	 *
	 * @return array
	 */
	public function update(array $twofa): array {
		$db_twofa = $this->validateUpdate($twofa);

		$upd_config = [];

		// strings
		$field_names = [
			'twofa_duo_api_hostname', 'twofa_duo_integration_key',
			'twofa_duo_secret_key', 'twofa_duo_a_key'
		];
		foreach ($field_names as $field_name) {
			if (array_key_exists($field_name, $twofa) && $twofa[$field_name] !== $db_twofa[$field_name]) {
				$upd_config[$field_name] = $twofa[$field_name];
			}
		}

		// integers
		$field_names = ['twofa_type'];
		foreach ($field_names as $field_name) {
			if (array_key_exists($field_name, $twofa) && $twofa[$field_name] != $db_twofa[$field_name]) {
				$upd_config[$field_name] = $twofa[$field_name];
			}
		}

		if ($upd_config) {
			DB::update('config', [
				'values' => $upd_config,
				'where' => ['configid' => $db_twofa['configid']]
			]);
		}

		$this->addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_TWOFA,
			[['configid' => $db_twofa['configid']] + $twofa], [$db_twofa['configid'] => $db_twofa]
		);

		return array_keys($twofa);
	}

	/**
	 * Validate updated 2FA parameters.
	 *
	 * @param array  $twofa
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	protected function validateUpdate(array $twofa): array {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'twofa_type' =>				['type' => API_INT32, 'in' => ZBX_AUTH_2FA_NONE.','.ZBX_AUTH_2FA_DUO.','.ZBX_AUTH_2FA_GGL],
			'twofa_duo_api_hostname' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'twofa_duo_api_hostname')],
			'twofa_duo_integration_key' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'twofa_duo_integration_key')],
			'twofa_duo_secret_key' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'twofa_duo_secret_key')],
			'twofa_duo_a_key' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'twofa_duo_a_key')],
		]];
		if (!CApiInputValidator::validate($api_input_rules, $twofa, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check permissions.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$output_fields = $this->output_fields;
		$output_fields[] = 'configid';

		return DB::select('config', ['output' => $output_fields])[0];
	}
}
