import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useChat as useVercelChat } from '@ai-sdk/react'
import { DefaultChatTransport, UIMessage } from 'ai'

import { Product } from '../components/ProductCard'
import { ComparisonData, ComparisonProduct } from '../components/ComparisonTable'

export interface Message {
  role: 'user' | 'assistant'
  content: string
  isError?: boolean
  id?: string
  products?: Product[]
  comparison?: ComparisonData
}

export interface ActiveTool {
  toolName: string
  state: 'input-streaming' | 'input-available' | 'executing'
}

function generateSessionId(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

function getOrCreateSessionId(): string {
  const key = 'wpaic_session_id'
  let sessionId = sessionStorage.getItem(key)
  if (!sessionId) {
    sessionId = generateSessionId()
    sessionStorage.setItem(key, sessionId)
  }
  return sessionId
}

const CHAT_HISTORY_KEY = 'wpaic_chat_history'

interface StoredMessage {
  id: string
  role: 'user' | 'assistant'
  parts: unknown[]
}

function isGreetingOnlyConversation(messages: Array<{ id?: string; role: string }>): boolean {
  return messages.length === 1 && messages[0].id === 'greeting' && messages[0].role === 'assistant'
}

function saveMessagesToStorage(messages: UIMessage[]): void {
  if (messages.length === 0) return
  // Skip saving if only greeting message
  if (messages.length === 1 && messages[0].id === 'greeting') return
  try {
    const toStore: StoredMessage[] = messages.map((msg) => ({
      id: msg.id,
      role: msg.role as 'user' | 'assistant',
      parts: msg.parts,
    }))
    sessionStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(toStore))
  } catch {
    // Storage full or unavailable, ignore
  }
}

function loadMessagesFromStorage(): StoredMessage[] | null {
  try {
    const stored = sessionStorage.getItem(CHAT_HISTORY_KEY)
    if (!stored) return null
    const parsed = JSON.parse(stored) as StoredMessage[]
    if (!Array.isArray(parsed) || parsed.length === 0) return null

    // Legacy clients could persist a greeting-only conversation, which
    // prevents updated greeting settings from appearing on the next load.
    if (isGreetingOnlyConversation(parsed)) {
      sessionStorage.removeItem(CHAT_HISTORY_KEY)
      return null
    }

    return parsed
  } catch {
    return null
  }
}

function clearStoredMessages(): void {
  sessionStorage.removeItem(CHAT_HISTORY_KEY)
}

// Initialize session ID once at module level to avoid ref access during render
const initialSessionId = typeof window !== 'undefined' ? getOrCreateSessionId() : ''

function extractTextContent(uiMessage: UIMessage): string {
  return uiMessage.parts
    .filter((part): part is { type: 'text'; text: string } => part.type === 'text')
    .map((part) => part.text)
    .join('')
}

interface DynamicToolPart {
  type: 'dynamic-tool'
  toolName: string
  toolCallId: string
  state: 'input-streaming' | 'input-available' | 'output-available'
  output?: unknown
}

function extractProductsFromMessage(uiMessage: UIMessage): Product[] {
  const products: Product[] = []

  for (const part of uiMessage.parts) {
    if (part.type === 'dynamic-tool') {
      const toolPart = part as DynamicToolPart
      if (
        toolPart.state === 'output-available' &&
        (toolPart.toolName === 'search_products' || toolPart.toolName === 'get_product_details')
      ) {
        const output = toolPart.output
        if (Array.isArray(output)) {
          for (const item of output) {
            if (isProduct(item)) {
              products.push(item)
            }
          }
        } else if (isProduct(output)) {
          products.push(output)
        }
      }
    }
  }

  return products
}

function isProduct(obj: unknown): obj is Product {
  return typeof obj === 'object' && obj !== null && 'id' in obj && 'name' in obj && 'url' in obj
}

function isComparisonProduct(obj: unknown): obj is ComparisonProduct {
  return typeof obj === 'object' && obj !== null && 'id' in obj && 'name' in obj && 'url' in obj
}

function extractComparisonFromMessage(uiMessage: UIMessage): ComparisonData | undefined {
  for (const part of uiMessage.parts) {
    if (part.type === 'dynamic-tool') {
      const toolPart = part as DynamicToolPart
      if (toolPart.state === 'output-available' && toolPart.toolName === 'compare_products') {
        const output = toolPart.output as { products?: unknown[]; attributes?: string[] }
        if (
          output &&
          typeof output === 'object' &&
          Array.isArray(output.products) &&
          Array.isArray(output.attributes)
        ) {
          const products = output.products.filter(isComparisonProduct) as ComparisonProduct[]
          if (products.length >= 2) {
            return {
              products,
              attributes: output.attributes,
            }
          }
        }
      }
    }
  }
  return undefined
}

function extractActiveTools(uiMessages: UIMessage[]): ActiveTool[] {
  const activeTools: ActiveTool[] = []

  for (const msg of uiMessages) {
    if (msg.role !== 'assistant') continue

    for (const part of msg.parts) {
      if (part.type === 'dynamic-tool') {
        const toolPart = part as DynamicToolPart
        // Only show tools that are still in progress (not output-available)
        if (toolPart.state === 'input-streaming' || toolPart.state === 'input-available') {
          activeTools.push({
            toolName: toolPart.toolName,
            state: toolPart.state === 'input-available' ? 'executing' : toolPart.state,
          })
        }
      }
    }
  }

  return activeTools
}

export function useChat() {
  const config = window.wpaicConfig
  const [sessionId, setSessionId] = useState(initialSessionId)
  const lastUserMessageRef = useRef<string | null>(null)
  const restoredSessionIdRef = useRef<string | null>(null)

  const getDefaultGreetingMessage = useCallback(
    (): string => config?.greeting || 'Hello! How can I help you today?',
    [config]
  )

  const getProactiveGreetingMessage = useCallback((): string => {
    if (config?.proactiveEnabled && config?.proactiveMessage) {
      return config.proactiveMessage
    }

    return getDefaultGreetingMessage()
  }, [config, getDefaultGreetingMessage])

  const transport = useMemo(() => {
    if (!config) return undefined
    return new DefaultChatTransport({
      api: `${config.apiUrl}/chat/stream`,
      headers: {
        'X-WP-Nonce': config.nonce,
      },
      body: {
        session_id: sessionId,
      },
    })
  }, [config, sessionId])

  const {
    messages: uiMessages,
    sendMessage: vercelSendMessage,
    status,
    stop,
    setMessages,
    error,
  } = useVercelChat({
    transport,
    id: sessionId,
  })

  const seedGreetingMessage = useCallback(
    (greeting: string) => {
      if (greeting) {
        setMessages([
          {
            id: 'greeting',
            role: 'assistant',
            parts: [{ type: 'text', text: greeting }],
          },
        ])
        return
      }

      setMessages([])
    },
    [setMessages]
  )

  // Restore the active session once per session ID so a new conversation
  // always gets its own greeting instead of reusing the previous chat state.
  useEffect(() => {
    if (!sessionId || restoredSessionIdRef.current === sessionId) return
    restoredSessionIdRef.current = sessionId

    const stored = loadMessagesFromStorage()
    if (stored && stored.length > 0) {
      setMessages(stored as UIMessage[])
      return
    }

    seedGreetingMessage(getDefaultGreetingMessage())
  }, [sessionId, setMessages, seedGreetingMessage, getDefaultGreetingMessage])

  // Save messages to storage when they change
  useEffect(() => {
    saveMessagesToStorage(uiMessages)
  }, [uiMessages])

  const messages: Message[] = useMemo(() => {
    return uiMessages.map((msg) => {
      const products = msg.role === 'assistant' ? extractProductsFromMessage(msg) : undefined
      const comparison = msg.role === 'assistant' ? extractComparisonFromMessage(msg) : undefined
      return {
        role: msg.role as 'user' | 'assistant',
        content: extractTextContent(msg),
        isError: false,
        id: msg.id,
        products: products && products.length > 0 ? products : undefined,
        comparison,
      }
    })
  }, [uiMessages])

  const messagesWithError = useMemo(() => {
    if (error && messages.length > 0) {
      const lastMsg = messages[messages.length - 1]
      if (lastMsg.role === 'assistant' && lastMsg.content === '') {
        return [
          ...messages.slice(0, -1),
          {
            ...lastMsg,
            content: 'Sorry, something went wrong. Please try again.',
            isError: true,
          },
        ]
      }
      // If there's an error but assistant has partial content, mark it as error
      if (lastMsg.role === 'assistant') {
        return [
          ...messages.slice(0, -1),
          {
            ...lastMsg,
            isError: true,
          },
        ]
      }
    }
    return messages
  }, [messages, error])

  const activeTools = useMemo(() => extractActiveTools(uiMessages), [uiMessages])

  const sendMessage = useCallback(
    (content: string) => {
      lastUserMessageRef.current = content
      vercelSendMessage({ text: content })
    },
    [vercelSendMessage]
  )

  const retry = useCallback(() => {
    if (!lastUserMessageRef.current) return
    // Remove the failed assistant message
    const filtered = uiMessages.filter((msg, i) => {
      if (i === uiMessages.length - 1 && msg.role === 'assistant') {
        return false
      }
      return true
    })
    setMessages(filtered)
    // Resend the last user message
    vercelSendMessage({ text: lastUserMessageRef.current })
  }, [uiMessages, setMessages, vercelSendMessage])

  const isLoading = status === 'streaming' || status === 'submitted'

  const stopGeneration = useCallback(() => {
    stop()
  }, [stop])

  const showProactiveGreeting = useCallback(() => {
    if (uiMessages.length > 0 && !isGreetingOnlyConversation(uiMessages)) {
      return
    }

    seedGreetingMessage(getProactiveGreetingMessage())
  }, [uiMessages, seedGreetingMessage, getProactiveGreetingMessage])

  const startNewConversation = useCallback(() => {
    stop()
    clearStoredMessages()
    lastUserMessageRef.current = null
    setMessages([])
    restoredSessionIdRef.current = null
    const newSessionId = generateSessionId()
    sessionStorage.setItem('wpaic_session_id', newSessionId)
    setSessionId(newSessionId)
  }, [setMessages, stop])

  return {
    messages: messagesWithError,
    sendMessage,
    isLoading,
    stopGeneration,
    showProactiveGreeting,
    startNewConversation,
    activeTools,
    retry,
  }
}
