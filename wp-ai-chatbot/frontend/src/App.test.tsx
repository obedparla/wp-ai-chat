import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, act } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import App from './App'
import * as useChatModule from './hooks/useChat'

vi.mock('./hooks/useChat')

describe('App', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(useChatModule.useChat).mockReturnValue({
      messages: [{ role: 'assistant', content: 'Hello! How can I help?' }],
      sendMessage: vi.fn(),
      isLoading: false,
      stopGeneration: vi.fn(),
    })
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
    expect(screen.getByRole('button', { name: 'Close chat' })).toBeInTheDocument()
  })

  it('hides chat widget when toggle button clicked again', async () => {
    render(<App />)
    const button = screen.getByRole('button', { name: 'Open chat' })

    await userEvent.click(button)
    expect(screen.getByText('AI Assistant')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Close chat' }))
    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
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

    expect(screen.getByPlaceholderText('Type a message...')).toBeInTheDocument()
  })

  it('preserves chat history when widget is closed and reopened', async () => {
    vi.mocked(useChatModule.useChat).mockReturnValue({
      messages: [
        { role: 'assistant', content: 'Hello! How can I help?' },
        { role: 'user', content: 'Test message' },
        { role: 'assistant', content: 'Response to test' },
      ],
      sendMessage: vi.fn(),
      isLoading: false,
      stopGeneration: vi.fn(),
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
      messages: [{ role: 'assistant', content: 'Hello!' }],
      sendMessage: mockSendMessage,
      isLoading: false,
      stopGeneration: vi.fn(),
    })

    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    const input = screen.getByPlaceholderText('Type a message...')

    await userEvent.type(input, 'Test message{Enter}')

    expect(mockSendMessage).toHaveBeenCalledWith('Test message')
  })
})

describe('ChatButton', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(useChatModule.useChat).mockReturnValue({
      messages: [],
      sendMessage: vi.fn(),
      isLoading: false,
      stopGeneration: vi.fn(),
    })
  })

  it('shows message icon when chat is closed', () => {
    render(<App />)
    const button = screen.getByRole('button', { name: 'Open chat' })
    const svg = button.querySelector('svg')
    expect(svg).toBeInTheDocument()
    expect(button.querySelector('path')).toBeInTheDocument()
  })

  it('shows close icon when chat is open', async () => {
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))

    const toggleBtn = screen.getByRole('button', { name: 'Close chat' })
    expect(toggleBtn.querySelectorAll('line')).toHaveLength(2)
  })

  it('has hidden-on-mobile class when chat is open', async () => {
    render(<App />)

    const button = screen.getByRole('button', { name: 'Open chat' })
    expect(button).not.toHaveClass('max-[480px]:hidden')

    await userEvent.click(button)

    const toggleBtn = screen.getByRole('button', { name: 'Close chat' })
    expect(toggleBtn).toHaveClass('max-[480px]:hidden')
  })
})

describe('Proactive engagement', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    sessionStorage.clear()
    vi.mocked(useChatModule.useChat).mockReturnValue({
      messages: [{ role: 'assistant', content: 'Hello!' }],
      sendMessage: vi.fn(),
      isLoading: false,
      stopGeneration: vi.fn(),
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('auto-opens chat after proactive delay', async () => {
    window.wpaicConfig = {
      apiUrl: 'http://test.local/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello!',
      proactiveEnabled: true,
      proactiveDelay: 5,
      proactiveMessage: 'Need help?',
    }

    render(<App />)

    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()

    await act(async () => {
      vi.advanceTimersByTime(5000)
    })

    expect(screen.getByText('AI Assistant')).toBeInTheDocument()
  })

  it('does not auto-open when proactive is disabled', async () => {
    window.wpaicConfig = {
      apiUrl: 'http://test.local/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello!',
      proactiveEnabled: false,
      proactiveDelay: 5,
      proactiveMessage: 'Need help?',
    }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(10000)
    })

    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
  })

  it('does not auto-open if user interacted before delay', async () => {
    window.wpaicConfig = {
      apiUrl: 'http://test.local/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello!',
      proactiveEnabled: true,
      proactiveDelay: 10,
      proactiveMessage: 'Need help?',
    }

    vi.useRealTimers()
    render(<App />)

    await userEvent.click(screen.getByRole('button', { name: 'Open chat' }))
    await userEvent.click(screen.getByRole('button', { name: 'Close' }))

    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
  })

  it('stores shown state in sessionStorage', async () => {
    window.wpaicConfig = {
      apiUrl: 'http://test.local/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello!',
      proactiveEnabled: true,
      proactiveDelay: 5,
      proactiveMessage: 'Need help?',
    }

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(5000)
    })

    expect(sessionStorage.getItem('wpaic_proactive_shown')).toBe('true')
  })

  it('does not auto-open if already shown in session', async () => {
    window.wpaicConfig = {
      apiUrl: 'http://test.local/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello!',
      proactiveEnabled: true,
      proactiveDelay: 5,
      proactiveMessage: 'Need help?',
    }

    sessionStorage.setItem('wpaic_proactive_shown', 'true')

    render(<App />)

    await act(async () => {
      vi.advanceTimersByTime(10000)
    })

    expect(screen.queryByText('AI Assistant')).not.toBeInTheDocument()
  })
})
