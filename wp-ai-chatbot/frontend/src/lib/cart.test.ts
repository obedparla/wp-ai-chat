import { describe, it, expect, vi, afterEach } from 'vitest'
import { applyCartUpdate, hasCartUpdateError } from './cart'

describe('cart helpers', () => {
  afterEach(() => {
    document.body.innerHTML = ''
    delete (window as typeof window & { jQuery?: unknown }).jQuery
  })

  it('replaces matching WooCommerce fragments in the DOM', () => {
    document.body.innerHTML = '<div class="cart-count">0</div>'

    applyCartUpdate({
      fragments: {
        'div.cart-count': '<div class="cart-count">3</div>',
      },
    })

    expect(document.querySelector('.cart-count')?.textContent).toBe('3')
  })

  it('triggers WooCommerce added_to_cart when jQuery is available', () => {
    const trigger = vi.fn()
    const jQuery = vi.fn(() => ({ trigger }))
    ;(window as typeof window & { jQuery?: typeof jQuery }).jQuery = jQuery

    applyCartUpdate({
      fragments: {
        'div.cart-count': '<div class="cart-count">1</div>',
      },
      cart_hash: 'hash-123',
    })

    expect(jQuery).toHaveBeenCalledWith(document.body)
    expect(trigger).toHaveBeenCalledWith('added_to_cart', [
      { 'div.cart-count': '<div class="cart-count">1</div>' },
      'hash-123',
      false,
    ])
  })

  it('treats WordPress AJAX error payloads as failures', () => {
    expect(hasCartUpdateError({ success: false })).toBe(true)
    expect(hasCartUpdateError({ error: true })).toBe(true)
    expect(hasCartUpdateError({ success: true })).toBe(false)
  })
})
