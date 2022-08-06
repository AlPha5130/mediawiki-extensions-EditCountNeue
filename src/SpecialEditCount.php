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
use MediaWiki\MediaWikiServices;
use User;

class SpecialEditCount extends SpecialPage {

	public function __construct() {
		parent::__construct( 'EditCount' );
	}

	public function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		$this->outputHeader();

		$userName = $par ?? $request->getText( 'wpuser' );

		if ( !$userName ) {
			$this->outputHTMLForm();
		} else {
			$user = MediaWikiServices::getInstance()
				->getUserFactory()
				->newFromName( $userName );

			if ( !$user || $user->getId() === 0 ) {
				$this->outputHTMLForm();
				$output->addHTML( '<br>' . Html::element(
					'strong',
					[ 'class' => 'error' ],
					$this->msg( 'editcount-nomatcheduser' )->text()
				) );
			} else {
				$this->outputHTMLForm( $user );

				$result = self::queryEditCount( $user );
				// add heading
				$output->addHTML( Html::element(
					'h2',
					[ 'id' => 'editcount-queryresult' ],
					$this->msg( 'editcount-resulttitle' )->params( $user->getName() )->text()
				) );
		
				$this->makeTable( $result );
			}
		}
	}

	/**
	 * @param string $user
	 */
	protected function outputHTMLForm( $user = '' ) {
		$formDescriptor = [
			'user' => [
				'type' => 'user',
				'exists' => true,
				'label-message' => 'editcount-user',
				'required' => true,
				'default' => $user
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
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
	 * @param User $user
	 */
	protected static function queryEditCount( User $user ) {

		$result = EditCountQuery::queryAllNamespaces( $user );

		return $result;
	}

	/**
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
			Html::element( 'th', [], $this->msg( 'editcount-namespace' )->text() ) .
			Html::element( 'th', [], $this->msg( 'editcount-count')->text() ) .
			Html::element( 'th', [], $this->msg( 'editcount-percentage' )->text() ) .
			Html::closeElement( 'tr' ) .
			Html::closeElement( 'thead' ) .
			Html::openElement( 'tbody' );

		foreach ( $data as $ns => $count ) {
			if ( is_int( $ns ) ) {
				if ( $ns == NS_MAIN ) {
					$nsName = $this->msg( 'blanknamespace' )->text();
				} else {
					$converter = MediaWikiServices::getInstance()->getLanguageConverterFactory()
						->getLanguageConverter( $lang );
					$nsName = $converter->convertNamespace( $ns );
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
			} else {
				$nsName = $this->msg( 'editcount-all-namespaces' )->text();
				$out .= Html::openElement( 'tr', [ 'class' => 'mw-editcounttable-footer' ] ) .
					Html::element( 'th', [], $nsName ) .
					Html::element(
						'th',
						[ 'class' => 'mw-editcounttable-count' ],
						$lang->formatNum( $count )
					) .
					Html::element(
						'th',
						[ 'class' => 'mw-editcounttable-percentage' ],
						wfPercent( 100 )
					) .
					Html::closeElement( 'tr' );
			}
		}

		$out .= Html::closeElement( 'tbody' ) .
			Html::closeElement( 'table' );

		$this->getOutput()->addHTML( $out );
	}
}
