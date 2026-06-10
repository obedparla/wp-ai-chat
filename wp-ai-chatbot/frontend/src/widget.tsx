import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import './styles.css'

export interface MountWidgetOptions {
  openOnMount?: boolean
  viaProactiveTeaser?: boolean
}

let mounted = false

// Mounts the full React widget into the chatbot root. Called by the tiny
// storefront loader (dynamic import) and by main.tsx for the dev entry.
// Safe to call more than once — only the first call mounts.
export function mountWidget(options: MountWidgetOptions = {}): void {
  if (mounted) return

  const root = document.getElementById('wpaic-chatbot-root')
  if (!root) return

  mounted = true
  createRoot(root).render(
    <StrictMode>
      <App openOnMount={options.openOnMount} viaProactiveTeaser={options.viaProactiveTeaser} />
    </StrictMode>
  )
}
