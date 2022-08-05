<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 * 
 * @file
 */

namespace MediaWiki\Extension\EditCount;

use ActorMigration;
use MediaWiki\MediaWikiServices;
use User;

class EditCountQuery {

	/**
	 * Count the number of edits of a user in all namespaces
	 * 
	 * @param User $user
	 * @return array
	 */
	public static function queryAllNamespaces( User $user ) {
		return self::execute( $user );
	}

	/**
	 * Count the number of edits of a user in given namespaces
	 * 
	 * @param User $user
	 * @param int|int[] $namespaces the namespaces to check
	 * @return array
	 */
	public static function queryNamespaces( User $user , $namespaces ) {
		if ( !is_array( $namespaces ) ) {
			$namespaces = [ $namespaces ];
		}
		$queryRes = self::execute( $user );
		$res = [];
		foreach ( $namespaces as $ns ) {
			$res[$ns] = isset( $queryRes[$ns] ) ? $queryRes[$ns] : 0;
		}
		return $res;
	}

	/**
	 * Execute the query
	 * 
	 * @param User $user the user to check
	 * @return array
	 */
	protected static function execute( User $user ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$query = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'count' => 'COUNT(*)' ] )
			->from( 'revision' )
			->join( 'page', null, 'page_id = rev_page' );
		// HACK: when actor migration finishes, use a more beautiful way
		$actorWhere = ActorMigration::newMigration()->getWhere( $dbr, 'rev_user', $user );
		foreach ( $actorWhere['joins'] as $k => $v ) {
			$query->join( $actorWhere['tables'][$k], $k, $v[1] );
		}
		$query->where( $actorWhere['conds'] )
			->groupBy( 'page_namespace' );
		$res = $query->fetchResultSet();

		$nsCount = [];
		$totalCount = 0;
		foreach ( $res as $row ) {
			$nsCount[$row->page_namespace] = (int)$row->count;
			$totalCount += (int)$row->count;
		}
		$nsCount['all'] = $totalCount;
		return $nsCount;
	}
}
