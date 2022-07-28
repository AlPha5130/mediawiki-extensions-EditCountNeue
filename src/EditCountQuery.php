<?php

namespace MediaWiki\Extension\EditCountNeue;

use MediaWiki\MediaWikiServices;

class EditCountQuery {

    public static function queryAllNamespaces( User $user ) {}

    public static function queryNamespaces( User $user , array $namespaces ) {}

    protected static function execute( User $user ) {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );
        $res = $dbr->newSelectQueryBuilder()
          ->select()
          ->fetchResultSet();
    }
}
