import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react'
import ComparisonTable, { ComparisonData, ComparisonProduct } from './ComparisonTable'

describe('ComparisonTable', () => {
  const mockProducts: ComparisonProduct[] = [
    {
      id: 1,
      name: 'Product A',
      url: 'https://example.com/product/1',
      price: '29.99',
      regular_price: '39.99',
      sale_price: '29.99',
      stock_status: 'instock',
      rating: 4.5,
      categories: ['Electronics', 'Gadgets'],
      image: 'https://example.com/image1.jpg',
      add_to_cart_url: 'https://example.com/cart?add-to-cart=1',
    },
    {
      id: 2,
      name: 'Product B',
      url: 'https://example.com/product/2',
      price: '49.99',
      regular_price: '49.99',
      stock_status: 'outofstock',
      rating: 3.0,
      categories: ['Electronics'],
      image: 'https://example.com/image2.jpg',
      add_to_cart_url: 'https://example.com/cart?add-to-cart=2',
    },
  ]

  const mockData: ComparisonData = {
    products: mockProducts,
    attributes: ['price', 'stock_status', 'rating', 'categories'],
  }

  beforeEach(() => {
    vi.resetAllMocks()
    window.wpaicConfig = undefined
  })

  it('renders nothing when products array is empty', () => {
    const emptyData: ComparisonData = { products: [], attributes: [] }
    const { container } = render(<ComparisonTable data={emptyData} />)
    expect(container.firstChild).toBeNull()
  })

  it('renders product names in header', () => {
    render(<ComparisonTable data={mockData} />)
    expect(screen.getByText('Product A')).toBeInTheDocument()
    expect(screen.getByText('Product B')).toBeInTheDocument()
  })

  it('renders product images', () => {
    render(<ComparisonTable data={mockData} />)
    const images = screen.getAllByRole('img')
    expect(images).toHaveLength(2)
    expect(images[0]).toHaveAttribute('src', 'https://example.com/image1.jpg')
  })

  it('renders attribute labels', () => {
    render(<ComparisonTable data={mockData} />)
    expect(screen.getByText('Price')).toBeInTheDocument()
    expect(screen.getByText('Availability')).toBeInTheDocument()
    expect(screen.getByText('Rating')).toBeInTheDocument()
    expect(screen.getByText('Categories')).toBeInTheDocument()
  })

  it('shows sale price with strikethrough', () => {
    render(<ComparisonTable data={mockData} />)
    expect(screen.getByText('$39.99')).toHaveClass('line-through')
    expect(screen.getByText('$29.99')).toHaveClass('text-red-600')
  })

  it('shows stock status labels', () => {
    render(<ComparisonTable data={mockData} />)
    expect(screen.getByText('In Stock')).toBeInTheDocument()
    expect(screen.getByText('Out of Stock')).toBeInTheDocument()
  })

  it('shows rating with stars', () => {
    render(<ComparisonTable data={mockData} />)
    expect(screen.getByText('(4.5)')).toBeInTheDocument()
    expect(screen.getByText('(3.0)')).toBeInTheDocument()
  })

  it('shows categories joined by comma', () => {
    render(<ComparisonTable data={mockData} />)
    expect(screen.getByText('Electronics, Gadgets')).toBeInTheDocument()
  })

  it('highlights best price value', () => {
    const { container } = render(<ComparisonTable data={mockData} />)
    const bestCells = container.querySelectorAll('.bg-green-50')
    expect(bestCells.length).toBeGreaterThan(0)
  })

  it('renders Add to Cart buttons for each product', () => {
    render(<ComparisonTable data={mockData} />)
    const buttons = screen.getAllByRole('button', { name: /add to cart/i })
    expect(buttons).toHaveLength(2)
  })

  it('redirects to add_to_cart_url when wcAjaxUrl not configured', () => {
    const originalLocation = window.location
    Object.defineProperty(window, 'location', {
      value: { href: '' },
      writable: true,
    })

    render(<ComparisonTable data={mockData} />)
    const buttons = screen.getAllByRole('button', { name: /add to cart/i })
    fireEvent.click(buttons[0])

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

    render(<ComparisonTable data={mockData} />)
    const buttons = screen.getAllByRole('button', { name: /add to cart/i })
    fireEvent.click(buttons[0])

    await waitFor(() => {
      expect(buttons[0]).toBeDisabled()
      expect(buttons[0].querySelector('.animate-spin')).toBeInTheDocument()
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

    render(<ComparisonTable data={mockData} />)
    const buttons = screen.getAllByRole('button', { name: /add to cart/i })
    fireEvent.click(buttons[0])

    await waitFor(() => {
      expect(screen.getByText('✓ Added')).toBeInTheDocument()
    })
  })

  it('updates cart fragments after successful AJAX add to cart', async () => {
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
          cart_hash: 'hash-3',
          fragments: {
            'div.cart-count': '<div class="cart-count">3</div>',
          },
        }),
    })

    render(<ComparisonTable data={mockData} />)
    const buttons = screen.getAllByRole('button', { name: /add to cart/i })
    fireEvent.click(buttons[0])

    await waitFor(() => {
      expect(document.querySelector('.cart-count')?.textContent).toBe('3')
    })
  })

  it('links to product URL from header', () => {
    render(<ComparisonTable data={mockData} />)
    const links = screen.getAllByRole('link')
    expect(links[0]).toHaveAttribute('href', 'https://example.com/product/1')
    expect(links[1]).toHaveAttribute('href', 'https://example.com/product/2')
  })

  it('shows dash for null rating', () => {
    const dataWithNoRating: ComparisonData = {
      products: [
        { ...mockProducts[0], rating: null },
        { ...mockProducts[1], rating: undefined },
      ],
      attributes: ['rating'],
    }
    render(<ComparisonTable data={dataWithNoRating} />)
    const dashes = screen.getAllByText('—')
    expect(dashes.length).toBeGreaterThanOrEqual(2)
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

    render(<ComparisonTable data={mockData} />)
    const buttons = screen.getAllByRole('button', { name: /add to cart/i })
    fireEvent.click(buttons[0])

    await act(async () => {})
    expect(screen.getByText('Error')).toBeInTheDocument()
    expect(window.location.href).toBe('')

    await act(async () => {
      await vi.advanceTimersByTimeAsync(2500)
    })
    expect(screen.queryByText('Error')).not.toBeInTheDocument()

    Object.defineProperty(window, 'location', { value: originalLocation, writable: true })
    vi.useRealTimers()
  })

  it('uses the flat rounded-full CTA styling', () => {
    render(<ComparisonTable data={mockData} />)
    const buttons = screen.getAllByRole('button', { name: /add to cart/i })
    expect(buttons[0].className).toContain('rounded-full')
    expect(buttons[0].className).not.toContain('rounded-lg')
    expect(buttons[0].className).not.toContain('gradient')
  })

  it('renders attribute, weight and dimension rows when the payload includes them', () => {
    const richData: ComparisonData = {
      products: [
        {
          ...mockProducts[0],
          attributes: { Color: 'Blue, Red', Material: 'Cotton' },
          weight: '1.5 kg',
          dimensions: '10 x 20 x 5 cm',
        },
        {
          ...mockProducts[1],
          attributes: { Color: 'Green' },
          weight: '2 kg',
        },
      ],
      attributes: ['price'],
    }
    render(<ComparisonTable data={richData} />)

    expect(screen.getByText('Color')).toBeInTheDocument()
    expect(screen.getByText('Blue, Red')).toBeInTheDocument()
    expect(screen.getByText('Green')).toBeInTheDocument()
    expect(screen.getByText('Material')).toBeInTheDocument()
    expect(screen.getByText('Cotton')).toBeInTheDocument()
    expect(screen.getByText('Weight')).toBeInTheDocument()
    expect(screen.getByText('1.5 kg')).toBeInTheDocument()
    expect(screen.getByText('2 kg')).toBeInTheDocument()
    expect(screen.getByText('Dimensions')).toBeInTheDocument()
    expect(screen.getByText('10 x 20 x 5 cm')).toBeInTheDocument()
    // Product B has no Material or dimensions: dashes fill the gaps.
    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(2)
  })

  it('renders no attribute, weight or dimension rows when the payload omits them', () => {
    render(<ComparisonTable data={mockData} />)
    expect(screen.queryByText('Weight')).not.toBeInTheDocument()
    expect(screen.queryByText('Dimensions')).not.toBeInTheDocument()
  })

  describe('expanded dialog', () => {
    it('opens the fullscreen dialog from the expand button and focuses the close button', () => {
      render(<ComparisonTable data={mockData} />)
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'Expand comparison' }))

      expect(screen.getByRole('dialog')).toBeInTheDocument()
      // Both grids render: product names appear twice.
      expect(screen.getAllByText('Product A')).toHaveLength(2)
      // The dialog grid has no expand button of its own.
      expect(screen.getAllByRole('button', { name: 'Expand comparison' })).toHaveLength(1)
      // Focus moves into the dialog so the focus trap is active immediately.
      expect(screen.getByRole('button', { name: 'Close comparison' })).toHaveFocus()
    })

    it('closes on Escape without the event reaching document-level bubble listeners', () => {
      const bubbleListener = vi.fn()
      document.addEventListener('keydown', bubbleListener)
      render(<ComparisonTable data={mockData} />)
      fireEvent.click(screen.getByRole('button', { name: 'Expand comparison' }))

      fireEvent.keyDown(screen.getByRole('button', { name: 'Close comparison' }), { key: 'Escape' })

      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      expect(bubbleListener).not.toHaveBeenCalled()
      document.removeEventListener('keydown', bubbleListener)
    })

    it('closes on backdrop click but not on clicks inside the panel', () => {
      render(<ComparisonTable data={mockData} />)
      fireEvent.click(screen.getByRole('button', { name: 'Expand comparison' }))

      fireEvent.click(screen.getAllByText('Product A')[1])
      expect(screen.getByRole('dialog')).toBeInTheDocument()

      fireEvent.click(screen.getByRole('dialog'))
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })
  })
})
