import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import SendTranscriptDialog from './SendTranscriptDialog'

describe('SendTranscriptDialog', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    window.wpaicConfig = undefined
  })

  function renderDialog() {
    const onClose = vi.fn()
    render(<SendTranscriptDialog messages={[]} onClose={onClose} />)
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
})
