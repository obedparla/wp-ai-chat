import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import SendTranscriptDialog from './SendTranscriptDialog'
import type { Message } from '../hooks/useChat'

describe('SendTranscriptDialog', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.unstubAllGlobals()
    window.wpaicConfig = undefined
    sessionStorage.clear()
  })

  function renderDialog(messages: Message[] = []) {
    const onClose = vi.fn()
    render(<SendTranscriptDialog messages={messages} onClose={onClose} />)
    return { onClose }
  }

  it('focuses the email input by default', () => {
    renderDialog()
    expect(screen.getByPlaceholderText('you@example.com')).toHaveFocus()
  })

  it('traps Tab inside the dialog (wraps from SEND back to the email input)', () => {
    renderDialog()
    const emailInput = screen.getByPlaceholderText('you@example.com')
    const sendButton = screen.getByRole('button', { name: 'SEND' })

    sendButton.focus()
    fireEvent.keyDown(sendButton, { key: 'Tab' })
    expect(emailInput).toHaveFocus()
  })

  it('traps Shift+Tab inside the dialog (wraps from the email input back to SEND)', () => {
    renderDialog()
    const emailInput = screen.getByPlaceholderText('you@example.com')
    const sendButton = screen.getByRole('button', { name: 'SEND' })

    emailInput.focus()
    fireEvent.keyDown(emailInput, { key: 'Tab', shiftKey: true })
    expect(sendButton).toHaveFocus()
  })

  it('closes on Escape', () => {
    const { onClose } = renderDialog()
    fireEvent.keyDown(screen.getByPlaceholderText('you@example.com'), { key: 'Escape' })
    expect(onClose).toHaveBeenCalled()
  })

  it('includes the stored session_id in the transcript request body', async () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello!',
    }
    sessionStorage.setItem('wpaic_session_id', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee')

    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({}) })
    vi.stubGlobal('fetch', fetchMock)

    renderDialog([{ role: 'user', content: 'Hi there' }])

    fireEvent.change(screen.getByPlaceholderText('you@example.com'), {
      target: { value: 'shopper@example.com' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'SEND' }))

    await waitFor(() => expect(fetchMock).toHaveBeenCalled())
    const requestBody = JSON.parse(fetchMock.mock.calls[0][1].body as string)
    expect(requestBody).toEqual({
      email: 'shopper@example.com',
      transcript: 'You: Hi there',
      session_id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    })
  })
})
