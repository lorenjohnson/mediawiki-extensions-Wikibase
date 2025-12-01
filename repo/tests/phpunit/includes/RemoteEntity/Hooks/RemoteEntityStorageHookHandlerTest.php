<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity\Hooks;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikibase\Repo\Content\ItemContent;
use Wikibase\Repo\RemoteEntity\Hooks\RemoteEntityStorageHookHandler;
use Wikibase\Repo\RemoteEntity\RemoteEntityId;
use Wikibase\Repo\RemoteEntity\RemoteEntityLookup;
use WikiPage;

/**
 * @covers \Wikibase\Repo\RemoteEntity\Hooks\RemoteEntityStorageHookHandler
 * @group Wikibase
 */
class RemoteEntityStorageHookHandlerTest extends MediaWikiIntegrationTestCase {

	private function newMockEntityContentFactory(): EntityContentFactory {
		$mock = $this->createMock( EntityContentFactory::class );
		$mock->method( 'isEntityContentModel' )
			->willReturn( true );
		return $mock;
	}

	private function newMockLookup(): RemoteEntityLookup {
		return $this->createMock( RemoteEntityLookup::class );
	}

	private function newItemWithRemoteEntity(): Item {
		$item = new Item( new ItemId( 'Q1' ) );

		$remoteEntityId = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$snak = new PropertyValueSnak(
			new NumericPropertyId( 'P1' ),
			new EntityIdValue( $remoteEntityId )
		);
		$statement = new Statement( $snak );
		$item->setStatements( new StatementList( $statement ) );

		return $item;
	}

	private function newMockRevision( $content ): RevisionRecord {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getContent' )
			->with( 'main' )
			->willReturn( $content );
		return $revision;
	}

	public function testDoesNothingWhenFederationDisabled(): void {
		$lookup = $this->newMockLookup();
		$lookup->expects( $this->never() )
			->method( 'ensureStored' );

		$handler = new RemoteEntityStorageHookHandler(
			$this->newMockEntityContentFactory(),
			$lookup,
			false // federation disabled
		);

		$handler->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( UserIdentity::class ),
			'summary',
			0,
			$this->newMockRevision( null ),
			null
		);
	}

	public function testDoesNothingForNonEntityContent(): void {
		$lookup = $this->newMockLookup();
		$lookup->expects( $this->never() )
			->method( 'ensureStored' );

		$handler = new RemoteEntityStorageHookHandler(
			$this->newMockEntityContentFactory(),
			$lookup,
			true
		);

		// Pass non-EntityContent
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getContent' )
			->willReturn( null );

		$handler->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( UserIdentity::class ),
			'summary',
			0,
			$revision,
			null
		);
	}

	public function testStoresRemoteEntityFromStatement(): void {
		$lookup = $this->newMockLookup();
		$lookup->expects( $this->once() )
			->method( 'ensureStored' )
			->with( 'https://www.wikidata.org/entity/Q42' );

		$item = $this->newItemWithRemoteEntity();
		$content = $this->createMock( ItemContent::class );
		$content->method( 'getEntity' )->willReturn( $item );
		$content->method( 'isRedirect' )->willReturn( false );
		$content->method( 'getModel' )->willReturn( 'wikibase-item' );

		$factory = $this->createMock( EntityContentFactory::class );
		$factory->method( 'isEntityContentModel' )->willReturn( true );

		$handler = new RemoteEntityStorageHookHandler(
			$factory,
			$lookup,
			true
		);

		$handler->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( UserIdentity::class ),
			'summary',
			0,
			$this->newMockRevision( $content ),
			null
		);
	}

	public function testSkipsRedirectContent(): void {
		$lookup = $this->newMockLookup();
		$lookup->expects( $this->never() )
			->method( 'ensureStored' );

		$content = $this->createMock( ItemContent::class );
		$content->method( 'isRedirect' )->willReturn( true );
		$content->method( 'getModel' )->willReturn( 'wikibase-item' );

		$handler = new RemoteEntityStorageHookHandler(
			$this->newMockEntityContentFactory(),
			$lookup,
			true
		);

		$handler->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( UserIdentity::class ),
			'summary',
			0,
			$this->newMockRevision( $content ),
			null
		);
	}
}

