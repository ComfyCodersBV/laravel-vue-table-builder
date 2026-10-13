export const SUPPORTED_SCHEMA_VERSION = '1.0'

const majorOf = (version: string): string => version.split('.')[0]

let warnedAboutMissingVersion = false

export function assertSupportedSchemaVersion(schemaVersion?: string): void {
    if (typeof schemaVersion === 'undefined') {
        if (import.meta.env.DEV && !warnedAboutMissingVersion) {
            warnedAboutMissingVersion = true

            console.warn(
                `[table-builder] This table payload carries no schemaVersion, so it predates the ${SUPPORTED_SCHEMA_VERSION} contract. It renders fine today. Install tranquil-tools/laravel-table-builder ^1.0 to keep the check meaningful.`,
            )
        }

        return
    }

    if (majorOf(schemaVersion) === majorOf(SUPPORTED_SCHEMA_VERSION)) {
        return
    }

    const message = `[table-builder] Schema version mismatch: the core emitted ${schemaVersion}, this renderer supports ${SUPPORTED_SCHEMA_VERSION}. Align tranquil-tools/laravel-table-builder and tranquil-tools/laravel-vue-table-builder.`

    if (import.meta.env.DEV) {
        throw new Error(message)
    }

    console.warn(message)
}
