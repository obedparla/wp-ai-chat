import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import ProductGrid from './ProductGrid'
import { Product } from './ProductCard'

describe('ProductGrid', () => {
  const mockProducts: Product[] = [
    {
      id: 1,
      name: 'Product One',
      url: 'https://example.com/product/1',
      price: '19.99',
      product_type: 'simple',
    },
    {
      id: 2,
      name: 'Product Two',
      url: 'https://example.com/product/2',
      price: '29.99',
      product_type: 'simple',
    },
    {
      id: 3,
      name: 'Product Three',
      url: 'https://example.com/product/3',
      price: '39.99',
      product_type: 'simple',
    },
  ]

  const mockVariableProduct: Product = {
    id: 4,
    name: 'Variable Product',
    url: 'https://example.com/product/4',
    price: '49.99',
    product_type: 'variable',
    is_complex: false,
    attributes: [{ name: 'pa_size', label: 'Size', options: ['S', 'M', 'L'] }],
    variations: [
      {
        variation_id: 101,
        attributes: { attribute_pa_size: 'S' },
        price: 49.99,
        regular_price: 49.99,
        is_in_stock: true,
      },
    ],
  }

  it('renders all products', () => {
    render(<ProductGrid products={mockProducts} />)
    expect(screen.getByText('Product One')).toBeInTheDocument()
    expect(screen.getByText('Product Two')).toBeInTheDocument()
    expect(screen.getByText('Product Three')).toBeInTheDocument()
  })

  it('renders nothing when products array is empty', () => {
    const { container } = render(<ProductGrid products={[]} />)
    expect(container.firstChild).toBeNull()
  })

  it('renders grid for 1-2 products, carousel for 3+', () => {
    // 2 products = grid (uses grid CSS classes)
    const { container: gridContainer, unmount } = render(
      <ProductGrid products={mockProducts.slice(0, 2)} />
    )
    const gridDiv = gridContainer.querySelector('.grid')
    expect(gridDiv).toBeInTheDocument()
    unmount()

    // 3+ products = carousel (uses carousel component)
    const { container: carouselContainer } = render(
      <ProductGrid products={mockProducts} />
    )
    expect(carouselContainer.querySelector('[data-slot="carousel"]')).toBeInTheDocument()
  })

  it('renders correct number of product cards', () => {
    render(<ProductGrid products={mockProducts} />)
    const links = screen.getAllByRole('link')
    expect(links).toHaveLength(3)
  })

  it('renders VariableProductCard for simple variable products', () => {
    render(<ProductGrid products={[mockVariableProduct]} />)
    expect(screen.getByText('Variable Product')).toBeInTheDocument()
    expect(screen.getByLabelText('Size')).toBeInTheDocument()
  })

  it('renders ProductCard for complex variable products', () => {
    const complexProduct: Product = {
      ...mockVariableProduct,
      id: 5,
      name: 'Complex Variable',
      is_complex: true,
      variations: undefined,
    }
    render(<ProductGrid products={[complexProduct]} />)
    expect(screen.getByText('Complex Variable')).toBeInTheDocument()
    expect(screen.queryByLabelText('Size')).not.toBeInTheDocument()
  })

  it('mixes simple and variable products', () => {
    render(<ProductGrid products={[...mockProducts, mockVariableProduct]} />)
    expect(screen.getByText('Product One')).toBeInTheDocument()
    expect(screen.getByText('Variable Product')).toBeInTheDocument()
    expect(screen.getByLabelText('Size')).toBeInTheDocument()
  })
})
