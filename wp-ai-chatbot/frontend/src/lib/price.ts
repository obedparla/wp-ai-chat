export interface CurrencyConfig {
  symbol?: string
  decimals?: number
  decimalSeparator?: string
  thousandSeparator?: string
  position?: 'left' | 'right' | 'left_space' | 'right_space'
}

const DEFAULTS: Required<CurrencyConfig> = {
  symbol: '$',
  decimals: 2,
  decimalSeparator: '.',
  thousandSeparator: ',',
  position: 'left',
}

function resolveConfig(override?: CurrencyConfig): Required<CurrencyConfig> {
  const fromWindow = (typeof window !== 'undefined' && window.wpaicConfig?.currency) || {}
  return {
    symbol: override?.symbol ?? fromWindow.symbol ?? DEFAULTS.symbol,
    decimals: override?.decimals ?? fromWindow.decimals ?? DEFAULTS.decimals,
    decimalSeparator:
      override?.decimalSeparator ?? fromWindow.decimalSeparator ?? DEFAULTS.decimalSeparator,
    thousandSeparator:
      override?.thousandSeparator ?? fromWindow.thousandSeparator ?? DEFAULTS.thousandSeparator,
    position: override?.position ?? fromWindow.position ?? DEFAULTS.position,
  }
}

function applyPosition(symbol: string, amount: string, position: Required<CurrencyConfig>['position']): string {
  switch (position) {
    case 'right':
      return `${amount}${symbol}`
    case 'left_space':
      return `${symbol} ${amount}`
    case 'right_space':
      return `${amount} ${symbol}`
    case 'left':
    default:
      return `${symbol}${amount}`
  }
}

// Products with a 0/empty price (e.g. unpriced sample data) should not render
// a "$0.00" line or an active ADD button.
export function hasPositivePrice(value: string | number | null | undefined): boolean {
  if (value === null || value === undefined || value === '') return false
  const numeric = typeof value === 'number' ? value : parseFloat(value)
  return Number.isFinite(numeric) && numeric > 0
}

export function formatPrice(value: string | number | null | undefined, override?: CurrencyConfig): string {
  const config = resolveConfig(override)

  let numeric: number
  if (value === null || value === undefined || value === '') {
    numeric = 0
  } else {
    numeric = typeof value === 'number' ? value : parseFloat(value)
    if (!Number.isFinite(numeric)) {
      numeric = 0
    }
  }

  const fixed = numeric.toFixed(config.decimals)
  const [intPart, fracPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, config.thousandSeparator)
  const amount = fracPart ? `${withThousands}${config.decimalSeparator}${fracPart}` : withThousands

  return applyPosition(config.symbol, amount, config.position)
}
