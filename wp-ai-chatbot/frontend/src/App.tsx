import { useState, useEffect } from 'react'
import ChatWidget from './components/ChatWidget'
import ChatButton from './components/ChatButton'
import { useChat } from './hooks/useChat'

declare global {
  interface Window {
    wpaicConfig?: {
      apiUrl: string
      nonce: string
      greeting: string
      themeColor?: string
      wcAjaxUrl?: string
      cartUrl?: string
      proactiveEnabled?: boolean
      proactiveDelay?: number
      proactiveMessage?: string
      chatbotName?: string
      chatbotLogo?: string
    }
  }
}

const PROACTIVE_SHOWN_KEY = 'wpaic_proactive_shown'

function hexToHoverColor(hex: string): string {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  const darken = (v: number) => Math.max(0, Math.floor(v * 0.8))
  return `#${darken(r).toString(16).padStart(2, '0')}${darken(g).toString(16).padStart(2, '0')}${darken(b).toString(16).padStart(2, '0')}`
}

export default function App() {
  const [isOpen, setIsOpen] = useState(false)
  const [hasInteracted, setHasInteracted] = useState(false)
  const chat = useChat()
  const themeColor = window.wpaicConfig?.themeColor || '#0073aa'
  const config = window.wpaicConfig

  useEffect(() => {
    const root = document.documentElement
    root.style.setProperty('--wpaic-primary', themeColor)
    root.style.setProperty('--wpaic-primary-hover', hexToHoverColor(themeColor))
  }, [themeColor])

  useEffect(() => {
    if (!config?.proactiveEnabled || hasInteracted) return

    const alreadyShown = sessionStorage.getItem(PROACTIVE_SHOWN_KEY)
    if (alreadyShown) return

    const delay = (config.proactiveDelay ?? 10) * 1000
    const timer = setTimeout(() => {
      if (!hasInteracted) {
        setIsOpen(true)
        sessionStorage.setItem(PROACTIVE_SHOWN_KEY, 'true')
      }
    }, delay)

    return () => clearTimeout(timer)
  }, [config?.proactiveEnabled, config?.proactiveDelay, hasInteracted])

  const handleClose = () => {
    setHasInteracted(true)
    setIsOpen(false)
  }

  const handleToggle = () => {
    setHasInteracted(true)
    setIsOpen(!isOpen)
  }

  return (
    <>
      {isOpen && (
        <ChatWidget
          onClose={handleClose}
          chat={chat}
          chatbotName={config?.chatbotName}
          chatbotLogo={config?.chatbotLogo}
        />
      )}
      <ChatButton onClick={handleToggle} isOpen={isOpen} />
    </>
  )
}
