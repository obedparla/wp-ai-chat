import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent, act } from '@testing-library/react'
import MessageList from './MessageList'
import type { Message } from '../hooks/useChat'

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

describe('MessageList checkout gating', () => {
  function messageWithCheckout(hasCart: boolean): Message[] {
    return [
      {
        role: 'assistant',
        content: 'Here is your cart.',
        id: 'm-checkout',
        checkoutAction: {
          checkout_url: 'https://shop.example/checkout',
          cart_url: 'https://shop.example/cart',
          has_cart: hasCart,
          item_count: hasCart ? 2 : 0,
        },
      },
    ]
  }

  it('does not render a checkout link when has_cart is false', () => {
    render(<MessageList messages={messageWithCheckout(false)} />)
    expect(screen.queryByText('CHECKOUT')).not.toBeInTheDocument()
    expect(screen.queryByText('VIEW CART')).not.toBeInTheDocument()
    expect(document.querySelector('a[href="https://shop.example/checkout"]')).toBeNull()
    expect(document.querySelector('a[href="https://shop.example/cart"]')).toBeNull()
  })

  it('renders the checkout button when has_cart is true', () => {
    render(<MessageList messages={messageWithCheckout(true)} />)
    const checkoutLink = screen.getByText('CHECKOUT').closest('a')
    expect(checkoutLink).toHaveAttribute('href', 'https://shop.example/checkout')
  })
})
