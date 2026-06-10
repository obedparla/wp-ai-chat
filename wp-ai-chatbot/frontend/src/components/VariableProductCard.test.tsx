import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react'
import VariableProductCard from './VariableProductCard'
import { Product } from './ProductCard'

describe('VariableProductCard', () => {
  const mockVariableProduct: Product = {
    id: 1,
    name: 'Variable T-Shirt',
    url: 'https://example.com/product/1',
    price: '19.99',
    regular_price: '29.99',
    sale_price: '19.99',
    image: 'https://example.com/image.jpg',
    product_type: 'variable',
    is_complex: false,
    variation_count: 4,
    attributes: [
      { name: 'pa_color', label: 'Color', options: ['Red', 'Blue'] },
      { name: 'pa_size', label: 'Size', options: ['S', 'M'] },
    ],
    variations: [
      {
        variation_id: 101,
        attributes: { attribute_pa_color: 'Red', attribute_pa_size: 'S' },
        price: 19.99,
        regular_price: 29.99,
        is_in_stock: true,
      },
      {
        variation_id: 102,
        attributes: { attribute_pa_color: 'Red', attribute_pa_size: 'M' },
        price: 21.99,
        regular_price: 29.99,
        is_in_stock: true,
      },
      {
        variation_id: 103,
        attributes: { attribute_pa_color: 'Blue', attribute_pa_size: 'S' },
        price: 19.99,
        regular_price: 29.99,
        is_in_stock: false,
      },
      {
        variation_id: 104,
        attributes: { attribute_pa_color: 'Blue', attribute_pa_size: 'M' },
        price: 24.99,
        regular_price: 29.99,
        is_in_stock: true,
      },
    ],
  }

  beforeEach(() => {
    vi.resetAllMocks()
    window.wpaicConfig = undefined
  })

  it('renders product name', () => {
    render(<VariableProductCard product={mockVariableProduct} />)
    expect(screen.getByText('Variable T-Shirt')).toBeInTheDocument()
  })

  it('renders attribute dropdowns', () => {
    render(<VariableProductCard product={mockVariableProduct} />)
    expect(screen.getByLabelText('Color')).toBeInTheDocument()
    expect(screen.getByLabelText('Size')).toBeInTheDocument()
  })

  it('renders human option labels while keeping slug option values', () => {
    const productWithLabels: Product = {
      ...mockVariableProduct,
      attributes: [
        {
          name: 'pa_color',
          label: 'Color',
          options: ['navy-blue', 'red'],
          option_labels: { 'navy-blue': 'Navy Blue', red: 'Red' },
        },
      ],
      variations: [],
    }

    render(<VariableProductCard product={productWithLabels} />)

    const option = screen.getByRole('option', { name: 'Navy Blue' }) as HTMLOptionElement
    expect(option.value).toBe('navy-blue')
  })

  it('falls back to the raw option when no labels map is provided', () => {
    render(<VariableProductCard product={mockVariableProduct} />)
    expect(screen.getByRole('option', { name: 'Blue' })).toBeInTheDocument()
  })

  it('shows Pick label when no attributes selected', () => {
    render(<VariableProductCard product={mockVariableProduct} />)
    expect(screen.getByRole('button', { name: /add to cart/i })).toHaveTextContent('PICK')
  })

  it('disables Add to Cart until all attributes selected', () => {
    render(<VariableProductCard product={mockVariableProduct} />)
    const button = screen.getByRole('button', { name: /add to cart/i })
    expect(button).toBeDisabled()
  })

  it('enables Add to Cart when all attributes selected', () => {
    render(<VariableProductCard product={mockVariableProduct} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Red' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'S' } })

    const button = screen.getByRole('button', { name: /add to cart/i })
    expect(button).not.toBeDisabled()
    expect(button).toHaveTextContent('ADD')
  })

  it('updates price when variation is selected', () => {
    render(<VariableProductCard product={mockVariableProduct} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Blue' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'M' } })

    expect(screen.getByText('$24.99')).toBeInTheDocument()
  })

  it('shows Out of Stock for unavailable variations', () => {
    render(<VariableProductCard product={mockVariableProduct} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Blue' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'S' } })

    expect(screen.getByRole('button')).toHaveTextContent('SOLD OUT')
    expect(screen.getByRole('button')).toBeDisabled()
  })

  it('sends variation_id when adding to cart', async () => {
    window.wpaicConfig = {
      apiUrl: 'https://example.com/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      wcAjaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    }

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ success: true }),
    })

    render(<VariableProductCard product={mockVariableProduct} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Red' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'S' } })

    const button = screen.getByRole('button', { name: /add to cart/i })
    fireEvent.click(button)

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('variation_id=101'),
        expect.any(Object)
      )
    })
  })

  it('fires the WC AJAX request with attribute params for a custom-attribute variation (Hoodie regression)', async () => {
    // Mirrors the backend payload: custom attribute "Logo" is sanitized to
    // name "logo" so it matches the variation's attribute_logo key.
    const hoodie: Product = {
      id: 2,
      name: 'Hoodie',
      url: 'https://example.com/product/hoodie',
      price: '42.00',
      product_type: 'variable',
      is_complex: false,
      variation_count: 2,
      attributes: [
        { name: 'pa_color', label: 'Color', options: ['blue', 'green'] },
        { name: 'logo', label: 'Logo', options: ['Yes', 'No'] },
      ],
      variations: [
        {
          variation_id: 201,
          attributes: { attribute_pa_color: 'blue', attribute_logo: 'Yes' },
          price: 45,
          regular_price: 45,
          is_in_stock: true,
        },
        {
          variation_id: 202,
          attributes: { attribute_pa_color: 'blue', attribute_logo: 'No' },
          price: 42,
          regular_price: 45,
          is_in_stock: true,
        },
      ],
    }

    window.wpaicConfig = {
      apiUrl: 'https://example.com/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      wcAjaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    }

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ success: true }),
    })

    render(<VariableProductCard product={hoodie} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'blue' } })
    fireEvent.change(screen.getByLabelText('Logo'), { target: { value: 'Yes' } })

    // Matched variation price replaces the product-level price.
    expect(screen.getByText('$45.00')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: /add to cart/i }))

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(1)
    })

    const requestUrl = vi.mocked(global.fetch).mock.calls[0][0] as string
    expect(requestUrl).toContain('variation_id=201')
    expect(requestUrl).toContain('attribute_pa_color=blue')
    expect(requestUrl).toContain('attribute_logo=Yes')
  })

  it('disables Add with an unavailable hint when the combination matches no variation', () => {
    const productWithGap: Product = {
      ...mockVariableProduct,
      variations: mockVariableProduct.variations!.filter(
        (variation) => variation.variation_id !== 102
      ),
    }

    global.fetch = vi.fn()

    render(<VariableProductCard product={productWithGap} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Red' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'M' } })

    const button = screen.getByRole('button')
    expect(button).toBeDisabled()
    expect(button).toHaveTextContent('UNAVAILABLE')
    expect(screen.getByRole('alert')).toHaveTextContent(/combination isn't available/i)

    fireEvent.click(button)
    expect(global.fetch).not.toHaveBeenCalled()
  })

  it('shows success state after adding to cart', async () => {
    window.wpaicConfig = {
      apiUrl: 'https://example.com/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      wcAjaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    }

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ success: true }),
    })

    render(<VariableProductCard product={mockVariableProduct} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Red' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'S' } })

    const button = screen.getByRole('button', { name: /add to cart/i })
    fireEvent.click(button)

    await waitFor(() => {
      expect(screen.getByText('ADDED')).toBeInTheDocument()
    })
  })

  it('updates cart fragments after adding a variation to cart', async () => {
    document.body.insertAdjacentHTML('afterbegin', '<div class="cart-count">0</div>')

    window.wpaicConfig = {
      apiUrl: 'https://example.com/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      wcAjaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    }

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () =>
        Promise.resolve({
          success: true,
          cart_hash: 'hash-2',
          fragments: {
            'div.cart-count': '<div class="cart-count">2</div>',
          },
        }),
    })

    render(<VariableProductCard product={mockVariableProduct} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Red' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'S' } })
    fireEvent.click(screen.getByRole('button', { name: /add to cart/i }))

    await waitFor(() => {
      expect(document.querySelector('.cart-count')?.textContent).toBe('2')
    })
  })

  it('shows an inline error state on fetch failure and auto-resets without navigating', async () => {
    vi.useFakeTimers()
    const originalLocation = window.location
    Object.defineProperty(window, 'location', {
      value: { href: '' },
      writable: true,
    })

    window.wpaicConfig = {
      apiUrl: 'https://example.com/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      wcAjaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    }

    global.fetch = vi.fn().mockRejectedValue(new Error('Network error'))

    render(<VariableProductCard product={mockVariableProduct} />)

    fireEvent.change(screen.getByLabelText('Color'), { target: { value: 'Red' } })
    fireEvent.change(screen.getByLabelText('Size'), { target: { value: 'S' } })
    fireEvent.click(screen.getByRole('button', { name: /add to cart/i }))

    await act(async () => {})
    expect(screen.getByText('ERROR')).toBeInTheDocument()
    expect(window.location.href).toBe('')

    await act(async () => {
      await vi.advanceTimersByTimeAsync(2500)
    })
    expect(screen.queryByText('ERROR')).not.toBeInTheDocument()
    expect(screen.getByText('ADD')).toBeInTheDocument()

    Object.defineProperty(window, 'location', { value: originalLocation, writable: true })
    vi.useRealTimers()
  })
})
