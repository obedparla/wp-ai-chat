import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import AddToCartTrigger from './AddToCartTrigger'
import {
  clearStoredAddToCartStatuses,
  markAddToCartToolCallsRestored,
} from '../hooks/useAddToCart'

vi.mock('@/lib/cart', () => ({
  requestAddToCart: vi.fn(),
  applyCartUpdate: vi.fn(),
}))

import { requestAddToCart, applyCartUpdate } from '@/lib/cart'

const mockRequest = requestAddToCart as ReturnType<typeof vi.fn>
const mockApply = applyCartUpdate as ReturnType<typeof vi.fn>

describe('AddToCartTrigger', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
    clearStoredAddToCartStatuses()
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'n',
      greeting: 'hi',
      wcAjaxUrl: 'https://shop.test/admin-ajax.php',
    }
  })

  afterEach(() => {
    window.wpaicConfig = undefined
  })

  it('fires the add-to-cart request once on mount and applies the cart update', async () => {
    mockRequest.mockResolvedValue({ success: true, fragments: {} })

    render(
      <AddToCartTrigger intent={{ toolCallId: 'a1', productId: 5, variationId: 9, quantity: 2 }} />
    )

    await waitFor(() => expect(mockApply).toHaveBeenCalled())

    expect(mockRequest).toHaveBeenCalledTimes(1)
    expect(mockRequest).toHaveBeenCalledWith(
      { productId: 5, variationId: 9, quantity: 2 },
      'https://shop.test/admin-ajax.php'
    )
    expect(await screen.findByText(/added to cart/i)).toBeInTheDocument()
  })

  it('shows an error state when the request fails', async () => {
    mockRequest.mockRejectedValue(new Error('nope'))

    render(<AddToCartTrigger intent={{ toolCallId: 'b2', productId: 5, quantity: 1 }} />)

    expect(await screen.findByText(/could not add/i)).toBeInTheDocument()
    expect(mockApply).not.toHaveBeenCalled()
  })

  it('shows an error state when no WooCommerce AJAX URL is configured', async () => {
    window.wpaicConfig = { apiUrl: '/x', nonce: 'n', greeting: 'hi' }

    render(<AddToCartTrigger intent={{ toolCallId: 'c3', productId: 5, quantity: 1 }} />)

    expect(await screen.findByText(/could not add/i)).toBeInTheDocument()
    expect(mockRequest).not.toHaveBeenCalled()
  })

  it('persists the added status for future page loads', async () => {
    mockRequest.mockResolvedValue({ success: true, fragments: {} })

    render(<AddToCartTrigger intent={{ toolCallId: 'd4', productId: 5, quantity: 1 }} />)

    await waitFor(() => expect(mockApply).toHaveBeenCalled())

    const stored = JSON.parse(sessionStorage.getItem('wpaic_add_to_cart_status') ?? '{}')
    expect(stored.d4).toBe('added')
  })

  it('renders the stored added badge without re-firing the cart request', async () => {
    sessionStorage.setItem('wpaic_add_to_cart_status', JSON.stringify({ e5: 'added' }))

    render(<AddToCartTrigger intent={{ toolCallId: 'e5', productId: 5, quantity: 1 }} />)

    expect(await screen.findByText(/added to cart/i)).toBeInTheDocument()
    expect(mockRequest).not.toHaveBeenCalled()
  })

  it('renders the stored error badge without re-firing the cart request', async () => {
    sessionStorage.setItem('wpaic_add_to_cart_status', JSON.stringify({ f6: 'error' }))

    render(<AddToCartTrigger intent={{ toolCallId: 'f6', productId: 5, quantity: 1 }} />)

    expect(await screen.findByText(/could not add/i)).toBeInTheDocument()
    expect(mockRequest).not.toHaveBeenCalled()
  })

  it('never executes intents restored from chat history, even without a stored status', async () => {
    markAddToCartToolCallsRestored(['g7'])

    render(<AddToCartTrigger intent={{ toolCallId: 'g7', productId: 5, quantity: 1 }} />)

    expect(await screen.findByText(/added to cart/i)).toBeInTheDocument()
    expect(mockRequest).not.toHaveBeenCalled()
  })
})
