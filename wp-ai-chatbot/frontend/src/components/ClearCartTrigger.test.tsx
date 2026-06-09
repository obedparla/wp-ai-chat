import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import ClearCartTrigger from './ClearCartTrigger'
import type { ClearCartIntent } from '../hooks/useChat'

const clearAllIntent: ClearCartIntent = {
  toolCallId: 'cc-1',
  clearAll: true,
  items: [{ productId: 1, name: 'Water', removeQuantity: 2, removeAll: true }],
}
const removeOneIntent: ClearCartIntent = {
  toolCallId: 'cc-2',
  clearAll: false,
  items: [{ productId: 1, name: 'Water', removeQuantity: 1, removeAll: true }],
}
const removePartialIntent: ClearCartIntent = {
  toolCallId: 'cc-4',
  clearAll: false,
  items: [{ productId: 1, name: 'Water', removeQuantity: 2, removeAll: false }],
}
const removeManyIntent: ClearCartIntent = {
  toolCallId: 'cc-3',
  clearAll: false,
  items: [
    { productId: 1, name: 'Water', removeQuantity: 1, removeAll: true },
    { productId: 2, name: 'Soda', removeQuantity: 1, removeAll: true },
  ],
}

describe('ClearCartTrigger', () => {
  it('renders nothing while awaiting confirmation', () => {
    const { container } = render(<ClearCartTrigger intent={clearAllIntent} status="pending" />)
    expect(container).toBeEmptyDOMElement()
  })

  it('renders nothing when there is no status', () => {
    const { container } = render(<ClearCartTrigger intent={clearAllIntent} />)
    expect(container).toBeEmptyDOMElement()
  })

  it('shows a clearing label', () => {
    render(<ClearCartTrigger intent={clearAllIntent} status="clearing" />)
    expect(screen.getByText(/updating cart/i)).toBeInTheDocument()
  })

  it('confirms the whole cart was cleared', () => {
    render(<ClearCartTrigger intent={clearAllIntent} status="cleared" />)
    expect(screen.getByText(/cart cleared/i)).toBeInTheDocument()
  })

  it('names a single fully removed item', () => {
    render(<ClearCartTrigger intent={removeOneIntent} status="cleared" />)
    expect(screen.getByText(/^Removed Water$/i)).toBeInTheDocument()
  })

  it('shows the removed quantity for a partial removal', () => {
    render(<ClearCartTrigger intent={removePartialIntent} status="cleared" />)
    expect(screen.getByText(/removed 2 × water/i)).toBeInTheDocument()
  })

  it('summarizes multiple removed items', () => {
    render(<ClearCartTrigger intent={removeManyIntent} status="cleared" />)
    expect(screen.getByText(/items removed/i)).toBeInTheDocument()
  })

  it('reflects a cancelled action', () => {
    render(<ClearCartTrigger intent={clearAllIntent} status="cancelled" />)
    expect(screen.getByText(/kept your cart/i)).toBeInTheDocument()
  })

  it('shows an error state', () => {
    render(<ClearCartTrigger intent={clearAllIntent} status="error" />)
    expect(screen.getByText(/could not update cart/i)).toBeInTheDocument()
  })
})
