<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use PHPUnit\Framework\TestCase;
use Wikibase\DataAccess\ApiEntitySource;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Lib\SubEntityTypesMapper;
use Wikibase\Repo\RemoteEntity\DefaultWikidataEntitySourceAdder;

/**
 * @covers \Wikibase\Repo\RemoteEntity\DefaultWikidataEntitySourceAdder
 * @group Wikibase
 */
class DefaultWikidataEntitySourceAdderTest extends TestCase {

	private function newSubEntityTypesMapper(): SubEntityTypesMapper {
		return new SubEntityTypesMapper( [] );
	}

	private function newLocalSource(): DatabaseEntitySource {
		return new DatabaseEntitySource(
			'local',
			false,
			[ Item::ENTITY_TYPE => [ 'namespaceId' => 0, 'slot' => 'main' ] ],
			'http://localhost/entity/',
			'',
			'',
			''
		);
	}

	public function testDoesNothingWhenFederatedValuesDisabled(): void {
		$adder = new DefaultWikidataEntitySourceAdder(
			false,
			$this->newSubEntityTypesMapper()
		);

		$original = new EntitySourceDefinitions(
			[ $this->newLocalSource() ],
			$this->newSubEntityTypesMapper()
		);

		$result = $adder->addDefaultIfRequired( $original );

		$this->assertSame( $original, $result );
	}

	public function testAddsWikidataSourceWhenFederatedValuesEnabled(): void {
		$adder = new DefaultWikidataEntitySourceAdder(
			true,
			$this->newSubEntityTypesMapper()
		);

		$original = new EntitySourceDefinitions(
			[ $this->newLocalSource() ],
			$this->newSubEntityTypesMapper()
		);

		$result = $adder->addDefaultIfRequired( $original );

		$this->assertNotSame( $original, $result );
		$sources = $result->getSources();
		$this->assertCount( 2, $sources );

		$wikidataSource = null;
		foreach ( $sources as $source ) {
			if ( $source->getSourceName() === 'wikidata' ) {
				$wikidataSource = $source;
				break;
			}
		}

		$this->assertNotNull( $wikidataSource );
		$this->assertInstanceOf( ApiEntitySource::class, $wikidataSource );
		$this->assertContains( Item::ENTITY_TYPE, $wikidataSource->getEntityTypes() );
		$this->assertSame( 'http://www.wikidata.org/entity/', $wikidataSource->getConceptBaseUri() );
		$this->assertSame( 'https://www.wikidata.org/w/api.php', $wikidataSource->getRepoApiUrl() );
	}

	public function testDoesNotAddWikidataWhenApiSourceForItemsAlreadyExists(): void {
		$adder = new DefaultWikidataEntitySourceAdder(
			true,
			$this->newSubEntityTypesMapper()
		);

		$existingApiSource = new ApiEntitySource(
			'custom-wikidata',
			[ Item::ENTITY_TYPE ],
			'https://custom.example.org/entity/',
			'',
			'',
			'',
			'https://custom.example.org/w/api.php'
		);

		$original = new EntitySourceDefinitions(
			[ $this->newLocalSource(), $existingApiSource ],
			$this->newSubEntityTypesMapper()
		);

		$result = $adder->addDefaultIfRequired( $original );

		$this->assertSame( $original, $result );
	}

	public function testAddsWikidataWhenApiSourceExistsButNotForItems(): void {
		$adder = new DefaultWikidataEntitySourceAdder(
			true,
			$this->newSubEntityTypesMapper()
		);

		$propertyApiSource = new ApiEntitySource(
			'property-source',
			[ Property::ENTITY_TYPE ],
			'https://props.example.org/entity/',
			'',
			'',
			'',
			'https://props.example.org/w/api.php'
		);

		$original = new EntitySourceDefinitions(
			[ $this->newLocalSource(), $propertyApiSource ],
			$this->newSubEntityTypesMapper()
		);

		$result = $adder->addDefaultIfRequired( $original );

		$this->assertNotSame( $original, $result );
		$sources = $result->getSources();
		$this->assertCount( 3, $sources );
	}
}

