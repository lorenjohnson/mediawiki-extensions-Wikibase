<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use MediaWikiIntegrationTestCase;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\RemoteEntity\RemoteEntityStore;

/**
 * @covers \Wikibase\Repo\RemoteEntity\RemoteEntityStore
 * @group Wikibase
 * @group Database
 */
class RemoteEntityStoreTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'wb_remote_entity';
	}

	private function newStore( array $settings = [] ): RemoteEntityStore {
		$defaultSettings = [
			'remoteEntityCacheTTL' => 3600,
		];
		$mergedSettings = array_merge( $defaultSettings, $settings );

		return new RemoteEntityStore(
			$this->getServiceContainer()->getDBLoadBalancerFactory(),
			new SettingsArray( $mergedSettings )
		);
	}

	public function testGetReturnsNullForMissingEntity(): void {
		$store = $this->newStore();

		$result = $store->get( 'https://www.wikidata.org/entity/Q999999' );

		$this->assertNull( $result );
	}

	public function testSetAndGet(): void {
		$store = $this->newStore();
		$conceptUri = 'https://www.wikidata.org/entity/Q42';
		$entityData = [
			'id' => 'Q42',
			'type' => 'item',
			'labels' => [ 'en' => [ 'language' => 'en', 'value' => 'Douglas Adams' ] ],
		];

		$store->set( $conceptUri, $entityData );
		$result = $store->get( $conceptUri );

		$this->assertSame( $entityData, $result );
	}

	public function testSetOverwritesExistingData(): void {
		$store = $this->newStore();
		$conceptUri = 'https://www.wikidata.org/entity/Q42';

		$store->set( $conceptUri, [ 'version' => 1 ] );
		$store->set( $conceptUri, [ 'version' => 2 ] );

		$result = $store->get( $conceptUri );
		$this->assertSame( [ 'version' => 2 ], $result );
	}

	public function testNormalizesHttpsToHttp(): void {
		$store = $this->newStore();

		// Store with https
		$store->set( 'https://www.wikidata.org/entity/Q42', [ 'id' => 'Q42' ] );

		// Retrieve with http (should find it due to normalization)
		$result = $store->get( 'http://www.wikidata.org/entity/Q42' );

		$this->assertSame( [ 'id' => 'Q42' ], $result );
	}

	public function testDelete(): void {
		$store = $this->newStore();
		$conceptUri = 'https://www.wikidata.org/entity/Q42';

		$store->set( $conceptUri, [ 'id' => 'Q42' ] );
		$this->assertNotNull( $store->get( $conceptUri ) );

		$store->delete( $conceptUri );
		$this->assertNull( $store->get( $conceptUri ) );
	}

	public function testGetReturnsNullForExpiredEntry(): void {
		// TTL of 1 second
		$store = $this->newStore( [ 'remoteEntityCacheTTL' => 1 ] );
		$conceptUri = 'https://www.wikidata.org/entity/Q42';

		$store->set( $conceptUri, [ 'id' => 'Q42' ] );

		// Wait for expiry
		sleep( 2 );

		$result = $store->get( $conceptUri );
		$this->assertNull( $result );
	}

	public function testGetReturnsDataWhenTtlNotConfigured(): void {
		$store = $this->newStore( [] ); // No TTL setting
		$conceptUri = 'https://www.wikidata.org/entity/Q42';

		$store->set( $conceptUri, [ 'id' => 'Q42' ] );
		$result = $store->get( $conceptUri );

		$this->assertSame( [ 'id' => 'Q42' ], $result );
	}

	public function testGetReturnsDataWhenTtlIsZero(): void {
		$store = $this->newStore( [ 'remoteEntityCacheTTL' => 0 ] );
		$conceptUri = 'https://www.wikidata.org/entity/Q42';

		$store->set( $conceptUri, [ 'id' => 'Q42' ] );
		$result = $store->get( $conceptUri );

		// TTL of 0 means no expiry
		$this->assertSame( [ 'id' => 'Q42' ], $result );
	}
}

