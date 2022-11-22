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

use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use WikiMedia\Rdbms\ILoadBalancer;

class EditCountQuery {

	/** @var ILoadBalancer */
	private $dbLoadBalancer;

	/** @var ActorNormalization */
	private $actorNormalization;

	/**
	 * @param ActorNormalization $actorNormalization
	 * @param ILoadBalancer $dbLoadBalancer
	 */
	public function __construct(
		ActorNormalization $actorNormalization,
		ILoadBalancer $dbLoadBalancer
	) {
		$this->actorNormalization = $actorNormalization;
		$this->dbLoadBalancer = $dbLoadBalancer;
	}

	/**
	 * Count the number of edits of a user in all namespaces
	 * @param User $user
	 * @return array
	 */
	public function queryAllNamespaces( UserIdentity $user ) {
		return $this->execute( $user );
	}

	/**
	 * Count the number of edits of a user in given namespaces
	 * @param UserIdentity $user
	 * @param int|int[] $namespaces the namespaces to check
	 * @return array
	 */
	public function queryNamespaces( UserIdentity $user , array $namespaces ) {
		if ( !is_array( $namespaces ) ) {
			$namespaces = [ $namespaces ];
		}
		if ( count( $namespaces ) === 0 ) {
			return [ 'sum' => 0 ];
		}
		$queryRes = $this->execute( $user );

		$res = [];
		$sum = 0;
		foreach ( $namespaces as $ns ) {
			$res[$ns] = isset( $queryRes[$ns] ) ? $queryRes[$ns] : 0;
			$sum += $res[$ns];
		}
		$res['sum'] = $sum;
		return $res;
	}

	/**
	 * Execute the query
	 * @param UserIdentity $user the user to check
	 * @return array
	 */
	protected function execute( UserIdentity $user ) {
		$dbr = $this->dbLoadBalancer->getConnectionRef( DB_REPLICA );
		$actorId = $this->actorNormalization->findActorId( $user, $dbr );
		if ( is_null( $actorId ) ) {
			return [ 'sum' => 0 ];
		}

		$query = $dbr->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->select( [ 'page_namespace', 'count' => 'COUNT(*)' ] )
			->from( 'revision' )
			->join( 'page', null, 'page_id = rev_page' )
			->join( 'actor', null, 'rev_actor = actor_id' )
			->where( "actor_id = $actorId" )
			->groupBy( 'page_namespace' );
		$res = $query->fetchResultSet();

		$nsCount = [];
		$totalCount = 0;
		foreach ( $res as $row ) {
			$nsCount[$row->page_namespace] = (int)$row->count;
			$totalCount += (int)$row->count;
		}
		$nsCount['sum'] = $totalCount;
		return $nsCount;
	}
}
