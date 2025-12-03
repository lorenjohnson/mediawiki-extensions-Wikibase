<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use MediaWiki\Http\HttpRequestFactory;
use MWHttpRequest;
use PHPUnit\Framework\TestCase;
use Status;
use Wikibase\DataAccess\ApiEntitySource;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Repo\RemoteEntity\RemoteEntitySearchClient;

/**
 * @covers \Wikibase\Repo\RemoteEntity\RemoteEntitySearchClient
 * @group Wikibase
 */
class RemoteEntitySearchClientTest extends TestCase {

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

	private function newEntitySourceDefinitions( bool $withApiSource = true ): EntitySourceDefinitions {
		$sources = [];

		if ( $withApiSource ) {
			$sources[] = new ApiEntitySource(
				'wikidata',
				[ Item::ENTITY_TYPE ],
				'http://www.wikidata.org/entity/',
				'',
				'',
				'',
				'https://www.wikidata.org/w/api.php'
			);
		} else {
			$sources[] = new DatabaseEntitySource(
				'local',
				false,
				[ Item::ENTITY_TYPE => [ 'namespaceId' => 0, 'slot' => 'main' ] ],
				'http://localhost/entity/',
				'',
				'',
				''
			);
		}

		return new EntitySourceDefinitions(
			$sources,
			new SubEntityTypesMapper( [] )
		);
	}

	public function testSearchEntitiesReturnsResults(): void {
		$responseData = [
			'search' => [
				[ 'id' => 'Q42', 'label' => 'Douglas Adams' ],
				[ 'id' => 'Q123', 'label' => 'Test Item' ],
			],
		];

		$client = new RemoteEntitySearchClient(
			$this->newMockHttpFactory( $responseData ),
			$this->newEntitySourceDefinitions()
		);

		$result = $client->searchEntities( [
			'search' => 'Douglas',
			'language' => 'en',
			'type' => 'item',
		] );

		$this->assertSame( $responseData, $result );
	}

	public function testSearchEntitiesReturnsEmptyOnHttpError(): void {
		$client = new RemoteEntitySearchClient(
			$this->newMockHttpFactory( null, false ),
			$this->newEntitySourceDefinitions()
		);

		$result = $client->searchEntities( [
			'search' => 'Test',
			'language' => 'en',
			'type' => 'item',
		] );

		$this->assertSame( [], $result );
	}

	public function testSearchEntitiesReturnsEmptyWhenNoApiSource(): void {
		$client = new RemoteEntitySearchClient(
			$this->newMockHttpFactory( [ 'search' => [] ] ),
			$this->newEntitySourceDefinitions( false ) // No API source
		);

		$result = $client->searchEntities( [
			'search' => 'Test',
			'language' => 'en',
			'type' => 'item',
		] );

		$this->assertSame( [], $result );
	}

	public function testSearchEntitiesPassesAllParameters(): void {
		$factory = $this->createMock( HttpRequestFactory::class );

		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'execute' )->willReturn( Status::newGood() );
		$request->method( 'getContent' )->willReturn( '{"search":[]}' );

		$factory->expects( $this->once() )
			->method( 'create' )
			->with(
				$this->callback( function ( $url ) {
					return str_contains( $url, 'search=Test' )
						&& str_contains( $url, 'language=en' )
						&& str_contains( $url, 'type=item' )
						&& str_contains( $url, 'limit=10' )
						&& str_contains( $url, 'continue=5' );
				} ),
				$this->anything()
			)
			->willReturn( $request );

		$client = new RemoteEntitySearchClient(
			$factory,
			$this->newEntitySourceDefinitions()
		);

		$client->searchEntities( [
			'search' => 'Test',
			'language' => 'en',
			'type' => 'item',
			'limit' => 10,
			'continue' => 5,
		] );
	}
}

