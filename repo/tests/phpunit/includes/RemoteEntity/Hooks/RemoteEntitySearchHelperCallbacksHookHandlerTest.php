<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity\Hooks;

use PHPUnit\Framework\TestCase;
use Wikibase\DataAccess\ApiEntitySource;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Lib\SettingsArray;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\RemoteEntity\Hooks\RemoteEntitySearchHelperCallbacksHookHandler;
use Wikibase\Repo\RemoteEntity\RemoteEntitySearchClient;
use Wikibase\Repo\RemoteEntity\RemoteEntitySearchHelper;

/**
 * @covers \Wikibase\Repo\RemoteEntity\Hooks\RemoteEntitySearchHelperCallbacksHookHandler
 * @group Wikibase
 */
class RemoteEntitySearchHelperCallbacksHookHandlerTest extends TestCase {

	private function newMockClient(): RemoteEntitySearchClient {
		return $this->createMock( RemoteEntitySearchClient::class );
	}

	private function newSettings( bool $federatedValuesEnabled ): SettingsArray {
		return new SettingsArray( [
			'federatedValuesEnabled' => $federatedValuesEnabled,
		] );
	}

	private function newEntitySourceDefinitions( array $apiEntityTypes = [] ): EntitySourceDefinitions {
		$sources = [];

		// Always add a local database source
		$sources[] = new DatabaseEntitySource(
			'local',
			false,
			[
				Item::ENTITY_TYPE => [ 'namespaceId' => 0, 'slot' => 'main' ],
				Property::ENTITY_TYPE => [ 'namespaceId' => 120, 'slot' => 'main' ],
			],
			'http://localhost/entity/',
			'',
			'',
			''
		);

		if ( !empty( $apiEntityTypes ) ) {
			$sources[] = new ApiEntitySource(
				'wikidata',
				$apiEntityTypes,
				'http://www.wikidata.org/entity/',
				'',
				'',
				'',
				'https://www.wikidata.org/w/api.php'
			);
		}

		return new EntitySourceDefinitions(
			$sources,
			new SubEntityTypesMapper( [] )
		);
	}

	public function testDoesNothingWhenFederationDisabled(): void {
		$handler = new RemoteEntitySearchHelperCallbacksHookHandler(
			$this->newMockClient(),
			$this->newSettings( false ),
			$this->newEntitySourceDefinitions( [ Item::ENTITY_TYPE ] )
		);

		$callbacks = [
			'item' => fn() => $this->createMock( EntitySearchHelper::class ),
		];
		$originalCallbacks = $callbacks;

		$handler->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		$this->assertSame( $originalCallbacks, $callbacks );
	}

	public function testDoesNothingWhenNoApiSources(): void {
		$handler = new RemoteEntitySearchHelperCallbacksHookHandler(
			$this->newMockClient(),
			$this->newSettings( true ),
			$this->newEntitySourceDefinitions( [] ) // No API sources
		);

		$callbacks = [
			'item' => fn() => $this->createMock( EntitySearchHelper::class ),
		];
		$originalCallbacks = $callbacks;

		$handler->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		$this->assertSame( $originalCallbacks, $callbacks );
	}

	public function testWrapsCallbacksForApiEntityTypes(): void {
		$handler = new RemoteEntitySearchHelperCallbacksHookHandler(
			$this->newMockClient(),
			$this->newSettings( true ),
			$this->newEntitySourceDefinitions( [ Item::ENTITY_TYPE ] )
		);

		$localHelper = $this->createMock( EntitySearchHelper::class );
		$callbacks = [
			'item' => fn() => $localHelper,
		];

		$handler->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		// The callback should now return a RemoteEntitySearchHelper
		$result = $callbacks['item']();
		$this->assertInstanceOf( RemoteEntitySearchHelper::class, $result );
	}

	public function testDoesNotWrapCallbacksForNonApiEntityTypes(): void {
		$handler = new RemoteEntitySearchHelperCallbacksHookHandler(
			$this->newMockClient(),
			$this->newSettings( true ),
			$this->newEntitySourceDefinitions( [ Item::ENTITY_TYPE ] ) // Only items from API
		);

		$localPropertyHelper = $this->createMock( EntitySearchHelper::class );
		$callbacks = [
			'property' => fn() => $localPropertyHelper,
		];
		$originalCallback = $callbacks['property'];

		$handler->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		// Property callback should remain unchanged
		$this->assertSame( $originalCallback, $callbacks['property'] );
	}

	public function testWrapsMultipleEntityTypes(): void {
		$handler = new RemoteEntitySearchHelperCallbacksHookHandler(
			$this->newMockClient(),
			$this->newSettings( true ),
			$this->newEntitySourceDefinitions( [ Item::ENTITY_TYPE, Property::ENTITY_TYPE ] )
		);

		$callbacks = [
			'item' => fn() => $this->createMock( EntitySearchHelper::class ),
			'property' => fn() => $this->createMock( EntitySearchHelper::class ),
		];

		$handler->onWikibaseRepoEntitySearchHelperCallbacks( $callbacks );

		$this->assertInstanceOf( RemoteEntitySearchHelper::class, $callbacks['item']() );
		$this->assertInstanceOf( RemoteEntitySearchHelper::class, $callbacks['property']() );
	}
}

