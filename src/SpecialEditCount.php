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

use SpecialPage;
use HTMLForm;
use Html;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use WikiMedia\Rdbms\ILoadBalancer;

class SpecialEditCount extends SpecialPage {

	/** @var LanguageConverterFactory */
	private $languageConverterFactory;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var EditCountQuery */
	private $editCountQuery;

	/**
	 * @param EditCountQuery $editCountQuery
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 */
	public function __construct(
		EditCountQuery $editCountQuery,
		LanguageConverterFactory $languageConverterFactory,
		UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct( 'EditCount' );
		$this->editCountQuery = $editCountQuery;
		$this->languageConverterFactory = $languageConverterFactory;
		$this->userIdentityLookup = $userIdentityLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'editcountneue' )->text();
	}

	/**
	 * @inheritDoc
	 * @param string $par
	 */
	public function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		$this->outputHeader();

		// Check URL parameters passed from the submit button first.
		// Usernames don't allow leading and trailing whitespaces, so trim them.
		// WebRequest::getText return '' by default but ?? only allow null-detection.
		$trimChars = "_ \n\r\t\v\x00";
		$usernameParams = [];
		$usernameParams[] = $request->getText( 'wpUsername' );
		$usernameParams[] = $request->getText( 'wpuser' );
		$usernameParams[] = $par !== null ? $par : '';

		foreach ( $usernameParams as $usernameParam ) {
			$username = trim( $usernameParam, $trimChars );
			if ( $par === '' || ( $usernameParam !== '' && $par !== $username ) ) {
				$username = $username !== '' ? $username : null;
				$url = SpecialPage::getTitleFor( 'EditCount', $username )->getLocalURL();
				$output->redirect( $url );
			}
		}

		$username = $par;

		// PHP considers '0' to be falsy –
		//  but '0' is a valid title and valid user name in MediaWiki.
		if ( $username === null || $username === '' ) {
			$this->outputHTMLForm();
			return;
		}

		$user = $this->userIdentityLookup
			->getUserIdentityByName( $username );
		if ( !$user || $user->getId() === 0 ) {
			$this->outputHTMLForm( null, $username );
			$output->addHTML( '<br>' . Html::element(
				'strong',
				[ 'class' => 'error' ],
				$this->msg( 'editcountneue-error-userdoesnotexist' )->params( $username )->text()
			) );
			return;
		}

		$this->outputHTMLForm( $user );

		$result = $this->queryEditCount( $user );
		// add heading
		$output->addHTML( Html::element(
			'h2',
			[ 'id' => 'editcount-queryresult' ],
			$this->msg( 'editcountneue-result-heading' )->params( $user->getName() )->text()
		) );
		
		$this->makeTable( $result );
	}

	/**
	 * Output form for query.
	 * @param ?UserIdentity $user
	 * @param string $username Usually for nonexistent username query
	 */
	protected function outputHTMLForm( ?UserIdentity $user = null, string $username = '' ) {
		$formDescriptor = [
			'username' => [
				'type' => 'user',
				'name' => 'wpUsername',
				'id' => 'editcount-username',
				'exists' => true,
				'label-message' => 'editcountneue-form-username',
				'required' => true,
				'default' => $user ? $user->getName() : $username
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			->setAction( SpecialPage::getTitleFor( 'EditCount' )->getLocalURL() )
			->setWrapperLegendMsg( 'editcountneue-form-legend' )
			->setSubmitTextMsg( 'editcountneue-form-submit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @codeCoverageIgnore
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Make query.
	 * @param UserIdentity $user
	 */
	protected function queryEditCount( UserIdentity $user ) {
		$result = $this->editCountQuery->queryAllNamespaces( $user );
		return $result;
	}

	/**
	 * Output result table.
	 * @param array $data
	 */
	protected function makeTable( $data ) {
		$lang = $this->getLanguage();

		$out = Html::openElement(
			'table',
			[ 'class' => 'mw-editcounttable wikitable' ]
		) . "\n";
		$out .= Html::openElement( 'thead' ) .
			Html::openElement( 'tr', [ 'class' => 'mw-editcounttable-header' ] ) .
			Html::element( 'th', [], $this->msg( 'editcountneue-result-namespace' )->text() ) .
			Html::element( 'th', [], $this->msg( 'editcountneue-result-count')->text() ) .
			Html::element( 'th', [], $this->msg( 'editcountneue-result-percentage' )->text() ) .
			Html::closeElement( 'tr' ) .
			Html::closeElement( 'thead' ) .
			Html::openElement( 'tbody' );

		$nsData = array_filter( $data, fn( $k ): bool => is_int( $k ), ARRAY_FILTER_USE_KEY );
		$converter = $this->languageConverterFactory->getLanguageConverter( $lang );

		foreach ( $nsData as $ns => $count ) {
			if ( $ns === NS_MAIN ) {
				$nsName = $this->msg( 'blanknamespace' )->text();
			} else {
				$nsName = $converter->convertNamespace( $ns );
				if ( $nsName === '' ) {
					$nsName = "NS$ns";
				}
			}
			$out .= Html::openElement( 'tr', [ 'class' => 'mw-editcounttable-row' ] ) .
				Html::element(
					'td',
					[ 'class' => 'mw-editcounttable-ns' ],
					$nsName
				) .
				Html::element(
					'td',
					[ 'class' => 'mw-editcounttable-count' ],
					$lang->formatNum( $count )
				) .
				Html::element(
					'td',
					[ 'class' => 'mw-editcounttable-percentage' ],
					wfPercent( $count / $data['sum'] * 100 )
				) .
				Html::closeElement( 'tr' );
		} 
			
		// bottom sum row
		$out .= Html::openElement( 'tr', [ 'class' => 'mw-editcounttable-footer' ] ) .
			Html::element( 'th', [], $this->msg( 'editcountneue-result-allnamespaces' )->text() ) .
			Html::element(
				'th',
				[ 'class' => 'mw-editcounttable-count' ],
				$lang->formatNum( $data['sum'] )
			) .
			Html::element(
				'th',
				[ 'class' => 'mw-editcounttable-percentage' ],
				wfPercent( 100 )
			) .
			Html::closeElement( 'tr' );

		$out .= Html::closeElement( 'tbody' ) .
			Html::closeElement( 'table' );

		$this->getOutput()->addHTML( $out );
	}
}
