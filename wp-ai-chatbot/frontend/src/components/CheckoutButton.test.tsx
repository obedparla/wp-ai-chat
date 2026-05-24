import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import CheckoutButton, { CheckoutAction } from './CheckoutButton'

describe('CheckoutButton', () => {
  const baseAction: CheckoutAction = {
    checkout_url: 'https://shop.example.com/checkout/',
    cart_url: 'https://shop.example.com/cart/',
    has_cart: true,
    item_count: 2,
  }

  it('renders checkout CTA pointing at checkout_url', () => {
    render(<CheckoutButton action={baseAction} />)

    const checkoutLink = screen.getByRole('link', { name: /CHECKOUT/i })
    expect(checkoutLink).toHaveAttribute('href', 'https://shop.example.com/checkout/')
  })

  it('renders secondary cart link when both URLs are present', () => {
    render(<CheckoutButton action={baseAction} />)

    const cartLink = screen.getByRole('link', { name: /view cart/i })
    expect(cartLink).toHaveAttribute('href', 'https://shop.example.com/cart/')
  })

  it('falls back to cart URL when checkout URL missing', () => {
    render(
      <CheckoutButton
        action={{ ...baseAction, checkout_url: '' }}
      />
    )

    const links = screen.getAllByRole('link')
    expect(links).toHaveLength(1)
    expect(links[0]).toHaveAttribute('href', 'https://shop.example.com/cart/')
    expect(links[0].textContent?.toUpperCase()).toContain('VIEW CART')
  })

  it('renders nothing when no URLs are provided', () => {
    const { container } = render(
      <CheckoutButton
        action={{ checkout_url: '', cart_url: '', has_cart: false, item_count: 0 }}
      />
    )

    expect(container.firstChild).toBeNull()
  })

  it('omits the secondary cart link when only checkout is present', () => {
    render(
      <CheckoutButton
        action={{ ...baseAction, cart_url: '' }}
      />
    )

    const links = screen.getAllByRole('link')
    expect(links).toHaveLength(1)
    expect(screen.queryByText(/view cart/i)).toBeNull()
  })
})
