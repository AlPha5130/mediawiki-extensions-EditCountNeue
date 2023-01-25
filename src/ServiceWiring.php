<?php

use MediaWiki\Extension\EditCount\EditCountQuery;
use MediaWiki\MediaWikiServices;

return [
	'EditCountNeue.EditCountQuery' => static function ( MediaWikiServices $services ): EditCountQuery {
		return new EditCountQuery(
			$services->getActorNormalization(),
			$services->getDBLoadBalancer()
		);
	}
];
