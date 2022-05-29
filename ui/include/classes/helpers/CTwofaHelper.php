<?php declare(strict_types = 1);

/**
 * A class for accessing once loaded parameters of Authentication API object.
 */
class CTwofaHelper extends CConfigGeneralHelper {

	public const TWOFA_TYPE = 'twofa_type';
	public const TWOFA_DUO_API_HOSTNAME = 'twofa_duo_api_hostname';
	public const TWOFA_DUO_INTEGRATION_KEY = 'twofa_duo_integration_key';
	public const TWOFA_DUO_SECRET_KEY = 'twofa_duo_secret_key';
	public const TWOFA_DUO_A_KEY = 'twofa_duo_a_key';

	/**
	 * Authentication API object parameters array.
	 *
	 * @static
	 *
	 * @var array
	 */
	protected static $params = [];

	/**
	 * @inheritdoc
	 */
	protected static function loadParams(?string $param = null, bool $is_global = false): void {
		if (!self::$params) {
			self::$params = API::Twofa()->get(['output' => 'extend']);

			if (self::$params === false) {
				throw new Exception(_('Unable to load 2FA API parameters.'));
			}
		}
	}
}
