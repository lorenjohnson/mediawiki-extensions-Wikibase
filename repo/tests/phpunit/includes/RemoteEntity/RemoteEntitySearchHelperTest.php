<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\Api\SearchEntities;
use Wikibase\Repo\RemoteEntity\RemoteEntitySearchClient;
use Wikibase\Repo\RemoteEntity\RemoteEntitySearchHelper;

/**
 * @covers \Wikibase\Repo\RemoteEntity\RemoteEntitySearchHelper
 * @group Wikibase
 */
class RemoteEntitySearchHelperTest extends TestCase {

	private function newMockLocalHelper( array $results = [] ): EntitySearchHelper {
		$mock = $this->createMock( EntitySearchHelper::class );
		$mock->method( 'getRankedSearchResults' )
			->willReturn( $results );
		return $mock;
	}

	private function newMockClient( array $response = [] ): RemoteEntitySearchClient {
		$mock = $this->createMock( RemoteEntitySearchClient::class );
		$mock->method( 'searchEntities' )
			->willReturn( $response );
		return $mock;
	}

	private function newSettings( bool $federatedValuesEnabled = true ): SettingsArray {
		return new SettingsArray( [
			'federatedValuesEnabled' => $federatedValuesEnabled,
		] );
	}

	protected function setUp(): void {
		parent::setUp();
		// Reset the static flag before each test
		SearchEntities::$remoteEntitiesRequested = false;
	}

	protected function tearDown(): void {
		SearchEntities::$remoteEntitiesRequested = false;
		parent::tearDown();
	}

	public function testReturnsOnlyLocalResultsWhenFederationDisabled(): void {
		$localResult = new TermSearchResult(
			new Term( 'en', 'Local Item' ),
			'label',
			null
		);

		$helper = new RemoteEntitySearchHelper(
			$this->newMockLocalHelper( [ $localResult ] ),
			$this->newMockClient( [ 'search' => [ [ 'id' => 'Q1' ] ] ] ),
			$this->newSettings( false )
		);

		$results = $helper->getRankedSearchResults( 'test', 'en', 'item', 10, false, null );

		$this->assertCount( 1, $results );
		$this->assertSame( $localResult, $results[0] );
	}

	public function testReturnsOnlyLocalResultsWhenRemoteNotRequested(): void {
		SearchEntities::$remoteEntitiesRequested = false;

		$localResult = new TermSearchResult(
			new Term( 'en', 'Local Item' ),
			'label',
			null
		);

		$helper = new RemoteEntitySearchHelper(
			$this->newMockLocalHelper( [ $localResult ] ),
			$this->newMockClient( [ 'search' => [ [ 'id' => 'Q1' ] ] ] ),
			$this->newSettings( true )
		);

		$results = $helper->getRankedSearchResults( 'test', 'en', 'item', 10, false, null );

		$this->assertCount( 1, $results );
	}

	public function testMergesLocalAndRemoteResultsWhenEnabled(): void {
		SearchEntities::$remoteEntitiesRequested = true;

		$localResult = new TermSearchResult(
			new Term( 'en', 'Local Item' ),
			'label',
			null
		);

		$remoteResponse = [
			'search' => [
				[
					'id' => 'Q42',
					'label' => 'Douglas Adams',
					'description' => 'English author',
					'concepturi' => 'https://www.wikidata.org/entity/Q42',
				],
			],
		];

		$helper = new RemoteEntitySearchHelper(
			$this->newMockLocalHelper( [ $localResult ] ),
			$this->newMockClient( $remoteResponse ),
			$this->newSettings( true )
		);

		$results = $helper->getRankedSearchResults( 'test', 'en', 'item', 10, false, null );

		$this->assertCount( 2, $results );
		$this->assertSame( $localResult, $results[0] );
		$this->assertInstanceOf( TermSearchResult::class, $results[1] );
	}

	public function testRemoteResultsIncludeConceptUri(): void {
		SearchEntities::$remoteEntitiesRequested = true;

		$remoteResponse = [
			'search' => [
				[
					'id' => 'Q42',
					'label' => 'Douglas Adams',
					'concepturi' => 'https://www.wikidata.org/entity/Q42',
				],
			],
		];

		$helper = new RemoteEntitySearchHelper(
			$this->newMockLocalHelper( [] ),
			$this->newMockClient( $remoteResponse ),
			$this->newSettings( true )
		);

		$results = $helper->getRankedSearchResults( 'douglas', 'en', 'item', 10, false, null );

		$this->assertCount( 1, $results );
		$metadata = $results[0]->getMetaData();
		$this->assertArrayHasKey( 'concepturi', $metadata );
		$this->assertSame( 'https://www.wikidata.org/entity/Q42', $metadata['concepturi'] );
	}

	public function testHandlesEmptyRemoteResponse(): void {
		SearchEntities::$remoteEntitiesRequested = true;

		$helper = new RemoteEntitySearchHelper(
			$this->newMockLocalHelper( [] ),
			$this->newMockClient( [ 'search' => [] ] ),
			$this->newSettings( true )
		);

		$results = $helper->getRankedSearchResults( 'nonexistent', 'en', 'item', 10, false, null );

		$this->assertSame( [], $results );
	}
}

