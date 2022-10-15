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
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentityLookup;
use WikiMedia\Rdbms\ILoadBalancer;

class Hooks implements \MediaWiki\Hook\ParserFirstCallInitHook {

	
	/** @var EditCountQuery */
	private $editCountQuery;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	public function __construct(
		ActorNormalization $actorNormalization,
		ILoadBalancer $dbLoadBalancer,
		UserIdentityLookup $userIdentityLookup
	) {
		$this->userIdentityLookup = $userIdentityLookup;
		$this->editCountQuery = new EditCountQuery( $actorNormalization, $dbLoadBalancer );
	}

	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'editcount', [ new Hooks( $this->userIdentityLookup ), 'editCount' ], Parser::SFH_OBJECT_ARGS );
	}

	public function editCount( Parser $parser, PPFrame $frame, array $args ) {
		$username = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$user = $this->userIdentityLookup
			->getUserIdentityByName( $username );
		// If user is invalid or does not exist, returns 0
		if ( !$user || $user->getId() === 0 ) {
			return '0';
		}

		if ( count( $args ) <= 1 ) {
			// If namespaces are not specified, query all namespaces
			$count = $this->editCountQuery->queryAllNamespaces( $user );
			return "$count[sum]";
		} else {
			// filter out the first argument (the username)
			$iter = array_filter( $args, function ( $i ) {
				return $i !== 0;
			} , ARRAY_FILTER_USE_KEY );

			$namespaces = [];
			foreach ( $iter as $v ) {
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

			$count = $this->editCountQuery->queryNamespaces( $user, $namespaces );
			return "$count[sum]";
		}
	}
}
