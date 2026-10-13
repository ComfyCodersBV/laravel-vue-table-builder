import {onMounted, onScopeDispose, watch, type Ref} from 'vue'

export interface TablePollingOptions {
    seconds: Ref<number | null | undefined>
    paused: Ref<boolean>
    refresh: () => void
}

export function useTablePolling(options: TablePollingOptions) {
    const {seconds, paused, refresh} = options

    let timer: ReturnType<typeof setInterval> | null = null
    let missed = false
    let mounted = false

    function tick(): void {
        if (paused.value || document.hidden) {
            missed = true

            return
        }

        missed = false
        refresh()
    }

    function catchUp(): void {
        if (missed && !document.hidden) {
            tick()
        }
    }

    function stop(): void {
        if (timer !== null) {
            clearInterval(timer)
            timer = null
        }
    }

    function start(): void {
        stop()
        missed = false

        if (!mounted || !seconds.value || seconds.value <= 0) {
            return
        }

        timer = setInterval(tick, seconds.value * 1000)
    }

    watch(seconds, start)

    onMounted(() => {
        mounted = true
        document.addEventListener('visibilitychange', catchUp)
        start()
    })

    onScopeDispose(() => {
        stop()

        if (mounted) {
            document.removeEventListener('visibilitychange', catchUp)
        }
    })

    return {start, stop}
}
