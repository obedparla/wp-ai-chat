import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import type { ClearCartItem, Message } from './useChat'

vi.mock('../lib/cart', () => ({
  requestClearCart: vi.fn(),
  applyCartUpdate: vi.fn(),
}))

import { requestClearCart, applyCartUpdate } from '../lib/cart'
import { useClearCart } from './useClearCart'

const mockRequest = requestClearCart as ReturnType<typeof vi.fn>
const mockApply = applyCartUpdate as ReturnType<typeof vi.fn>

function messageWithIntent(
  toolCallId: string,
  clearAll: boolean,
  items: ClearCartItem[] = [{ productId: 7, name: 'Water', removeQuantity: 2, removeAll: true }]
): Message {
  return {
    role: 'assistant',
    content: '',
    clearCartIntents: [{ toolCallId, clearAll, items }],
  }
}

describe('useClearCart', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    sessionStorage.clear()
    window.wpaicConfig = {
      apiUrl: '/x',
      nonce: 'n',
      greeting: 'hi',
      wcAjaxUrl: 'https://shop.test/admin-ajax.php',
    }
  })

  afterEach(() => {
    window.wpaicConfig = undefined
  })

  it('exposes the first unhandled intent as pending', () => {
    const { result } = renderHook(() => useClearCart([messageWithIntent('cc-1', true)]))
    expect(result.current.pending?.toolCallId).toBe('cc-1')
  })

  it('clears the whole cart on confirm and marks it cleared', async () => {
    mockRequest.mockResolvedValue({ success: true, fragments: {} })
    const { result } = renderHook(() => useClearCart([messageWithIntent('cc-1', true)]))

    act(() => result.current.confirm())

    await waitFor(() => expect(result.current.statuses['cc-1']).toBe('cleared'))
    expect(mockRequest).toHaveBeenCalledWith(undefined, 'https://shop.test/admin-ajax.php')
    expect(mockApply).toHaveBeenCalled()
    expect(result.current.pending).toBeNull()
  })

  it('passes the per-item remove quantities when removing specific items', async () => {
    mockRequest.mockResolvedValue({ success: true })
    const { result } = renderHook(() =>
      useClearCart([
        messageWithIntent('cc-2', false, [
          { productId: 7, name: 'Water', removeQuantity: 2, removeAll: false },
          { productId: 9, name: 'Soda', removeQuantity: 1, removeAll: true },
        ]),
      ])
    )

    act(() => result.current.confirm())

    await waitFor(() => expect(result.current.statuses['cc-2']).toBe('cleared'))
    expect(mockRequest).toHaveBeenCalledWith(
      [
        { productId: 7, quantity: 2 },
        { productId: 9, quantity: 1 },
      ],
      'https://shop.test/admin-ajax.php'
    )
  })

  it('marks the intent cancelled and clears pending on cancel', () => {
    const { result } = renderHook(() => useClearCart([messageWithIntent('cc-3', true)]))

    act(() => result.current.cancel())

    expect(result.current.statuses['cc-3']).toBe('cancelled')
    expect(result.current.pending).toBeNull()
    expect(mockRequest).not.toHaveBeenCalled()
  })

  it('does not re-open an already handled intent after reload', () => {
    sessionStorage.setItem('wpaic_clear_cart_status', JSON.stringify({ 'cc-4': 'cleared' }))

    const { result } = renderHook(() => useClearCart([messageWithIntent('cc-4', true)]))

    expect(result.current.pending).toBeNull()
    expect(result.current.statuses['cc-4']).toBe('cleared')
  })

  it('errors when no WooCommerce AJAX url is configured', () => {
    window.wpaicConfig = { apiUrl: '/x', nonce: 'n', greeting: 'hi' }
    const { result } = renderHook(() => useClearCart([messageWithIntent('cc-5', true)]))

    act(() => result.current.confirm())

    expect(result.current.statuses['cc-5']).toBe('error')
    expect(mockRequest).not.toHaveBeenCalled()
  })
})
