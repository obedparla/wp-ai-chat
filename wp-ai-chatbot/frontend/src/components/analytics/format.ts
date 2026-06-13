// Client-side number/currency formatters, built from the store currency passed
// in the analytics blob. Numbers use the visitor's locale separators.

export interface Formatters {
  usd: (value: number) => string
  usdc: (value: number) => string
  num: (value: number) => string
  pct: (value: number, digits?: number) => string
}

export function makeFormatters(currency: { symbol: string; code: string }): Formatters {
  const symbol = currency.symbol || '$'
  return {
    usd: (value) => symbol + Math.round(value).toLocaleString(),
    usdc: (value) =>
      symbol + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    num: (value) => value.toLocaleString(),
    pct: (value, digits = 1) => value.toFixed(digits) + '%',
  }
}
