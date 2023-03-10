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

use ApiQuery;
use ApiQueryBase;
use MediaWiki\Extension\EditCount\EditCountQuery;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use WikiMedia\Rdbms\ILoadBalancer;

class ApiQueryEditCount extends ApiQueryBase {

	/** @var EditCountQuery */
	private $editCountQuery;

	public function __construct(
		ApiQuery $query,
		$moduleName,
		EditCountQuery $editCountQuery,
	) {
		parent::__construct( $query, $moduleName, 'ec' );
		$this->editCountQuery = $editCountQuery;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$users = array_filter( $params['user'], fn ( $v ): bool => $v->getId() !== 0 );

		$result = $this->getResult();
		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], '' );

		foreach ( $users as $user ) {
			if ( !isset( $params['namespace'] ) ) {
				$queryResult = $this->editCountQuery->queryAllNamespaces( $user );
			} else {
				$queryResult = $this->editCountQuery->queryNamespaces( $user, $params['namespace'] );
			}
			$nsResult = array_filter( $queryResult, fn( $k ): bool => is_int( $k ), ARRAY_FILTER_USE_KEY );
			$vals = [
				'user' => $user->getName(),
				'userid' => $user->getId(),
				'stat' => array_map( fn ( $k, $v ): array => [
					'ns' => $k,
					'count' => $v
				], array_keys( $nsResult ), array_values( $nsResult ) ),
				'sum' => $queryResult['sum']
			];
			$result->addValue( [ 'query', $this->getModuleName() ], null, $vals );
		}
	}

	protected function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
				UserDef::PARAM_RETURN_OBJECT => true,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => true
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
