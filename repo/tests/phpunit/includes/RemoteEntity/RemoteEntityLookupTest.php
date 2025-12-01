<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use PHPUnit\Framework\TestCase;
use Status;
use Wikibase\DataAccess\ApiEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Repo\RemoteEntity\RemoteEntityLookup;
use Wikibase\Repo\RemoteEntity\RemoteEntityStore;

/**
 * @covers \Wikibase\Repo\RemoteEntity\RemoteEntityLookup
 * @group Wikibase
 */
class RemoteEntityLookupTest extends TestCase {

	private function newMockHttpFactory( ?array $responseData, bool $success = true ): HttpRequestFactory {
		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'execute' )
			->willReturn( $success ? Status::newGood() : Status::newFatal( 'error' ) );
		$request->method( 'getContent' )
			->willReturn( $responseData !== null ? json_encode( $responseData ) : '' );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )
			->willReturn( $request );

		return $factory;
	}

	private function newEntitySourceDefinitions(): EntitySourceDefinitions {
		$apiSource = new ApiEntitySource(
			'wikidata',
			[ Item::ENTITY_TYPE ],
			'http://www.wikidata.org/entity/',
			'',
			'',
			'',
			'https://www.wikidata.org/w/api.php'
		);

		return new EntitySourceDefinitions(
			[ $apiSource ],
			new SubEntityTypesMapper( [] )
		);
	}

	private function newMockStore( ?array $cachedData = null ): RemoteEntityStore {
		$store = $this->createMock( RemoteEntityStore::class );
		$store->method( 'get' )
			->willReturn( $cachedData );
		return $store;
	}

	public function testFetchEntityReturnsCachedData(): void {
		$cachedData = [ 'id' => 'Q42', 'labels' => [] ];
		$store = $this->newMockStore( $cachedData );
		$httpFactory = $this->newMockHttpFactory( null ); // Should not be called

		$lookup = new RemoteEntityLookup(
			$httpFactory,
			$this->newEntitySourceDefinitions(),
			$store
		);

		$result = $lookup->fetchEntity( 'https://www.wikidata.org/entity/Q42' );

		$this->assertSame( $cachedData, $result );
	}

	public function testFetchEntityFetchesFromRemoteOnCacheMiss(): void {
		$remoteData = [
			'entities' => [
				'Q42' => [ 'id' => 'Q42', 'type' => 'item' ],
			],
		];

		$store = $this->newMockStore( null );
		$httpFactory = $this->newMockHttpFactory( $remoteData );

		$lookup = new RemoteEntityLookup(
			$httpFactory,
			$this->newEntitySourceDefinitions(),
			$store
		);

		$result = $lookup->fetchEntity( 'https://www.wikidata.org/entity/Q42' );

		$this->assertSame( [ 'id' => 'Q42', 'type' => 'item' ], $result );
	}

	public function testGetEntityStoresInCache(): void {
		$remoteData = [
			'entities' => [
				'Q42' => [ 'id' => 'Q42', 'type' => 'item' ],
			],
		];

		$store = $this->createMock( RemoteEntityStore::class );
		$store->method( 'get' )->willReturn( null );
		$store->expects( $this->once() )
			->method( 'set' )
			->with(
				'https://www.wikidata.org/entity/Q42',
				[ 'id' => 'Q42', 'type' => 'item' ]
			);

		$httpFactory = $this->newMockHttpFactory( $remoteData );

		$lookup = new RemoteEntityLookup(
			$httpFactory,
			$this->newEntitySourceDefinitions(),
			$store
		);

		$lookup->getEntity( 'https://www.wikidata.org/entity/Q42' );
	}

	public function testEnsureStoredReturnsTrueWhenAlreadyCached(): void {
		$store = $this->newMockStore( [ 'id' => 'Q42' ] );
		$httpFactory = $this->newMockHttpFactory( null );

		$lookup = new RemoteEntityLookup(
			$httpFactory,
			$this->newEntitySourceDefinitions(),
			$store
		);

		$result = $lookup->ensureStored( 'https://www.wikidata.org/entity/Q42' );

		$this->assertTrue( $result );
	}

	public function testFetchEntityReturnsNullOnHttpError(): void {
		$store = $this->newMockStore( null );
		$httpFactory = $this->newMockHttpFactory( null, false );

		$lookup = new RemoteEntityLookup(
			$httpFactory,
			$this->newEntitySourceDefinitions(),
			$store
		);

		$result = $lookup->fetchEntity( 'https://www.wikidata.org/entity/Q42' );

		$this->assertNull( $result );
	}
}

