<?php

namespace MediaWiki\Extension\EditCountNeue;

class SpecialEditCount extends FormSpecialPage {
	public function __construct() {
		parent::__construct( 'Editcount', 'EditCount', 'Edit Count' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		return [
			'user' => [
				'type' => 'user',
				'exists' => true,
				'label-message' => 'editcount-user',
				'required' => true
			]
		]
	}

	/**
	 * @codeCoverageIgnore
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * @param array $data
	 */
	public function onSubmit( array $data ) {}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}
}