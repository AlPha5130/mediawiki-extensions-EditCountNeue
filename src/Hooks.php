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

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use Parser;
use PPFrame;
use PPNode;

class Hooks implements \MediaWiki\Hook\ParserFirstCallInitHook {

	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'editcount', [ self::class, 'editCount' ] );
	}

	public static function editCount( Parser $parser, PPFrame $frame, array $args ) {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$userName = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$user = $userFactory->newFromName( $userName );
		// If user is invalid or does not exist, returns 0
		if ( !$user || $user->getId() === 0 ) {
			return '0';
		}

		if ( count( $args ) <= 1 ) {
			// If namespaces are not specified, query all namespaces
			$count = EditCountQuery::queryAllNamespaces( $user );
			return "$count->sum";
		} else {
			$ns = [];
			foreach ( $args as $i => $v ) {
				// except user
				if ( $i !== 0 ) {
					if ( intval( $v ) || $v === '0' ) {
						$index = intval( $v );
					} else {
						$index = $parser->getContentLanguage()->getNsIndex( str_replace( ' ', '_', $v ) );
					}
					if ( $index !== false && !in_array( $index, $ns ) ) {
						$ns[] = $index;
					}
				}
			}
			$count = EditCountQuery::queryNamespaces( $user, $ns );
			return "$count->sum";
		}
	}
}

