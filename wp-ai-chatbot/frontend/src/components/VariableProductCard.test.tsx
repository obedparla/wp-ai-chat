import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
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

  it('shows Select Options when no attributes selected', () => {
    render(<VariableProductCard product={mockVariableProduct} />)
    expect(screen.getByRole('button', { name: /add to cart/i })).toHaveTextContent('Select Options')
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
    expect(button).toHaveTextContent('Add to Cart')
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

    expect(screen.getByRole('button')).toHaveTextContent('Out of Stock')
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
      expect(screen.getByText('✓ Added')).toBeInTheDocument()
    })
  })
})
