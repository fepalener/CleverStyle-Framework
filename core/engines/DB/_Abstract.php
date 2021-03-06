<?php
/**
 * @package   CleverStyle Framework
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2011-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
namespace cs\DB;
use
	Exception;

abstract class _Abstract {
	/**
	 * Is connection established
	 *
	 * @var bool
	 */
	protected $connected = false;
	/**
	 * DB type, may be used for constructing requests, accounting particular features of current DB (lowercase name)
	 *
	 * @var string
	 */
	protected $db_type = '';
	/**
	 * Current DB
	 *
	 * @var string
	 */
	protected $database;
	/**
	 * Current prefix
	 *
	 * @var string
	 */
	protected $prefix;
	/**
	 * Total time of requests execution
	 *
	 * @var float
	 */
	protected $time;
	/**
	 * Array for storing of data of the last executed request
	 *
	 * @var array
	 */
	protected $query = [
		'time' => 0,
		'text' => ''
	];
	/**
	 * Array for storing data of all executed requests
	 *
	 * @var array
	 */
	protected $queries = [
		'num'  => 0,
		'time' => [],
		'text' => []
	];
	/**
	 * Connection time
	 *
	 * @var float
	 */
	protected $connecting_time;
	/**
	 * @var bool
	 */
	protected $in_transaction = false;
	/**
	 * Connecting to the DB
	 *
	 * @param string $database
	 * @param string $user
	 * @param string $password
	 * @param string $host
	 * @param string $prefix
	 */
	abstract public function __construct ($database, $user = '', $password = '', $host = 'localhost', $prefix = '');
	/**
	 * Query
	 *
	 * SQL request into DB
	 *
	 * @abstract
	 *
	 * @param string|string[] $query      SQL query string or array, may be a format string in accordance with the first parameter of sprintf() function or may
	 *                                    contain markers for prepared statements (but not both at the same time)
	 * @param array           $parameters There might be arbitrary number of parameters for formatting SQL statement or for using in prepared statements.<br>
	 *                                    If an array provided as second argument - its items will be used, so that you can either specify parameters as an
	 *                                    array, or in line.
	 *
	 * @return bool|object|resource
	 */
	public function q ($query, ...$parameters) {
		$normalized = $this->normalize_parameters($query, $parameters);
		if (!$normalized) {
			return false;
		}
		list($query, $parameters) = $normalized;
		/**
		 * Executing multiple queries
		 */
		if (is_array($query)) {
			return $this->execute_multiple($query, $parameters);
		}
		return $this->execute_single($query, $parameters);
	}
	/**
	 * @param string|string[] $query
	 * @param array           $parameters
	 *
	 * @return array|false
	 */
	protected function normalize_parameters ($query, $parameters) {
		if (!$query) {
			return false;
		}
		$query = str_replace('[prefix]', $this->prefix, $query);
		/** @noinspection NotOptimalIfConditionsInspection */
		if (count($parameters) == 1 && is_array($parameters[0])) {
			$parameters = $parameters[0];
		}
		return [
			$query,
			$parameters
		];
	}
	/**
	 * @param string[] $queries
	 * @param string[] $parameters
	 *
	 * @return bool
	 */
	protected function execute_multiple ($queries, $parameters) {
		$time_from         = microtime(true);
		$parameters_server = [];
		foreach ($queries as &$q) {
			$q = $this->prepare_query_and_parameters($q, $parameters);
			if ($q[1]) {
				$q                 = $q[0];
				$parameters_server = $parameters;
				break;
			}
			$q = $q[0];
		}
		unset($q);
		$this->queries['num'] += count($queries);
		$result = $this->q_multi_internal($queries, $parameters_server);
		$this->time += round(microtime(true) - $time_from, 6);
		return $result;
	}
	/**
	 * @param string   $query
	 * @param string[] $parameters
	 *
	 * @return array
	 */
	protected function prepare_query_and_parameters ($query, $parameters) {
		if (!$parameters || strpos($query, '?') !== false) {
			return [$query, $parameters];
		}
		foreach ($parameters as &$parameter) {
			$parameter = $this->s($parameter, false);
		}
		return [vsprintf($query, $parameters), []];
	}
	/**
	 * @param string   $query
	 * @param string[] $parameters
	 *
	 * @return false|object|resource
	 */
	protected function execute_single ($query, $parameters) {
		$time_from = microtime(true);
		list($query, $parameters) = $this->prepare_query_and_parameters($query, $parameters);
		$this->query['text'] = $query[0];
		if (DEBUG) {
			$this->queries['text'][] = $this->query['text'];
		}
		$result              = $this->q_internal($query, $parameters);
		$this->query['time'] = round(microtime(true) - $time_from, 6);
		$this->time += $this->query['time'];
		if (DEBUG) {
			$this->queries['time'][] = $this->query['time'];
		}
		++$this->queries['num'];
		return $result;
	}
	/**
	 * SQL request to DB
	 *
	 * @abstract
	 *
	 * @param string   $query
	 * @param string[] $parameters If not empty, than server-side prepared statements should be used
	 *
	 * @return false|object|resource
	 */
	abstract protected function q_internal ($query, $parameters = []);
	/**
	 * Multiple SQL request to DB
	 *
	 * @abstract
	 *
	 * @param string[] $query
	 * @param string[] $parameters If not empty, than server-side prepared statements should be used
	 *
	 * @return bool
	 */
	protected function q_multi_internal ($query, $parameters = []) {
		$result = true;
		foreach ($query as $q) {
			$result = $result && $this->q_internal($q, $parameters);
		}
		return $result;
	}
	/**
	 * Fetch
	 *
	 * Fetch a result row as an associative array
	 *
	 * @abstract
	 *
	 * @param false|object|resource $query_result
	 * @param bool                  $single_column If <b>true</b> function will return not array with one element, but directly its value
	 * @param bool                  $array         If <b>true</b> returns array of associative arrays of all fetched rows
	 * @param bool                  $indexed       If <b>false</b> - associative array will be returned
	 *
	 * @return array[]|false|int|int[]|string|string[]
	 */
	abstract public function f ($query_result, $single_column = false, $array = false, $indexed = false);
	/**
	 * Query, Fetch
	 *
	 * Short for `::f(::q())`, arguments are exactly the same as in `::q()`
	 *
	 * @param string[] $query
	 *
	 * @return array|false
	 */
	public function qf (...$query) {
		return $this->f($this->q(...$query));
	}
	/**
	 * Query, Fetch, Array
	 *
	 * Short for `::f(::q(), false, true)`, arguments are exactly the same as in `::q()`
	 *
	 * @param string[] $query
	 *
	 * @return array[]|false
	 */
	public function qfa (...$query) {
		return $this->f($this->q(...$query), false, true);
	}
	/**
	 * Query, Fetch, Single
	 *
	 * Short for `::f(::q(), true)`, arguments are exactly the same as in `::q()`
	 *
	 * @param string[] $query
	 *
	 * @return false|int|string
	 */
	public function qfs (...$query) {
		return $this->f($this->q(...$query), true);
	}
	/**
	 * Query, Fetch, Array, Single
	 *
	 * Short for `::f(::q(), true, true)`, arguments are exactly the same as in `::q()`
	 *
	 * @param string[] $query
	 *
	 * @return false|int[]|string[]
	 */
	public function qfas (...$query) {
		return $this->f($this->q(...$query), true, true);
	}
	/**
	 * Method for simplified inserting of several rows
	 *
	 * @param string        $query
	 * @param array|array[] $parameters Array of array of parameters for inserting
	 * @param bool          $join       If true - inserting of several rows will be combined in one query. For this, be sure, that your query has keyword
	 *                                  <i>VALUES</i> in uppercase. Part of query after this keyword will be multiplied with coma separator.
	 *
	 * @return bool
	 */
	public function insert ($query, $parameters, $join = true) {
		if (!$query || !$parameters) {
			return false;
		}
		if ($join) {
			$query    = explode('VALUES', $query, 2);
			$query[1] = explode(')', $query[1], 2);
			$query    = [
				$query[0],
				$query[1][0].')',
				$query[1][1]
			];
			$query[1] .= str_repeat(",$query[1]", count($parameters) - 1);
			$query = $query[0].'VALUES'.$query[1].$query[2];
			return (bool)$this->q(
				$query,
				array_merge(...array_map('array_values', _array($parameters)))
			);
		} else {
			$result = true;
			foreach ($parameters as $p) {
				$result = $result && (bool)$this->q($query, $p);
			}
			return $result;
		}
	}
	/**
	 * Id
	 *
	 * Get id of last inserted row
	 *
	 * @abstract
	 *
	 * @return int
	 */
	abstract public function id ();
	/**
	 * Affected
	 *
	 * Get number of affected rows during last query
	 *
	 * @abstract
	 *
	 * @return int
	 */
	abstract public function affected ();
	/**
	 * Execute transaction
	 *
	 * All queries done inside callback will be within single transaction, throwing any exception or returning boolean `false` from callback will cause
	 * rollback. Nested transaction calls will be wrapped into single big outer transaction, so you might call it safely if needed.
	 *
	 * @param callable $callback This instance will be used as single argument
	 *
	 * @return bool
	 *
	 * @throws Exception Re-throws exception thrown inside callback
	 */
	public function transaction ($callback) {
		$start_transaction = !$this->in_transaction;
		if ($start_transaction) {
			$this->in_transaction = true;
			if (!$this->q_internal('BEGIN')) {
				return false;
			}
		}
		try {
			$result = $callback($this);
		} catch (Exception $e) {
			$this->transaction_rollback();
			throw $e;
		}
		if ($result === false) {
			$this->transaction_rollback();
			return false;
		} elseif ($start_transaction) {
			$this->in_transaction = false;
			return (bool)$this->q_internal('COMMIT');
		}
		return true;
	}
	protected function transaction_rollback () {
		if ($this->in_transaction) {
			$this->in_transaction = false;
			$this->q_internal('ROLLBACK');
		}
	}
	/**
	 * Free result memory
	 *
	 * @abstract
	 *
	 * @param false|object|resource $query_result
	 */
	abstract public function free ($query_result);
	/**
	 * Get columns list of table
	 *
	 * @param string       $table
	 * @param false|string $like
	 *
	 * @return string[]
	 */
	abstract public function columns ($table, $like = false);
	/**
	 * Get tables list
	 *
	 * @param false|string $like
	 *
	 * @return string[]
	 */
	abstract public function tables ($like = false);
	/**
	 * Safe
	 *
	 * Preparing string for using in SQL query
	 * SQL Injection Protection
	 *
	 * @param string|string[] $string
	 * @param bool            $single_quotes_around
	 *
	 * @return string|string[]
	 */
	public function s ($string, $single_quotes_around = true) {
		if (is_array($string)) {
			foreach ($string as &$s) {
				$s = $this->s_internal($s, $single_quotes_around);
			}
			return $string;
		}
		return $this->s_internal($string, $single_quotes_around);
	}
	/**
	 * Preparing string for using in SQL query
	 * SQL Injection Protection
	 *
	 * @param string $string
	 * @param bool   $single_quotes_around
	 *
	 * @return string
	 */
	abstract protected function s_internal ($string, $single_quotes_around);
	/**
	 * Get information about server
	 *
	 * @return string
	 */
	abstract public function server ();
	/**
	 * Connection state
	 *
	 * @return bool
	 */
	public function connected () {
		return $this->connected;
	}
	/**
	 * Database type (lowercase, for example <i>mysql</i>)
	 *
	 * @return string
	 */
	public function db_type () {
		return $this->db_type;
	}
	/**
	 * Database name
	 *
	 * @return string
	 */
	public function database () {
		return $this->database;
	}
	/**
	 * Queries array, has 3 properties:<ul>
	 * <li>num - total number of performed queries
	 * <li>time - array with time of each query execution
	 * <li>text - array with text text of each query
	 *
	 * @return array
	 */
	public function queries () {
		return $this->queries;
	}
	/**
	 * Last query information, has 2 properties:<ul>
	 * <li>time - execution time
	 * <li>text - query text
	 *
	 * @return array
	 */
	public function query () {
		return $this->query;
	}
	/**
	 * Total working time (including connection, queries execution and other delays)
	 *
	 * @return float
	 */
	public function time () {
		return $this->time;
	}
	/**
	 * Connecting time
	 *
	 * @return float
	 */
	public function connecting_time () {
		return $this->connecting_time;
	}
	/**
	 * Cloning restriction
	 *
	 * @final
	 */
	final protected function __clone () {
	}
	/**
	 * Disconnecting from DB
	 */
	abstract public function __destruct ();
}
