<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Tests\RemoteEntity;

use PHPUnit\Framework\TestCase;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Repo\RemoteEntity\RemoteEntityId;
use Wikibase\Repo\RemoteEntity\RemoteEntityIdValueFormatter;
use Wikibase\Repo\RemoteEntity\RemoteEntityLookup;

/**
 * @covers \Wikibase\Repo\RemoteEntity\RemoteEntityIdValueFormatter
 * @group Wikibase
 */
class RemoteEntityIdValueFormatterTest extends TestCase {

	private function newMockInnerFormatter( string $returnValue = 'inner-formatted' ): ValueFormatter {
		$mock = $this->createMock( ValueFormatter::class );
		$mock->method( 'format' )
			->willReturn( $returnValue );
		return $mock;
	}

	private function newMockLookup( ?array $entityData ): RemoteEntityLookup {
		$mock = $this->createMock( RemoteEntityLookup::class );
		$mock->method( 'fetchEntity' )
			->willReturn( $entityData );
		return $mock;
	}

	public function testFormatLocalEntityIdUsesInnerFormatter(): void {
		$formatter = new RemoteEntityIdValueFormatter(
			$this->newMockInnerFormatter( 'local-result' ),
			$this->newMockLookup( null ),
			[ 'en' ]
		);

		$value = new EntityIdValue( new ItemId( 'Q42' ) );
		$result = $formatter->format( $value );

		$this->assertSame( 'local-result', $result );
	}

	public function testFormatRemoteEntityIdWithLabel(): void {
		$entityData = [
			'id' => 'Q42',
			'labels' => [
				'en' => [ 'language' => 'en', 'value' => 'Douglas Adams' ],
			],
		];

		$formatter = new RemoteEntityIdValueFormatter(
			$this->newMockInnerFormatter(),
			$this->newMockLookup( $entityData ),
			[ 'en' ],
			SnakFormatter::FORMAT_HTML
		);

		$remoteId = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$value = new EntityIdValue( $remoteId );
		$result = $formatter->format( $value );

		$this->assertStringContainsString( 'Douglas Adams', $result );
		$this->assertStringContainsString( 'wb-remote-entity-wrapper', $result );
		$this->assertStringContainsString( 'wb-remote-entity-link', $result );
		$this->assertStringContainsString( 'wb-remote-entity-badge', $result );
		$this->assertStringContainsString( 'www.wikidata.org', $result );
	}

	public function testFormatRemoteEntityIdPlainText(): void {
		$entityData = [
			'id' => 'Q42',
			'labels' => [
				'en' => [ 'language' => 'en', 'value' => 'Douglas Adams' ],
			],
		];

		$formatter = new RemoteEntityIdValueFormatter(
			$this->newMockInnerFormatter(),
			$this->newMockLookup( $entityData ),
			[ 'en' ],
			SnakFormatter::FORMAT_PLAIN
		);

		$remoteId = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$value = new EntityIdValue( $remoteId );
		$result = $formatter->format( $value );

		$this->assertSame( 'Douglas Adams', $result );
	}

	public function testFormatRemoteEntityIdFallsBackToIdWhenNoLabel(): void {
		$entityData = [
			'id' => 'Q42',
			'labels' => [],
		];

		$formatter = new RemoteEntityIdValueFormatter(
			$this->newMockInnerFormatter(),
			$this->newMockLookup( $entityData ),
			[ 'en' ],
			SnakFormatter::FORMAT_HTML
		);

		$remoteId = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$value = new EntityIdValue( $remoteId );
		$result = $formatter->format( $value );

		$this->assertStringContainsString( 'Q42', $result );
	}

	public function testFormatRemoteEntityIdUsesLanguageFallback(): void {
		$entityData = [
			'id' => 'Q42',
			'labels' => [
				'de' => [ 'language' => 'de', 'value' => 'Douglas Adams (DE)' ],
			],
		];

		$formatter = new RemoteEntityIdValueFormatter(
			$this->newMockInnerFormatter(),
			$this->newMockLookup( $entityData ),
			[ 'en', 'de' ], // en first, then de
			SnakFormatter::FORMAT_PLAIN
		);

		$remoteId = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$value = new EntityIdValue( $remoteId );
		$result = $formatter->format( $value );

		$this->assertSame( 'Douglas Adams (DE)', $result );
	}

	public function testFormatRemoteEntityIdFallsBackToInnerOnLookupFailure(): void {
		$formatter = new RemoteEntityIdValueFormatter(
			$this->newMockInnerFormatter( 'fallback-result' ),
			$this->newMockLookup( null ), // Lookup returns null
			[ 'en' ]
		);

		$remoteId = new RemoteEntityId( 'https://www.wikidata.org/entity/Q42' );
		$value = new EntityIdValue( $remoteId );
		$result = $formatter->format( $value );

		$this->assertSame( 'fallback-result', $result );
	}

	public function testFormatNonEntityIdValueUsesInnerFormatter(): void {
		$innerFormatter = $this->newMockInnerFormatter( 'string-result' );

		$formatter = new RemoteEntityIdValueFormatter(
			$innerFormatter,
			$this->newMockLookup( null ),
			[ 'en' ]
		);

		$result = $formatter->format( 'some string' );

		$this->assertSame( 'string-result', $result );
	}
}

