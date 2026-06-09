import { describe, it, expect, vi, afterEach } from 'vitest'
import { applyCartUpdate, hasCartUpdateError, requestAddToCart } from './cart'

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

describe('requestAddToCart', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('posts product_id and quantity to the WooCommerce AJAX endpoint', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ success: true, fragments: {} }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const data = await requestAddToCart({ productId: 7, quantity: 2 }, 'https://shop.test/admin-ajax.php')

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('action=woocommerce_ajax_add_to_cart')
    expect(url).toContain('product_id=7')
    expect(url).toContain('quantity=2')
    expect(url).not.toContain('variation_id')
    expect(init).toMatchObject({ method: 'POST', credentials: 'same-origin' })
    expect(data).toEqual({ success: true, fragments: {} })
  })

  it('includes variation_id when provided', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ success: true }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await requestAddToCart({ productId: 7, variationId: 42 }, 'https://shop.test/admin-ajax.php')

    expect(fetchMock.mock.calls[0][0]).toContain('variation_id=42')
  })

  it('throws when the HTTP response is not ok', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500 }))

    await expect(
      requestAddToCart({ productId: 7 }, 'https://shop.test/admin-ajax.php')
    ).rejects.toThrow()
  })

  it('throws when WooCommerce returns an error payload', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: true, json: async () => ({ success: false }) })
    )

    await expect(
      requestAddToCart({ productId: 7 }, 'https://shop.test/admin-ajax.php')
    ).rejects.toThrow()
  })
})
