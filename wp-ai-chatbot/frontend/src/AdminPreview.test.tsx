import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import AdminPreview from './AdminPreview'

describe('AdminPreview teaser variant', () => {
  beforeEach(() => {
    window.wpaicAdminPreview = {
      greeting: 'Hello! How can I help you today?',
      chatbotName: '',
      chatbotLogo: '',
      chatbotRole: '',
      themeColor: '#2545B8',
      proactiveMessage: 'Need a hand?',
    }
  })

  afterEach(() => {
    delete window.wpaicAdminPreview
    document.getElementById('wpaic_proactive_message')?.remove()
  })

  it('renders the configured proactive message in the teaser', () => {
    render(<AdminPreview variant="teaser" />)
    expect(screen.getByText('Need a hand?')).toBeInTheDocument()
  })

  it('falls back to the greeting when no proactive message is set', () => {
    window.wpaicAdminPreview!.proactiveMessage = ''
    render(<AdminPreview variant="teaser" />)
    expect(screen.getByText('Hello! How can I help you today?')).toBeInTheDocument()
  })

  it('live-updates the teaser as the proactive message textarea changes', () => {
    const textarea = document.createElement('textarea')
    textarea.id = 'wpaic_proactive_message'
    document.body.appendChild(textarea)

    render(<AdminPreview variant="teaser" />)

    textarea.value = 'Updated teaser message'
    fireEvent.input(textarea)

    expect(screen.getByText('Updated teaser message')).toBeInTheDocument()
  })

  it('renders the full widget preview by default', () => {
    render(<AdminPreview />)
    expect(screen.getByText('AI Assistant')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Dismiss message' })).not.toBeInTheDocument()
  })
})
