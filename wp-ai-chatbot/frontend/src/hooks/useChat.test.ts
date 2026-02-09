import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import { useChat } from './useChat'

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

  it('calls vercel sendMessage with text object when sendMessage called', () => {
    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.sendMessage('Hello')
    })

    expect(mockSendMessage).toHaveBeenCalledWith({ text: 'Hello' })
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
    expect(result.current.messages[0]).toEqual({
      role: 'user',
      content: 'Hello',
      isError: false,
      id: '1',
    })
    expect(result.current.messages[1]).toEqual({
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
    expect(result.current.messages[1]).toEqual({
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

  it('stopGeneration calls stop', () => {
    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.stopGeneration()
    })

    expect(mockStop).toHaveBeenCalled()
  })

  it('clearChat calls stop and setMessages with greeting', () => {
    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.clearChat()
    })

    expect(mockStop).toHaveBeenCalled()
    // First call clears, second call sets greeting
    expect(mockSetMessages).toHaveBeenCalled()
  })

  it('clearChat with no greeting results in empty messages call', () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: '',
    }

    const { result } = renderHook(() => useChat())

    act(() => {
      result.current.clearChat()
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

  it('retry removes failed assistant message and resends last user message', () => {
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

    // First send a message to store it as lastUserMessage
    act(() => {
      result.current.sendMessage('Hello')
    })

    // Now retry
    act(() => {
      result.current.retry()
    })

    // Should have called setMessages to remove failed message
    expect(mockSetMessages).toHaveBeenCalled()
    // Should have called sendMessage again with the last user message
    expect(mockSendMessage).toHaveBeenCalledWith({ text: 'Hello' })
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

  it('stores last user message when sendMessage is called', () => {
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
      result.current.sendMessage('First message')
    })

    act(() => {
      result.current.sendMessage('Second message')
    })

    // Mock an error state
    mockUseVercelChat.mockReturnValue({
      messages: [
        {
          id: '1',
          role: 'user',
          parts: [{ type: 'text', text: 'Second message' }],
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

    // Retry should resend 'Second message' (the last one)
    act(() => {
      result.current.retry()
    })

    expect(mockSendMessage).toHaveBeenLastCalledWith({ text: 'Second message' })
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
        const parsed = JSON.parse(stored!)
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

    it('clearChat removes stored messages', async () => {
      sessionStorage.setItem('wpaic_chat_history', JSON.stringify([
        { id: '1', role: 'user', parts: [{ type: 'text', text: 'Hi' }] },
      ]))

      const { result } = renderHook(() => useChat())

      act(() => {
        result.current.clearChat()
      })

      expect(sessionStorage.getItem('wpaic_chat_history')).toBeNull()
    })
  })
})
