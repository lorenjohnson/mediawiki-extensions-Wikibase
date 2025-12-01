// repo/resources/wikibase.remoteEntity/wikibase.remoteEntity.entityselector.js
( function ( $, mw ) {
	'use strict';

	function isRemoteEntityEnabled() {
		return !!mw.config.get( 'wbFederatedValuesEnabled' );
	}

	function getSuggestionHost( stub ) {
		var concepturi = ( stub && ( stub.concepturi || ( stub.meta && stub.meta.concepturi ) ) ) || null;
		if ( concepturi ) {
			try { return new URL( concepturi ).host || null; } catch ( e ) {}
		}
		return null;
	}

	function isRemoteSuggestion( suggestion ) {
		var host = getSuggestionHost( suggestion );
		return host && host !== window.location.host;
	}

	/**
	 * Check if the entityselector is being used in a "value context" where remote
	 * entities should be shown (e.g., statement values, qualifiers, references).
	 * Returns false for global site search and other non-value contexts.
	 *
	 * @param {jQuery} $element The entityselector input element
	 * @return {boolean}
	 */
	function isValueSearchContext( $element ) {
		// Global search input is not a value context
		if ( $element.is( '#searchInput' ) || $element.closest( '#searchform' ).length > 0 ) {
			return false;
		}
		// Scoped typeahead search is not a value context
		if ( $element.closest( '.vector-typeahead-search-wrapper' ).length > 0 ) {
			return false;
		}
		// Check if we're inside a snakview (statement value, qualifier, or reference)
		if ( $element.closest( '.wikibase-snakview' ).length > 0 ) {
			return true;
		}
		// Check if we're inside the new mobile editing UI value input
		if ( $element.closest( '.wikibase-wbui2025-snak-value' ).length > 0 ) {
			return true;
		}
		if ( $element.closest( '.wikibase-wbui2025-edit-statement-snak-value' ).length > 0 ) {
			return true;
		}
		// Default to false (don't show remote entities in unknown contexts)
		return false;
	}

	function decorateEntitySelectorLabelsForRemoteEntity( selectorProto ) {
		var origCreateLabelFromSuggestion = selectorProto._createLabelFromSuggestion;

		selectorProto._createLabelFromSuggestion = function ( entityStub ) {
			var $label = origCreateLabelFromSuggestion.call( this, entityStub );

			if ( !isRemoteEntityEnabled() ) {
				return $label;
			}

			var host = getSuggestionHost( entityStub );
			if ( !host || host === window.location.host ) {
				return $label; // local → no badge
			}

			var $badge = $( '<span>' )
				.addClass( 'wb-entityselector-remote-badge' )
				.text( host );

			$label.prepend( $badge );
			return $label;
		};
	}

	function decorateEntitySelectorValuesForRemoteEntity( selectorProto ) {
		var origCombineResults = selectorProto._combineResults;
		if ( typeof origCombineResults !== 'function' ) {
			return;
		}

		selectorProto._combineResults = function () {
			var args = Array.prototype.slice.call( arguments );
			var results = args[ 1 ];

			if ( isRemoteEntityEnabled() && Array.isArray( results ) ) {
				// Rewrite IDs for remote suggestions to use concept URI as canonical ID
				results = results.map( function ( suggestion ) {
					if ( !isRemoteSuggestion( suggestion ) ) {
						return suggestion;
					}

					var concepturi = suggestion.concepturi || ( suggestion.meta && suggestion.meta.concepturi );
					if ( concepturi ) {
						// Use concept URI as canonical id for remote selections.
						suggestion = $.extend( {}, suggestion, { id: concepturi } );
					}
					return suggestion;
				} );

				args[ 1 ] = results;
			}

			return origCombineResults.apply( this, args );
		};
	}

	/**
	 * Decorate _getSearchApiParameters to add federatedvalues=1 only when in value context.
	 * This ensures we don't query remote sources for global search, scoped search, etc.
	 */
	function decorateSearchApiParametersForRemoteEntity( selectorProto ) {
		var origGetSearchApiParameters = selectorProto._getSearchApiParameters;
		if ( typeof origGetSearchApiParameters !== 'function' ) {
			return;
		}

		selectorProto._getSearchApiParameters = function ( term ) {
			var data = origGetSearchApiParameters.call( this, term );

			// Only add remoteentities=1 if:
			// 1. Remote entity feature is enabled
			// 2. We're searching for items (not properties, etc.)
			// 3. We're in a value search context (statement value, qualifier, reference)
			if ( isRemoteEntityEnabled() &&
				 data.type === 'item' &&
				 isValueSearchContext( this.element ) ) {
				data.remoteentities = 1;
			}

			return data;
		};
	}

	function initRemoteEntitySelectorDecorators() {
		if ( !$.wikibase || !$.wikibase.entityselector ) {
			return;
		}
		var selectorProto = $.wikibase.entityselector.prototype;
		decorateEntitySelectorLabelsForRemoteEntity( selectorProto );
		decorateEntitySelectorValuesForRemoteEntity( selectorProto );
		decorateSearchApiParametersForRemoteEntity( selectorProto );

		// Also decorate entitysearch if it's already loaded (global search widget)
		if ( $.wikibase.entitysearch ) {
			var searchProto = $.wikibase.entitysearch.prototype;
			decorateEntitySelectorLabelsForRemoteEntity( searchProto );
			decorateEntitySelectorValuesForRemoteEntity( searchProto );
			decorateSearchApiParametersForRemoteEntity( searchProto );
		}
	}

	// Decorate entitysearch when it loads (may load after entityselector)
	function initRemoteEntitySearchDecorators() {
		if ( !$.wikibase || !$.wikibase.entitysearch ) {
			return;
		}
		var searchProto = $.wikibase.entitysearch.prototype;
		// Only decorate if not already decorated
		if ( !searchProto._remoteEntityDecorated ) {
			decorateEntitySelectorLabelsForRemoteEntity( searchProto );
			decorateEntitySelectorValuesForRemoteEntity( searchProto );
			decorateSearchApiParametersForRemoteEntity( searchProto );
			searchProto._remoteEntityDecorated = true;
		}
	}

	mw.loader.using( [ 'jquery.wikibase.entityselector' ] ).done( initRemoteEntitySelectorDecorators );

	// Try to decorate entitysearch when wikibase.ui.entitysearch loads
	mw.loader.using( [ 'wikibase.ui.entitysearch' ] ).done( initRemoteEntitySearchDecorators );
}( jQuery, mediaWiki ) );
