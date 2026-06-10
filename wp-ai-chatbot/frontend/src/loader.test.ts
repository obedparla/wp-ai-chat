import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { initLoader } from './loader'

type ImportWidgetModule = Parameters<typeof initLoader>[0]

const createMocks = () => {
  const mountWidget = vi.fn()
  const importWidgetModule = vi.fn(async () => ({ mountWidget })) as NonNullable<ImportWidgetModule>
  return { mountWidget, importWidgetModule }
}

const getLauncher = () =>
  document.querySelector<HTMLButtonElement>('.wpaic-loader-launcher')

const getTeaserMessage = () =>
  document.querySelector<HTMLButtonElement>('.wpaic-loader-teaser-message')

const requireElement = (selector: string): HTMLElement => {
  const element = document.querySelector<HTMLElement>(selector)
  if (!element) throw new Error(`Missing element: ${selector}`)
  return element
}

const proactiveConfig = {
  apiUrl: '/wp-json/wpaic/v1',
  nonce: 'test-nonce',
  greeting: 'Hello! How can I help you today?',
  proactiveEnabled: true,
  proactiveDelay: 5,
  proactiveMessage: 'Need help?',
}

const flushMicrotasks = async () => {
  await Promise.resolve()
  await Promise.resolve()
}

describe('loader', () => {
  beforeEach(() => {
    sessionStorage.clear()
    document.body.innerHTML = '<div id="wpaic-chatbot-root"></div>'
  })

  afterEach(() => {
    document.body.innerHTML = ''
    vi.useRealTimers()
  })

  it('renders the launcher button when the chatbot root exists', () => {
    const { importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    const launcher = getLauncher()
    expect(launcher).toBeInTheDocument()
    expect(launcher).toHaveAttribute('aria-label', 'Open chat')
    expect(importWidgetModule).not.toHaveBeenCalled()
  })

  it('does nothing when the chatbot root is missing', () => {
    document.body.innerHTML = ''
    const { importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    expect(getLauncher()).not.toBeInTheDocument()
    expect(importWidgetModule).not.toHaveBeenCalled()
  })

  it('loads and opens the widget when the launcher is clicked', async () => {
    const { mountWidget, importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    requireElement('.wpaic-loader-launcher').click()
    await flushMicrotasks()

    expect(mountWidget).toHaveBeenCalledTimes(1)
    expect(mountWidget).toHaveBeenCalledWith({ openOnMount: true, viaProactiveTeaser: false })
    expect(getLauncher()).not.toBeInTheDocument()
    expect(sessionStorage.getItem('wpaic_proactive_dismissed')).toBe('true')
  })

  it('shows the proactive teaser after the configured delay', () => {
    vi.useFakeTimers()
    window.wpaicConfig = { ...proactiveConfig }
    const { importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    vi.advanceTimersByTime(4999)
    expect(getTeaserMessage()).not.toBeInTheDocument()

    vi.advanceTimersByTime(1)
    expect(getTeaserMessage()).toHaveTextContent('Need help?')
    expect(importWidgetModule).not.toHaveBeenCalled()
  })

  it('opens the widget via the proactive path when the teaser is clicked', async () => {
    vi.useFakeTimers()
    window.wpaicConfig = { ...proactiveConfig }
    const { mountWidget, importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    vi.advanceTimersByTime(5000)
    requireElement('.wpaic-loader-teaser-message').click()
    await flushMicrotasks()

    expect(mountWidget).toHaveBeenCalledWith({ openOnMount: true, viaProactiveTeaser: true })
    expect(getTeaserMessage()).not.toBeInTheDocument()
    expect(getLauncher()).not.toBeInTheDocument()
    expect(sessionStorage.getItem('wpaic_proactive_dismissed')).toBe('true')
  })

  it('dismisses the teaser without loading the widget', () => {
    vi.useFakeTimers()
    window.wpaicConfig = { ...proactiveConfig }
    const { importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    vi.advanceTimersByTime(5000)
    requireElement('.wpaic-loader-teaser-dismiss').click()

    expect(getTeaserMessage()).not.toBeInTheDocument()
    expect(getLauncher()).toBeInTheDocument()
    expect(importWidgetModule).not.toHaveBeenCalled()
    expect(sessionStorage.getItem('wpaic_proactive_dismissed')).toBe('true')
  })

  it('does not show the teaser when dismissed earlier in the session', () => {
    vi.useFakeTimers()
    sessionStorage.setItem('wpaic_proactive_dismissed', 'true')
    window.wpaicConfig = { ...proactiveConfig }
    const { importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    vi.advanceTimersByTime(10000)
    expect(getTeaserMessage()).not.toBeInTheDocument()
    expect(importWidgetModule).not.toHaveBeenCalled()
  })

  it('falls back to a 10s delay when the configured delay is empty', () => {
    vi.useFakeTimers()
    window.wpaicConfig = { ...proactiveConfig, proactiveDelay: '' as unknown as number }
    const { importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    vi.advanceTimersByTime(9999)
    expect(getTeaserMessage()).not.toBeInTheDocument()

    vi.advanceTimersByTime(1)
    expect(getTeaserMessage()).toBeInTheDocument()
  })

  it('idle-loads the widget without opening when a stored conversation exists', async () => {
    vi.useFakeTimers()
    sessionStorage.setItem('wpaic_chat_history', '[{"id":"1"}]')
    const { mountWidget, importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    expect(importWidgetModule).not.toHaveBeenCalled()

    // jsdom has no requestIdleCallback, so the 5s setTimeout fallback runs.
    await vi.advanceTimersByTimeAsync(5000)

    expect(mountWidget).toHaveBeenCalledTimes(1)
    expect(mountWidget).toHaveBeenCalledWith()
    expect(getLauncher()).not.toBeInTheDocument()
  })

  it('uses requestIdleCallback for the idle load when available', async () => {
    sessionStorage.setItem('wpaic_chat_history', '[{"id":"1"}]')
    const requestIdleCallback = vi.fn((callback: IdleRequestCallback) => {
      callback({} as IdleDeadline)
      return 1
    })
    vi.stubGlobal('requestIdleCallback', requestIdleCallback)

    const { mountWidget, importWidgetModule } = createMocks()
    initLoader(importWidgetModule)
    await flushMicrotasks()

    expect(requestIdleCallback).toHaveBeenCalledWith(expect.any(Function), { timeout: 5000 })
    expect(mountWidget).toHaveBeenCalledWith()

    vi.unstubAllGlobals()
  })

  it('does not idle-load when no stored conversation exists', async () => {
    vi.useFakeTimers()
    const { importWidgetModule } = createMocks()
    initLoader(importWidgetModule)

    await vi.advanceTimersByTimeAsync(10000)

    expect(importWidgetModule).not.toHaveBeenCalled()
    expect(getLauncher()).toBeInTheDocument()
  })

  it('prefers the click open when it races an in-flight idle load', async () => {
    vi.useFakeTimers()
    sessionStorage.setItem('wpaic_chat_history', '[{"id":"1"}]')

    const mountWidget = vi.fn()
    let resolveImport: ((module: { mountWidget: typeof mountWidget }) => void) | undefined
    const importWidgetModule = vi.fn(
      () =>
        new Promise<{ mountWidget: typeof mountWidget }>((resolve) => {
          resolveImport = resolve
        })
    )
    initLoader(importWidgetModule)

    // Idle load kicks off the import but it has not resolved yet.
    await vi.advanceTimersByTimeAsync(5000)
    expect(importWidgetModule).toHaveBeenCalledTimes(1)

    requireElement('.wpaic-loader-launcher').click()
    resolveImport?.({ mountWidget })
    await flushMicrotasks()

    expect(mountWidget).toHaveBeenCalledTimes(1)
    expect(mountWidget).toHaveBeenCalledWith({ openOnMount: true, viaProactiveTeaser: false })
  })
})
