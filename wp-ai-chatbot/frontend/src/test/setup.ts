import '@testing-library/jest-dom'

declare global {
  interface Window {
    wpaicConfig?: {
      apiUrl: string
      nonce: string
      greeting: string
      chatbotName?: string
      chatbotLogo?: string
      currency?: {
        symbol?: string
        decimals?: number
        decimalSeparator?: string
        thousandSeparator?: string
        position?: 'left' | 'right' | 'left_space' | 'right_space'
      }
      pageContext?: {
        page_type: 'product' | 'cart' | 'checkout' | 'shop' | 'product_category' | 'product_tag' | 'singular' | 'other'
        title: string
        url: string
        post_id?: number
        post_type?: string
        product_id?: number
        term_id?: number
        taxonomy?: string
        term_slug?: string
        term_name?: string
      }
      conversationStarters?: string[]
    }
  }
}

Element.prototype.scrollIntoView = function () {
  // Mock implementation for jsdom
}

const noop = () => undefined

// Mock matchMedia for embla-carousel
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: noop,
    removeListener: noop,
    addEventListener: noop,
    removeEventListener: noop,
    dispatchEvent: () => false,
  }),
})

// Mock IntersectionObserver for embla-carousel
class MockIntersectionObserver {
  observe = noop
  unobserve = noop
  disconnect = noop
}
Object.defineProperty(window, 'IntersectionObserver', {
  writable: true,
  value: MockIntersectionObserver,
})

// Mock ResizeObserver for embla-carousel
class MockResizeObserver {
  observe = noop
  unobserve = noop
  disconnect = noop
}
Object.defineProperty(window, 'ResizeObserver', {
  writable: true,
  value: MockResizeObserver,
})

beforeEach(() => {
  window.wpaicConfig = {
    apiUrl: '/wp-json/wpaic/v1',
    nonce: 'test-nonce',
    greeting: 'Hello! How can I help you today?',
    pageContext: {
      page_type: 'other',
      title: 'Current page',
      url: 'http://example.com/current-page',
    },
    conversationStarters: [],
  }
})
