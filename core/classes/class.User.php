<?php
namespace cs;
class User {
	protected	$current				= [
					'session'		=> false,
					'is'			=> [
						'admin'			=> false,
						'user'			=> false,
						'bot'			=> false,
						'guest'			=> false,
						'system'		=> false
					]
				],
				$id						= false,	//id of current user
				$update_cache			= [],		//Do we need to update users cache
				$data					= [],		//Local cache of users data
				$data_set				= [],		//Changed users data, at the finish, data in db must be replaced by this data
				$db						= false,	//Link to db object
				$db_prime				= false,	//Link to primary db object
				$cache					= [],		//Cache with some temporary data
				$init					= false,	//Current state of initialization
				$reg_id					= 0,		//User id after registration
				$users_columns			= [],		//Copy of columns list of users table for internal needs without Cache usage
				$permissions_table		= [];		//Array of all permissions for quick selecting

	function __construct () {
		global $Cache, $Config, $Page, $L, $Key;
		if (($this->users_columns = $Cache->{'users/columns'}) === false) {
			$this->users_columns = $Cache->{'users/columns'} = $this->db()->columns('[prefix]users');
		}
		//Detecting of current user
		//Last part in page path - key
		$rc = $Config->routing['current'];
		if (
			$this->user_agent == 'CleverStyle CMS' &&
			(
				($this->login_attempts(hash('sha224', 0)) < $Config->core['login_attempts_block_count']) ||
				$Config->core['login_attempts_block_count'] == 0
			) &&
			isset($rc[count($rc) - 1]) &&
			(
				$key_data = $Key->get(
					$Config->components['modules']['System']['db']['keys'],
					$key = $rc[count($rc) - 1],
					true
				)
			) &&
			is_array($key_data)
		) {
			if ($this->current['is']['system'] = $key_data['url'] == $Config->server['host'].'/'.$Config->server['raw_relative_address']) {
				$this->current['is']['admin'] = true;
				interface_off();
				$_POST['data'] = _json_decode($_POST['data']);
				return;
			} else {
				$this->current['is']['guest'] = true;
				//Иммитируем неудачный вход, чтобы при намеренной попытке взлома заблокировать доступ
				$this->login_result(false, hash('sha224', 'system'));
				unset($_POST['data']);
				sleep(1);
			}
		}
		unset($key_data, $key, $rc);
		//If session exists
		if (_getcookie('session')) {
			$this->id = $this->get_session_user();
		} else {
			//Loading bots list
			if (($bots = $Cache->{'users/bots'}) === false) {
				$bots = $this->db()->qfa(
					'SELECT `u`.`id`, `u`.`login`, `u`.`email`
					FROM `[prefix]users` AS `u`, `[prefix]users_groups` AS `g`
					WHERE `u`.`id` = `g`.`id` AND `g`.`group` = 3 AND `u`.`status` = 1'
				);
				if (is_array($bots) && !empty($bots)) {
					foreach ($bots as &$bot) {
						$bot['login'] = _json_decode($bot['login']);
						$bot['email'] = _json_decode($bot['email']);
					}
					unset($bot);
					$Cache->{'users/bots'} = $bots;
				} else {
					$Cache->{'users/bots'} = 'null';
				}
			}
			//For bots: login is user agent, email is IP
			$bot_hash	= hash('sha224', $this->user_agent.$this->ip);
			//If list is not empty - try to find bot
			if (is_array($bots) && !empty($bots)) {
				//Load data
				if (($this->id = $Cache->{'users/'.$bot_hash}) === false) {
					//If no data - try to find bot in list of known bots
					foreach ($bots as &$bot) {
						if (is_array($bot['login']) && !empty($bot['login'])) {
							foreach ($bot['login'] as $login) {
								if ($this->user_agent == $login || preg_match($login, $this->user_agent)) {
									$this->id = $bot['id'];
									break 2;
								}
							}
						}
						if (is_array($bot['email']) && !empty($bot['email'])) {
							foreach ($bot['email'] as $email) {
								if ($this->ip == $email || preg_match($email, $this->ip)) {
									$this->id = $bot['id'];
									break 2;
								}
							}
						}
					}
					unset($bots, $bot, $login, $email);
					//If found id - this i bot
					if ($this->id) {
						$Cache->{'users/'.$bot_hash} = $this->id;
					//If bot not found - will be guest
					} else {
						$Cache->{'users/'.$bot_hash} = $this->id = 1;
					}
				}
			//If bots list is empty - will be guest
			} else {
				$Cache->{'users/'.$bot_hash} = $this->id = 1;
			}
			unset($bots, $bot_hash);
			$this->add_session($this->id);
		}
		//Load user data
		//Return point, runs if user is blocked, inactive, or disabled
		getting_user_data:
		$data = $this->get(['login', 'username', 'language', 'timezone', 'status', 'block_until', 'avatar']);
		if (is_array($data)) {
			if ($data['status'] != 1) {
				//If user is disabled
				if ($data['status'] == 0) {
					$Page->warning($L->your_account_disabled);
					//Mark user as guest, load data again
					$this->del_session();
					goto getting_user_data;
				//If user is not active
				} else {
					$Page->warning($L->your_account_is_not_active);
					//Mark user as guest, load data again
					$this->del_session();
					goto getting_user_data;
				}
			//If user if blocked
			} elseif ($data['block_until'] > TIME) {
				$Page->warning($L->your_account_blocked_until.' '.date($L->_datetime, $data['block_until']));
				//Mark user as guest, load data again
				$this->del_session();
				goto getting_user_data;
			}
		} elseif ($this->id != 1) {
			//If data wasn't loaded - mark user as guest, load data again
			$this->del_session();
			goto getting_user_data;
		}
		unset($data);
		if ($this->id == 1) {
			$this->current['is']['guest'] = true;
		} else {
			//Checking of user type
			$groups = $this->get_user_groups() ?: [];
			if (in_array(1, $groups)) {
				$this->current['is']['admin']	= $Config->can_be_admin;
				$this->current['is']['user']	= true;
			} elseif (in_array(2, $groups)) {
				$this->current['is']['user']	= true;
			} elseif (in_array(3, $groups)) {
				$this->current['is']['guest']	= true;
				$this->current['is']['bot']		= true;
			}
			unset($groups);
		}
		//If not guest - apply some individual settings
		if ($this->id != 1) {
			if ($this->timezone) {
				date_default_timezone_set($this->timezone);
			}
			if ($this->language) {
				if ($this->language != _getcookie('language')) {
					_setcookie('language', $this->language);
				}
				$L->change($this->language);
			}
			if ($this->theme) {
				$theme = _json_decode($this->theme);
				if (
					!is_array($theme) &&
					$theme['theme'] &&
					$theme['color_scheme'] &&
					$theme['theme'] != _getcookie('theme') &&
					$theme['color_scheme'] != _getcookie('color_scheme')
				) {
					_setcookie('theme', $theme['theme']);
					_setcookie('color_scheme', $theme['color_scheme']);
				}
			}
		}
		//Security check for data, sended with POST method
		$session_id = $this->get_session();
		if (!isset($_POST[$session_id]) || $_POST[$session_id] != $session_id) {
			$_POST = [];
		}
		$this->init = true;
	}
	/**
	 * @param string|string[] $item
	 * @param bool|int $user
	 * @return bool|string|string[]
	 */
	function get ($item, $user = false) {
		switch ($item) {
			case 'user_agent':
				return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
			case 'ip':
				return $_SERVER['REMOTE_ADDR'];
			case 'forwarded_for':
				return isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : false;
			case 'client_ip':
				return isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : false;
		}
		return $this->get_internal($item, $user);
	}
	/**
	 * @param string|string[] $item
	 * @param bool|int $user
	 * @param bool $cache_only
	 * @return bool|string|string[]
	 */
	protected function get_internal ($item, $user = false, $cache_only = false) {
		$user = (int)($user ?: $this->id);
		if (!$user) {
			return false;
		}
		global $Cache;
		//Link for simplier use
		$data = &$this->data[$user];
		//Если получаем массив значений
		if (is_array($item)) {
			$result = $new_items = [];
			//Пытаемся достать значения с локального кеша, иначе составляем массив недостающих значений
			foreach ($item as $i) {
				if (in_array($i, $this->users_columns)) {
					if (($res = $this->get($i, $user, true)) !== false) {
						$result[$i] = $res;
					} else {
						$new_items[] = $i;
					}
				}
			}
			if (empty($new_items)) {
				return $result;
			}
			//Если есть недостающие значения - достаем их из БД
			$res = $this->db()->qf(
				'SELECT `'.implode('`, `', $new_items).'`
				FROM `[prefix]users`
				WHERE `id` = '.$user.'
				LIMIT 1'
			);
			if (is_array($res)) {
				$this->update_cache[$user] = true;
				if (isset($res['data'])) {
					$res['data'] = _json_decode($res['data']);
				}
				$data = array_merge((array)$data, $res);
				$result = array_merge($result, $res);
				//Пересортируем результирующий массив в том же порядке, что и входящий массив элементов
				$res = [];
				foreach ($item as $i) {
					$res[$i] = &$result[$i];
				}
				return $res;
			} else {
				return false;
			}
			//Если получаем одно значение
		} elseif (in_array($item, $this->users_columns)) {
			//Указатель начала получения данных
			get_data:
			//Если данные в локальном кеше - возвращаем
			if (isset($data[$item])) {
				return $data[$item];
				//Иначе если из кеша данные не доставали - пробуем достать
			} elseif (!isset($new_data) && ($new_data = $Cache->{'users/'.$user}) !== false && is_array($new_data)) {
				//Обновляем локальный кеш
				if (is_array($new_data)) {
					$data = array_merge((array)$data, $new_data);
				}
				//Делаем новую попытку загрузки данных
				goto get_data;
			} elseif (!$cache_only) {
				$new_data = $this->db()->qf('SELECT `'.$item.'` FROM `[prefix]users` WHERE `id` = '.($user).' LIMIT 1', $item);
				if ($new_data !== false) {
					$this->update_cache[$user] = true;
					if ($item == 'data') {
						$new_data = _json_decode($new_data);
					}
					return $data[$item] = $new_data;
				}
			}
		}
		return false;
	}
	/**
	 * @param array|string $item
	 * @param $value
	 * @param bool|int $user
	 * @return bool
	 */
	function set ($item, $value = '', $user = false) {
		$user = (int)($user ?: $this->id);
		if (!$user) {
			return false;
		}
		if (is_array($item)) {
			foreach ($item as $i => &$v) {
				if (in_array($i, $this->users_columns) && $i != 'id') {
					$this->set($i, $v, $user);
				}
			}
		} elseif (in_array($item, $this->users_columns) && $item != 'id') {
			if ($item == 'login') {
				global $Cache;
				unset($Cache->{'users/'.hash('sha224', $this->$item)});
			}
			$this->update_cache[$user] = true;
			$this->data[$user][$item] = $value;
			if ($this->init) {
				$this->data_set[$user][$item] = $this->data[$user][$item];
			}
		}
		return true;
	}
	function __get ($item) {
		return $this->get($item);
	}
	function __set ($item, $value = '') {
		$this->set($item, $value);
	}
	/**
	 * Returns link to the object of db for reading (can be mirror)
	 * @return \cs\database\_Abstract
	 */
	function db () {
		if (is_object($this->db)) {
			return $this->db;
		}
		if (is_object($this->db_prime)) {
			return $this->db = $this->db_prime;
		}
		global $Config, $db;
		//Save link for faster access
		$this->db = $db->{$Config->components['modules']['System']['db']['users']}();
		return $this->db;
	}
	/**
	 * Returns link to the object of db for writting (always main db)
	 * @return \cs\database\_Abstract
	 */
	function db_prime () {
		if (is_object($this->db_prime)) {
			return $this->db_prime;
		}
		global $Config, $db;
		//Save link for faster access
		$this->db_prime = $db->{$Config->components['modules']['System']['db']['users']}();
		return $this->db_prime;
	}
	/**
	 * Who is current visitor
	 * @param string $mode admin|user|guest|bot|system
	 * @return bool
	 */
	function is ($mode) {
		return isset($this->current['is'][$mode]) && $this->current['is'][$mode];
	}
	/**
	 * Returns user id by login or email hash (sha224)
	 *
	 * @param  string $login_hash
	 * @return bool|int
	 */
	function get_id ($login_hash) {
		if (!preg_match('/^[0-9a-z]{56}$/', $login_hash)) {
			return false;
		}
		global $Cache;
		if (($id = $Cache->{'users/'.$login_hash}) === false) {
			$Cache->{'users/'.$login_hash} = $id = $this->db()->qf(
				'SELECT `id` FROM `[prefix]users`
				WHERE
					`login_hash` = '.$this->db()->s($login_hash).' OR
					`email_hash` = '.$this->db()->s($login_hash).'
				LIMIT 1',
				'id'
			);
		}
		return $id && $id != 1 ? $id : false;
	}
	/**
	 * Returns user name or login or email, depending on existed in DB information
	 *
	 * @param  bool|int $user
	 * @return bool|int
	 */
	function get_username ($user = false) {
		$user = (int)($user ?: $this->id);
		return $this->get('username', $user) ?: ($this->get('login', $user) ?: $this->get('email', $user));
	}
	function search_users ($search_phrase) {
		$search_phrase = $this->db()->s(trim($search_phrase, "%\n").'%');
		$found_users = $this->db()->qfa("
			SELECT `id`
			FROM `[prefix]users`
			WHERE
				(
					`login`		LIKE $search_phrase OR
					`username`	LIKE $search_phrase OR
					`email`		LIKE $search_phrase
				) AND `status` != '-1'",
			'id'
		);
		return $found_users;
	}
	/**
	 * Returns permission state for specified user
	 *
	 * @param int $group		Permission group
	 * @param string $label		Permission label
	 * @param bool|int $user
	 *
	 * @return bool				If permission exists - returns its state for specified user, otherwise returns true
	 */
	function get_user_permission ($group, $label, $user = false) {
		$user = (int)($user ?: $this->id);
		if ($this->is('system') || $user == 2) {
			return true;
		}
		if (!$user) {
			return false;
		}
		if (!isset($this->data[$user])) {
			$this->data[$user] = [];
		}
		if (!isset($this->data[$user]['permissions'])) {
			$this->data[$user]['permissions']	= [];
			$permissions						= &$this->data[$user]['permissions'];
			if ($user != 1) {
				$groups								= $this->get_user_groups($user);
				if (is_array($groups)) {
					foreach ($groups as $group_id) {
						$permissions = array_merge($permissions ?: [], $this->get_group_permissions($group_id) ?: []);
					}
				}
				unset($groups, $group_id);
			}
			$permissions						= array_merge($permissions ?: [], $this->get_user_permissions($user) ?: []);
			unset($permissions);
		}
		if (isset($this->get_permissions_table()[$group], $this->get_permissions_table()[$group][$label])) {
			$permission = $this->get_permissions_table()[$group][$label];
			if (isset($this->data[$user]['permissions'][$permission])) {
				return (bool)$this->data[$user]['permissions'][$permission];
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	/**
	 * Returns array of all permissions state for specified user
	 *
	 * @param bool|int $user
	 * @return array|bool
	 */
	function get_user_permissions ($user = false) {
		$user = (int)($user ?: $this->id);
		if (!$user) {
			return false;
		}
		return $this->get_any_permissions($user, 'user');
	}
	/**
	 * @param	array		$data
	 * @param	bool|int	$user
	 * @return	bool
	 */
	function set_user_permissions ($data, $user = false) {
		$user = (int)($user ?: $this->id);
		if (!$user) {
			return false;
		}
		return $this->set_any_permissions($data, $user, 'user');
	}
	/**
	 * @param	bool|int	$user
	 * @return	bool
	 */
	function del_user_permissions_all ($user = false) {
		$user = (int)($user ?: $this->id);
		if (!$user) {
			return false;
		}
		return $this->del_any_permissions_all($user, 'user');
	}
	/**
	 * Get user groups
	 *
	 * @param	bool|int $user
	 * @return	array|bool
	 */
	function get_user_groups ($user = false) {
		$user = (int)($user ?: $this->id);
		if (!$user || $user == 1) {
			return false;
		}
		global $Cache;
		if (($groups = $Cache->{'users/groups/'.$user}) === false) {
			$groups = $this->db()->qfa('SELECT `group` FROM `[prefix]users_groups` WHERE `id` = '.$user.' ORDER BY `priority` DESC', 'group');
			return $Cache->{'users/groups/'.$user} = $groups;
		}
		return $groups;
	}
	/**
	 * Set user groups
	 *
	 * @param	array	$data
	 * @param	int		$user
	 * @return	bool
	 */
	function set_user_groups ($data, $user) {
		$user		= (int)($user ?: $this->id);
		if (!$user) {
			return false;
		}
		if (!empty($data)) {
			foreach ($data as $i => &$group) {
				if (!($group = (int)$group)) {
					unset($data[$i]);
				}
			}
		}
		unset($i, $group);
		$exitsing	= $this->get_user_groups($user);
		$return		= true;
		$insert		= array_diff($data, $exitsing);
		$delete		= array_diff($exitsing, $data);
		unset($exitsing);
		if (!empty($delete)) {
			$return	= $return && $this->db_prime()->q(
				'DELETE FROM `[prefix]users_groups` WHERE `id` ='.$user.' AND `group` IN ('.implode(', ', $delete).')'
			);
		}
		unset($delete);
		if (!empty($insert)) {
			$q			= [];
			foreach ($insert as $group) {
				$q[] = $user.', '.(int)$group;
			}
			unset($group, $insert);
			$return		= $return && $this->db_prime()->q('
				INSERT INTO `[prefix]users_groups`
					(`id`, `group`)
				VALUES
					('.implode('), (', $q).')'
			);
			unset($q);
		}
		$update		= [];
		foreach ($data as $i => $group) {
			$update[] = 'UPDATE `[prefix]users_groups` SET `priority` = '.(int)$i.' WHERE `id` = '.$user.' AND `group` = '.$group.' LIMIT 1';
		}
		$return		= $return && $this->db_prime()->q($update);
		global $Cache;
		unset(
			$Cache->{'users/groups/'.$user},
			$Cache->{'users/permissions/'.$user}
		);
		return $return;
	}
	/**
	 * Add new group
	 *
	 * @param string $title
	 * @param string $description
	 * @return bool|int
	 */
	function add_group ($title, $description) {
		$title			= $this->db_prime()->s(xap($title, false));
		$description	= $this->db_prime()->s(xap($description, false));
		if (!$title || !$description) {
			return false;
		}
		if ($this->db_prime()->q(
			'INSERT INTO `[prefix]groups` (`title`, `description`) VALUES ('.$title.', '.$description.')'
		)) {
			global $Cache;
			unset($Cache->{'groups/list'});
			return $this->db_prime()->id();
		} else {
			return false;
		}
	}
	/**
	 * Delete group
	 *
	 * @param $group
	 * @return bool
	 */
	function del_group ($group) {
		$group = (int)$group;
		if ($group != 1 && $group != 2 && $group != 3) {
			$return = $this->db_prime()->q([
				'DELETE FROM `[prefix]groups` WHERE `id` = '.$group,
				'DELETE FROM `[prefix]users_groups` WHERE `group` = '.$group,
				'DELETE FROM `[prefix]groups_permissions` WHERE `id` = '.$group
			]);
			global $Cache;
			unset(
				$Cache->{'users/groups/'.$group},
				$Cache->{'users/permissions'},
				$Cache->{'groups/'.$group},
				$Cache->{'groups/permissions/'.$group},
				$Cache->{'groups/list'}
			);
			return (bool)$return;
		} else {
			return false;
		}
	}
	/**
	 * @return array|bool
	 */
	function get_groups_list () {
		global $Cache;
		if (($groups_list = $Cache->{'groups/list'}) === false) {
			$Cache->{'groups/list'} = $groups_list = $this->db()->qfa('SELECT `id`, `title`, `description` FROM `[prefix]groups`');
		}
		return $groups_list;
	}
	/**
	 * @param int $group
	 * @param bool|string $item
	 * @return array|bool
	 */
	function get_group_data ($group, $item = false) {
		global $Cache;
		$group = (int)$group;
		if (!$group) {
			return false;
		}
		if (($group_data = $Cache->{'groups/'.$group}) === false) {
			$group_data = $this->db()->qf(
				'SELECT `title`, `description`, `data`
				FROM `[prefix]groups`
				WHERE `id` = '.$group.'
				LIMIT 1'
			);
			$group_data['data'] = _json_decode($group_data['data']);
			$Cache->{'groups/'.$group} = $group_data;
		}
		if ($item !== false) {
			if (isset($group_data[$item])) {
				return $group_data[$item];
			} else {
				return false;
			}
		} else {
			return $group_data;
		}
	}
	function set_group_data ($data, $group) {
		$group = (int)$group;
		if (!$group) {
			return false;
		}
		$update = [];
		if (isset($data['title'])) {
			$update[] = '`title` = '.$this->db_prime()->s(xap($data['title'], false));
		}
		if (isset($data['description'])) {
			$update[] = '`description` = '.$this->db_prime()->s(xap($data['description'], false));
		}
		if (isset($data['data'])) {
			$update[] = '`data` = '.$this->db_prime()->s(_json_encode($data['data']));
		}
		if (!empty($update) && $this->db_prime()->q('UPDATE `[prefix]groups` SET '.implode(', ', $update).' WHERE `id` = '.$group.' LIMIT 1')) {
			global $Cache;
			unset(
				$Cache->{'groups/'.$group},
				$Cache->{'groups/list'}
			);
			return true;
		} else {
			return false;
		}
	}
	/**
	 * @param int $group
	 * @return array
	 */
	function get_group_permissions ($group) {
		return $this->get_any_permissions($group, 'group');
	}
	function set_group_permissions ($data, $group) {
		return $this->set_any_permissions($data, (int)$group, 'group');
	}
	function del_group_permissions_all ($group) {
		return $this->del_any_permissions_all((int)$group, 'group');
	}
	/**
	 * Common function for get_user_permissions() and get_group_permissions() because of their similarity
	 *
	 * @param	int			$id
	 * @param	string		$type
	 * @return	array|bool
	 */
	protected function get_any_permissions ($id, $type) {
		if (!($id = (int)$id)) {
			return false;
		}
		switch ($type) {
			case 'user':
				$table	= '[prefix]users_permissions';
				$path	= 'users/permissions/';
				break;
			case 'group':
				$table	= '[prefix]group_permissions';
				$path	= 'groups/permissions/';
				break;
			default:
				return false;
		}
		global $Cache;
		if (($permissions = $Cache->{$path.$id}) === false) {
			$permissions_array = $this->db()->qfa('SELECT `permission`, `value` FROM `'.$table.'` WHERE `id` = '.$id);
			if (is_array($permissions_array)) {
				$permissions = [];
				foreach ($permissions_array as $permission) {
					$permissions[$permission['permission']] = (int)(bool)$permission['value'];
				}
				unset($permissions_array, $permission);
				return $Cache->{$path.$id} = $permissions;
			} else {
				return $Cache->{$path.$id} = false;
			}
		}
		return $permissions;
	}
	/**
	 * Common function for set_user_permissions() and set_group_permissions() because of their similarity
	 *
	 * @param	array	$data
	 * @param	int		$id
	 * @param	string	$type
	 * @return	bool
	 */
	protected function set_any_permissions ($data, $id, $type) {
		$id			= (int)$id;
		if (!is_array($data) || empty($data) || !$id) {
			return false;
		}
		switch ($type) {
			case 'user':
				$table	= '[prefix]users_permissions';
				$path	= 'users/permissions/';
				break;
			case 'group':
				$table	= '[prefix]groups_permissions';
				$path	= 'groups/permissions/';
				break;
			default:
				return false;
		}
		$delete = [];
		foreach ($data as $i => $val) {
			if ($val == -1) {
				$delete[] = (int)$i;
				unset($data[$i]);
			}
		}
		unset($i, $val);
		$return = true;
		if (!empty($delete)) {
			$return = $this->db_prime()->q(
				'DELETE FROM `'.$table.'` WHERE `id` = '.$id.' AND `permission` IN ('.implode(', ', $delete).')'
			);
		}
		unset($delete);
		if (!empty($data)) {
			$exitsing	= $this->get_any_permissions($id, $type);
			if (!empty($exitsing)) {
				$update		= [];
				foreach ($exitsing as $permission => $value) {
					if (isset($data[$permission]) && $data[$permission] != $value) {
						$update[] = 'UPDATE `'.$table.'`
							SET `value` = '.(int)(bool)$data[$permission].'
							WHERE `permission` = '.$permission.' AND `id` = '.$id;
					}
					unset($data[$permission]);
				}
				unset($exitsing, $permission, $value);
				if (!empty($update)) {
					$return = $return && $this->db_prime()->q($update);
				}
				unset($update);
			}
			if (!empty($data)) {
				$insert	= [];
				foreach ($data as $permission => $value) {
					$insert[] = $id.', '.(int)$permission.', '.(int)(bool)$value;
				}
				unset($data, $permission, $value);
				if (!empty($insert)) {
					$return = $return && $this->db_prime()->q(
						'INSERT INTO `'.$table.'`
							(`id`, `permission`, `value`)
						VALUES
							('.implode('), (', $insert).')'
					);
				}
			}
		}
		global $Cache;
		unset($Cache->{$path.$id});
		if ($type == 'group') {
			unset($Cache->{'users/permissions'});
		}
		return $return;
	}
	/**
	 * Common function for del_user_permissions_all() and del_group_permissions_all() because of their similarity
	 *
	 * @param	int		$id
	 * @param	string	$type
	 * @return	bool
	 */
	protected function del_any_permissions_all ($id, $type) {
		$id			= (int)$id;
		if (!$id) {
			return false;
		}
		switch ($type) {
			case 'user':
				$table	= '[prefix]users_permissions';
				$path	= 'users/permissions/';
			break;
			case 'group':
				$table	= '[prefix]groups_permissions';
				$path	= 'groups/permissions/';
			break;
			default:
				return false;
		}
		$return = $this->db_prime()->q('DELETE FROM `'.$table.'` WHERE `id` = '.$id);
		if ($return) {
			global $Cache;
			unset($Cache->{$path.$id});
			return true;
		}
		return false;
	}
	function get_permissions_table () {
		if (empty($this->permissions_table)) {
			global $Cache;
			if (($this->permissions_table = $Cache->permissions_table) === false) {
				$this->permissions_table	= [];
				$data						= $this->db()->qfa('SELECT `id`, `label`, `group` FROM `[prefix]permissions`');
				foreach ($data as $item) {
					if (!isset($this->permissions_table[$item['group']])) {
						$this->permissions_table[$item['group']] = [];
					}
					$this->permissions_table[$item['group']][$item['label']] = $item['id'];
				}
				unset($data, $item);
				$Cache->permissions_table = $this->permissions_table;
			}
		}
		return $this->permissions_table;
	}
	function del_permission_table () {
		$this->permissions_table = [];
		global $Cache;
		unset($Cache->permissions_table);
	}
	function add_permission ($group, $label) {
		$group	= $this->db_prime()->s(xap($group));
		$label	= $this->db_prime()->s(xap($label));
		if ($this->db_prime()->q('INSERT INTO `[prefix]permissions` (`label`, `group`) VALUES ('.$label.', '.$group.')')) {
			$this->del_permission_table();
			return $this->db_prime()->id();
		} else {
			return false;
		}
	}
	/**
	 * Get permission data<br>
	 * If <b>$group</b> or/and <b>$label</b> parameter is specified, <b>$id</b> is ignored.
	 *
	 * @param int     $id
	 * @param string  $group
	 * @param string  $label
	 * @param string  $condition and|or
	 *
	 * @return array|bool If only <b>$id</b> specified - result is array of permission data,
	 * in other cases result will be array of arrays of corresponding permissions data.
	 */
	function get_permission ($id = null, $group = null, $label = null, $condition = 'and') {
		switch ($condition) {
			case 'or':
				$condition = 'OR';
			break;
			default:
				$condition = 'AND';
			break;
		}
		if ($group !== null && $group && $label !== null && $label) {
			return $this->db()->qfa('
				SELECT `id`, `label`, `group`
				FROM `[prefix]permissions`
				WHERE `group` = '.$this->db()->s($group).' '.$condition.' `label` = '.$this->db()->s($label)
			);
		} elseif ($group !== null && $group) {
			return $this->db()->qfa('SELECT `id`, `label`, `group` FROM `[prefix]permissions` WHERE `group` = '.$this->db()->s($group));
		} elseif ($label !== null && $label) {
			return $this->db()->qfa('SELECT `id`, `label`, `group` FROM `[prefix]permissions` WHERE `label` = '.$this->db()->s($label));
		} else {
			$id		= (int)$id;
			if (!$id) {
				return false;
			}
			return $this->db()->qf('SELECT `id`, `label`, `group` FROM `[prefix]permissions` WHERE `id` = '.$id.' LIMIT 1');
		}
	}
	function set_permission ($id, $group, $label) {
		$id		= (int)$id;
		if (!$id) {
			return false;
		}
		$group	= $this->db_prime()->s(xap($group));
		$label	= $this->db_prime()->s(xap($label));
		if ($this->db_prime()->q('UPDATE `[prefix]permissions` SET `label` = '.$label.', `group` = '.$group.' WHERE `id` = '.$id.' LIMIT 1')) {
			$this->del_permission_table();
			return true;
		} else {
			return false;
		}
	}
	/**
	 * Deleting of permission or array of permissions
	 *
	 * @param array|int $id
	 *
	 * @return bool
	 */
	function del_permission ($id) {
		if (is_array($id) && !empty($id)) {
			foreach ($id as &$item) {
				$item = (int)$item;
			}
			$id = implode(',', $id);
			return $this->db_prime()->q([
				'DELETE FROM `[prefix]permissions`			WHERE `id` IN ('.$id.') LIMIT 1',
				'DELETE FROM `[prefix]users_permissions`	WHERE `permission` IN ('.$id.')',
				'DELETE FROM `[prefix]groups_permissions`	WHERE `permission` IN ('.$id.')'
			]);
		}
		$id		= (int)$id;
		if (!$id) {
			return false;
		}
		if ($this->db_prime()->q([
			'DELETE FROM `[prefix]permissions`			WHERE `id` = '.$id.' LIMIT 1',
			'DELETE FROM `[prefix]users_permissions`	WHERE `permission` = '.$id,
			'DELETE FROM `[prefix]groups_permissions`	WHERE `permission` = '.$id
		])) {
			global $Cache;
			unset($Cache->{'users/permissions'}, $Cache->{'groups/permissions'});
			$this->del_permission_table();
			return true;
		} else {
			return false;
		}
	}
	/**
	 * Returns current session id
	 *
	 * @return string
	 */
	function get_session () {
		return $this->current['session'];
	}
	/**
	 * Find the session by id, and return id of owner (user)
	 *
	 * @param string $session_id
	 * @return int User id
	 */
	function get_session_user ($session_id = '') {
		$this->current['session'] = _getcookie('session');
		$session_id = $session_id ?: $this->current['session'];
		global $Cache, $Config;
		$result = false;
		if ($session_id && !($result = $Cache->{'sessions/'.$session_id})) {
			$result = $this->db()->qf(
				'SELECT
					`user`, `expire`, `user_agent`, `ip`, `forwarded_for`, `client_ip`
				FROM `[prefix]sessions`
				WHERE
					`id` = '.$this->db()->s($session_id).' AND
					`expire` > '.TIME.' AND
					`user_agent` = '.$this->db()->s($this->user_agent).
					(
					$Config->core['remember_user_ip'] ? ' AND
						`ip` = \''.ip2hex($this->ip).'\' AND
						`forwarded_for` = \''.ip2hex($this->forwarded_for).'\' AND
						`client_ip` = \''.ip2hex($this->client_ip).'\'' : ''
					).'
				LIMIT 1'
			);
			if ($result) {
				$Cache->{'sessions/'.$session_id} = $result;
			}
		}
		if (!$session_id || !is_array($result)) {
			$this->add_session(1);
			return 1;
		}
		$update = [];
		if ($result['user'] != 0 && $this->get('last_login', $result['user']) < TIME - $Config->core['online_time']) {
			$update[] = 'UPDATE `[prefix]users`
				SET
					`last_login`	= '.TIME.',
					`last_ip`	= \''.($ip = ip2hex($this->ip)).'\'
				WHERE `id` ='.$result['user'];
			$this->set('last_login', TIME, $result['user']);
			$this->set('last_ip', $ip, $result['user']);
			unset($ip);
		}
		if ($result['expire'] - TIME < $Config->core['session_expire'] * $Config->core['update_ratio'] / 100) {
			$update[] = 'UPDATE `[prefix]sessions`
				SET `expire` = '.(TIME + $Config->core['session_expire']).'
				WHERE `id` = \''.$session_id.'\'';
			$result['expire'] = TIME + $Config->core['session_expire'];
			$Cache->{'sessions/'.$session_id} = $result;
		}
		if (!empty($update)) {
			$this->db_prime()->q($update);
		}
		return $result['user'];
	}
	/**
	 * Create the session for the user with specified id
	 *
	 * @param int $id
	 * @return bool
	 */
	function add_session ($id) {
		$id = (int)$id;
		if (!$id) {
			$id = 1;
		}
		if (preg_match('/^[0-9a-z]{32}$/', $this->current['session'])) {
			$this->del_session_internal(null, false);
		}
		global $Config;
		//Generate hash in cycle, to obtain unique value
		for ($i = 0; $hash = md5(MICROTIME + $i); ++$i) {
			if ($this->db_prime()->qf('SELECT `id` FROM `[prefix]sessions` WHERE `id` = \''.$hash.'\' LIMIT 1')) {
				continue;
			}
			$this->db_prime()->q([
				'INSERT INTO `[prefix]sessions`
					(`id`, `user`, `created`, `expire`, `user_agent`, `ip`, `forwarded_for`, `client_ip`)
						VALUES
					(
						\''.$hash.'\',
						'.$id.',
						'.TIME.',
						'.(TIME + $Config->core['session_expire']).',
						'.$this->db_prime()->s($this->user_agent).',
						\''.($ip = ip2hex($this->ip)).'\',
						\''.($forwarded_for = ip2hex($this->forwarded_for)).'\',
						\''.($client_ip = ip2hex($this->client_ip)).'\'
					)',
				$id != 1 ? 'UPDATE `[prefix]users` SET `last_login` = '.TIME.', `last_ip` = \''.$ip.'\' WHERE `id` ='.$id : false
			]);
			global $Cache;
			$Cache->{'sessions/'.$hash} = $this->current['session'] = [
				'user'			=> $id,
				'expire'		=> TIME + $Config->core['session_expire'],
				'user_agent'	=> $this->user_agent,
				'ip'			=> $ip,
				'forwarded_for'	=> $forwarded_for,
				'client_ip'		=> $client_ip
			];
			_setcookie('session', $hash, TIME + $Config->core['session_expire'], true);
			$this->get_session_user();
			if (($this->db()->qf('SELECT COUNT(`id`) AS `count` FROM `[prefix]sessions`', 'count') % $Config->core['inserts_limit']) == 0) {
				$this->db_prime()->q('DELETE FROM `[prefix]sessions` WHERE `expire` < '.TIME);
			}
			return true;
		}
		return false;
	}
	/**
	 * Deletion of the session
	 *
	 * @param string $session_id
	 *
	 * @return bool
	 */
	function del_session ($session_id = null) {
		return $this->del_session_internal($session_id);
	}
	/**
	 * Deletion of the session
	 *
	 * @param string $session_id
	 * @param bool   $create_guest_session
	 *
	 * @return bool
	 */
	function del_session_internal ($session_id = null, $create_guest_session = true) {
		$session_id = $session_id ?: $this->current['session'];
		global $Cache;
		$this->current['session'] = false;
		_setcookie('session', '');
		if (!preg_match('/^[0-9a-z]{32}$/', $session_id)) {
			return false;
		}
		unset($Cache->{'sessions/'.$session_id});
		$result = $session_id ? $this->db_prime()->q(
			'UPDATE `[prefix]sessions`
			SET `expire` = 0
			WHERE `id` = \''.$session_id.'\''
		) : false;
		if ($create_guest_session) {
			return $this->add_session(1);
		}
		return $result;
	}
	/**
	 * Remove all user sessions
	 * @param bool|int $id
	 * @return bool
	 */
	function del_all_sessions ($id = false) {
		global $Cache;
		$id = $id ?: $this->id;
		_setcookie('session', '');
		$sessions = $this->db_prime()->qfa('SELECT `id` FROM `[prefix]sessions` WHERE `user` = '.$id, 'id');
		if (is_array($sessions)) {
			$delete = [];
			foreach ($sessions as $session) {
				$delete[] = 'sessions/'.$session;
			}
			$Cache->del($delete);
			unset($delete, $sessions, $session);
		}
		$result = $this->db_prime()->q('UPDATE `[prefix]sessions` SET `expire` = 0 WHERE `user` = '.$id);
		$this->add_session(1);
		return $result;
	}
	/**
	 * Check number of login attempts
	 * @param bool|string $login_hash
	 * @return int Number of attempts
	 */
	function login_attempts ($login_hash = false) {
		$login_hash = $login_hash ?: hash('sha224', $_POST['login']);
		if (!preg_match('/^[0-9a-z]{56}$/', $login_hash)) {
			return false;
		}
		if (isset($this->cache['login_attempts'][$login_hash])) {
			return $this->cache['login_attempts'][$login_hash];
		}
		$count = $this->db()->qf(
			'SELECT COUNT(`expire`) as `count` FROM `[prefix]logins` '.
				'WHERE `expire` > '.TIME.' AND ('.
					'`login_hash` = \''.$login_hash.'\' OR `ip` = \''.ip2hex($this->ip).'\''.
				')',
			'count'
		);
		return $count ? $this->cache['login_attempts'][$login_hash] = $count : 0;
	}
	/**
	 * Process login result
	 * @param bool $result
	 * @param bool|string $login_hash
	 */
	function login_result ($result, $login_hash = false) {
		$login_hash = $login_hash ?: hash('sha224', $_POST['login']);
		if (!preg_match('/^[0-9a-z]{56}$/', $login_hash)) {
			return;
		}
		if ($result) {
			$this->db_prime()->q(
				'UPDATE `[prefix]logins`
				SET `expire` = 0
				WHERE
					`expire` > '.TIME.' AND (
						`login_hash` = \''.$login_hash.'\' OR `ip` = \''.ip2hex($this->ip).'\'
					)'
			);
		} else {
			global $Config;
			$this->db_prime()->q(
				'INSERT INTO `[prefix]logins` (
					`expire`,
					`login_hash`,
					`ip`
				) VALUES (
					'.(TIME + $Config->core['login_attempts_block_time']).',
					\''.$login_hash.'\',
					\''.ip2hex($this->ip).'\'
				)'
			);
			if (isset($this->cache['login_attempts'][$login_hash])) {
				++$this->cache['login_attempts'][$login_hash];
			}
			global $Config;
			if ($this->db_prime()->id() % $Config->core['inserts_limit'] == 0) {
				$this->db_prime()->q('DELETE FROM `[prefix]logins` WHERE `expire` < '.TIME);
			}
		}
	}
	/**
	 * Processing of user registration
	 *
	 * @param string $email
	 * @param bool $confirmation	If <b>true</b> - default system option is used, if <b>false</b> - registration will be
	 *								finished without necessity of confirmation, independently from default system option
	 *								(is used for manual registration).
	 *
	 * @return array|bool|string	<b>exists</b>	- if user with such email is already registered<br>
	 * 								<b>error</b>	- if error occured<br>
	 * 								<b>false</b>	- if email is incorrect<br>
	 * 								<b>array(<br>
	 * 								&nbsp;'reg_key'		=> *,</b>	//Registration confirmation key, or <b>true</b> if confirmation is not required<br>
	 * 								&nbsp;<b>'password'	=> *,</b>	//Automatically generated password<br>
	 * 								&nbsp;<b>'id`		=> *</b>	//Id of registered user in DB<br>
	 * 								<b>)</b>
	 */
	function registration ($email, $confirmation = true) {
		global $Config;
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		$email_hash		= hash('sha224', $email);
		$login			= strstr($email, '@', true);
		$login_hash		= hash('sha224', $login);
		if ($this->get_id($login_hash) !== false) {
			$login		= $email;
			$login_hash	= $email_hash;
		}
		if (!$this->db_prime()->q("SELECT `id` FROM `[prefix]users` WHERE `email_hash` = '$email_hash' LIMIT 1")) {
			return 'exists';
		}
		$password		= password_generate($Config->core['password_min_length'], $Config->core['password_min_strength']);
		$password_hash	= hash('sha512', $password);
		$reg_key		= md5($password.$this->ip);
		$ip_hash		= ip2hex($this->ip);
		if ($this->db_prime()->q(
			"INSERT INTO `[prefix]users` (
				`login`,
				`login_hash`,
				`password_hash`,
				`email`,
				`email_hash`,
				`reg_date`,
				`reg_ip`,
				`reg_key`,
				`status`
			) VALUES (
				'$login',
				'$login_hash',
				'$password_hash',
				'$email',
				'$email_hash',
				".TIME.",
				'$ip_hash',
				'$reg_key',
				".(!$confirmation ? 1 : ($Config->core['require_registration_confirmation'] ? "'-1'" : 1))."
			)"
		)) {
			$this->reg_id = $this->db_prime()->id();
			if (!$confirmation) {
				$this->set_user_groups([2], $this->reg_id);
			}
			if (!$Config->core['require_registration_confirmation'] && $Config->core['autologin_after_registration']) {
				$this->add_session($this->reg_id);
			}
			if ($this->reg_id % $Config->core['inserts_limit'] == 0) {
				$this->db_prime()->q('
					DELETE
					FROM `[prefix]users`
					WHERE
						`login_hash`	= \'\' AND
						`email_hash`	= \'\' AND
						`password_hash`	= \'\' AND
						`status`		= \'-1\' AND
						`id`			!= 1 AND
						`id`			!= 2'
				);
			}
			return [
				'reg_key'	=> !$confirmation ? true : ($Config->core['require_registration_confirmation'] ? $reg_key : true),
				'password'	=> $password,
				'id'		=> $this->reg_id
			];
		} else {
			return 'error';
		}
	}
	/**
	 * Confirmation of registration process
	 * @param $reg_key
	 * @return array|bool
	 */
	function confirmation ($reg_key) {
		global $Config;
		if (!preg_match('/^[0-9a-z]{32}$/', $reg_key)) {
			return false;
		}
		$ids = $this->db_prime()->qfa(
			'SELECT `id` FROM `[prefix]users`
			WHERE
				`last_login` = 0 AND
				`status` = \'-1\' AND
				`reg_date` < '.(TIME - $Config->core['registration_confirmation_time']*86400),	//1 day = 86400 seconds
			'id'
		);
		$this->del_user($ids);
		$data = $this->db_prime()->qf(
			'SELECT `id`, `email` FROM `[prefix]users` WHERE `reg_key` = \''.$reg_key.'\' AND `status` = \'-1\' LIMIT 1'
		);
		if (!isset($data['email'])) {
			return false;
		}
		$this->reg_id	= $data['id'];
		$password		= password_generate($Config->core['password_min_length'], $Config->core['password_min_strength']);
		$this->set(
			[
				'password_hash'	=> hash('sha512', $password),
				'status'		=> 1
			],
			null,
			$this->reg_id
		);
		$this->set_user_groups([2], $this->reg_id);
		$this->add_session($this->reg_id);
		return [
			'email'		=> $data['email'],
			'password'	=> $password
		];
	}
	/**
	 * Canceling of bad registration
	 */
	function registration_cancel () {
		if ($this->reg_id == 0) {
			return;
		}
		$this->add_session(1);
		$this->del_user($this->reg_id);
		$this->reg_id = 0;
	}
	/**
	 * Delete specified user or array of users
	 *
	 * @param $user	array|int User id or array of users ids
	 */
	function del_user ($user) {
		$this->del_user_internal($user);
	}
	/**
	 * @param $user	array|int
	 * @param $update
	 */
	protected function del_user_internal ($user, $update = true) {
		if (is_array($user)) {
			foreach ($user as $id) {
				$this->del_user_internal($id, false);
			}
			$user = implode(',', $user);
			$this->db_prime()->q(
				"UPDATE `[prefix]users` SET
					`login`			= null,
					`login_hash`	= null,
					`username`		= 'deleted',
					`password_hash`	= null,
					`email`			= null,
					`email_hash`	= null,
					`reg_date`		= 0,
					`reg_ip`		= null,
					`reg_key`		= null,
					`status`		= '-1'
				WHERE
					`id` IN ($user)"
			);
			return;
		}
		$user = (int)$user;
		if (!$user) {
			return;
		}
		$this->set_user_groups([], $user);
		$this->del_user_permissions_all($user);
		global $Cache;
		unset($Cache->{'users/'.$user});
		if ($update) {
			$this->db_prime()->q(
				"UPDATE `[prefix]users` SET
					`login`			= null,
					`login_hash`	= null,
					`username`		= 'deleted',
					`password_hash`	= null,
					`email`			= null,
					`email_hash`	= null,
					`reg_date`		= 0,
					`reg_ip`		= null,
					`reg_key`		= null,
					`status`		= '-1'
				WHERE
					`id` = $user
				LIMIT 1"
			);
		}
	}
	/**
	 * Bost addition
	 *
	 * @param string $name			//Bot name
	 * @param string $user_agent	//User Agent string or regular expression
	 * @param string $ip			//IP string or regular expression
	 *
	 * @return bool|int				//Bot <b>id</b> in DB or <b>false</b> on error
	 */
	function add_boot ($name, $user_agent, $ip) {
		$name		= $this->db_prime()->s(xap($name));
		$user_agent	= $this->db_prime()->s(xap($user_agent));
		$ip			= $this->db_prime()->s(xap($ip));
		if ($this->db_prime()->q("INSERT INTO `[prefix]users` (`username`, `login`, `email`, `status`) VALUES ($name, $user_agent, $ip, 1)")) {
			$this->set_user_groups([3], $this->db_prime()->id());
			return $this->db_prime()->id();
		} else {
			return false;
		}
	}
	/**
	 * Delete specified bot or array of users
	 *
	 * @param $bot	array|int Bot id or array of bots ids
	 */
	function del_bot ($bot) {
		$this->del_user($bot);
		global $Cache;
		unset($Cache->{'users/bots'});
	}
	function get_users_columns () {
		return $this->users_columns;
	}
	/**
	 * Cloning restriction
	 */
	function __clone () {}
	/**
	 * Saving cache changing, and users data
	 */
	function __finish () {
		global $Cache;
		//Update users data
		$users_columns = $Cache->{'users/columns'};
		if (is_array($this->data_set) && !empty($this->data_set)) {
			$update = [];
			foreach ($this->data_set as $id => &$data_set) {
				$data = [];
				foreach ($data_set as $i => &$val) {
					if (in_array($i, $users_columns) && $i != 'id') {
						if ($i == 'data') {
							$data[] = '`'.$i.'` = '.$this->db_prime()->s(_json_encode($val));
							continue;
						} elseif ($i == 'text') {
							$val = xap($val, true);
						} else {
							$val = xap($val, false);
						}
						$data[] = '`'.$i.'` = '.$this->db_prime()->s($val);
					} elseif ($i != 'id') {
						unset($data_set[$i]);
					}
				}
				$update[] = 'UPDATE `[prefix]users` SET '.implode(', ', $data).' WHERE `id` = '.$id;
				unset($i, $val, $data);
			}
			if (!empty($update)) {
				$this->db_prime()->q($update);
			}
			unset($update);
		}
		//Update users cache
		foreach ($this->data as $id => &$data) {
			if (isset($this->update_cache[$id]) && $this->update_cache[$id]) {
				$data['id'] = $id;
				$Cache->{'users/'.$id} = $data;
			}
		}
		$this->update_cache = [];
		unset($id, $data);
		$this->data_set = [];
	}
}