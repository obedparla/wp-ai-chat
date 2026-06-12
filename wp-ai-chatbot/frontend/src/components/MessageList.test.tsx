import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent, act } from '@testing-library/react'
import MessageList, { curateProducts, MAX_RENDERED_PRODUCTS } from './MessageList'
import type { Message } from '../hooks/useChat'
import type { Product } from './ProductCard'
import type { ComparisonData } from './ComparisonTable'

function makeMessages(count: number): Message[] {
  return Array.from({ length: count }, (_, i) => ({
    role: i % 2 === 0 ? 'assistant' : 'user',
    content: `Message ${i + 1}`,
    id: `m-${i}`,
  }))
}

function getScrollContainer(): HTMLDivElement {
  const button = screen.queryByLabelText('Jump to latest message')
  // The scrollable container is the sibling of the optional button. Find it via the overflow-y-auto class.
  const container = document.querySelector('.overflow-y-auto') as HTMLDivElement
  if (!container) throw new Error('scroll container not found')
  // Reference button so unused-variable lint stays quiet when not present.
  void button
  return container
}

function setScrollGeometry(container: HTMLDivElement, opts: { scrollHeight: number; clientHeight: number; scrollTop: number }) {
  Object.defineProperty(container, 'scrollHeight', { configurable: true, value: opts.scrollHeight })
  Object.defineProperty(container, 'clientHeight', { configurable: true, value: opts.clientHeight })
  Object.defineProperty(container, 'scrollTop', { configurable: true, writable: true, value: opts.scrollTop })
}

describe('MessageList jump-to-latest button', () => {
  it('does not show the button initially when at bottom', () => {
    render(<MessageList messages={makeMessages(3)} />)
    expect(screen.queryByLabelText('Jump to latest message')).not.toBeInTheDocument()
  })

  it('shows the button when user scrolls away from the bottom', () => {
    render(<MessageList messages={makeMessages(20)} />)
    const container = getScrollContainer()
    setScrollGeometry(container, { scrollHeight: 1000, clientHeight: 400, scrollTop: 100 })

    act(() => {
      fireEvent.scroll(container)
    })

    expect(screen.getByLabelText('Jump to latest message')).toBeInTheDocument()
  })

  it('hides the button when user scrolls back to the bottom', () => {
    render(<MessageList messages={makeMessages(20)} />)
    const container = getScrollContainer()

    // Scroll up to reveal button.
    setScrollGeometry(container, { scrollHeight: 1000, clientHeight: 400, scrollTop: 100 })
    act(() => {
      fireEvent.scroll(container)
    })
    expect(screen.getByLabelText('Jump to latest message')).toBeInTheDocument()

    // Scroll back near the bottom (distance = 1000 - 600 - 400 = 0 -> below threshold).
    setScrollGeometry(container, { scrollHeight: 1000, clientHeight: 400, scrollTop: 600 })
    act(() => {
      fireEvent.scroll(container)
    })
    expect(screen.queryByLabelText('Jump to latest message')).not.toBeInTheDocument()
  })

  it('clicking the button scrolls the container to the bottom', () => {
    render(<MessageList messages={makeMessages(20)} />)
    const container = getScrollContainer()
    setScrollGeometry(container, { scrollHeight: 1000, clientHeight: 400, scrollTop: 100 })

    let scrollToArgs: ScrollToOptions | undefined
    container.scrollTo = (arg?: number | ScrollToOptions) => {
      if (typeof arg === 'object') scrollToArgs = arg
    }

    act(() => {
      fireEvent.scroll(container)
    })

    const button = screen.getByLabelText('Jump to latest message')
    fireEvent.click(button)

    expect(scrollToArgs).toEqual({ top: 1000, behavior: 'smooth' })
  })
})

describe('MessageList checkout rendering', () => {
  function messageWithCheckout(): Message[] {
    return [
      {
        role: 'assistant',
        content: 'Here is your cart.',
        id: 'm-checkout',
        checkoutAction: {
          checkout_url: 'https://shop.example/checkout',
          cart_url: 'https://shop.example/cart',
          has_cart: true,
          item_count: 2,
        },
      },
    ]
  }

  it('does not render a checkout link when there is no checkout action', () => {
    render(
      <MessageList
        messages={[{ role: 'assistant', content: 'Here is your cart.', id: 'm-checkout' }]}
      />
    )
    expect(screen.queryByText('CHECKOUT')).not.toBeInTheDocument()
    expect(screen.queryByText('VIEW CART')).not.toBeInTheDocument()
  })

  it('renders the checkout button when a checkout action is present', () => {
    render(<MessageList messages={messageWithCheckout()} />)
    const checkoutLink = screen.getByText('CHECKOUT').closest('a')
    expect(checkoutLink).toHaveAttribute('href', 'https://shop.example/checkout')
  })
})

describe('MessageList product card curation', () => {
  function makeProduct(id: number, name: string): Product {
    return {
      id,
      name,
      url: `https://shop.example/product/${id}`,
      price: '19.99',
      product_type: 'simple',
    }
  }

  function makeComparison(products: Product[]): ComparisonData {
    return {
      products: products.map((product) => ({
        id: product.id,
        name: product.name,
        url: product.url,
        price: product.price,
      })),
      attributes: ['price'],
    }
  }

  function assistantMessage(overrides: Partial<Message>): Message[] {
    return [{ role: 'assistant', content: 'Here you go.', id: 'm-products', ...overrides }]
  }

  it('dedupes products with the same id within a message', () => {
    const productOne = makeProduct(1, 'Product One')
    render(
      <MessageList
        messages={assistantMessage({
          products: [productOne, { ...productOne }, makeProduct(2, 'Product Two')],
        })}
      />
    )
    expect(screen.getAllByText('Product One')).toHaveLength(1)
    expect(screen.getAllByText('Product Two')).toHaveLength(1)
  })

  it('suppresses product cards for ids already shown in the comparison table', () => {
    const products = [makeProduct(1, 'Product One'), makeProduct(2, 'Product Two')]
    render(
      <MessageList
        messages={assistantMessage({
          products,
          comparison: makeComparison(products),
        })}
      />
    )
    // Names appear once each (in the comparison table), not again as cards.
    expect(screen.getAllByText('Product One')).toHaveLength(1)
    expect(screen.getAllByText('Product Two')).toHaveLength(1)
    expect(screen.queryByText(/PICKS/)).not.toBeInTheDocument()
  })

  it('keeps cards for products not part of the comparison', () => {
    const compared = [makeProduct(1, 'Product One'), makeProduct(2, 'Product Two')]
    const extra = makeProduct(3, 'Product Three')
    render(
      <MessageList
        messages={assistantMessage({
          products: [...compared, extra],
          comparison: makeComparison(compared),
        })}
      />
    )
    expect(screen.getAllByText('Product One')).toHaveLength(1)
    expect(screen.getAllByText('Product Three')).toHaveLength(1)
  })

  it('caps rendered picks at 6 with the header reflecting the shown count', () => {
    const names = ['One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight']
    const products = names.map((name, i) => makeProduct(i + 1, `Product ${name}`))
    render(<MessageList messages={assistantMessage({ products })} />)
    expect(screen.getByText('6 PICKS')).toBeInTheDocument()
    expect(screen.getByText('Product Six')).toBeInTheDocument()
    expect(screen.queryByText('Product Seven')).not.toBeInTheDocument()
    expect(screen.queryByText('Product Eight')).not.toBeInTheDocument()
  })
})

describe('curateProducts', () => {
  const product = (id: number): Product => ({
    id,
    name: `Product ${id}`,
    url: `https://shop.example/product/${id}`,
    price: '10.00',
  })

  it('preserves order and ids when nothing needs curating', () => {
    const products = [product(1), product(2)]
    expect(curateProducts(products)).toEqual(products)
  })

  it('caps at MAX_RENDERED_PRODUCTS', () => {
    const products = Array.from({ length: 10 }, (_, i) => product(i + 1))
    expect(curateProducts(products)).toHaveLength(MAX_RENDERED_PRODUCTS)
  })

  it('applies dedupe and comparison suppression before the cap', () => {
    const products = [product(1), product(1), product(2), product(3)]
    const comparison: ComparisonData = {
      products: [product(2), product(3)],
      attributes: ['price'],
    }
    expect(curateProducts(products, comparison).map((p) => p.id)).toEqual([1])
  })
})

describe('MessageList accessibility', () => {
  it('exposes the message container as a polite live log', () => {
    render(<MessageList messages={makeMessages(3)} />)
    const log = screen.getByRole('log', { name: 'Chat messages' })
    expect(log).toHaveAttribute('aria-live', 'polite')
  })
})

describe('MessageList streamed tool UI', () => {
  function makeProduct(id: number, name: string): Product {
    return {
      id,
      name,
      url: `https://shop.example/product/${id}`,
      price: '19.99',
      product_type: 'simple',
    }
  }

  it('renders product cards as soon as they are extracted, with no skeletons', () => {
    render(
      <MessageList
        messages={[
          {
            role: 'assistant',
            content: 'Here are some options.',
            id: 'm-stream',
            products: [makeProduct(1, 'Product One')],
          },
        ]}
      />
    )

    expect(screen.getByText('Product One')).toBeInTheDocument()
    expect(screen.queryByRole('status', { name: 'Loading products' })).not.toBeInTheDocument()
  })

  it('renders product cards above the text bubble (arrival order: tools, then text)', () => {
    render(
      <MessageList
        messages={[
          {
            role: 'assistant',
            content: 'Here are some options.',
            id: 'm-stream',
            products: [makeProduct(1, 'Product One')],
          },
        ]}
      />
    )

    const card = screen.getByText('Product One')
    const text = screen.getByText('Here are some options.')
    expect(card.compareDocumentPosition(text) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('renders cards even before any text has streamed', () => {
    render(
      <MessageList
        messages={[
          {
            role: 'assistant',
            content: '',
            id: 'm-stream',
            products: [makeProduct(1, 'Product One')],
          },
        ]}
      />
    )

    expect(screen.getByText('Product One')).toBeInTheDocument()
  })

  it('renders the checkout button immediately', () => {
    render(
      <MessageList
        messages={[
          {
            role: 'assistant',
            content: 'Taking you to checkout.',
            id: 'm-stream',
            checkoutAction: {
              checkout_url: 'https://shop.example/checkout',
              cart_url: 'https://shop.example/cart',
              item_count: 1,
            },
          },
        ]}
      />
    )

    expect(screen.getByText('CHECKOUT')).toBeInTheDocument()
  })

  it('mounts add-to-cart triggers immediately', () => {
    render(
      <MessageList
        messages={[
          {
            role: 'assistant',
            content: 'Added!',
            id: 'm-stream',
            addToCartIntents: [{ toolCallId: 'call-1', productId: 1, quantity: 1 }],
          },
        ]}
      />
    )

    expect(screen.getByText(/Adding to cart|Added to cart|Could not add to cart/)).toBeInTheDocument()
  })
})
