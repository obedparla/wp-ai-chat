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

  it('carousel prev/next buttons have accessible aria-labels', () => {
    render(<ProductGrid products={mockProducts} />)
    expect(screen.getByRole('button', { name: 'Previous slide' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Next slide' })).toBeInTheDocument()
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

  it('renders external products with custom button text linking to external_url', () => {
    const external: Product = {
      id: 10,
      name: 'Affiliate Item',
      url: 'https://example.com/product/10',
      price: '99.00',
      product_type: 'external',
      external_url: 'https://amazon.com/dp/ABC',
      button_text: 'Buy on Amazon',
    }
    render(<ProductGrid products={[external]} />)
    const links = screen.getAllByRole('link')
    const buyLink = links.find((l) => l.textContent?.includes('BUY ON AMAZON'))
    expect(buyLink).toBeDefined()
    expect(buyLink).toHaveAttribute('href', 'https://amazon.com/dp/ABC')
    expect(buyLink).toHaveAttribute('target', '_blank')
  })

  it('falls back to "Buy product" label for external without button_text', () => {
    const external: Product = {
      id: 11,
      name: 'External Item',
      url: 'https://example.com/product/11',
      price: '50.00',
      product_type: 'external',
      external_url: 'https://other.com',
    }
    render(<ProductGrid products={[external]} />)
    expect(screen.getByText(/BUY PRODUCT/i)).toBeInTheDocument()
  })

  it('renders grouped products with View options button', () => {
    const grouped: Product = {
      id: 12,
      name: 'Grouped Item',
      url: 'https://example.com/product/12',
      price: '20.00',
      product_type: 'grouped',
    }
    render(<ProductGrid products={[grouped]} />)
    expect(screen.getByText(/VIEW OPTIONS/i)).toBeInTheDocument()
  })

  it('renders bundle products with View options button', () => {
    const bundle: Product = {
      id: 13,
      name: 'Bundle Item',
      url: 'https://example.com/product/13',
      price: '120.00',
      product_type: 'bundle',
    }
    render(<ProductGrid products={[bundle]} />)
    expect(screen.getByText(/VIEW OPTIONS/i)).toBeInTheDocument()
  })

  it('renders unknown product types as link cards with View product label', () => {
    const unknown: Product = {
      id: 14,
      name: 'Unknown Item',
      url: 'https://example.com/product/14',
      price: '10.00',
      product_type: 'composite' as Product['product_type'],
    }
    render(<ProductGrid products={[unknown]} />)
    expect(screen.getByText(/VIEW PRODUCT/i)).toBeInTheDocument()
  })

  it('renders subscription products as ProductCard with Add button', () => {
    const subscription: Product = {
      id: 15,
      name: 'Monthly Box',
      url: 'https://example.com/product/15',
      price: '29.99',
      product_type: 'subscription',
    }
    render(<ProductGrid products={[subscription]} />)
    expect(screen.getByRole('button', { name: /add to cart/i })).toBeInTheDocument()
  })

  it('renders variable-subscription as VariableProductCard', () => {
    const varSub: Product = {
      ...mockVariableProduct,
      id: 16,
      name: 'Variable Subscription',
      product_type: 'variable-subscription',
    }
    render(<ProductGrid products={[varSub]} />)
    expect(screen.getByLabelText('Size')).toBeInTheDocument()
  })

  it('renders zero-price simple products as View product link without price or ADD', () => {
    const zeroPrice: Product = {
      id: 20,
      name: 'Unpriced Tee',
      url: 'https://example.com/product/20',
      price: '0',
      product_type: 'simple',
    }
    render(<ProductGrid products={[zeroPrice]} />)
    expect(screen.getByText(/VIEW PRODUCT/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /add to cart/i })).not.toBeInTheDocument()
    expect(screen.queryByText('$0.00')).not.toBeInTheDocument()
  })

  it('renders empty-price simple products as View product link without price or ADD', () => {
    const emptyPrice: Product = {
      id: 21,
      name: 'Unpriced Shoes',
      url: 'https://example.com/product/21',
      price: '',
      product_type: 'simple',
    }
    render(<ProductGrid products={[emptyPrice]} />)
    expect(screen.getByText(/VIEW PRODUCT/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /add to cart/i })).not.toBeInTheDocument()
    expect(screen.queryByText('$0.00')).not.toBeInTheDocument()
  })

  it('keeps the ADD button for positively priced simple products', () => {
    render(<ProductGrid products={[mockProducts[0]]} />)
    expect(screen.getByRole('button', { name: /add to cart/i })).toBeInTheDocument()
    expect(screen.getByText('$19.99')).toBeInTheDocument()
  })
})
