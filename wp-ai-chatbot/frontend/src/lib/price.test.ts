import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { formatPrice } from './price'

describe('formatPrice', () => {
  const originalConfig = window.wpaicConfig

  beforeEach(() => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
    }
  })

  afterEach(() => {
    window.wpaicConfig = originalConfig
  })

  it('formats integer-like values with two decimals', () => {
    expect(formatPrice('28')).toBe('$28.00')
  })

  it('pads single-decimal values to two decimals', () => {
    expect(formatPrice('28.8')).toBe('$28.80')
  })

  it('preserves two-decimal values', () => {
    expect(formatPrice('24.76')).toBe('$24.76')
  })

  it('adds thousand separators for large prices', () => {
    expect(formatPrice('13292.99')).toBe('$13,292.99')
    expect(formatPrice('1241.39')).toBe('$1,241.39')
  })

  it('handles millions', () => {
    expect(formatPrice('1234567.89')).toBe('$1,234,567.89')
  })

  it('accepts numeric input', () => {
    expect(formatPrice(28.8)).toBe('$28.80')
    expect(formatPrice(0)).toBe('$0.00')
  })

  it('returns $0.00 for empty or nullish values', () => {
    expect(formatPrice('')).toBe('$0.00')
    expect(formatPrice(null)).toBe('$0.00')
    expect(formatPrice(undefined)).toBe('$0.00')
  })

  it('returns $0.00 for non-numeric strings', () => {
    expect(formatPrice('not a number')).toBe('$0.00')
  })

  it('uses currency symbol from wpaicConfig', () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      currency: { symbol: '€' },
    }
    expect(formatPrice('1234.5')).toBe('€1,234.50')
  })

  it('honors decimals, separators, and right position from config', () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      currency: {
        symbol: '€',
        decimals: 2,
        decimalSeparator: ',',
        thousandSeparator: '.',
        position: 'right_space',
      },
    }
    expect(formatPrice('13292.99')).toBe('13.292,99 €')
  })

  it('respects zero decimals when configured', () => {
    window.wpaicConfig = {
      apiUrl: '/wp-json/wpaic/v1',
      nonce: 'test-nonce',
      greeting: 'Hello',
      currency: { symbol: '¥', decimals: 0 },
    }
    expect(formatPrice('1234.56')).toBe('¥1,235')
  })

  it('accepts per-call override regardless of window config', () => {
    expect(formatPrice('1234.5', { symbol: 'USD ', position: 'left' })).toBe('USD 1,234.50')
  })

  it('formats negative numbers correctly', () => {
    expect(formatPrice('-1234.5')).toBe('$-1,234.50')
  })
})
