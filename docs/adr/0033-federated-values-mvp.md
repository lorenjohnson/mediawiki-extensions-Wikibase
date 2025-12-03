# 33) Federated Values — MVP {#adr_0033}

Date: 2025-07-15
Status: Draft

## Context

As part of the broader Wikibase federation effort, this ADR defines the first minimum-viable feature that allows users to select and display Items from remote Wikibase repositories (initially Wikidata only) as statement values.

When editing a statement, users can search for Items. Remote results from Wikidata appear in the autocomplete dropdown alongside local results. Users can select a remote Item, save the statement, and the remote value is persisted locally.

When displayed, remote values are visually indicated as originating from a remote Wikibase instance. The value renders as a link that, when clicked, opens the source entity page in a new window based on the source's concept URI.

## Decision

The Federated Values MVP consists of five key components:

### Entity Source Configuration

Phabricator: [T406224](https://phabricator.wikimedia.org/T406224)

This component reuses and extends the entity source configuration pattern established by Federated Properties (see [ADR #10](@ref adr_0010), [ADR #19](@ref adr_0019), and [ADR #21](@ref adr_0021)). The existing `entitySources` configuration already defines remote sources with a `conceptBaseUri`. We extend `ApiEntitySource` with a `repoApiUrl` property, and add a helper function that derives the remote API endpoint from the concept URI when not explicitly configured.

`DefaultWikidataEntitySourceAdder` automatically injects Wikidata as a remote entity source when the feature flag is enabled:

```php
$wgWBRepoSettings['federatedValuesEnabled'] = true;
```

Key classes:
- `ApiEntitySource` — extended with optional `repoApiUrl` property
- `EntitySourceDefinitions` — new `getApiSources()` helper method
- `DefaultWikidataEntitySourceAdder` — configures Wikidata as default remote source, re-using a pattern from Federated Properties

### Remote Entity Search

Phabricator: [T406226](https://phabricator.wikimedia.org/T406226), [T409951](https://phabricator.wikimedia.org/T409951)

When a user edits a statement, they can search for and select remote entities. Local results appear first, followed by remote results (currently only from Wikidata). Remote results are identified by their concept URI (e.g. `https://www.wikidata.org/entity/Q42`).

Key classes:
- `RemoteEntitySearchClient` — queries remote `wbsearchentities` API
- `RemoteEntitySearchHelper` — decorator merging remote results with local search
- `RemoteEntitySearchHelperCallbacksHookHandler` — hooks into entity search pipeline
- JavaScript entity selector override for displaying remote results in autocomplete

### Remote Entity Storage

Phabricator: [T408517](https://phabricator.wikimedia.org/T408517)

When a user saves a page containing a remote entity reference, the entity data is fetched and stored locally. The `RemoteEntityId` value object (e.g. `https://www.wikidata.org/entity/Q42`) is persisted in the statement, while the entity's JSON data is cached in the `wb_remote_entity` table.

Key classes:
- `RemoteEntityId` — value object representing a remote entity
- `RemoteEntityStore` — database-backed storage in `wb_remote_entity` table
- `RemoteEntityIdParser` — parses remote entity ID strings
- `RemoteEntityStorageHookHandler` — defers storage until page save via `PageSaveComplete` hook

No automatic background synchronization or cache invalidation is performed in this MVP. The stored snapshot is used for all future reads until an explicit refresh occurs.

### Remote Entity Lookup & Formatting

Phabricator: [T408520](https://phabricator.wikimedia.org/T408520), [T409948](https://phabricator.wikimedia.org/T409948)

When displaying a page, remote entities are rendered as HTML links pointing to their source repository. Each link includes a badge showing the source (e.g. "www.wikidata.org") and opens in a new tab.

Key classes:
- `RemoteEntityLookup` — retrieves entity data from local cache, fetches via `wbgetentities` API if missing
- `RemoteEntityIdValueFormatter` — renders remote entities as HTML links with source badges

### Remote Entity Sync

Phabricator: [T408518](https://phabricator.wikimedia.org/T408518)

Scope TBD. This component will address keeping cached remote Items synchronized with their source. Options under consideration:

- Periodic maintenance scripts via MediaWiki job queue
- On-demand "refresh" action in the UI
- Visual staleness indicators (e.g. color-coded freshness based on configurable thresholds)

Note: The storage layer includes an optional `remoteEntityCacheTTL` Repo setting (`$wgWBRepoSettings['remoteEntityCacheTTL']`) that, when configured, treats cached entities as expired after the specified number of seconds. By default this is not set, meaning cached data never expires automatically. This mechanism is implemented but not advertised until the broader sync strategy is decided.

### Remote Entity Handling in Client

Scope TBD. When accessing a Wikibase repository as a Client (e.g. Wikipedia consuming Wikidata), Remote Items referenced in statements need appropriate handling and display. This may require changes to how Client renders entity values or follows links to remote sources.

## Consequences

- Enables merged search and selection of remote Items within statement editors
- Introduces the concept of a stable, locally cached "remote value"
- Adds no background synchronization load to Wikidata
- Provides a layered, testable architecture with clear separation of concerns
- Each layer can be reviewed and deployed independently via Gerrit changeset chain

## Future: Expanding to Other Entity Types

While this MVP is scoped to Item values only, the underlying architecture is largely entity-type agnostic. Expanding support to include Lexeme and EntitySchema remote values is possible and may require minimal changes:

- **JavaScript**: Update the entity selector type check (`data.type === 'item'`) to include additional types
- **Entity Source Configuration**: Add entity types to the API source definition
- **RemoteEntityIdParser**: Add type mappings for `L` (Lexeme) and `E` (EntitySchema) prefixes

The core storage, lookup, and formatting infrastructure already handles any entity type.

## Note on Federated Properties

This feature does not strictly require removal of the existing Federated Properties code. However, it is expected that by the time Federated Values is released, the Federated Properties implementation will likely be removed.

The Remote Entity storage and lookup system implemented here retraces a significant portion of the Federated Properties feature set while using more up-to-date implementation patterns aligned with current Wikibase best practices. This creates a strong common foundation for extending federation features in the future.

Removal of Federated Properties has been discussed across the Wikibase teams with general consensus to proceed. The introduction of this Remote Entity layer further justifies that decision by providing a cleaner, more maintainable approach to federation that can support both federated values (Items as statement values) and, in the future, federated properties if needed.

## Edge Cases and Open Concerns

- **Content moderation**: Remote entity labels or descriptions may contain explicit, offensive, or otherwise unwanted content. Should such entities be filtered during search, or is content moderation the responsibility of the source repository? How should local administrators handle problematic remote content?

- **Feature disablement**: If an instance disables Federated Values after having used it, previously selected remote Item values will display as "deleted" entities. Is this the desired behavior, or should there be a migration path or clearer user messaging?

- **Remote source unavailability**: What happens when the remote source (e.g. Wikidata) is temporarily or permanently unavailable? For autocomplete search, results simply won't appear. For display, cached data will be used if available. What if cache is empty and source is unreachable?

- **Rate limiting and performance**: High-traffic instances may trigger rate limits on remote APIs. Should there be local rate limiting, request batching, or caching strategies to mitigate this?

- **Entity deletion on remote**: If a remote entity is deleted at its source, the local cache retains stale data. Should sync detect deletions and update local display accordingly?

- **ADR structure**: This ADR combines feature scope with architectural decisions. Would it be clearer to split into separate ADRs? For example:
  - ADR 0034: Remote Entity Caching Strategy (the `wb_remote_entity` storage pattern)
  - ADR 0035: API Source URL Resolution (extending the concept URI pattern from ADR 0019/0021)
