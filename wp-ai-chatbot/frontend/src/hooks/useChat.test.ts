import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import { useChat } from './useChat'
import { useAddToCart, clearStoredAddToCartStatuses } from './useAddToCart'

// Mock the Vercel AI SDK
vi.mock('@ai-sdk/react', () => ({
  useChat: vi.fn(),
}))

vi.mock('ai', () => ({
  DefaultChatTransport: class MockDefaultChatTransport {
    api: string | undefined
    headers: Record<string, string> | undefined
    body: Record<string, unknown> | undefined

    constructor(options?: {
      api?: string
      headers?: Record<string, string>
      body?: Record<string, unknown>
    }) {
      this.api = options?.api
      this.headers = options?.headers
      this.body = options?.body
    }
  },
}))

import { useChat as useVercelChat } from '@ai-sdk/react'

const mockUseVercelChat = useVercelChat as ReturnType<typeof vi.fn>

describe('useChat', () => {
  let mockSetMessages: ReturnType<typeof vi.fn>
  let mockSendMessage: ReturnType<typeof vi.fn>
  let mockStop: ReturnType<typeof vi.fn>

  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
    clearStoredAddToCartStatuses()
    mockSetMessages = vi.fn()
    mockSendMessage = vi.fn()
    mockStop = vi.fn()

    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello! How can I help?',
    }

    mockUseVercelChat.mockReturnValue({
      messages: [],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })
  })

  afterEach(() => {
    vi.clearAllMocks()
    vi.useRealTimers()
    sessionStorage.clear()
  })

  it('initializes with greeting message via setMessages', async () => {
    renderHook(() => useChat())

    await waitFor(() => {
      expect(mockSetMessages).toHaveBeenCalledWith([
        {
          id: 'greeting',
          role: 'assistant',
          parts: [{ type: 'text', text: 'Hello! How can I help?' }],
        },
      ])
    })
  })

  it('starts with isLoading false when status is ready', () => {
    const { result } = renderHook(() => useChat())
    expect(result.current.isLoading).toBe(false)
  })

  it('isLoading is true when status is streaming', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [],
      sendMessage: mockSendMessage,
      status: 'streaming',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())
    expect(result.current.isLoading).toBe(true)
  })

  it('isLoading is true when status is submitted', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [],
      sendMessage: mockSendMessage,
      status: 'submitted',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())
    expect(result.current.isLoading).toBe(true)
  })

  it('queues a message locally and submits it after the debounce window', async () => {
    vi.useFakeTimers()
    const { result } = renderHook(() => useChat())
    mockSetMessages.mockClear()

    act(() => {
      result.current.sendMessage('Hello')
    })

    expect(result.current.messages).toEqual([
      expect.objectContaining({
        role: 'user',
        content: 'Hello',
        isError: false,
      }),
    ])
    expect(result.current.isLoading).toBe(true)
    expect(mockSendMessage).not.toHaveBeenCalled()

    await act(async () => {
      vi.advanceTimersByTime(6000)
    })

    expect(mockSetMessages).toHaveBeenLastCalledWith([
      {
        id: expect.any(String),
        role: 'user',
        parts: [{ type: 'text', text: 'Hello' }],
      },
    ])
    expect(mockSendMessage).toHaveBeenCalledTimes(1)
    expect(mockSendMessage.mock.calls[0]).toEqual([])
  })

  it('includes page_context in the transport body', () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello! How can I help?',
      pageContext: {
        page_type: 'product',
        title: 'Blue Widget',
        url: 'http://example.com/product/blue-widget/',
        post_id: 42,
        post_type: 'product',
        product_id: 42,
      },
    }

    renderHook(() => useChat())

    const options = mockUseVercelChat.mock.calls[0]?.[0] as {
      transport?: { body?: Record<string, unknown> }
    }
    expect(options.transport?.body?.page_context).toEqual(window.wpaicConfig.pageContext)
  })

  it('converts UIMessages to Message format with text content', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'Hello' }],
        },
        {
          id: '2',
          role: 'assistant',
          parts: [{ type: 'text', text: 'Hi there!' }],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages).toHaveLength(2)
    expect(result.current.messages[0]).toMatchObject({
      role: 'user',
      content: 'Hello',
      isError: false,
      id: '1',
    })
    expect(result.current.messages[1]).toMatchObject({
      role: 'assistant',
      content: 'Hi there!',
      isError: false,
      id: '2',
    })
  })

  it('concatenates multiple text parts in a message', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            { type: 'text', text: 'Hello ' },
            { type: 'text', text: 'world!' },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].content).toBe('Hello world!')
  })

  it('handles error state and marks last empty message as error', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'Hi' }],
        },
        {
          id: '2',
          role: 'assistant',
          parts: [],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'error',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: new Error('API Error'),
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages).toHaveLength(2)
    expect(result.current.messages[1]).toMatchObject({
      role: 'assistant',
      content: 'Sorry, something went wrong. Please try again.',
      isError: true,
      id: '2',
    })
  })

  it('marks assistant message with partial content as error when error occurs', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'Hi' }],
        },
        {
          id: '2',
          role: 'assistant',
          parts: [{ type: 'text', text: 'Partial response...' }],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'error',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: new Error('API Error'),
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages).toHaveLength(2)
    expect(result.current.messages[1].content).toBe('Partial response...')
    expect(result.current.messages[1].isError).toBe(true)
  })

  it('appends a synthetic error message when the request fails before any reply', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'Hi' }],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'error',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: new Error('Network Error'),
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages).toHaveLength(2)
    expect(result.current.messages[1]).toMatchObject({
      role: 'assistant',
      content: 'Sorry, something went wrong. Please try again.',
      isError: true,
      id: 'wpaic-error-retry',
    })
  })

  it('showProactiveGreeting swaps in the proactive message for an idle chat', () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello! How can I help?',
      proactiveEnabled: true,
      proactiveMessage: 'Need help?',
    }

    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.showProactiveGreeting()
    })

    expect(mockSetMessages).toHaveBeenCalledWith([
      {
        id: 'greeting',
        role: 'assistant',
        parts: [{ type: 'text', text: 'Need help?' }],
      },
    ])
  })

  it('showProactiveGreeting does not replace an active conversation', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: 'greeting',
          role: 'assistant',
          parts: [{ type: 'text', text: 'Hello!' }],
        },
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'I need help finding a product' }],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello! How can I help?',
      proactiveEnabled: true,
      proactiveMessage: 'Need help?',
    }

    const { result } = renderHook(() => useChat())
    mockSetMessages.mockClear()

    act(() => {
      result.current.showProactiveGreeting()
    })

    expect(mockSetMessages).not.toHaveBeenCalled()
  })

  it('startNewConversation clears state, stops streaming, and reseeds the greeting', () => {
    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.startNewConversation()
    })

    expect(mockStop).toHaveBeenCalled()
    expect(mockSetMessages).toHaveBeenCalledWith([])
    expect(mockSetMessages).toHaveBeenCalledWith([
      {
        id: 'greeting',
        role: 'assistant',
        parts: [{ type: 'text', text: 'Hello! How can I help?' }],
      },
    ])
  })

  it('startNewConversation with no greeting keeps the session empty', () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: '',
    }

    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.startNewConversation()
    })

    expect(mockStop).toHaveBeenCalled()
    expect(mockSetMessages).toHaveBeenCalledWith([])
  })

  it('shows default greeting when config is undefined', () => {
    window.wpaicConfig = undefined

    renderHook(() => useChat())

    expect(mockSetMessages).toHaveBeenCalledWith([
      {
        id: 'greeting',
        role: 'assistant',
        parts: [{ type: 'text', text: 'Hello! How can I help you today?' }],
      },
    ])
  })

  it('filters non-text parts from messages', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            { type: 'text', text: 'Here is some info' },
            { type: 'tool-call', toolCallId: '123', toolName: 'search' },
            { type: 'text', text: ' and more text' },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].content).toBe('Here is some info and more text')
  })

  it('returns empty activeTools when no tool calls in progress', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [{ type: 'text', text: 'Hello' }],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.activeTools).toEqual([])
  })

  it('extracts active tool when state is input-streaming', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'search_products',
              toolCallId: '123',
              state: 'input-streaming',
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'streaming',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.activeTools).toHaveLength(1)
    expect(result.current.activeTools[0]).toEqual({
      toolName: 'search_products',
      state: 'input-streaming',
    })
  })

  it('extracts active tool with executing state when input-available', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'get_product_details',
              toolCallId: '456',
              state: 'input-available',
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'streaming',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.activeTools).toHaveLength(1)
    expect(result.current.activeTools[0]).toEqual({
      toolName: 'get_product_details',
      state: 'executing',
    })
  })

  it('excludes tools with output-available state', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'search_products',
              toolCallId: '123',
              state: 'output-available',
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'streaming',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.activeTools).toEqual([])
  })

  it('ignores tool parts in user messages', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'search_products',
              toolCallId: '123',
              state: 'input-streaming',
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.activeTools).toEqual([])
  })

  it('extracts add_to_cart intent from a successful tool output', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'add_to_cart',
              toolCallId: 'tc-1',
              state: 'output-available',
              output: {
                success: true,
                action: 'add_to_cart',
                product_id: 55,
                variation_id: 99,
                quantity: 2,
                name: 'Cool Shirt',
              },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].addToCartIntents).toEqual([
      { toolCallId: 'tc-1', productId: 55, variationId: 99, quantity: 2 },
    ])
  })

  it('ignores add_to_cart tool output that did not succeed', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'add_to_cart',
              toolCallId: 'tc-2',
              state: 'output-available',
              output: {
                success: false,
                needs_variation: true,
                product_id: 55,
              },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].addToCartIntents).toBeUndefined()
  })

  it('extracts a clear_cart intent from a successful tool output', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'clear_cart',
              toolCallId: 'cc-1',
              state: 'output-available',
              output: {
                success: true,
                action: 'clear_cart',
                clear_all: false,
                items: [{ product_id: 7, name: 'Water', remove_quantity: 2, remove_all: false }],
              },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].clearCartIntents).toEqual([
      {
        toolCallId: 'cc-1',
        clearAll: false,
        items: [{ productId: 7, name: 'Water', removeQuantity: 2, removeAll: false }],
      },
    ])
  })

  it('ignores clear_cart tool output that did not succeed', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'clear_cart',
              toolCallId: 'cc-2',
              state: 'output-available',
              output: { success: false, reason: 'cart_empty' },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].clearCartIntents).toBeUndefined()
  })

  it('retry removes the failed assistant message and resubmits the conversation', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'Hello' }],
        },
        {
          id: '2',
          role: 'assistant',
          parts: [],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'error',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: new Error('API Error'),
    })

    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.retry()
    })

    expect(mockSetMessages).toHaveBeenCalledWith([
      {
        id: '1',
        role: 'user',
        parts: [{ type: 'text', text: 'Hello' }],
      },
    ])
    expect(mockSendMessage).toHaveBeenCalledTimes(1)
    expect(mockSendMessage.mock.calls[0]).toEqual([])
  })

  it('retry does nothing when no previous user message exists', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.retry()
    })

    // sendMessage should not have been called since no prior message
    expect(mockSendMessage).not.toHaveBeenCalled()
  })

  it('batches multiple queued user messages into one submission', async () => {
    vi.useFakeTimers()
    mockUseVercelChat.mockReturnValue({
      messages: [],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())
    mockSetMessages.mockClear()

    act(() => {
      result.current.sendMessage('First message')
    })

    act(() => {
      result.current.sendMessage('Second message')
    })

    expect(result.current.messages).toEqual([
      expect.objectContaining({
        role: 'user',
        content: 'First message',
      }),
      expect.objectContaining({
        role: 'user',
        content: 'Second message',
      }),
    ])
    expect(mockSendMessage).not.toHaveBeenCalled()

    await act(async () => {
      vi.advanceTimersByTime(6000)
    })

    expect(mockSetMessages).toHaveBeenLastCalledWith([
      {
        id: expect.any(String),
        role: 'user',
        parts: [{ type: 'text', text: 'First message' }],
      },
      {
        id: expect.any(String),
        role: 'user',
        parts: [{ type: 'text', text: 'Second message' }],
      },
    ])
    expect(mockSendMessage).toHaveBeenCalledTimes(1)
    expect(mockSendMessage.mock.calls[0]).toEqual([])
  })

  it('extracts products from search_products tool output', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            { type: 'text', text: 'Here are some products:' },
            {
              type: 'dynamic-tool',
              toolName: 'search_products',
              toolCallId: '123',
              state: 'output-available',
              output: [
                { id: 1, name: 'Product A', url: 'https://example.com/1', price: '10' },
                { id: 2, name: 'Product B', url: 'https://example.com/2', price: '20' },
              ],
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].products).toHaveLength(2)
    expect(result.current.messages[0].products?.[0].name).toBe('Product A')
    expect(result.current.messages[0].products?.[1].name).toBe('Product B')
  })

  it('extracts single product from get_product_details tool output', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            { type: 'text', text: 'Here is the product:' },
            {
              type: 'dynamic-tool',
              toolName: 'get_product_details',
              toolCallId: '456',
              state: 'output-available',
              output: { id: 1, name: 'Single Product', url: 'https://example.com/1', price: '50' },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].products).toHaveLength(1)
    expect(result.current.messages[0].products?.[0].name).toBe('Single Product')
  })

  it('renders get_product_details products before search results so the cap never cuts them', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'search_products',
              toolCallId: '123',
              state: 'output-available',
              output: Array.from({ length: 6 }, (_, i) => ({
                id: i + 1,
                name: `Product ${i + 1}`,
                url: `https://example.com/${i + 1}`,
                price: '10',
              })),
            },
            {
              type: 'dynamic-tool',
              toolName: 'get_product_details',
              toolCallId: '456',
              state: 'output-available',
              output: { id: 99, name: 'Named Pick', url: 'https://example.com/99', price: '999' },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].products?.[0].name).toBe('Named Pick')
  })

  it('does not extract products from non-product tool output', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'get_categories',
              toolCallId: '789',
              state: 'output-available',
              output: [{ id: 1, name: 'Category', slug: 'cat' }],
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].products).toBeUndefined()
  })

  it('does not extract products from tool with input-available state', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'search_products',
              toolCallId: '123',
              state: 'input-available',
              output: undefined,
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'streaming',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].products).toBeUndefined()
  })

  it('extracts checkout action from get_checkout_action tool output', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            { type: 'text', text: 'Taking you to checkout.' },
            {
              type: 'dynamic-tool',
              toolName: 'get_checkout_action',
              toolCallId: 'co-1',
              state: 'output-available',
              output: {
                checkout_url: 'https://shop.test/checkout/',
                cart_url: 'https://shop.test/cart/',
                has_cart: true,
                item_count: 2,
              },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].checkoutAction).toEqual({
      checkout_url: 'https://shop.test/checkout/',
      cart_url: 'https://shop.test/cart/',
      item_count: 2,
    })
  })

  it('omits checkout action when both URLs are empty', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'get_checkout_action',
              toolCallId: 'co-2',
              state: 'output-available',
              output: {
                checkout_url: '',
                cart_url: '',
                has_cart: false,
                item_count: 0,
              },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].checkoutAction).toBeUndefined()
  })

  it('omits checkout action when has_cart is false even with URLs present', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'assistant',
          parts: [
            {
              type: 'dynamic-tool',
              toolName: 'get_checkout_action',
              toolCallId: 'co-3',
              state: 'output-available',
              output: {
                checkout_url: 'https://shop.test/checkout/',
                cart_url: 'https://shop.test/cart/',
                has_cart: false,
                item_count: 0,
              },
            },
          ],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].checkoutAction).toBeUndefined()
  })

  it('does not add products to user messages', () => {
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'Show me products' }],
        },
      ],
      sendMessage: mockSendMessage,
      status: 'ready',
      stop: mockStop,
      setMessages: mockSetMessages,
      error: undefined,
    })

    const { result } = renderHook(() => useChat())

    expect(result.current.messages[0].products).toBeUndefined()
  })

  describe('chat history persistence', () => {
    it('restores messages from sessionStorage on mount', async () => {
      const storedMessages = [
        { id: 'greeting', role: 'assistant', parts: [{ type: 'text', text: 'Hello!' }] },
        { id: '1', role: 'user', parts: [{ type: 'text', text: 'Hi there' }] },
        { id: '2', role: 'assistant', parts: [{ type: 'text', text: 'How can I help?' }] },
      ]
      sessionStorage.setItem('wpaic_chat_history', JSON.stringify(storedMessages))

      renderHook(() => useChat())

      await waitFor(() => {
        expect(mockSetMessages).toHaveBeenCalledWith(storedMessages)
      })
    })

    it('ignores legacy greeting-only storage and reseeds the current greeting', async () => {
      sessionStorage.setItem('wpaic_chat_history', JSON.stringify([
        { id: 'greeting', role: 'assistant', parts: [{ type: 'text', text: 'Old greeting' }] },
      ]))

      window.wpaicConfig = {
        apiUrl: '/wp-json/wpaic/v1',
        nonce: 'test-nonce',
        greeting: 'Updated greeting',
      }

      renderHook(() => useChat())

      await waitFor(() => {
        expect(mockSetMessages).toHaveBeenCalledWith([
          {
            id: 'greeting',
            role: 'assistant',
            parts: [{ type: 'text', text: 'Updated greeting' }],
          },
        ])
      })

      expect(sessionStorage.getItem('wpaic_chat_history')).toBeNull()
    })

    it('shows greeting when no stored messages exist', async () => {
      renderHook(() => useChat())

      await waitFor(() => {
        expect(mockSetMessages).toHaveBeenCalledWith([
          {
            id: 'greeting',
            role: 'assistant',
            parts: [{ type: 'text', text: 'Hello! How can I help?' }],
          },
        ])
      })
    })

    it('saves messages to sessionStorage when messages change', async () => {
      const messages = [
        { id: 'greeting', role: 'assistant', parts: [{ type: 'text', text: 'Hello!' }] },
        { id: '1', role: 'user', parts: [{ type: 'text', text: 'Hi' }] },
      ]

      mockUseVercelChat.mockReturnValue({
        messages,
        sendMessage: mockSendMessage,
        status: 'ready',
        stop: mockStop,
        setMessages: mockSetMessages,
        error: undefined,
      })

      renderHook(() => useChat())

      await waitFor(() => {
        const stored = sessionStorage.getItem('wpaic_chat_history')
        expect(stored).not.toBeNull()
        if (!stored) {
          throw new Error('Expected chat history to be stored')
        }
        const parsed = JSON.parse(stored)
        expect(parsed).toHaveLength(2)
        expect(parsed[1].id).toBe('1')
      })
    })

    it('does not save to storage if only greeting message', async () => {
      const messages = [
        { id: 'greeting', role: 'assistant', parts: [{ type: 'text', text: 'Hello!' }] },
      ]

      mockUseVercelChat.mockReturnValue({
        messages,
        sendMessage: mockSendMessage,
        status: 'ready',
        stop: mockStop,
        setMessages: mockSetMessages,
        error: undefined,
      })

      renderHook(() => useChat())

      await waitFor(() => {
        expect(mockSetMessages).toHaveBeenCalled()
      })

      const stored = sessionStorage.getItem('wpaic_chat_history')
      expect(stored).toBeNull()
    })

    it('startNewConversation removes stored messages', async () => {
      sessionStorage.setItem('wpaic_chat_history', JSON.stringify([
        { id: '1', role: 'user', parts: [{ type: 'text', text: 'Hi' }] },
      ]))

      const { result } = renderHook(() => useChat())

      act(() => {
        result.current.startNewConversation()
      })

      expect(sessionStorage.getItem('wpaic_chat_history')).toBeNull()
    })

    it('startNewConversation clears stored clear-cart statuses', async () => {
      sessionStorage.setItem('wpaic_clear_cart_status', JSON.stringify({ 'cc-1': 'cleared' }))

      const { result } = renderHook(() => useChat())

      act(() => {
        result.current.startNewConversation()
      })

      expect(sessionStorage.getItem('wpaic_clear_cart_status')).toBeNull()
    })

    it('startNewConversation clears stored add-to-cart statuses', async () => {
      sessionStorage.setItem('wpaic_add_to_cart_status', JSON.stringify({ 'tc-1': 'added' }))

      const { result } = renderHook(() => useChat())

      act(() => {
        result.current.startNewConversation()
      })

      expect(sessionStorage.getItem('wpaic_add_to_cart_status')).toBeNull()
    })

    it('never re-fires cart requests for add_to_cart intents in a restored conversation', async () => {
      const fetchMock = vi.fn()
      vi.stubGlobal('fetch', fetchMock)

      window.wpaicConfig = {
        apiUrl: '/wp-json/wpaic/v1',
        nonce: 'test-nonce',
        greeting: 'Hello!',
        wcAjaxUrl: 'https://shop.test/admin-ajax.php',
      }

      sessionStorage.setItem(
        'wpaic_chat_history',
        JSON.stringify([
          { id: '1', role: 'user', parts: [{ type: 'text', text: 'add water to my cart' }] },
          {
            id: '2',
            role: 'assistant',
            parts: [
              {
                type: 'dynamic-tool',
                toolName: 'add_to_cart',
                toolCallId: 'tc-restored',
                state: 'output-available',
                output: {
                  success: true,
                  action: 'add_to_cart',
                  product_id: 55,
                  quantity: 2,
                  name: 'Water',
                },
              },
            ],
          },
        ])
      )

      renderHook(() => useChat())

      await waitFor(() => {
        expect(mockSetMessages).toHaveBeenCalled()
      })

      // MessageList mounts an AddToCartTrigger (useAddToCart) for the restored intent.
      const { result } = renderHook(() =>
        useAddToCart({ toolCallId: 'tc-restored', productId: 55, quantity: 2 })
      )

      expect(result.current).toBe('added')
      await waitFor(() => {
        expect(fetchMock).not.toHaveBeenCalled()
      })

      vi.unstubAllGlobals()
    })
  })
})
