import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
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
  stopGeneration: ReturnType<typeof vi.fn>
  clearChat: ReturnType<typeof vi.fn>
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
    stopGeneration: vi.fn(),
    clearChat: vi.fn(),
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
    expect(screen.getAllByText('AI Assistant')).toHaveLength(1)
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

  it('shows subtitle when custom name provided', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} chatbotName="ShopBot" />)
    const subtitles = screen.getAllByText('AI Assistant')
    expect(subtitles.length).toBeGreaterThan(0)
  })

  it('does not show subtitle when only logo provided', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} chatbotLogo="https://example.com/logo.png" />)
    const titles = screen.getAllByText('AI Assistant')
    expect(titles).toHaveLength(1)
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
    expect(screen.getByPlaceholderText('Type a message...')).toBeInTheDocument()
  })

  it('renders send button', () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)
    expect(screen.getByRole('button', { name: 'Send' })).toBeInTheDocument()
  })

  it('updates input value on change', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const input = screen.getByPlaceholderText('Type a message...')
    await userEvent.type(input, 'Hello')

    expect(input).toHaveValue('Hello')
  })

  it('clears input after form submit', async () => {
    render(<ChatWidget onClose={mockOnClose} chat={createMockChat()} />)

    const input = screen.getByPlaceholderText('Type a message...')
    const form = input.closest('form')

    await userEvent.type(input, 'Hello')
    if (form) fireEvent.submit(form)

    expect(input).toHaveValue('')
  })

  it('calls sendMessage on form submit', async () => {
    const mockChat = createMockChat()
    render(<ChatWidget onClose={mockOnClose} chat={mockChat} />)

    const input = screen.getByPlaceholderText('Type a message...')
    const form = input.closest('form')

    await userEvent.type(input, 'Hello')
    if (form) fireEvent.submit(form)

    expect(mockChat.sendMessage).toHaveBeenCalledWith('Hello')
  })

  it('sends message when Enter key pressed', async () => {
    const mockChat = createMockChat()
    render(<ChatWidget onClose={mockOnClose} chat={mockChat} />)

    const input = screen.getByPlaceholderText('Type a message...')
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

    const input = screen.getByPlaceholderText('Type a message...')
    await userEvent.type(input, '   ')

    const sendBtn = screen.getByRole('button', { name: 'Send' })
    expect(sendBtn).toBeDisabled()
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

describe('ChatWidget clear chat', () => {
  it('renders clear button in header', () => {
    render(<ChatWidget onClose={vi.fn()} chat={createMockChat()} />)
    expect(screen.getByRole('button', { name: 'Clear chat' })).toBeInTheDocument()
  })

  it('calls clearChat when clear button clicked', async () => {
    const mockChat = createMockChat()
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)

    const clearBtn = screen.getByRole('button', { name: 'Clear chat' })
    await userEvent.click(clearBtn)

    expect(mockChat.clearChat).toHaveBeenCalled()
  })
})

describe('ChatWidget loading state', () => {
  it('shows typing indicator when loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Typing...')).toBeInTheDocument()
  })

  it('disables input when loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    const input = screen.getByPlaceholderText('Type a message...')
    expect(input).toBeDisabled()
  })

  it('does not show typing indicator when not loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: false })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.queryByText('Typing...')).not.toBeInTheDocument()
  })

  it('shows stop button when loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByRole('button', { name: 'Stop' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send' })).not.toBeInTheDocument()
  })

  it('shows send button when not loading', () => {
    const mockChat = createMockChat({ messages: [], isLoading: false })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByRole('button', { name: 'Send' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Stop' })).not.toBeInTheDocument()
  })

  it('calls stopGeneration when stop button clicked', async () => {
    const mockChat = createMockChat({ messages: [], isLoading: true })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    const stopBtn = screen.getByRole('button', { name: 'Stop' })
    await userEvent.click(stopBtn)

    expect(mockChat.stopGeneration).toHaveBeenCalled()
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

  it('shows generic message for unknown tool', () => {
    const mockChat = createMockChat({
      messages: [],
      isLoading: true,
      activeTools: [{ toolName: 'custom_tool', state: 'executing' }],
    })
    render(<ChatWidget onClose={vi.fn()} chat={mockChat} />)
    expect(screen.getByText('Running custom_tool...')).toBeInTheDocument()
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
