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

namespace MediaWiki\Extension\EditCount\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use User;
use MediaWiki\Extension\EditCount\EditCountQuery;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use WikiMedia\Rdbms\ILoadBalancer;

class ApiQueryEditCount extends ApiQueryBase {

	/** @var EditCountQuery */
	private $editCountQuery;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var UserNameUtils */
	private $userNameUtils;

	public function __construct(
		ApiQuery $query,
		$moduleName,
		ActorNormalization $actorNormalization,
		ILoadBalancer $dbLoadBalancer,
		UserIdentityLookup $userIdentityLookup,
		UserNameUtils $userNameUtils
	) {
		parent::__construct( $query, $moduleName, 'ec' );
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userNameUtils = $userNameUtils;
		$this->editCountQuery = new EditCountQuery( $actorNormalization, $dbLoadBalancer );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$this->checkEmpty( $params, 'user' );
		$this->checkEmpty( $params, 'namespace' );

		$names = [];
		foreach ( $params['user'] as $u ) {
			if ( $u === '' ) {
				$encParamName = $this->encodeParamName( 'user' );
				$this->dieWithError( [ 'apierror-paramempty', $encParamName ], "paramempty_$encParamName" );
			}
			$name = $this->userNameUtils->getCanonical( $u );
			if ( $name == false ) {
				$encParamName = $this->encodeParamName( 'user' );
				$this->dieWithError(
					[ 'apierror-baduser', $encParamName, wfEscapeWikiText( $u ) ], "baduser_$encParamName"
				);
			}
			$names[] = $name;
		}
		$userIter = $this->userIdentityLookup
			->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->whereUserNames( $names )
			->orderByName()
			->fetchUserIdentities();

		$result = $this->getResult();
		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], '' );
		foreach ( $userIter as $user ) {
			$queryResult = $this->editCountQuery->queryNamespaces( $user, $params['namespace'] );
			$nsResult = array_filter( $queryResult, fn( $k ): bool => is_int( $k ), ARRAY_FILTER_USE_KEY );
			$vals = [
				'user' => $user->getName(),
				'userid' => $user->getId(),
				'stat' => array_map( fn ( $k, $v ): array => [
					'namespace' => $k,
					'count' => $v
				], array_keys( $nsResult ), array_values( $nsResult ) ),
				'sum' => $queryResult['sum']
			];
			$result->addValue( [ 'query', $this->getModuleName() ], null, $vals );
		}
	}

	protected function checkEmpty( array $params, string $key ) {
		if ( !isset( $params[$key] ) || $params[$key] === [] ) {
			$encParamName = $this->encodeParamName( $key );
			$this->dieWithError( [ 'apierror-paramempty', $encParamName ], "paramempty_$encParamName" );
		}
	}

	protected function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
				ParamValidator::PARAM_ISMULTI => true
			],
			'namespace' => [
				ParamValidator::PARAM_TYPE => 'namespace',
				ParamValidator::PARAM_ISMULTI => true
			]
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&list=editcount&ecuser=Example'
				=> 'apihelp-query+editcount-example-user'
		];
	}
}
