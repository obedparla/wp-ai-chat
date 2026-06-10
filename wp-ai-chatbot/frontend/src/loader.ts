// Tiny storefront loader. Enqueued on every pageview instead of the full
// React bundle: renders the launcher button and the proactive teaser with
// plain DOM, then dynamic-imports the real widget on first interaction.
// When a conversation already exists in sessionStorage the full app is
// loaded at idle (not first click) so session restore and the unread badge
// keep working for returning visitors.

interface WidgetModule {
  mountWidget: (options?: { openOnMount?: boolean; viaProactiveTeaser?: boolean }) => void
}

const PROACTIVE_DISMISSED_KEY = 'wpaic_proactive_dismissed'
const CHAT_HISTORY_KEY = 'wpaic_chat_history'
const IDLE_LOAD_TIMEOUT_MS = 5000

const LOADER_STYLES = `
.wpaic-loader-launcher{position:fixed;bottom:24px;right:24px;width:60px;height:60px;border-radius:9999px;border:0;background:__THEME__;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:9999;padding:0;box-shadow:0 10px 15px -3px rgb(0 0 0/.1),0 4px 6px -4px rgb(0 0 0/.1);transition:transform .2s ease-out,box-shadow .2s ease-out;animation:wpaic-loader-pulse 2s ease-in-out infinite}
.wpaic-loader-launcher:hover{transform:scale(1.08);animation:none;box-shadow:0 20px 25px -5px rgb(0 0 0/.1),0 8px 10px -6px rgb(0 0 0/.1)}
.wpaic-loader-launcher:active{transform:scale(.95)}
.wpaic-loader-launcher:focus-visible{outline:2px solid __THEME__;outline-offset:2px}
.wpaic-loader-badge{position:absolute;top:-4px;right:-4px;display:none;align-items:center;justify-content:center;width:20px;height:20px;border-radius:9999px;background:#ef4444;color:#fff;font-size:11px;font-weight:600;line-height:1}
@keyframes wpaic-loader-pulse{0%,100%{box-shadow:0 10px 15px -3px rgb(0 0 0/.1),0 0 0 0 rgba(0,115,170,.4)}50%{box-shadow:0 10px 15px -3px rgb(0 0 0/.1),0 0 0 12px rgba(0,115,170,0)}}
.wpaic-loader-teaser{position:fixed;bottom:96px;right:24px;z-index:9998;max-width:300px;animation:wpaic-loader-slideUp .3s ease-out}
@keyframes wpaic-loader-slideUp{from{opacity:0;transform:translateY(20px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}
.wpaic-loader-teaser-message{display:block;width:100%;text-align:left;background:#fff;border:1px solid #e2e8f0;border-radius:16px 16px 6px 16px;box-shadow:0 20px 25px -5px rgb(0 0 0/.1),0 8px 10px -6px rgb(0 0 0/.1);padding:12px 16px;font-size:14px;font-family:inherit;line-height:1.625;letter-spacing:-.025em;color:#1e293b;cursor:pointer;transition:box-shadow .2s}
.wpaic-loader-teaser-message:hover{box-shadow:0 25px 50px -12px rgb(0 0 0/.25)}
.wpaic-loader-teaser-message:focus-visible,.wpaic-loader-teaser-dismiss:focus-visible{outline:2px solid __THEME__;outline-offset:2px}
.wpaic-loader-teaser-dismiss{position:absolute;top:-10px;left:-10px;width:24px;height:24px;border-radius:9999px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 4px 6px -1px rgb(0 0 0/.1);color:#64748b;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;transition:color .2s}
.wpaic-loader-teaser-dismiss:hover{color:#1e293b}
@media (max-width:480px){
.wpaic-loader-launcher{bottom:20px;right:20px;width:56px;height:56px}
.wpaic-loader-teaser{bottom:88px;right:20px;max-width:calc(100vw - 80px)}
}
`

const LAUNCHER_ICON_SVG =
  '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'

const DISMISS_ICON_SVG =
  '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>'

function sessionStorageGet(key: string): string | null {
  try {
    return sessionStorage.getItem(key)
  } catch {
    return null
  }
}

function sessionStorageSet(key: string, value: string): void {
  try {
    sessionStorage.setItem(key, value)
  } catch {
    // Storage unavailable, ignore.
  }
}

export function initLoader(
  importWidgetModule: () => Promise<WidgetModule> = () => import('./widget')
): void {
  if (!document.getElementById('wpaic-chatbot-root')) return

  const config = window.wpaicConfig
  const configuredColor = config?.themeColor ?? ''
  const themeColor = /^#[0-9a-fA-F]{3,8}$/.test(configuredColor) ? configuredColor : '#2545B8'

  const container = document.createElement('div')
  container.id = 'wpaic-loader'

  const style = document.createElement('style')
  style.textContent = LOADER_STYLES.replace(/__THEME__/g, themeColor)
  container.appendChild(style)

  const launcher = document.createElement('button')
  launcher.type = 'button'
  launcher.className = 'wpaic-loader-launcher'
  launcher.setAttribute('aria-label', 'Open chat')
  // Badge placeholder for markup parity with the React launcher; only the
  // full app can flip it visible (unread state originates in-app).
  launcher.innerHTML = `<span class="wpaic-loader-badge">1</span>${LAUNCHER_ICON_SVG}`
  container.appendChild(launcher)

  let teaser: HTMLDivElement | null = null
  let openRequested = false
  let widgetModulePromise: Promise<WidgetModule> | null = null

  const loadWidgetModule = (): Promise<WidgetModule> => {
    if (!widgetModulePromise) {
      widgetModulePromise = importWidgetModule()
    }
    return widgetModulePromise
  }

  const removeTeaser = (): void => {
    teaser?.remove()
    teaser = null
  }

  const cleanup = (): void => {
    removeTeaser()
    container.remove()
  }

  const openWidget = (viaProactiveTeaser: boolean): void => {
    openRequested = true
    sessionStorageSet(PROACTIVE_DISMISSED_KEY, 'true')
    removeTeaser()
    void loadWidgetModule().then((widget) => {
      widget.mountWidget({ openOnMount: true, viaProactiveTeaser })
      cleanup()
    })
  }

  launcher.addEventListener('click', () => openWidget(false))

  const showTeaser = (): void => {
    if (teaser || openRequested || !container.isConnected) return

    const message =
      config?.proactiveMessage || config?.greeting || 'Hello! How can I help you today?'

    teaser = document.createElement('div')
    teaser.className = 'wpaic-loader-teaser'

    const messageButton = document.createElement('button')
    messageButton.type = 'button'
    messageButton.className = 'wpaic-loader-teaser-message'
    messageButton.textContent = message
    messageButton.addEventListener('click', () => openWidget(true))

    const dismissButton = document.createElement('button')
    dismissButton.type = 'button'
    dismissButton.className = 'wpaic-loader-teaser-dismiss'
    dismissButton.setAttribute('aria-label', 'Dismiss message')
    dismissButton.title = 'Dismiss'
    dismissButton.innerHTML = DISMISS_ICON_SVG
    dismissButton.addEventListener('click', () => {
      sessionStorageSet(PROACTIVE_DISMISSED_KEY, 'true')
      removeTeaser()
    })

    teaser.appendChild(messageButton)
    teaser.appendChild(dismissButton)
    container.appendChild(teaser)
  }

  if (config?.proactiveEnabled && !sessionStorageGet(PROACTIVE_DISMISSED_KEY)) {
    // wp_localize_script delivers numbers as strings; an empty value would
    // coerce to a 0ms delay, so only accept a positive finite number.
    const configuredDelay = Number(config.proactiveDelay)
    const delaySeconds =
      Number.isFinite(configuredDelay) && configuredDelay > 0 ? configuredDelay : 10
    window.setTimeout(showTeaser, delaySeconds * 1000)
  }

  // Returning session: load the full app at idle so the stored conversation
  // is restored (and post-stream unread badges work) without an interaction.
  if (sessionStorageGet(CHAT_HISTORY_KEY) !== null) {
    const idleMount = (): void => {
      void loadWidgetModule().then((widget) => {
        // A click raced the idle load — its handler mounts with openOnMount.
        if (openRequested) return
        widget.mountWidget()
        cleanup()
      })
    }
    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(idleMount, { timeout: IDLE_LOAD_TIMEOUT_MS })
    } else {
      window.setTimeout(idleMount, IDLE_LOAD_TIMEOUT_MS)
    }
  }

  document.body.appendChild(container)
}

if (!import.meta.env.TEST) {
  initLoader()
}
