import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import ChatWidget from './ChatWidget'
import type { ActiveTool } from '../hooks/useChat'
import type { Product } from './ProductCard'

interface MockMessage {
  role: 'user' | 'assistant'
  content: string
  isError?: boolean
  id?: string
  products?: Product[]
}

interface MockChat {
  messages: MockMessage[]
  sendMessage: ReturnType<typeof vi.fn>
  isLoading: boolean
  startNewConversation: ReturnType<typeof vi.fn>
  activeTools: ActiveTool[]
  retry: ReturnType<typeof vi.fn>
}

function createMockChat(overrides: Partial<MockChat> = {}): MockChat {
  return {
    messages: [
      { role: 'assistant', content: 'Hello! How can I help?' },
      { role: 'user', content: 'Test message' },
    ],
    sendMessage: vi.fn(),
    isLoading: false,
    startNewConversation: vi.fn(),
    activeTools: [],
    retry: vi.fn(),
    ...overrides,
  }
}

describe('ChatWidget', () => {
  const mockOnClose = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders chat header with default title', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)
    expect(screen.getByText('AI Assistant')).toBeInTheDocument()
  })

  it('renders custom chatbot name', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} chatbotName="ShopBot" />)
    expect(screen.getByText('ShopBot')).toBeInTheDocument()
  })

  it('renders chatbot logo when provided', () => {
    const { container } = render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} chatbotLogo="https://example.com/logo.png" />)
    const logo = container.querySelector('img')
    expect(logo).toBeInTheDocument()
    expect(logo).toHaveAttribute('src', 'https://example.com/logo.png')
  })

  it('renders both name and logo', () => {
    const { container } = render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} chatbotName="ShopBot" chatbotLogo="https://example.com/logo.png" />)
    expect(screen.getByText('ShopBot')).toBeInTheDocument()
    expect(container.querySelector('img')).toBeInTheDocument()
  })

  it('shows configured role in subtitle', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} chatbotName="ShopBot" chatbotRole="Personal stylist" />)
    expect(screen.getByText(/Personal stylist/)).toBeInTheDocument()
  })

  it('defaults subtitle to AI Assistant role when no role configured', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} chatbotName="ShopBot" />)
    expect(screen.getByText(/AI Assistant/)).toBeInTheDocument()
  })

  it('renders close button', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)
    expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument()
  })

  it('calls onClose when close button clicked', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const closeBtn = screen.getByRole('button', { name: 'Close' })
    await userEvent.click(closeBtn)

    expect(mockOnClose).toHaveBeenCalled()
  })

  it('renders messages from chat prop', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)
    expect(screen.getByText('Hello! How can I help?')).toBeInTheDocument()
    expect(screen.getByText('Test message')).toBeInTheDocument()
  })

  it('renders input field', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)
    expect(screen.getByPlaceholderText('Ask anything...')).toBeInTheDocument()
  })

  it('input has accessible aria-label for screen readers', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)
    expect(screen.getByRole('textbox', { name: 'Type your message' })).toBeInTheDocument()
  })

  it('auto-focuses the input when requested', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} autoFocusInput />)

    await waitFor(() => {
      expect(screen.getByPlaceholderText('Ask anything...')).toHaveFocus()
    })
  })

  it('renders send button', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)
    expect(screen.getByRole('button', { name: 'Send' })).toBeInTheDocument()
  })

  it('updates input value on change', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const input = screen.getByPlaceholderText('Ask anything...')
    await userEvent.type(input, 'Hello')

    expect(input).toHaveValue('Hello')
  })

  it('clears input after form submit', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const input = screen.getByPlaceholderText('Ask anything...')
    const form = input.closest('form')

    await userEvent.type(input, 'Hello')
    if (form) fireEvent.submit(form)

    expect(input).toHaveValue('')
  })

  it('calls sendMessage on form submit', async () => {
    const mockChat = createMockChat()
    render(<ChatWidget onClose={mockOnClose} chat={mockChat} />)

    const input = screen.getByPlaceholderText('Ask anything...')
    const form = input.closest('form')

    await userEvent.type(input, 'Hello')
    if (form) fireEvent.submit(form)

    expect(mockChat.sendMessage).toHaveBeenCalledWith('Hello')
  })

  it('re-focuses the input after submitting a message', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const input = screen.getByPlaceholderText('Ask anything...')
    await userEvent.type(input, 'Hello')
    await userEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => {
      expect(input).toHaveFocus()
    })
  })

  it('sends message when Enter key pressed', async () => {
    const mockChat = createMockChat()
    render(<ChatWidget onClose={mockOnClose} chat={mockChat} />)

    const input = screen.getByPlaceholderText('Ask anything...')
    await userEvent.type(input, 'Test via Enter{enter}')

    expect(mockChat.sendMessage).toHaveBeenCalledWith('Test via Enter')
  })

  it('does not submit when input is empty', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const sendBtn = screen.getByRole('button', { name: 'Send' })
    expect(sendBtn).toBeDisabled()
  })

  it('does not submit when input is only whitespace', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const input = screen.getByPlaceholderText('Ask anything...')
    await userEvent.type(input, '   ')

    const sendBtn = screen.getByRole('button', { name: 'Send' })
    expect(sendBtn).toBeDisabled()
  })

  it('shows conversation starters for a greeting-only conversation', () => {
    render(
      <ChatWidget
        onClose={mockOnClose}
        chat={createMockChat({
          messages: [{ role: 'assistant', content: 'Hello! How can I help?', id: 'greeting' }],
        })}
        conversationStarters={['Find a product', 'Track my order']}
      />
    )

    expect(screen.getByRole('button', { name: 'Find a product' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Track my order' })).toBeInTheDocument()
  })

  it('hides conversation starters once the conversation has real history', () => {
    render(
      <ChatWidget
        onClose={mockOnClose}
        chat={createMockChat()}
        conversationStarters={['Find a product']}
      />
    )

    expect(screen.queryByRole('button', { name: 'Find a product' })).not.toBeInTheDocument()
  })

  it('sends a starter immediately when clicked', async () => {
    const mockChat = createMockChat({
      messages: [{ role: 'assistant', content: 'Hello! How can I help?', id: 'greeting' }],
    })

    render(
      <ChatWidget
        onClose={mockOnClose}
        chat={mockChat}
        conversationStarters={['Find a product']}
      />
    )

    await userEvent.click(screen.getByRole('button', { name: 'Find a product' }))

    expect(mockChat.sendMessage).toHaveBeenCalledWith('Find a product')
  })
})

describe('ChatWidget error messages', () => {
  it('renders error messages with error styling', () => {
    const mockChat = createMockChat({
      messages: [{ role: 'assistant', content: 'Chat is unavailable', isError: true }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    const errorBubble = screen.getByText('Chat is unavailable').closest('.bg-red-50')
    expect(errorBubble).toBeInTheDocument()
  })

  it('does not apply error styling to normal messages', () => {
    const mockChat = createMockChat({
      messages: [{ role: 'assistant', content: 'Hello!' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    const errorBubble = screen.getByText('Hello!').closest('.bg-red-50')
    expect(errorBubble).not.toBeInTheDocument()
  })
})

describe('ChatWidget retry button', () => {
  it('shows retry button on last error message', () => {
    const mockChat = createMockChat({
      messages: [
        { role: 'user', content: 'Hello' },
        { role: 'assistant', content: 'Something went wrong', isError: true },
      ],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('does not show retry button on non-error messages', () => {
    const mockChat = createMockChat({
      messages: [
        { role: 'user', content: 'Hello' },
        { role: 'assistant', content: 'Hi there!' },
      ],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument()
  })

  it('does not show retry button on error message that is not last', () => {
    const mockChat = createMockChat({
      messages: [
        { role: 'assistant', content: 'First error', isError: true },
        { role: 'user', content: 'Trying again' },
        { role: 'assistant', content: 'Success!' },
      ],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument()
  })

  it('calls retry when retry button clicked', async () => {
    const mockChat = createMockChat({
      messages: [
        { role: 'user', content: 'Hello' },
        { role: 'assistant', content: 'Something went wrong', isError: true },
      ],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)

    const retryBtn = screen.getByRole('button', { name: 'Retry' })
    await userEvent.click(retryBtn)

    expect(mockChat.retry).toHaveBeenCalled()
  })
})

describe('ChatWidget new conversation', () => {
  it('renders new conversation button in header', () => {
    render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)
    expect(screen.getByRole('button', { name: 'New conversation' })).toBeInTheDocument()
  })

  it('asks to confirm before starting a new conversation with history', async () => {
    const mockChat = createMockChat()
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)

    await userEvent.click(screen.getByRole('button', { name: 'New conversation' }))

    expect(screen.getByText('Start new conversation?')).toBeInTheDocument()
    expect(mockChat.startNewConversation).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: /start new/i }))

    expect(mockChat.startNewConversation).toHaveBeenCalled()
  })

  it('cancels the new conversation dialog without resetting', async () => {
    const mockChat = createMockChat()
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)

    await userEvent.click(screen.getByRole('button', { name: 'New conversation' }))
    await userEvent.click(screen.getByRole('button', { name: /cancel/i }))

    expect(mockChat.startNewConversation).not.toHaveBeenCalled()
    expect(screen.queryByText('Start new conversation?')).not.toBeInTheDocument()
  })

  it('starts a new conversation immediately when only the greeting is present', async () => {
    const mockChat = createMockChat({
      messages: [{ role: 'assistant', content: 'Hello!', id: 'greeting' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)

    await userEvent.click(screen.getByRole('button', { name: 'New conversation' }))

    expect(mockChat.startNewConversation).toHaveBeenCalled()
    expect(screen.queryByText('Start new conversation?')).not.toBeInTheDocument()
  })
})

describe('ChatWidget loading state', () => {
  it('shows typing indicator when loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Typing...')).toBeInTheDocument()
  })

  it('keeps input enabled when loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    const input = screen.getByPlaceholderText('Ask anything...')
    expect(input).not.toBeDisabled()
  })

  it('does not show typing indicator when not loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: false })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.queryByText('Typing...')).not.toBeInTheDocument()
  })

  it('keeps the send button visible while loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByRole('button', { name: 'Send' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Stop' })).not.toBeInTheDocument()
  })

  it('allows sending another message while loading', async () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)

    const input = screen.getByPlaceholderText('Ask anything...')
    await userEvent.type(input, 'Another question{Enter}')

    expect(mockChat.sendMessage).toHaveBeenCalledWith('Another question')
  })
})

describe('ChatWidget product cards', () => {
  it('renders product cards when message has products', () => {
    const mockChat = createMockChat({
      messages: [
        {
          role: 'assistant',
          content: 'Here are some products:',
          products: [
            {
              id: 1,
              name: 'Test Product',
              url: 'https://example.com/product/1',
              price: '29.99',
            },
          ],
        },
      ],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Test Product')).toBeInTheDocument()
    expect(screen.getByText('$29.99')).toBeInTheDocument()
  })

  it('renders multiple product cards', () => {
    const mockChat = createMockChat({
      messages: [
        {
          role: 'assistant',
          content: 'Found products:',
          products: [
            { id: 1, name: 'Product A', url: 'https://example.com/1', price: '10' },
            { id: 2, name: 'Product B', url: 'https://example.com/2', price: '20' },
          ],
        },
      ],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Product A')).toBeInTheDocument()
    expect(screen.getByText('Product B')).toBeInTheDocument()
  })

  it('does not render product grid when message has no products', () => {
    const mockChat = createMockChat({
      messages: [{ role: 'assistant', content: 'Hello!' }],
    })
    const { container } = render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(container.querySelector('.wpaic-product-grid')).not.toBeInTheDocument()
  })
})

describe('ChatWidget mobile fullscreen', () => {
  const originalMatchMedia = window.matchMedia

  function mockMatchMedia(matches: boolean) {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: (query: string) => ({
        matches,
        media: query,
        onchange: null,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
      }),
    })
  }

  afterEach(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: originalMatchMedia,
    })
    document.body.style.overflow = ''
    document.documentElement.style.overflow = ''
    document.getElementById('wpadminbar')?.remove()
  })

  it('uses 100dvh (not 100vh) for the fullscreen height', () => {
    const { container } = render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)
    const widget = container.firstChild as HTMLElement
    expect(widget.className).toContain('max-[480px]:h-[calc(100dvh-var(--wpaic-mobile-top-offset,0px))]')
    expect(widget.className).not.toContain('100vh]')
  })

  it('locks body scroll while fullscreen and restores it on unmount', () => {
    mockMatchMedia(true)
    document.body.style.overflow = 'scroll'

    const { unmount } = render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)

    expect(document.body.style.overflow).toBe('hidden')
    expect(document.documentElement.style.overflow).toBe('hidden')

    unmount()

    expect(document.body.style.overflow).toBe('scroll')
    expect(document.documentElement.style.overflow).toBe('')
  })

  it('does not lock body scroll when not fullscreen', () => {
    mockMatchMedia(false)

    render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)

    expect(document.body.style.overflow).toBe('')
  })

  it('offsets the widget below a visible admin bar', () => {
    const adminBar = document.createElement('div')
    adminBar.id = 'wpadminbar'
    adminBar.getBoundingClientRect = () =>
      ({ bottom: 46, top: 0, left: 0, right: 0, width: 0, height: 46, x: 0, y: 0, toJSON: () => ({}) }) as DOMRect
    document.body.appendChild(adminBar)

    const { container } = render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)
    const widget = container.firstChild as HTMLElement

    expect(widget.style.getPropertyValue('--wpaic-mobile-top-offset')).toBe(
      'calc(46px + env(safe-area-inset-top, 0px))'
    )
  })

  it('clamps the admin bar offset at zero when the bar is scrolled out of view', () => {
    const adminBar = document.createElement('div')
    adminBar.id = 'wpadminbar'
    adminBar.getBoundingClientRect = () =>
      ({ bottom: -20, top: -66, left: 0, right: 0, width: 0, height: 46, x: 0, y: -66, toJSON: () => ({}) }) as DOMRect
    document.body.appendChild(adminBar)

    const { container } = render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)
    const widget = container.firstChild as HTMLElement

    expect(widget.style.getPropertyValue('--wpaic-mobile-top-offset')).toBe(
      'calc(0px + env(safe-area-inset-top, 0px))'
    )
  })

  it('uses a zero admin bar offset when no admin bar is present', () => {
    const { container } = render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)
    const widget = container.firstChild as HTMLElement

    expect(widget.style.getPropertyValue('--wpaic-mobile-top-offset')).toBe(
      'calc(0px + env(safe-area-inset-top, 0px))'
    )
  })
})

describe('ChatWidget product skeletons', () => {
  // Skeletons are driven by message.hasPendingProductTool (see MessageList
  // tests); ChatWidget no longer computes anything skeleton-related, so a
  // running product tool alone must not render one.
  it('does not render skeletons from active tools alone', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'search_products', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.queryByRole('status', { name: 'Loading products' })).not.toBeInTheDocument()
  })
})

describe('ChatWidget tool progress', () => {
  it('shows tool progress instead of typing when tool is active', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'search_products', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Searching products...')).toBeInTheDocument()
    expect(screen.queryByText('Typing...')).not.toBeInTheDocument()
  })

  it('shows product details progress for get_product_details tool', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'get_product_details', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Loading product details...')).toBeInTheDocument()
  })

  it('shows categories progress for get_categories tool', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'get_categories', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Loading categories...')).toBeInTheDocument()
  })

  it('shows a friendly generic message for an unknown tool, never the raw tool name', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'custom_tool', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Working on it…')).toBeInTheDocument()
    expect(screen.queryByText(/custom_tool/)).not.toBeInTheDocument()
  })

  it('shows a human-readable label for add_to_cart', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'add_to_cart', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Adding to cart...')).toBeInTheDocument()
  })

  it('shows a human-readable label for clear_cart', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'clear_cart', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Updating your cart...')).toBeInTheDocument()
  })

  it('shows typing indicator when loading with no active tools', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Typing...')).toBeInTheDocument()
  })

  it('does not show tool progress when not loading', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: false,
      activeTools: [{ toolName: 'search_products', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.queryByText('Searching products...')).not.toBeInTheDocument()
  })
})
