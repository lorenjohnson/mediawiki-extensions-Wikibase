<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\RemoteEntity\RemoteEntityId;
use Wikibase\Repo\RemoteEntity\RemoteEntityIdParser;

/**
 * @covers \Wikibase\Repo\RemoteEntity\RemoteEntityIdParser
 * @group Wikibase
 */
class RemoteEntityIdParserTest extends TestCase {

	private function newMockInnerParser(): EntityIdParser {
		$mock = $this->createMock( EntityIdParser::class );
		$mock->method( 'parse' )
			->willReturnCallback( function ( $id ) {
				if ( preg_match( '/^Q\d+$/', $id ) ) {
					return new ItemId( $id );
				}
				if ( preg_match( '/^P\d+$/', $id ) ) {
					return new NumericPropertyId( $id );
				}
				throw new EntityIdParsingException( "Invalid entity ID: $id" );
			} );
		return $mock;
	}

	public function testParseConceptUriHttps(): void {
		$parser = new RemoteEntityIdParser( $this->newMockInnerParser() );

		$result = $parser->parse( 'https://www.wikidata.org/entity/Q42' );

		$this->assertInstanceOf( RemoteEntityId::class, $result );
		$this->assertSame( 'https://www.wikidata.org/entity/Q42', $result->getSerialization() );
	}

	public function testParseConceptUriHttp(): void {
		$parser = new RemoteEntityIdParser( $this->newMockInnerParser() );

		$result = $parser->parse( 'http://www.wikidata.org/entity/Q42' );

		$this->assertInstanceOf( RemoteEntityId::class, $result );
		$this->assertSame( 'http://www.wikidata.org/entity/Q42', $result->getSerialization() );
	}

	public function testParseLocalIdFallsBackToInnerParser(): void {
		$parser = new RemoteEntityIdParser( $this->newMockInnerParser() );

		$result = $parser->parse( 'Q123' );

		$this->assertInstanceOf( ItemId::class, $result );
		$this->assertSame( 'Q123', $result->getSerialization() );
	}

	public function testParsePropertyId(): void {
		$parser = new RemoteEntityIdParser( $this->newMockInnerParser() );

		$result = $parser->parse( 'P31' );

		$this->assertInstanceOf( PropertyId::class, $result );
		$this->assertSame( 'P31', $result->getSerialization() );
	}

	public function testParseColonFormatFallsBackToInnerParser(): void {
		$parser = new RemoteEntityIdParser( $this->newMockInnerParser() );

		// 'wikidata:Q42' is not a concept URI, so it should try inner parser
		// Inner parser will throw since 'wikidata:Q42' is not a valid local ID
		$this->expectException( EntityIdParsingException::class );
		$parser->parse( 'wikidata:Q42' );
	}

	public function testParseConceptUriValidatesLocalPart(): void {
		$mockParser = $this->createMock( EntityIdParser::class );
		$mockParser->method( 'parse' )
			->willThrowException( new EntityIdParsingException( 'Invalid' ) );

		$parser = new RemoteEntityIdParser( $mockParser );

		// The concept URI pattern matches but inner parser rejects 'INVALID'
		$this->expectException( EntityIdParsingException::class );
		$parser->parse( 'https://www.wikidata.org/entity/INVALID' );
	}
}

