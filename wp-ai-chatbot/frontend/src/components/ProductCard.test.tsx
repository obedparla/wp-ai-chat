import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import ProductCard, { Product } from './ProductCard'

describe('ProductCard', () => {
  const mockProduct: Product = {
    id: 1,
    name: 'Test Product',
    url: 'https://example.com/product/1',
    price: '29.99',
    regular_price: '39.99',
    sale_price: '29.99',
    image: 'https://example.com/image.jpg',
    add_to_cart_url: 'https://example.com/cart?add-to-cart=1',
  }

  beforeEach(() => {
    vi.resetAllMocks()
    window.wpaicConfig = undefined
  })

  it('renders product name', () => {
    render(<ProductCard product={mockProduct} />)
    expect(screen.getByText('Test Product')).toBeInTheDocument()
  })

  it('renders product image when provided', () => {
    render(<ProductCard product={mockProduct} />)
    const img = screen.getByRole('img')
    expect(img).toHaveAttribute('src', 'https://example.com/image.jpg')
    expect(img).toHaveAttribute('alt', 'Test Product')
  })

  it('renders placeholder when no image', () => {
    const productNoImage = { ...mockProduct, image: undefined }
    const { container } = render(<ProductCard product={productNoImage} />)
    expect(container.querySelector('.animate-shimmer')).toBeInTheDocument()
  })

  it('links to product URL', () => {
    render(<ProductCard product={mockProduct} />)
    const link = screen.getByRole('link')
    expect(link).toHaveAttribute('href', 'https://example.com/product/1')
    expect(link).toHaveAttribute('target', '_blank')
  })

  it('shows sale price with strikethrough regular price', () => {
    render(<ProductCard product={mockProduct} />)
    expect(screen.getByText('$39.99')).toHaveClass('line-through')
    expect(screen.getByText('$29.99')).toHaveClass('text-red-600')
  })

  it('shows regular price when no discount', () => {
    const productNoSale: Product = {
      id: 2,
      name: 'Regular Product',
      url: 'https://example.com/product/2',
      price: '49.99',
    }
    render(<ProductCard product={productNoSale} />)
    expect(screen.getByText('$49.99')).toBeInTheDocument()
    expect(screen.queryByText(/\$0/)).not.toBeInTheDocument()
  })

  it('shows $0 when price is empty', () => {
    const productNoPrice: Product = {
      id: 3,
      name: 'Free Product',
      url: 'https://example.com/product/3',
      price: '',
    }
    render(<ProductCard product={productNoPrice} />)
    expect(screen.getByText('$0')).toBeInTheDocument()
  })

  it('renders Add to Cart button', () => {
    render(<ProductCard product={mockProduct} />)
    expect(screen.getByRole('button', { name: /add to cart/i })).toBeInTheDocument()
  })

  it('redirects to add_to_cart_url when wcAjaxUrl not configured', () => {
    const originalLocation = window.location
    const mockAssign = vi.fn()
    Object.defineProperty(window, 'location', {
      value: { href: '', assign: mockAssign },
      writable: true,
    })

    render(<ProductCard product={mockProduct} />)
    const button = screen.getByRole('button', { name: /add to cart/i })
    fireEvent.click(button)

    expect(window.location.href).toBe('https://example.com/cart?add-to-cart=1')

    Object.defineProperty(window, 'location', { value: originalLocation, writable: true })
  })

  it('shows loading state when clicked with wcAjaxUrl configured', async () => {
    window.wpaicConfig = {
      apiUrl: 'https://example.com/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      wcAjaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    }

    global.fetch = vi.fn().mockImplementation(
      () =>
        new Promise(() => {
          /* pending promise */
        })
    )

    render(<ProductCard product={mockProduct} />)
    const button = screen.getByRole('button', { name: /add to cart/i })
    fireEvent.click(button)

    await waitFor(() => {
      expect(button).toBeDisabled()
      expect(button.querySelector('.animate-spin')).toBeInTheDocument()
    })
  })

  it('shows success state after successful AJAX add to cart', async () => {
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

    render(<ProductCard product={mockProduct} />)
    const button = screen.getByRole('button', { name: /add to cart/i })
    fireEvent.click(button)

    await waitFor(() => {
      expect(screen.getByText('✓ Added')).toBeInTheDocument()
    })
  })

  it('falls back to redirect on fetch error', async () => {
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

    render(<ProductCard product={mockProduct} />)
    const button = screen.getByRole('button', { name: /add to cart/i })
    fireEvent.click(button)

    await waitFor(() => {
      expect(window.location.href).toBe('https://example.com/cart?add-to-cart=1')
    })

    Object.defineProperty(window, 'location', { value: originalLocation, writable: true })
  })
})
