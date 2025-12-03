<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wikibase\Repo\RemoteEntity\RemoteEntityId;

/**
 * @covers \Wikibase\Repo\RemoteEntity\RemoteEntityId
 */
class RemoteEntityIdTest extends TestCase {

	public function testConstructorWithValidConceptUri(): void {
		$id = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$this->assertSame( 'https://www.wikidata.org/entity/Q42', $id->getConceptUri() );
	}

	public function testConstructorWithHttpUri(): void {
		$id = new RemoteEntityId( 'http://www.wikidata.org/entity/Q42' );
		$this->assertSame( 'http://www.wikidata.org/entity/Q42', $id->getConceptUri() );
	}

	public function testConstructorRejectsEmptyString(): void {
		$this->expectException( InvalidArgumentException::class );
		new RemoteEntityId( '' );
	}

	public function testConstructorRejectsNonHttpUri(): void {
		$this->expectException( InvalidArgumentException::class );
		new RemoteEntityId( 'urn:wikidata:Q42' );
	}

	public function testGetSerialization(): void {
		$id = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$this->assertSame( 'https://www.wikidata.org/entity/Q42', $id->getSerialization() );
	}

	public function testToString(): void {
		$id = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$this->assertSame( 'https://www.wikidata.org/entity/Q42', (string)$id );
	}

	public function testEquals(): void {
		$a = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$b = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$c = new RemoteEntityId( 'https://www.wikidata.org/entity/Q43' );
		$d = new RemoteEntityId( 'https://commons.wikimedia.org/entity/M12345' );

		$this->assertTrue( $a->equals( $b ), 'Same concept URI should be equal' );
		$this->assertFalse( $a->equals( $c ), 'Different entity id should not be equal' );
		$this->assertFalse( $a->equals( $d ), 'Different source should not be equal' );
	}

	public function testPhpSerializeRoundTrip(): void {
		$original = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );

		$copy = unserialize( serialize( $original ) );

		$this->assertInstanceOf( RemoteEntityId::class, $copy );
		$this->assertSame( 'https://www.wikidata.org/entity/Q42', $copy->getSerialization() );
		$this->assertTrue( $original->equals( $copy ) );
	}
}

