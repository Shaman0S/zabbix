<?php

/**
 * Class containing methods for operations with user groups.
 */
class CAdUserGroup extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];
	protected $tableName = 'adusrgrp';
	protected $tableAlias = 'ag';
	protected $sortColumns = ['adusrgrpid', 'name'];

	/**
	 * Get AD groups.
	 *
	 * @param array  $options
	 * @param array  $options['adusrgrpids']
	 * @param array  $options['usrgrpids']
	 * @param int    $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['adusrgrp' => 'ag.adusrgrpid'],
			'from'		=> ['adusrgrp' => 'adusrgrp ag'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'adusrgrpids'				=> null,
			'usrgrpids'				=> null,
			// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'		=> null,
			// output
			'editable'				=> false,
			'output'				=> API_OUTPUT_EXTEND,
			'selectUsrgrps'				=> null,
			'selectRole'				=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		];

		$options = zbx_array_merge($defOptions, $options);

		// permissions
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if (!$options['editable']) {
				$sqlParts['where'][] = 'ag.usrgrpid IN ('.
					'SELECT adgg.usrgrpid'.
					' FROM adgroups_groups adgg'.
					' WHERE adgg.adusrgrpid='.self::$userData['adusrgrpid'].
				')';
			}
			else {
				return [];
			}
		}

		// adusrgrpids
		if ($options['adusrgrpids'] !== null) {
			zbx_value2array($options['adusrgrpids']);

			$sqlParts['where'][] = dbConditionInt('ag.adusrgrpid', $options['adusrgrpids']);
		}

		// usrgrpids
		if ($options['usrgrpids'] !== null) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['from']['adgroups_groups'] = 'adgroups_groups agg';
			$sqlParts['where'][] = dbConditionInt('agg.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['agug'] = 'agg.adusrgrpid=ag.adusrgrpid';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('adusrgrp ag', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('adusrgrp ag', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($adusrgrp = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $adusrgrp['rowscount'];
			}
			else {
				$result[$adusrgrp['adusrgrpid']] = $adusrgrp;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		// adding user groups
		if ($options['selectUsrgrps'] !== null && $options['selectUsrgrps'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'adusrgrpid', 'usrgrpid', 'adgroups_groups');

			$dbUserGroups = API::UserGroup()->get([
				'output' => $options['selectUsrgrps'],
				'usrgrpids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);

			$result = $relationMap->mapMany($result, $dbUserGroups, 'usrgrps');
		}

		$adusrgrpIds = zbx_objectValues($result, 'adusrgrpid');
		// adding user role
		if ($options['selectRole'] !== null && $options['selectRole'] !== API_OUTPUT_COUNT) {
			if ($options['selectRole'] === API_OUTPUT_EXTEND) {
				$options['selectRole'] = ['roleid', 'name', 'type', 'readonly'];
			}

			$db_roles = DBselect(
				'SELECT adg.adusrgrpid'.($options['selectRole'] ? ',r.'.implode(',r.', $options['selectRole']) : '').
				' FROM adusrgrp adg,role r'.
				' WHERE adg.roleid=r.roleid'.
				' AND '.dbConditionInt('adg.adusrgrpid', $adusrgrpIds)
			);

			foreach ($result as $adusrgrpid => $adusrgrp) {
				$result[$adusrgrpid]['role'] = [];
			}

			while ($db_role = DBfetch($db_roles)) {
				$adusrgrpid = $db_role['adusrgrpid'];
				unset($db_role['adusrgrpid']);

				$result[$adusrgrpid]['role'] = $db_role;
			}
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array  $adusrgrps
	 *
	 * @return array
	 */
	public function create(array $adusrgrps) {
		$this->validateCreate($adusrgrps);

		$ins_adusrgrps = [];

		foreach ($adusrgrps as $adusrgrp) {
			unset($adusrgrp['usgrpids']);
			$ins_adusrgrps[] = $adusrgrp;
		}
		$adusrgrpids = DB::insert('adusrgrp', $ins_adusrgrps);

		foreach ($adusrgrps as $index => &$adusrgrp) {
			$adusrgrp['adusrgrpid'] = $adusrgrpids[$index];
		}
		unset($adusrgrp);

		$this->updateAdGroupsGroups($adusrgrps, __FUNCTION__);

		$this->addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_AD_GROUP, $adusrgrps);

		return ['adusrgrpids' => $adusrgrpids];
	}

	/**
	 * @param array $adusrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$adusrgrps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permissions to create LDAP groups.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('adusrgrp', 'name')],
			'roleid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'usrgrps' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
			'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $adusrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(zbx_objectValues($adusrgrps, 'name'));
		$this->checkUserGroups($adusrgrps);
	}

	/**
	 * @param array  $adusrgrps
	 *
	 * @return array
	 */
	public function update($adusrgrps) {
		$this->validateUpdate($adusrgrps, $db_adusrgrps);

		$upd_adusrgrps = [];

		foreach ($adusrgrps as $adusrgrp) {
			$db_adusrgrp = $db_adusrgrps[$adusrgrp['adusrgrpid']];

			$upd_adusrgrp = [];

			if (array_key_exists('name', $adusrgrp) && $adusrgrp['name'] !== $db_adusrgrp['name']) {
				$upd_adusrgrp['name'] = $adusrgrp['name'];
			}

			if (array_key_exists('roleid', $adusrgrp) && $adusrgrp['roleid'] != $db_adusrgrp['roleid']) {
				$upd_adusrgrp['roleid'] = $adusrgrp['roleid'];
			}

			if ($upd_adusrgrp) {
				$upd_adusrgrps[] = [
					'values' => $upd_adusrgrp,
					'where' => ['adusrgrpid' => $adusrgrp['adusrgrpid']]
				];
			}
		}

		if ($upd_adusrgrps) {
			DB::update('adusrgrp', $upd_adusrgrps);
		}

		$this->updateAdGroupsUserGroups($adusrgrps, __FUNCTION__);
		$this->addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_AD_GROUP, $adusrgrps, $db_adusrgrps);

		return ['adusrgrpids'=> zbx_objectValues($adusrgrps, 'adusrgrpid')];
	}

	/**
	 * @param array $adusrgrps
	 * @param array $db_adusrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$adusrgrps, array &$db_adusrgrps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update AD groups.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['adusrgrpid'], ['name']], 'fields' => [
			'adusrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('adusrgrp', 'name')],
			'roleid' =>		['type' => API_ID],
			'usrgrps' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
			'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $adusrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check AD group names.
		$db_adusrgrps = DB::select('adusrgrp', [
			'output' => ['adusrgrpid', 'name', 'roleid'],
			'adusrgrpids' => zbx_objectValues($adusrgrps, 'adusrgrpid'),
			'preservekeys' => true
		]);

		// Get readonly super admin role ID and name.
		$db_roles = DBfetchArray(DBselect(
			'SELECT roleid,name'.
			' FROM role'.
			' WHERE type='.USER_TYPE_SUPER_ADMIN.
				' AND readonly=1'
		));
		$readonly_superadmin_role = $db_roles[0];

		$superadminids_to_update = [];
		$names = [];
		$check_roleids = [];

		foreach ($adusrgrps as $adusrgrp) {
			// Check if this AD group exists.
			if (!array_key_exists($adusrgrp['adusrgrpid'], $db_adusrgrps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_adusrgrp = $db_adusrgrps[$adusrgrp['adusrgrpid']];

			if (array_key_exists('name', $adusrgrp) && $adusrgrp['name'] !== $db_adusrgrp['name']) {
				$names[] = $adusrgrp['name'];
			}

			if (array_key_exists('roleid', $adusrgrp) && $adusrgrp['roleid'] != $adusrgrp['roleid']) {
				if ($db_adusrgrp['roleid'] == $readonly_superadmin_role['roleid']) {
					$superadminids_to_update[] = $adusrgrp['userid'];
				}

				$check_roleids[] = $user['roleid'];
			}
		}

		if ($names) {
			$this->checkDuplicates($names);
		}

		if ($check_roleids) {
			$this->checkRoles($check_roleids);
		}

		$this->checkUserGroups($adusrgrps);
	}

	/**
	 * Check for duplicated AD groups.
	 *
	 * @param array  $names
	 *
	 * @throws APIException  if AD group already exists.
	 */
	private function checkDuplicates(array $names) {
		$db_adusrgrps = DB::select('adusrgrp', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_adusrgrps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('LDAP group "%1$s" already exists.', $db_adusrgrps[0]['name']));
		}
	}

	/**
	 * Check for valid user groups.
	 *
	 * @param array  $adusrgrps
	 * @param array  $adusrgrps[]['usrgrpids']   (optional)
	 *
	 * @throws APIException
	 */
	private function checkUserGroups(array $adusrgrps) {
		$usrgrpids = [];

		foreach ($adusrgrps as $adusrgrp) {
			if (array_key_exists('usrgrpids', $adusrgrp)) {
				foreach ($adusrgrp['usrgrpids'] as $usrgrpid) {
					$usrgrpids[$usrgrpid] = true;
				}
			}
		}

		if (!$usrgrpids) {
			return;
		}

		$usrgrpids = array_keys($usrgrpids);

		$db_usrgrps = DB::select('usrgrp', [
			'output' => [],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		foreach ($usrgrpids as $usrgrpid) {
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group with ID "%1$s" is not available.', $usrgrpid));
			}
		}
	}

	/**
	 * Check to exclude an opportunity to leave AD group without user groups.
	 *
	 * @param array  $adusrgrps
	 * @param array  $adusrgrps[]['adusrgrpid']
	 * @param array  $adusrgrps[]['usrgrpids']   (optional)
	 *
	 * @throws APIException
	 */
	private function checkAdGroupsWithoutUserGroups(array $adusrgrps) {
		$adgroups_groups = [];

		foreach ($adusrgrps as $adusrgrp) {
			if (array_key_exists('usrgrpids', $adusrgrp)) {
				$adgroups_groups[$adusrgrp['adusrgrpid']] = [];

				foreach ($adusrgrp['usrgrpids'] as $usrgrpid) {
					$adgroups_groups[$adusrgrp['adusrgrpid']][$usrgrpid] = true;
				}
			}
		}

		if (!$adgroups_groups) {
			return;
		}

		$db_adgroups_groups = DB::select('adgroups_groups', [
			'output' => ['usrgrpid', 'adusrgrpid'],
			'filter' => ['usrgrpid' => array_keys($adgroups_groups)]
		]);

		$ins_usrgrpids = [];
		$del_usrgrpids = [];

		foreach ($db_adgroups_groups as $db_adgroup_group) {
			if (array_key_exists($db_adgroup_group['usrgrpid'], $adgroups_groups[$db_adgroups_group['adusrgrpid']])) {
				unset($adgroups_groups[$db_adgroup_group['adusrgrpid']][$db_adgroup_group['usrgrpid']]);
			}
			else {
				if (!array_key_exists($db_adgroup_group['usrgrpid'], $del_usrgrpids)) {
					$del_usrgrpids[$db_adgroup_group['usrgrpid']] = 0;
				}
				$del_usrgrpids[$db_adgroup_group['usrgrpid']]++;
			}
		}

		foreach ($adgroups_groups as $adusrgrpid => $usrgrpids) {
			foreach (array_keys($usrgrpids) as $usrgrpid) {
				$ins_usrgrpids[$usrgrpid] = true;
			}
		}

		$del_usrgrpids = array_diff_key($del_usrgrpids, $ins_usrgrpids);

		if (!$del_usrgrpids) {
			return;
		}

		$db_usrgrps = DBselect(
			'SELECT ag.adusrgrpid,ag.name,count(agg.usrgrpid) as usrgrp_num'.
			' FROM adusrgrp ag,adgroups_groups agg'.
			' WHERE ag.adusrgrpid=agg.adusrgrpid'.
				' AND '.dbConditionInt('agg.usrgrpid', array_keys($del_usrgrpids)).
			' GROUP BY u.userid,u.alias'
		);

		while ($db_usrgrp = DBfetch($db_usrgrps)) {
			if ($db_usrgrp['usrgrp_num'] == $del_usrgrpids[$db_usrgrp['usrgrpid']]) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('LDAP group "%1$s" cannot be without user group.', $db_usrgrp['name'])
				);
			}
		}
	}

	/**
	 * Check for valid user roles.
	 *
	 * @param array $roleids
	 *
	 * @throws APIException
	 */
	private function checkRoles(array $roleids): void {
		$db_roles = DB::select('role', [
			'output' => ['roleid'],
			'roleids' => $roleids,
			'preservekeys' => true
		]);

		foreach ($roleids as $roleid) {
			if (!array_key_exists($roleid, $db_roles)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User role with ID "%1$s" is not available.', $roleid));
			}
		}
	}

	/**
	 * Update table "adgroups_groups".
	 *
	 * @param array  $adusrgrps
	 * @param string $method
	 */
	private function updateAdGroupsGroups(array $adusrgrps, $method) {
		$adgroups_groups = [];

		foreach ($adusrgrps as $adusrgrp) {
			if (array_key_exists('usrgrps', $adusrgrp)) {
				$adgroups_groups[$adusrgrp['adusrgrpid']] = [];

				foreach ($adusrgrp['usrgrps'] as $usrgrp) {
					$adgroups_groups[$adusrgrp['adusrgrpid']][$usrgrp['usrgrpid']] = true;
				}
			}
		}

		if (!$adgroups_groups) {
			return;
		}

		$db_adgroups_groups = ($method === 'update')
			? DB::select('adgroups_groups', [
				'output' => ['id', 'usrgrpid', 'adusrgrpid'],
				'filter' => ['adusrgrpid' => array_keys($adgroups_groups)]
			])
			: [];

		$ins_adgroups_groups = [];
		$del_ids = [];

		foreach ($db_adgroups_groups as $db_adgroup_group) {
			if (array_key_exists($db_adgroup_group['usrgrpid'], $adgroups_groups[$db_adgroup_group['adusrgrpid']])) {
				unset($adgroups_groups[$db_adgroup_group['adusrgrpid']][$db_adgroup_group['usrgrpid']]);
			}
			else {
				$del_ids[] = $db_adgroup_group['id'];
			}
		}

		foreach ($adgroups_groups as $adusrgrpid => $usrgrpids) {
			foreach (array_keys($usrgrpids) as $usrgrpid) {
				$ins_adgroups_groups[] = [
					'adusrgrpid' => $adusrgrpid,
					'usrgrpid' => $usrgrpid
				];
			}
		}

		if ($ins_adgroups_groups) {
			DB::insertBatch('adgroups_groups', $ins_adgroups_groups);
		}

		if ($del_ids) {
			DB::delete('adgroups_groups', ['id' => $del_ids]);
		}
	}

	/**
	 * @param array $adusrgrpids
	 *
	 * @return array
	 */
	public function delete(array $adusrgrpids) {
		$this->validateDelete($adusrgrpids, $db_adusrgrps);

		DB::delete('adgroups_groups', ['adusrgrpid' => $adusrgrpids]);
		DB::delete('adusrgrp', ['adusrgrpid' => $adusrgrpids]);

		$this->addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_AD_GROUP, $db_adusrgrps);

		return ['adusrgrpids' => $adusrgrpids];
	}

	/**
	 * @throws APIException
	 *
	 * @param array $adusrgrpids
	 * @param array $db_adusrgrps
	 */
	protected function validateDelete(array &$adusrgrpids, array &$db_adusrgrps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete LDAP groups.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $adusrgrpids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_adusrgrps = DB::select('adusrgrp', [
			'output' => ['adusrgrpid', 'name'],
			'adusrgrpids' => $adusrgrpids,
			'preservekeys' => true
		]);

		$adusrgrps = [];

		foreach ($adusrgrpids as $adusrgrpid) {
			// Check if this AD group exists.
			if (!array_key_exists($adusrgrpid, $db_adusrgrps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$adusrgrps[] = [
				'adusrgrpid' => $adusrgrpid,
				'userids' => []
			];
		}

		$this->checkAdGroupsWithoutUserGroups($adusrgrps);
	}

	/**
	 * Update table "adgroups_groups".
	 *
	 * @param array  $adusrgrps
	 * @param string $method
	 */
	private function updateAdGroupsUserGroups(array $adusrgrps, $method) {
		$users_groups = [];

		foreach ($adusrgrps as $adusrgrp) {
			if (array_key_exists('usrgrps', $adusrgrp)) {
				$users_groups[$adusrgrp['adusrgrpid']] = [];

				foreach ($adusrgrp['usrgrps'] as $usrgrp) {
					$users_groups[$adusrgrp['adusrgrpid']][$usrgrp['usrgrpid']] = true;
				}
			}
		}

		if (!$users_groups) {
			return;
		}

		$db_adgroups_groups = ($method === 'update')
			? DB::select('adgroups_groups', [
				'output' => ['id', 'usrgrpid', 'adusrgrpid'],
				'filter' => ['adusrgrpid' => array_keys($users_groups)]
			])
			: [];

		$ins_users_groups = [];
		$del_ids = [];

		foreach ($db_adgroups_groups as $db_adgroup_group) {
			if (array_key_exists($db_adgroup_group['usrgrpid'], $users_groups[$db_adgroup_group['adusrgrpid']])) {
				unset($users_groups[$db_adgroup_group['adusrgrpid']][$db_adgroup_group['usrgrpid']]);
			}
			else {
				$del_ids[] = $db_adgroup_group['id'];
			}
		}

		foreach ($users_groups as $adusrgrpid => $usrgrpids) {
			foreach (array_keys($usrgrpids) as $usrgrpid) {
				$ins_users_groups[] = [
					'adusrgrpid' => $adusrgrpid,
					'usrgrpid' => $usrgrpid
				];
			}
		}

		if ($ins_users_groups) {
			DB::insertBatch('adgroups_groups', $ins_users_groups);
		}

		if ($del_ids) {
			DB::delete('adgroups_groups', ['id' => $del_ids]);
		}
	}
}
