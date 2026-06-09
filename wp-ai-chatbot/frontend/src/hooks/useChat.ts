import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useChat as useVercelChat } from '@ai-sdk/react'
import { DefaultChatTransport, UIMessage } from 'ai'

import { Product } from '../components/ProductCard'
import { ComparisonData, ComparisonProduct } from '../components/ComparisonTable'
import { CheckoutAction } from '../components/CheckoutButton'
import { isProductTool } from './tools'

export interface AddToCartIntent {
  toolCallId: string
  productId: number
  variationId?: number
  quantity: number
}

export interface Message {
  role: 'user' | 'assistant'
  content: string
  isError?: boolean
  id?: string
  products?: Product[]
  comparison?: ComparisonData
  checkoutAction?: CheckoutAction
  addToCartIntents?: AddToCartIntent[]
  createdAt?: number
}

export interface ActiveTool {
  toolName: string
  state: 'input-streaming' | 'input-available' | 'executing'
}

interface PendingUserMessage {
  id: string
  content: string
  createdAt: number
}

const MESSAGE_DEBOUNCE_MS = 3000

function generateSessionId(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

function generateClientMessageId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return generateSessionId()
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
const CHAT_TIMESTAMPS_KEY = 'wpaic_chat_timestamps'

interface StoredMessage {
  id: string
  role: 'user' | 'assistant'
  parts: unknown[]
}

function loadTimestampsFromStorage(): Record<string, number> {
  try {
    const stored = sessionStorage.getItem(CHAT_TIMESTAMPS_KEY)
    if (!stored) return {}
    const parsed = JSON.parse(stored) as Record<string, number>
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function saveTimestampsToStorage(timestamps: Record<string, number>): void {
  try {
    sessionStorage.setItem(CHAT_TIMESTAMPS_KEY, JSON.stringify(timestamps))
  } catch {
    // Ignore
  }
}

function clearStoredTimestamps(): void {
  sessionStorage.removeItem(CHAT_TIMESTAMPS_KEY)
}

function isGreetingOnlyConversation(messages: { id?: string; role: string }[]): boolean {
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

function createUserUIMessage(message: PendingUserMessage): UIMessage {
  return {
    id: message.id,
    role: 'user',
    parts: [{ type: 'text', text: message.content }],
  }
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
      if (toolPart.state === 'output-available' && isProductTool(toolPart.toolName)) {
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

function extractCheckoutActionFromMessage(uiMessage: UIMessage): CheckoutAction | undefined {
  for (const part of uiMessage.parts) {
    if (part.type !== 'dynamic-tool') continue
    const toolPart = part as DynamicToolPart
    if (toolPart.state !== 'output-available' || toolPart.toolName !== 'get_checkout_action') {
      continue
    }
    const output = toolPart.output as Partial<CheckoutAction> | undefined
    if (!output || typeof output !== 'object') continue
    const checkoutUrl = typeof output.checkout_url === 'string' ? output.checkout_url : ''
    const cartUrl = typeof output.cart_url === 'string' ? output.cart_url : ''
    if ((!checkoutUrl && !cartUrl) || !output.has_cart) continue
    return {
      checkout_url: checkoutUrl,
      cart_url: cartUrl,
      has_cart: true,
      item_count: typeof output.item_count === 'number' ? output.item_count : 0,
    }
  }
  return undefined
}

function extractAddToCartIntents(uiMessage: UIMessage): AddToCartIntent[] {
  const intents: AddToCartIntent[] = []

  for (const part of uiMessage.parts) {
    if (part.type !== 'dynamic-tool') continue
    const toolPart = part as DynamicToolPart
    if (toolPart.state !== 'output-available' || toolPart.toolName !== 'add_to_cart') continue

    const output = toolPart.output as
      | { success?: boolean; product_id?: number; variation_id?: number; quantity?: number }
      | undefined
    if (!output || output.success !== true || typeof output.product_id !== 'number') continue

    const intent: AddToCartIntent = {
      toolCallId: toolPart.toolCallId,
      productId: output.product_id,
      quantity: typeof output.quantity === 'number' && output.quantity > 0 ? output.quantity : 1,
    }
    if (typeof output.variation_id === 'number' && output.variation_id > 0) {
      intent.variationId = output.variation_id
    }
    intents.push(intent)
  }

  return intents
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
  const [pendingMessages, setPendingMessages] = useState<PendingUserMessage[]>([])
  const restoredSessionIdRef = useRef<string | null>(null)
  const pendingMessagesRef = useRef<PendingUserMessage[]>([])
  const pendingFlushRequestedRef = useRef(false)
  const uiMessagesRef = useRef<UIMessage[]>([])
  const statusRef = useRef<'submitted' | 'streaming' | 'ready' | 'error'>('ready')
  const errorRef = useRef<Error | undefined>(undefined)
  const debounceTimerRef = useRef<number | null>(null)
  const timestampsRef = useRef<Record<string, number>>({})
  const [timestampsVersion, setTimestampsVersion] = useState(0)

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
    const body: Record<string, unknown> = {
      session_id: sessionId,
    }
    if (config.pageContext) {
      body.page_context = config.pageContext
    }
    return new DefaultChatTransport({
      api: `${config.apiUrl}/chat/stream`,
      headers: {
        'X-WP-Nonce': config.nonce,
      },
      body,
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
    onFinish: ({ isError }) => {
      if (!pendingFlushRequestedRef.current) return

      window.setTimeout(() => {
        flushPendingMessages(isError)
      }, 0)
    },
  })

  const clearPendingSubmissionTimer = useCallback(() => {
    if (debounceTimerRef.current !== null) {
      window.clearTimeout(debounceTimerRef.current)
      debounceTimerRef.current = null
    }
  }, [])

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

    timestampsRef.current = loadTimestampsFromStorage()
    setTimestampsVersion((v) => v + 1)

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

  // Track timestamps for new message ids.
  useEffect(() => {
    const timestamps = timestampsRef.current
    let changed = false
    const now = Date.now()
    for (const msg of uiMessages) {
      if (!msg.id || msg.id === 'greeting') continue
      if (timestamps[msg.id] === undefined) {
        timestamps[msg.id] = now
        changed = true
      }
    }
    if (changed) {
      saveTimestampsToStorage(timestamps)
      setTimestampsVersion((v) => v + 1)
    }
  }, [uiMessages])

  useEffect(() => {
    pendingMessagesRef.current = pendingMessages
  }, [pendingMessages])

  useEffect(() => {
    uiMessagesRef.current = uiMessages
  }, [uiMessages])

  useEffect(() => {
    statusRef.current = status
  }, [status])

  useEffect(() => {
    errorRef.current = error
  }, [error])

  useEffect(() => {
    return () => {
      clearPendingSubmissionTimer()
    }
  }, [clearPendingSubmissionTimer])

  const messages: Message[] = useMemo(() => {
    const timestamps = timestampsRef.current
    return uiMessages.map((msg) => {
      const products = msg.role === 'assistant' ? extractProductsFromMessage(msg) : undefined
      const comparison = msg.role === 'assistant' ? extractComparisonFromMessage(msg) : undefined
      const checkoutAction = msg.role === 'assistant' ? extractCheckoutActionFromMessage(msg) : undefined
      const addToCartIntents = msg.role === 'assistant' ? extractAddToCartIntents(msg) : undefined
      return {
        role: msg.role as 'user' | 'assistant',
        content: extractTextContent(msg),
        isError: false,
        id: msg.id,
        products: products && products.length > 0 ? products : undefined,
        comparison,
        checkoutAction,
        addToCartIntents: addToCartIntents && addToCartIntents.length > 0 ? addToCartIntents : undefined,
        createdAt: msg.id ? timestamps[msg.id] : undefined,
      }
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uiMessages, timestampsVersion])

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

  const optimisticMessages = useMemo<Message[]>(
    () =>
      pendingMessages.map((message) => ({
        id: message.id,
        role: 'user',
        content: message.content,
        isError: false,
        createdAt: message.createdAt,
      })),
    [pendingMessages]
  )

  const flushPendingMessages = useCallback((dropLastAssistant = false) => {
    if (statusRef.current === 'streaming' || statusRef.current === 'submitted') {
      return
    }

    const queuedMessages = pendingMessagesRef.current
    if (queuedMessages.length === 0) {
      pendingFlushRequestedRef.current = false
      return
    }

    const lastUiMessage = uiMessagesRef.current[uiMessagesRef.current.length - 1]
    const baseMessages =
      (dropLastAssistant || errorRef.current) && lastUiMessage?.role === 'assistant'
        ? uiMessagesRef.current.slice(0, -1)
        : uiMessagesRef.current

    setMessages([
      ...baseMessages,
      ...queuedMessages.map((message) => createUserUIMessage(message)),
    ])
    setPendingMessages([])
    pendingFlushRequestedRef.current = false
    void vercelSendMessage()
  }, [setMessages, vercelSendMessage])

  const schedulePendingSubmission = useCallback(() => {
    clearPendingSubmissionTimer()
    pendingFlushRequestedRef.current = false
    debounceTimerRef.current = window.setTimeout(() => {
      pendingFlushRequestedRef.current = true
      flushPendingMessages()
    }, MESSAGE_DEBOUNCE_MS)
  }, [clearPendingSubmissionTimer, flushPendingMessages])

  const sendMessage = useCallback(
    (content: string) => {
      const trimmedContent = content.trim()
      if (!trimmedContent) return

      setPendingMessages((currentMessages) => {
        const id = generateClientMessageId()
        const createdAt = Date.now()
        timestampsRef.current[id] = createdAt
        saveTimestampsToStorage(timestampsRef.current)
        const nextMessages = [
          ...currentMessages,
          {
            id,
            content: trimmedContent,
            createdAt,
          },
        ]
        pendingMessagesRef.current = nextMessages
        return nextMessages
      })
      setTimestampsVersion((v) => v + 1)
      schedulePendingSubmission()
    },
    [schedulePendingSubmission]
  )

  const retry = useCallback(() => {
    const filtered = uiMessages.filter((msg, i) => !(i === uiMessages.length - 1 && msg.role === 'assistant'))
    if (!filtered.some((message) => message.role === 'user')) return

    setMessages(filtered)
    void vercelSendMessage()
  }, [uiMessages, setMessages, vercelSendMessage])

  const isRequestInFlight = status === 'streaming' || status === 'submitted'
  const isLoading = isRequestInFlight || pendingMessages.length > 0

  const showProactiveGreeting = useCallback(() => {
    if (
      pendingMessagesRef.current.length > 0 ||
      (uiMessages.length > 0 && !isGreetingOnlyConversation(uiMessages))
    ) {
      return
    }

    seedGreetingMessage(getProactiveGreetingMessage())
  }, [uiMessages, seedGreetingMessage, getProactiveGreetingMessage])

  const startNewConversation = useCallback(() => {
    clearPendingSubmissionTimer()
    stop()
    clearStoredMessages()
    clearStoredTimestamps()
    timestampsRef.current = {}
    setTimestampsVersion((v) => v + 1)
    setPendingMessages([])
    pendingFlushRequestedRef.current = false
    setMessages([])
    restoredSessionIdRef.current = null
    const newSessionId = generateSessionId()
    sessionStorage.setItem('wpaic_session_id', newSessionId)
    setSessionId(newSessionId)
  }, [clearPendingSubmissionTimer, setMessages, stop])

  return {
    messages: [...messagesWithError, ...optimisticMessages],
    sendMessage,
    isLoading,
    showProactiveGreeting,
    startNewConversation,
    activeTools,
    retry,
  }
}
