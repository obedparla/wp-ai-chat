import '@testing-library/jest-dom'

declare global {
  interface Window {
    wpaicConfig?: {
      apiUrl: string
      nonce: string
      greeting: string
      chatbotName?: string
      chatbotLogo?: string
    }
  }
}

Element.prototype.scrollIntoView = function () {
  // Mock implementation for jsdom
}

// Mock matchMedia for embla-carousel
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  }),
})

// Mock IntersectionObserver for embla-carousel
class MockIntersectionObserver {
  observe = () => {}
  unobserve = () => {}
  disconnect = () => {}
}
Object.defineProperty(window, 'IntersectionObserver', {
  writable: true,
  value: MockIntersectionObserver,
})

// Mock ResizeObserver for embla-carousel
class MockResizeObserver {
  observe = () => {}
  unobserve = () => {}
  disconnect = () => {}
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
  }
})
