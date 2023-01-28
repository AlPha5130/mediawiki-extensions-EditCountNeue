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

use Parser;
use PPFrame;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use WikiMedia\Rdbms\ILoadBalancer;

class Hooks implements \MediaWiki\Hook\ParserFirstCallInitHook {

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var EditCountQuery */
	private $editCountQuery;

	/** @var array */
	private $queryResult;

	/**
	 * @param EditCountQuery $editCountQuery
	 * @param UserIdentityLookup $userIdentityLookup
	 */
	public function __construct(
		EditCountQuery $editCountQuery,
		UserIdentityLookup $userIdentityLookup
	) {
		$this->userIdentityLookup = $userIdentityLookup;
		$this->editCountQuery = $editCountQuery;
		$this->queryResult = [];
	}

	/**
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'editcount', [ $this, 'editCount' ], Parser::SFH_OBJECT_ARGS );
	}

	/**
	 * Entry point for {{#editcount}} parser function.
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string
	 */
	public function editCount( Parser $parser, PPFrame $frame, array $args ) {
		$username = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$user = $this->userIdentityLookup->getUserIdentityByName( $username );
		// If user is invalid or does not exist, returns 0
		if ( !$user || $user->getId() === 0 ) {
			return '0';
		}
		$uid = $user->getId();

		// save query result to cache to prevent excessive DB queries
		if ( !isset( $this->queryResult[$uid] ) ) {
			$this->makeQuery( $user );
		}

		$nsIter = array_filter( $args, fn ( $i ): bool => $i !== 0, ARRAY_FILTER_USE_KEY );
		if ( count( $nsIter ) === 0 ) {
			return "{$this->queryResult[$uid]['sum']}";
		} else {
			// normalize ns array
			$namespaces = [];
			foreach ( $nsIter as $v ) {
				$ns = trim( $frame->expand( $v ) );
				if ( intval( $ns ) || $ns === '0' ) {
					$index = intval( $ns );
				} else {
					$index = $parser->getContentLanguage()->getNsIndex( str_replace( ' ', '_', $ns ) );
				}
				if ( $index !== false && !in_array( $index, $namespaces ) ) {
					$namespaces[] = $index;
				}
			}

			$count = 0;
			foreach ( $namespaces as $v ) {
				$count += $this->queryResult[$uid][$v] ?? 0;
			}
			return "$count";
		}
	}

	/**
	 * Make DB query if cannot find from cache.
	 * @param UserIdentity $user
	 */
	protected function makeQuery( UserIdentity $user ) {
		$result = $this->editCountQuery->queryAllNamespaces( $user );
		$this->queryResult[$user->getId()] = $result;
	}
}
