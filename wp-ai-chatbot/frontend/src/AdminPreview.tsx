import { useState, useEffect, useCallback } from 'react'
import ChatWidgetUI from './components/ChatWidgetUI'
import ProactiveTeaser from './components/ProactiveTeaser'
import type { Message } from './hooks/useChat'
import type { Product } from './components/ProductCard'

declare global {
  interface Window {
    wpaicAdminPreview?: {
      greeting: string
      chatbotName: string
      chatbotLogo: string
      chatbotRole: string
      themeColor: string
      proactiveMessage?: string
    }
  }
}

const PREVIEW_PRODUCTS: Product[] = [
  {
    id: 1,
    name: 'Fjallraven - Foldsack No. 1 Backpack',
    url: '#',
    price: '109.95',
    image: 'https://fakestoreapi.com/img/81fPKd-2AYL._AC_SL1500_t.png',
    categories: ["Men's clothing"],
  },
  {
    id: 2,
    name: 'Mens Casual Premium Slim Fit T-Shirts',
    url: '#',
    price: '22.30',
    regular_price: '28.00',
    sale_price: '22.30',
    image: 'https://fakestoreapi.com/img/71-3HjGNDUL._AC_SY879._SX._UX._SY._UY_t.png',
    categories: ["Men's clothing"],
  },
  {
    id: 3,
    name: 'Mens Cotton Jacket',
    url: '#',
    price: '55.99',
    image: 'https://fakestoreapi.com/img/71li-ujtlUL._AC_UX679_t.png',
    categories: ["Men's clothing"],
  },
  {
    id: 4,
    name: 'Mens Casual Slim Fit',
    url: '#',
    price: '15.99',
    image: 'https://fakestoreapi.com/img/71YXzeOuslL._AC_UY879_t.png',
    categories: ["Men's clothing"],
  },
  {
    id: 5,
    name: "John Hardy Women's Legends Naga Gold & Silver Dragon Station Chain Bracelet",
    url: '#',
    price: '695.00',
    image: 'https://fakestoreapi.com/img/71pWzhdJNwL._AC_UL640_QL65_ML3_t.png',
    categories: ['Jewelry'],
  },
]

function buildPreviewMessages(greeting: string): Message[] {
  const now = Date.now()
  return [
    {
      id: 'greeting',
      role: 'assistant',
      content: greeting || 'Hello! How can I help you today?',
      createdAt: now - 60000,
    },
    {
      id: 'user-1',
      role: 'user',
      content: "I'm looking for something new to wear, what do you have?",
      createdAt: now - 45000,
    },
    {
      id: 'assistant-1',
      role: 'assistant',
      content: "Here are some popular picks from our collection:",
      products: PREVIEW_PRODUCTS,
      createdAt: now - 30000,
    },
  ]
}

function hexToHoverColor(hex: string): string {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  const darken = (v: number) => Math.max(0, Math.floor(v * 0.8))
  return `#${darken(r).toString(16).padStart(2, '0')}${darken(g).toString(16).padStart(2, '0')}${darken(b).toString(16).padStart(2, '0')}`
}

function isValidHex(color: string): boolean {
  return /^#[0-9a-fA-F]{6}$/.test(color)
}

interface AdminPreviewProps {
  variant?: 'widget' | 'teaser'
}

export default function AdminPreview({ variant = 'widget' }: AdminPreviewProps) {
  const config = window.wpaicAdminPreview
  const [chatbotName, setChatbotName] = useState(config?.chatbotName ?? '')
  const [chatbotLogo, setChatbotLogo] = useState(config?.chatbotLogo ?? '')
  const [chatbotRole, setChatbotRole] = useState(config?.chatbotRole ?? '')
  const [themeColor, setThemeColor] = useState(config?.themeColor ?? '#2545B8')
  const [proactiveMessage, setProactiveMessage] = useState(config?.proactiveMessage ?? '')
  const [input, setInput] = useState('')

  const messages = buildPreviewMessages(config?.greeting ?? '')

  const applyThemeColor = useCallback((color: string) => {
    if (!isValidHex(color)) return
    setThemeColor(color)
    const container = document.getElementById('wpaic-admin-preview')
    if (container) {
      container.style.setProperty('--wpaic-primary', color)
      container.style.setProperty('--wpaic-primary-hover', hexToHoverColor(color))
    }
  }, [])

  useEffect(() => {
    applyThemeColor(themeColor)
  }, [])

  useEffect(() => {
    const nameInput = document.querySelector<HTMLInputElement>('input[name="wpaic_settings[chatbot_name]"]')
    const roleInput = document.querySelector<HTMLInputElement>('input[name="wpaic_settings[chatbot_role]"]')
    const logoInput = document.querySelector<HTMLInputElement>('#wpaic_chatbot_logo')
    const colorInput = document.querySelector<HTMLInputElement>('#wpaic_theme_color_input')
    const colorDots = document.querySelectorAll<HTMLElement>('.wpaic-color-dot')

    const handlers: Array<[EventTarget, string, EventListener]> = []

    const listen = (target: EventTarget | null, event: string, handler: EventListener) => {
      if (!target) return
      target.addEventListener(event, handler)
      handlers.push([target, event, handler])
    }

    listen(nameInput, 'input', () => setChatbotName(nameInput!.value))
    listen(roleInput, 'input', () => setChatbotRole(roleInput!.value))
    listen(logoInput, 'input', () => setChatbotLogo(logoInput!.value))
    listen(colorInput, 'input', () => applyThemeColor(colorInput!.value))

    colorDots.forEach((dot) => {
      listen(dot, 'click', () => {
        const color = dot.dataset.color
        if (color) applyThemeColor(color)
      })
    })

    return () => {
      handlers.forEach(([target, event, handler]) => target.removeEventListener(event, handler))
    }
  }, [applyThemeColor])

  // Engagement tab: live-update the teaser preview as the proactive message is typed.
  useEffect(() => {
    if (variant !== 'teaser') return
    const messageInput = document.querySelector<HTMLTextAreaElement>('#wpaic_proactive_message')
    if (!messageInput) return

    const handleInput = () => setProactiveMessage(messageInput.value)
    messageInput.addEventListener('input', handleInput)
    return () => messageInput.removeEventListener('input', handleInput)
  }, [variant])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
  }

  const handleClick = (e: React.MouseEvent) => {
    const target = e.target as HTMLElement
    if (target.closest('button')) {
      e.preventDefault()
    }
  }

  if (variant === 'teaser') {
    const teaserMessage =
      proactiveMessage.trim() || config?.greeting || 'Hello! How can I help you today?'
    return (
      <div className="relative h-[240px] w-full [&_button]:pointer-events-none">
        <ProactiveTeaser
          message={teaserMessage}
          onOpen={() => undefined}
          onDismiss={() => undefined}
          className="absolute bottom-[88px] right-5 z-auto"
        />
        <div
          className="absolute bottom-5 right-5 w-[60px] h-[60px] rounded-full bg-[var(--wpaic-primary)] text-white shadow-lg flex items-center justify-center"
          aria-hidden
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
        </div>
      </div>
    )
  }

  return (
    <div className="h-[680px] w-[428px]" style={{ '--wpaic-primary': themeColor, '--wpaic-primary-hover': hexToHoverColor(themeColor) } as React.CSSProperties}>
      <div className="h-full [&_a]:pointer-events-none [&_button]:pointer-events-none [&_form]:pointer-events-auto [&_textarea]:pointer-events-auto [&_[data-slot=carousel-content]]:pointer-events-auto [&_[data-slot=carousel-previous]]:pointer-events-auto [&_[data-slot=carousel-next]]:pointer-events-auto" onClick={handleClick}>
        <ChatWidgetUI
          messages={messages}
          chatbotName={chatbotName}
          chatbotLogo={chatbotLogo}
          chatbotRole={chatbotRole}
          input={input}
          onInputChange={setInput}
          onSubmit={handleSubmit}
        />
      </div>
    </div>
  )
}
