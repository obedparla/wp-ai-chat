import { useState, useEffect, useRef } from 'react'
import ChatWidget from './components/ChatWidget'
import ChatButton from './components/ChatButton'
import ProactiveTeaser from './components/ProactiveTeaser'
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
      chatbotRole?: string
      currency?: {
        symbol?: string
        decimals?: number
        decimalSeparator?: string
        thousandSeparator?: string
        position?: 'left' | 'right' | 'left_space' | 'right_space'
      }
      pageContext?: {
        page_type: 'product' | 'cart' | 'checkout' | 'shop' | 'product_category' | 'product_tag' | 'singular' | 'other'
        title: string
        url: string
        post_id?: number
        post_type?: string
        product_id?: number
        term_id?: number
        taxonomy?: string
        term_slug?: string
        term_name?: string
      }
      conversationStarters?: string[]
    }
  }
}

const PROACTIVE_DISMISSED_KEY = 'wpaic_proactive_dismissed'

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
  const [autoFocusInput, setAutoFocusInput] = useState(false)
  const [showTeaser, setShowTeaser] = useState(false)
  const [hasUnread, setHasUnread] = useState(false)
  const chat = useChat()
  const { showProactiveGreeting, isLoading } = chat
  const previousIsLoadingRef = useRef(isLoading)
  const themeColor = window.wpaicConfig?.themeColor || '#2545B8'
  const config = window.wpaicConfig

  useEffect(() => {
    const root = document.documentElement
    root.style.setProperty('--wpaic-primary', themeColor)
    root.style.setProperty('--wpaic-primary-hover', hexToHoverColor(themeColor))
  }, [themeColor])

  // A response finished streaming while the widget was closed — surface an
  // unread badge on the launcher until the visitor opens the chat.
  useEffect(() => {
    if (previousIsLoadingRef.current && !isLoading && !isOpen) {
      setHasUnread(true)
    }
    previousIsLoadingRef.current = isLoading
  }, [isLoading, isOpen])

  useEffect(() => {
    if (!config?.proactiveEnabled || hasInteracted || isOpen) return

    const dismissed = sessionStorage.getItem(PROACTIVE_DISMISSED_KEY)
    if (dismissed) return

    // wp_localize_script delivers numbers as strings; an empty value would
    // coerce to a 0ms delay, so only accept a positive finite number.
    const configuredDelay = Number(config.proactiveDelay)
    const delaySeconds = Number.isFinite(configuredDelay) && configuredDelay > 0 ? configuredDelay : 10
    const timer = setTimeout(() => {
      setShowTeaser(true)
    }, delaySeconds * 1000)

    return () => clearTimeout(timer)
  }, [config?.proactiveEnabled, config?.proactiveDelay, hasInteracted, isOpen])

  const dismissProactiveForSession = () => {
    sessionStorage.setItem(PROACTIVE_DISMISSED_KEY, 'true')
    setShowTeaser(false)
  }

  const handleTeaserOpen = () => {
    dismissProactiveForSession()
    setHasInteracted(true)
    setHasUnread(false)
    showProactiveGreeting()
    setAutoFocusInput(true)
    setIsOpen(true)
  }

  const handleClose = () => {
    setHasInteracted(true)
    setAutoFocusInput(false)
    setIsOpen(false)
  }

  const handleToggle = () => {
    setHasInteracted(true)
    const nextIsOpen = !isOpen
    if (nextIsOpen) {
      // Opening the chat consumes the teaser for this session.
      dismissProactiveForSession()
      setHasUnread(false)
    }
    setAutoFocusInput(nextIsOpen)
    setIsOpen(nextIsOpen)
  }

  const teaserMessage =
    config?.proactiveMessage || config?.greeting || 'Hello! How can I help you today?'

  return (
    <>
      {isOpen && (
        <ChatWidget
          onClose={handleClose}
          chat={chat}
          chatbotName={config?.chatbotName}
          chatbotLogo={config?.chatbotLogo}
          chatbotRole={config?.chatbotRole}
          conversationStarters={config?.conversationStarters ?? []}
          autoFocusInput={autoFocusInput}
        />
      )}
      {!isOpen && showTeaser && (
        <ProactiveTeaser
          message={teaserMessage}
          onOpen={handleTeaserOpen}
          onDismiss={dismissProactiveForSession}
        />
      )}
      {!isOpen && <ChatButton onClick={handleToggle} isOpen={isOpen} hasUnread={hasUnread} />}
    </>
  )
}
