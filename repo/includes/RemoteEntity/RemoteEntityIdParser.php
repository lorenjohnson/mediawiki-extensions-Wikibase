<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\RemoteEntity;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;

/**
 * Decorator for EntityIdParser that understands concept URIs
 * (e.g. "https://www.wikidata.org/entity/Q42") as remote entity IDs.
 */
class RemoteEntityIdParser implements EntityIdParser {

	private EntityIdParser $innerParser;

	public function __construct( EntityIdParser $innerParser ) {
		$this->innerParser = $innerParser;
	}

	public function parse( $idSerialization ): EntityId {
		if ( !is_string( $idSerialization ) ) {
			return $this->innerParser->parse( $idSerialization );
		}

		// Concept URI format: https://…/entity/Q123
		if ( preg_match( '~^https?://.+/entity/[A-Za-z]\d+$~', $idSerialization ) ) {
			// Validate local part via inner parser; throws on bad ids.
			$this->innerParser->parse( basename( $idSerialization ) );
			return new RemoteEntityId( $idSerialization );
		}

		// Fallback to the original parser for local Q/P/etc ids.
		return $this->innerParser->parse( $idSerialization );
	}
}

