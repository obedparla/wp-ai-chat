import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, act, fireEvent } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import App from './App'
import * as useChatModule from './hooks/useChat'

vi.mock('./hooks/useChat')

const createMockChat = () => ({
  messages: [{ role: 'assistant' as const, content: 'Hello! How can I help?' }],
  sendMessage: vi.fn(),
  isLoading: false,
  showProactiveGreeting: vi.fn(),
  startNewConversation: vi.fn(),
  activeTools: [],
  retry: vi.fn(),
})

describe('App', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(useChatModule.useChat).mockReturnValue(createMockChat())
  })

  it('renders toggle button at bottom-right position', () => {
    render(<App />)
    const button = screen.getByRole('button', { name: 'Open chat' })
    expect(button).toBeInTheDocument()
    expect(button).toHaveClass('fixed', 'bottom-6', 'right-6')
  })

  it('does not show chat widget initially', () => {
    render(<App />)
    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
  })

  it('shows chat widget when toggle button clicked', async () => {
    render(<App />)
    const button = screen.getByRole('button', { name: 'Open chat' })

    await userEvent.click(button)

    expect(screen.getByText('AI Assistant')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Open chat' })).not.toBeInTheDocument()
  })

  it('hides the floating toggle button while the widget is open', async () => {
    render(<App />)
    const button = screen.getByRole('button', { name: 'Open chat' })

    await userEvent.click(button)
    expect(screen.queryByRole('button', { name: 'Open chat' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Close' }))
    expect(screen.getByRole('button', { name: 'Open chat' })).toBeInTheDocument()
  })

  it('hides chat widget when close button in header clicked', async () => {
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    expect(screen.getByText('AI Assistant')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Close' }))
    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
  })

  it('shows greeting message when widget opened', async () => {
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))

    expect(screen.getByText('Hello! How can I help?')).toBeInTheDocument()
  })

  it('shows message input when widget opened', async () => {
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))

    expect(screen.getByPlaceholderText('Ask anything...')).toBeInTheDocument()
  })

  it('focuses the message input when the widget is opened from the button', async () => {
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))

    expect(screen.getByPlaceholderText('Ask anything...')).toHaveFocus()
  })

  it('preserves chat history when widget is closed and reopened', async () => {
    vi.mocked(useChatModule.useChat).mockReturnValue({
      ...createMockChat(),
      messages: [
        { role: 'assistant', content: 'Hello! How can I help?' },
        { role: 'user', content: 'Test message' },
        { role: 'assistant', content: 'Response to test' },
      ],
    })

    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    expect(screen.getByText('Test message')).toBeInTheDocument()
    expect(screen.getByText('Response to test')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Close' }))
    expect(screen.queryByText('Test message')).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    expect(screen.getByText('Test message')).toBeInTheDocument()
    expect(screen.getByText('Response to test')).toBeInTheDocument()
  })

  it('closes chat widget when Escape key pressed', async () => {
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    expect(screen.getByText('AI Assistant')).toBeInTheDocument()

    await userEvent.keyboard('{Escape}')
    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
  })

  it('sends message when Enter key pressed in input', async () => {
    const mockSendMessage = vi.fn()
    vi.mocked(useChatModule.useChat).mockReturnValue({
      ...createMockChat(),
      messages: [{ role: 'assistant', content: 'Hello!' }],
      sendMessage: mockSendMessage,
    })

    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    const input = screen.getByPlaceholderText('Ask anything...')

    await userEvent.type(input, 'Test message{Enter}')

    expect(mockSendMessage).toHaveBeenCalledWith('Test message')
  })

  it('passes configured conversation starters into the widget', async () => {
    window.wpaicConfig = {
      apiUrl: 'http://test.local/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello!',
      conversationStarters: ['Find a product', 'Track my order'],
    }

    vi.mocked(useChatModule.useChat).mockReturnValue({
      ...createMockChat(),
      messages: [{ role: 'assistant', content: 'Hello!', id: 'greeting' }],
    })

    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))

    expect(screen.getByRole('button', { name: 'Find a product' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Track my order' })).toBeInTheDocument()
  })
})

describe('ChatButton', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(useChatModule.useChat).mockReturnValue({
      messages: [],
      sendMessage: vi.fn(),
      isLoading: false,
      showProactiveGreeting: vi.fn(),
      startNewConversation: vi.fn(),
      activeTools: [],
      retry: vi.fn(),
    })
  })

  it('shows message icon when chat is closed', () => {
    render(<App />)
    const button = screen.getByRole('button', { name: 'Open chat' })
    const svg = button.querySelector('svg')
    expect(svg).toBeInTheDocument()
    expect(button.querySelector('path')).toBeInTheDocument()
  })

  it('is not rendered while the widget is open', async () => {
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))

    expect(screen.queryByRole('button', { name: 'Open chat' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Close chat' })).not.toBeInTheDocument()
  })
})

describe('Proactive engagement teaser', () => {
  const proactiveConfig = {
    apiUrl: 'http://test.local/wp-json/wpaic/v1',
    nonce: 'test-nonce',
    greeting: 'Hello!',
    proactiveEnabled: true,
    proactiveDelay: 5,
    proactiveMessage: 'Need help?',
  }

  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    sessionStorage.clear()
    vi.mocked(useChatModule.useChat).mockReturnValue(createMockChat())
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the teaser bubble after the configured delay without opening the widget', async () => {
    window.wpaicConfig = { ...proactiveConfig }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(4999)
    })
    expect(screen.queryByText('Need help?')).not.toBeInTheDocument()

    await act(async () => {
      vi.advanceTimersByTime(1)
    })

    expect(screen.getByText('Need help?')).toBeInTheDocument()
    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Open chat' })).toBeInTheDocument()
  })

  it('expands the full chat with the proactive greeting when the teaser is clicked', async () => {
    const mockChat = createMockChat()
    vi.mocked(useChatModule.useChat).mockReturnValue(mockChat)
    window.wpaicConfig = { ...proactiveConfig }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(5000)
    })

    fireEvent.click(screen.getByRole('button', { name: 'Need help?' }))

    expect(screen.getByText('AI Assistant')).toBeInTheDocument()
    expect(mockChat.showProactiveGreeting).toHaveBeenCalled()
    expect(screen.queryByRole('button', { name: 'Need help?' })).not.toBeInTheDocument()
  })

  it('dismisses the teaser and persists dismissal for the session', async () => {
    window.wpaicConfig = { ...proactiveConfig }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(5000)
    })

    fireEvent.click(screen.getByRole('button', { name: 'Dismiss message' }))

    expect(screen.queryByText('Need help?')).not.toBeInTheDocument()
    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
    expect(sessionStorage.getItem('wpaic_proactive_dismissed')).toBe('true')
  })

  it('does not show the teaser when dismissed earlier in the session', async () => {
    window.wpaicConfig = { ...proactiveConfig }
    sessionStorage.setItem('wpaic_proactive_dismissed', 'true')

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(10000)
    })

    expect(screen.queryByText('Need help?')).not.toBeInTheDocument()
  })

  it('does not show the teaser when proactive is disabled', async () => {
    window.wpaicConfig = { ...proactiveConfig, proactiveEnabled: false }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(10000)
    })

    expect(screen.queryByText('Need help?')).not.toBeInTheDocument()
  })

  it('does not show the teaser if the user interacted before the delay', async () => {
    window.wpaicConfig = { ...proactiveConfig, proactiveDelay: 10 }

    vi.useRealTimers()
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    await userEvent.click(screen.getByRole('button', { name: 'Close' }))

    expect(screen.queryByText('Need help?')).not.toBeInTheDocument()
    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
  })

  it('falls back to the 10s default when the configured delay is empty (no instant teaser)', async () => {
    // wp_localize_script delivers settings as strings; an empty delay must not
    // coerce to a 0ms timer.
    window.wpaicConfig = { ...proactiveConfig, proactiveDelay: '' as unknown as number }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(9999)
    })
    expect(screen.queryByText('Need help?')).not.toBeInTheDocument()

    await act(async () => {
      vi.advanceTimersByTime(1)
    })
    expect(screen.getByText('Need help?')).toBeInTheDocument()
  })

  it('honors a string-valued configured delay', async () => {
    window.wpaicConfig = { ...proactiveConfig, proactiveDelay: '5' as unknown as number }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(4999)
    })
    expect(screen.queryByText('Need help?')).not.toBeInTheDocument()

    await act(async () => {
      vi.advanceTimersByTime(1)
    })
    expect(screen.getByText('Need help?')).toBeInTheDocument()
  })

  it('falls back to the greeting when no proactive message is configured', async () => {
    window.wpaicConfig = { ...proactiveConfig, proactiveMessage: undefined }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(5000)
    })

    expect(screen.getByRole('button', { name: 'Hello!' })).toBeInTheDocument()
  })
})

describe('Unread badge', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
  })

  it('shows an unread badge when a response completes while the widget is closed', () => {
    vi.mocked(useChatModule.useChat).mockReturnValue({ ...createMockChat(), isLoading: true })
    const { rerender } = render(<App />)

    expect(screen.queryByText('1')).not.toBeInTheDocument()

    vi.mocked(useChatModule.useChat).mockReturnValue({ ...createMockChat(), isLoading: false })
    rerender(<App />)

    expect(screen.getByRole('button', { name: 'Open chat (1 unread message)' })).toBeInTheDocument()
    expect(screen.getByText('1')).toBeInTheDocument()
  })

  it('clears the badge when the chat is opened', async () => {
    vi.mocked(useChatModule.useChat).mockReturnValue({ ...createMockChat(), isLoading: true })
    const { rerender } = render(<App />)

    vi.mocked(useChatModule.useChat).mockReturnValue({ ...createMockChat(), isLoading: false })
    rerender(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat (1 unread message)' }))
    await userEvent.click(screen.getByRole('button', { name: 'Close' }))

    expect(screen.getByRole('button', { name: 'Open chat' })).toBeInTheDocument()
    expect(screen.queryByText('1')).not.toBeInTheDocument()
  })

  it('does not show a badge when the response completes while the widget is open', async () => {
    vi.mocked(useChatModule.useChat).mockReturnValue({ ...createMockChat(), isLoading: true })
    const { rerender } = render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))

    vi.mocked(useChatModule.useChat).mockReturnValue({ ...createMockChat(), isLoading: false })
    rerender(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Close' }))

    expect(screen.getByRole('button', { name: 'Open chat' })).toBeInTheDocument()
    expect(screen.queryByText('1')).not.toBeInTheDocument()
  })
})
