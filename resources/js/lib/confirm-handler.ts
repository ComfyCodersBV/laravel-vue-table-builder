import type {ConfirmDetails} from '../types/table-builder'

export type ConfirmHandler = (message: string, details?: ConfirmDetails) => boolean | Promise<boolean>

let registeredHandler: ConfirmHandler | null = null

export function registerConfirmHandler(handler: ConfirmHandler | null): void {
    registeredHandler = handler
}

export function resolveConfirmHandler(handler?: ConfirmHandler | null): ConfirmHandler | null {
    return handler ?? registeredHandler
}
