import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
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
})
