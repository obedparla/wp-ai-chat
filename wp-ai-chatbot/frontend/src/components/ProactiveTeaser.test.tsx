import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import ProactiveTeaser from './ProactiveTeaser'

describe('ProactiveTeaser', () => {
  it('renders the proactive message', () => {
    render(<ProactiveTeaser message="Need help finding something?" onOpen={vi.fn()} onDismiss={vi.fn()} />)
    expect(screen.getByText('Need help finding something?')).toBeInTheDocument()
  })

  it('calls onOpen when the message bubble is clicked', async () => {
    const onOpen = vi.fn()
    render(<ProactiveTeaser message="Need help?" onOpen={onOpen} onDismiss={vi.fn()} />)

    await userEvent.click(screen.getByRole('button', { name: 'Need help?' }))

    expect(onOpen).toHaveBeenCalled()
  })

  it('calls onDismiss (and not onOpen) when the dismiss button is clicked', async () => {
    const onOpen = vi.fn()
    const onDismiss = vi.fn()
    render(<ProactiveTeaser message="Need help?" onOpen={onOpen} onDismiss={onDismiss} />)

    await userEvent.click(screen.getByRole('button', { name: 'Dismiss message' }))

    expect(onDismiss).toHaveBeenCalled()
    expect(onOpen).not.toHaveBeenCalled()
  })
})
