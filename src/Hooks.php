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

class Hooks implements \MediaWiki\Hook\ParserFirstCallInitHook {

	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'editcount', [ self::class, 'editCount' ] );
	}

	public static function editCount( Parser $parser, $param1 = '', $param2 = '' ) {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$user = $userFactory->newFromName( $param1 );
		// If user is invalid or does not exist, returns 0
		if ( !$user || $user->getId() === 0 ) {
			return '0';
		}

		// If param2 is not specified, query all namespaces
		if ( $param2 === '' ) {
			$count = EditCountQuery::queryAllNamespaces( $user )['all'];
		} else {
			$count = EditCountQuery::queryNamespaces( $user, $param2 )[$param2];
		}
		$output = "$count";
		return $output;
	}
}
