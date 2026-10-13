import {afterEach, describe, expect, it, vi} from 'vitest'
import {SUPPORTED_SCHEMA_VERSION, assertSupportedSchemaVersion} from '../../resources/js/lib/schema-version'

describe('assertSupportedSchemaVersion', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('accepts a payload of the same major version', () => {
        expect(() => assertSupportedSchemaVersion(SUPPORTED_SCHEMA_VERSION)).not.toThrow()
        expect(() => assertSupportedSchemaVersion(`${SUPPORTED_SCHEMA_VERSION.split('.')[0]}.9`)).not.toThrow()
    })

    it('throws during development on another major version', () => {
        expect(() => assertSupportedSchemaVersion('2.0')).toThrow(/Schema version mismatch/)
    })

    it('only warns once about a payload without a version', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined)

        assertSupportedSchemaVersion(undefined)
        assertSupportedSchemaVersion(undefined)

        expect(warn.mock.calls.length).toBeLessThanOrEqual(1)
    })
})
