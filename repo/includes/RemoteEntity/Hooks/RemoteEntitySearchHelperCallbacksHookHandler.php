<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\RemoteEntity\Hooks;

use Wikibase\DataAccess\ApiEntitySource;
use Wikibase\DataAccess\EntitySourceDefinitions;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\RemoteEntity\RemoteEntitySearchClient;
use Wikibase\Repo\RemoteEntity\RemoteEntitySearchHelper;
use Wikibase\Repo\Hooks\WikibaseRepoEntitySearchHelperCallbacksHook;

class RemoteEntitySearchHelperCallbacksHookHandler implements WikibaseRepoEntitySearchHelperCallbacksHook {

	private RemoteEntitySearchClient $remoteClient;
	private SettingsArray $settings;
	private EntitySourceDefinitions $entitySourceDefinitions;

	public function __construct(
		RemoteEntitySearchClient $remoteClient,
		SettingsArray $settings,
		EntitySourceDefinitions $entitySourceDefinitions
	) {
		$this->remoteClient = $remoteClient;
		$this->settings = $settings;
		$this->entitySourceDefinitions = $entitySourceDefinitions;
	}

	/**
	 * @param array<string,callable> &$callbacks
	 */
	public function onWikibaseRepoEntitySearchHelperCallbacks( array &$callbacks ): void {
		if ( !$this->settings->getSetting( 'federatedValuesEnabled' ) ) {
			return;
		}

		// Get entity types available from API sources
		$apiEntityTypes = $this->getApiEntityTypes();
		if ( empty( $apiEntityTypes ) ) {
			return;
		}

		$remoteClient = $this->remoteClient;
		$settings = $this->settings;

		// Only wrap callbacks for entity types that have API sources
		foreach ( $callbacks as $entityType => $localSearchHelperFactory ) {
			if ( !in_array( $entityType, $apiEntityTypes, true ) ) {
				// This entity type has no API source, don't add remote search
				continue;
			}

			// Wrap the existing factory with our decorator.
			$callbacks[$entityType] = static function ( ...$args ) use ( $localSearchHelperFactory, $remoteClient, $settings ) {
				$localSearchHelper = $localSearchHelperFactory( ...$args );

				return new RemoteEntitySearchHelper(
					$localSearchHelper,
					$remoteClient,
					$settings
				);
			};
		}
	}

	/**
	 * Get all entity types that are available from API sources.
	 *
	 * @return string[]
	 */
	private function getApiEntityTypes(): array {
		$apiEntityTypes = [];
		foreach ( $this->entitySourceDefinitions->getSources() as $source ) {
			if ( $source->getType() === ApiEntitySource::TYPE ) {
				$apiEntityTypes = array_merge( $apiEntityTypes, $source->getEntityTypes() );
			}
		}
		return array_unique( $apiEntityTypes );
	}
}
