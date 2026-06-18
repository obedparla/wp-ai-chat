import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import ConfirmDialog from './ConfirmDialog'

function renderDialog(overrides: Partial<React.ComponentProps<typeof ConfirmDialog>> = {}) {
  const onConfirm = vi.fn()
  const onCancel = vi.fn()
  render(
    <ConfirmDialog
      title="Start new conversation?"
      description="This clears history."
      confirmLabel="Start new"
      onConfirm={onConfirm}
      onCancel={onCancel}
      {...overrides}
    />
  )
  return { onConfirm, onCancel }
}

describe('ConfirmDialog', () => {
  it('focuses the cancel button for destructive dialogs', () => {
    renderDialog({ destructive: true })
    expect(screen.getByRole('button', { name: 'CANCEL' })).toHaveFocus()
  })

  it('focuses the confirm button for non-destructive dialogs so Enter confirms', () => {
    renderDialog()
    expect(screen.getByRole('button', { name: 'START NEW' })).toHaveFocus()
  })

  it('traps Tab inside the dialog (wraps from confirm back to cancel)', () => {
    renderDialog()
    const cancelButton = screen.getByRole('button', { name: 'CANCEL' })
    const confirmButton = screen.getByRole('button', { name: 'START NEW' })

    confirmButton.focus()
    fireEvent.keyDown(confirmButton, { key: 'Tab' })
    expect(cancelButton).toHaveFocus()
  })

  it('traps Shift+Tab inside the dialog (wraps from cancel back to confirm)', () => {
    renderDialog()
    const cancelButton = screen.getByRole('button', { name: 'CANCEL' })
    const confirmButton = screen.getByRole('button', { name: 'START NEW' })

    cancelButton.focus()
    fireEvent.keyDown(cancelButton, { key: 'Tab', shiftKey: true })
    expect(confirmButton).toHaveFocus()
  })

  it('cancels on Escape', () => {
    const { onCancel } = renderDialog()
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })
    expect(onCancel).toHaveBeenCalled()
  })

  it('confirms only via the confirm button', () => {
    const { onConfirm } = renderDialog()
    fireEvent.click(screen.getByRole('button', { name: 'START NEW' }))
    expect(onConfirm).toHaveBeenCalled()
  })
})
