<?php
/**
 * @package   CleverStyle Framework
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
namespace cs;
/**
 * CRUD trait extended with useful additional methods, for example, for searching
 *
 * Might me used instead of regular CRUD trait
 */
trait CRUD_helpers {
	use
		CRUD;
	/**
	 * Generic search
	 *
	 * @param mixed[] $search_parameters Array in form [attribute => value];<br>
	 *                                   Or [attribute => [value1, value2, value3]];<br>
	 *                                   Or [attribute => [from => a, to => b]];<br>
	 *                                   Or [attribute => [...]] in case of joined tables, where ... is any of three constructions mentioned above;<br>
	 *                                   if `total_count => true` element is present - total number of found rows will be returned instead of rows themselves
	 * @param int     $page
	 * @param int     $count
	 * @param string  $order_by
	 * @param bool    $asc
	 *
	 * @return false|int|int[]|string[] Array of `id` or number of elements
	 */
	protected function search ($search_parameters = [], $page = 1, $count = 100, $order_by = 'id', $asc = false) {
		$joins        = '';
		$join_params  = [];
		$join_index   = 0;
		$where        = [];
		$where_params = [];
		foreach ($search_parameters as $key => $details) {
			if (!isset($this->data_model[$key])) {
				continue;
			}
			if (isset($this->data_model[$key]['data_model'])) {
				$this->search_conditions_join_table('t', $key, $details, $joins, $join_params, $join_index);
			} else {
				$this->search_conditions('t', $key, $details, $where, $where_params);
			}
		}
		return $this->search_do('t', @$search_parameters['total_count'], $where, $where_params, $joins, $join_params, $page, $count, $order_by, $asc);
	}
	/**
	 * @param string   $table_alias
	 * @param bool     $total_count
	 * @param string[] $where
	 * @param array    $where_params
	 * @param string   $joins
	 * @param array    $join_params
	 * @param int      $page
	 * @param int      $count
	 * @param string   $order_by
	 * @param bool     $asc
	 *
	 * @return false|int|int[]|string[]
	 */
	private function search_do ($table_alias, $total_count, $where, $where_params, $joins, $join_params, $page, $count, $order_by, $asc) {
		$first_column = array_keys($this->data_model)[0];
		$where        = $where ? 'WHERE '.implode(' AND ', $where) : '';
		if ($total_count) {
			return (int)$this->db()->qfs(
				"SELECT COUNT(`$table_alias`.`$first_column`)
				FROM `$this->table` AS `$table_alias`
				$joins
				$where
				GROUP BY `$table_alias`.`$first_column`",
				array_merge($join_params, $where_params)
			);
		}
		$where_params[] = $count;
		$where_params[] = ($page - 1) * $count;
		$order_by       = $this->search_order_by($table_alias, $order_by, $joins, $join_index);
		$asc            = $asc ? 'ASC' : 'DESC';
		$return         = $this->db()->qfas(
			"SELECT `$table_alias`.`$first_column`
			FROM `$this->table` AS `$table_alias`
			$joins
			$where
			GROUP BY `$table_alias`.`$first_column`
			ORDER BY $order_by $asc
			LIMIT %d OFFSET %d",
			array_merge($join_params, $where_params)
		);
		return $this->read_field_post_processing($return, array_values($this->data_model)[0]);
	}
	/**
	 * @param string   $table_alias
	 * @param string   $key
	 * @param array    $details
	 * @param string[] $where
	 * @param array    $where_params
	 */
	private function search_conditions ($table_alias, $key, $details, &$where, &$where_params) {
		if (is_scalar($details)) {
			$where[]        = "`$table_alias`.`$key` = '%s'";
			$where_params[] = $details;
		} elseif (is_array($details) && $details) {
			if (is_array_indexed($details)) {
				$where_tmp = [];
				foreach ($details as $d) {
					$where_tmp[]    = "`$table_alias`.`$key` = '%s'";
					$where_params[] = $d;
				}
				$where[] = '('.implode(' OR ', $where_tmp).')';
				return;
			}
			if (isset($details['from'])) {
				$where[]        = "`$table_alias`.`$key` >= '%s'";
				$where_params[] = $details['from'];
			}
			if (isset($details['to'])) {
				$where[]        = "`$table_alias`.`$key` <= '%s'";
				$where_params[] = $details['to'];
			}
		}
	}
	/**
	 * @param string           $table_alias
	 * @param string           $table
	 * @param array|int|string $details
	 * @param string           $joins
	 * @param array            $join_params
	 * @param int              $join_index
	 */
	private function search_conditions_join_table ($table_alias, $table, $details, &$joins, &$join_params, &$join_index) {
		$data_model        = $this->data_model[$table];
		$first_column      = array_keys($this->data_model)[0];
		$first_column_join = array_keys($data_model['data_model'])[0];
		if (is_scalar($details)) {
			$details = [
				array_keys($data_model['data_model'])[1] => $details
			];
		}
		/**
		 * @var array $details
		 */
		++$join_index;
		$joins .=
			"INNER JOIN `{$this->table}_$table` AS `j$join_index`
			ON
				`$table_alias`.`$first_column` = `j$join_index`.`$first_column_join`";
		$language_field = isset($data_model['language_field']) ? $data_model['language_field'] : false;
		foreach ($details as $field => $value) {
			if ($language_field === $field) {
				continue;
			}
			$where_tmp = [];
			$this->search_conditions("j$join_index", $field, $value, $where_tmp, $join_params);
			$joins .= ' AND '.implode(' AND ', $where_tmp);
		}
		if ($language_field) {
			/** @noinspection OffsetOperationsInspection */
			$clang = isset($details[$language_field]) ? $details[$language_field] : Language::instance()->clang;
			$joins .=
				" AND
				(
					`j$join_index`.`lang`	= '$clang' OR
					`j$join_index`.`lang`	= ''
				)";
		}
	}
	/**
	 * @param string $table_alias
	 * @param string $order_by
	 * @param string $joins
	 * @param int    $join_index
	 *
	 * @return string
	 */
	private function search_order_by ($table_alias, $order_by, &$joins, &$join_index) {
		$order_by = explode(':', $order_by);
		if (!isset($this->data_model[$order_by[0]])) {
			/**
			 * Non-existing field
			 */
			$order_by = ['id'];
		} elseif (isset($order_by[1])) {
			/**
			 * Non-existing field in joined table
			 */
			if (!isset($this->data_model[$order_by[0]]['data_model'][$order_by[1]])) {
				$order_by = ['id'];
			}
		} elseif (is_array($this->data_model[$order_by[0]]) && isset($this->data_model[$order_by[0]]['data_model'])) {
			/**
			 * Default field in joined table
			 */
			$order_by[1] = array_keys($this->data_model[$order_by[0]]['data_model'])[1];
		}
		if (isset($order_by[1])) {
			++$join_index;
			$first_column      = array_keys($this->data_model)[0];
			$first_column_join = array_keys($this->data_model[$order_by[0]]['data_model'])[0];
			$joins .=
				"INNER JOIN `{$this->table}_$order_by[0]` AS `j$join_index`
				ON
					`$table_alias`.`$first_column`	= `j$join_index`.`$first_column_join`";
			$order_by = "`j$join_index`.`$order_by[1]`";
		} else {
			$order_by = "`$table_alias`.`$order_by[0]`";
		}
		return $order_by;
	}
}
