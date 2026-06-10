import { useEffect, useState, type ReactNode } from 'react'
import { Maximize2, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatPrice } from '@/lib/price'
import { applyCartUpdate, requestAddToCart } from '@/lib/cart'
import { useFocusTrap } from '../hooks/useFocusTrap'

export interface ComparisonProduct {
  id: number
  name: string
  url: string
  price: string
  regular_price?: string
  sale_price?: string
  stock_status?: string
  rating?: number | null
  categories?: string[]
  image?: string
  add_to_cart_url?: string
  /** Human attribute label -> value, e.g. { Color: 'Blue, Red' }. */
  attributes?: Record<string, string>
  weight?: string
  dimensions?: string
}

export interface ComparisonData {
  products: ComparisonProduct[]
  attributes: string[]
}

interface ComparisonTableProps {
  data: ComparisonData
}

type CartState = 'idle' | 'loading' | 'success' | 'error'

const ATTRIBUTE_LABELS: Record<string, string> = {
  price: 'Price',
  regular_price: 'Regular Price',
  stock_status: 'Availability',
  rating: 'Rating',
  categories: 'Categories',
}

const STOCK_STATUS_LABELS: Record<string, string> = {
  instock: 'In Stock',
  outofstock: 'Out of Stock',
  onbackorder: 'On Backorder',
}

// Mounted only while expanded so the focus trap attaches on open.
function ExpandedComparisonDialog({ onClose, children }: { onClose: () => void; children: ReactNode }) {
  const dialogRef = useFocusTrap<HTMLDivElement>()

  // Capture phase so Escape closes only this dialog — the widget's
  // document-level (bubble) handler would otherwise close the whole chat.
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return
      e.stopPropagation()
      onClose()
    }
    document.addEventListener('keydown', handleKeyDown, true)
    return () => document.removeEventListener('keydown', handleKeyDown, true)
  }, [onClose])

  return (
    <div
      ref={dialogRef}
      className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/50 p-4 animate-wpaic-fadeIn"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label="Product comparison"
    >
      <div
        className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
          <h2 className="text-base font-semibold text-slate-900">Compare products</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close comparison"
            className="flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 cursor-pointer transition-colors hover:text-slate-800 hover:bg-slate-100"
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        <div className="flex-1 overflow-auto">{children}</div>
      </div>
    </div>
  )
}

export default function ComparisonTable({ data }: ComparisonTableProps) {
  const [cartStates, setCartStates] = useState<Record<number, CartState>>({})
  const [isExpanded, setIsExpanded] = useState(false)

  if (data.products.length === 0) return null

  const handleAddToCart = async (product: ComparisonProduct, e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()

    if (cartStates[product.id] === 'loading') return

    setCartStates((prev) => ({ ...prev, [product.id]: 'loading' }))

    const wcAjaxUrl = window.wpaicConfig?.wcAjaxUrl
    if (!wcAjaxUrl) {
      // eslint-disable-next-line react-hooks/immutability
      window.location.href = product.add_to_cart_url || product.url
      return
    }

    try {
      const resData = await requestAddToCart({ productId: product.id, quantity: 1 }, wcAjaxUrl)

      setCartStates((prev) => ({ ...prev, [product.id]: 'success' }))
      applyCartUpdate(resData)

      setTimeout(() => {
        setCartStates((prev) => ({ ...prev, [product.id]: 'idle' }))
      }, 2000)
    } catch {
      // Stay in the conversation: show the inline error state, then reset.
      setCartStates((prev) => ({ ...prev, [product.id]: 'error' }))
      setTimeout(() => {
        setCartStates((prev) => ({ ...prev, [product.id]: 'idle' }))
      }, 2500)
    }
  }

  const formatValue = (product: ComparisonProduct, attr: string): React.ReactNode => {
    switch (attr) {
      case 'price': {
        const hasDiscount =
          product.sale_price &&
          product.regular_price &&
          parseFloat(product.sale_price) < parseFloat(product.regular_price)
        if (hasDiscount) {
          return (
            <>
              <span className="line-through text-slate-400 text-xs mr-1">
                {formatPrice(product.regular_price)}
              </span>
              <span className="text-red-600 font-bold">{formatPrice(product.sale_price)}</span>
            </>
          )
        }
        return (
          <span className="font-bold text-[var(--wpaic-primary)]">{formatPrice(product.price)}</span>
        )
      }
      case 'regular_price':
        return product.regular_price ? formatPrice(product.regular_price) : '—'
      case 'stock_status':
        return STOCK_STATUS_LABELS[product.stock_status || ''] || product.stock_status || '—'
      case 'rating':
        if (product.rating === null || product.rating === undefined) return '—'
        return (
          <span className="text-amber-500">
            {'★'.repeat(Math.round(product.rating))}
            {'☆'.repeat(5 - Math.round(product.rating))}
            <span className="text-slate-500 text-[10px] ml-1">({product.rating.toFixed(1)})</span>
          </span>
        )
      case 'categories':
        return product.categories?.length ? product.categories.join(', ') : '—'
      default:
        return '—'
    }
  }

  const getBestValue = (attr: string): number | null => {
    if (attr === 'price') {
      const prices = data.products.map((p) => parseFloat(p.sale_price || p.price || '0'))
      const minPrice = Math.min(...prices.filter((p) => p > 0))
      return data.products.findIndex(
        (p) => parseFloat(p.sale_price || p.price || '0') === minPrice
      )
    }
    if (attr === 'rating') {
      const ratings = data.products.map((p) => p.rating ?? 0)
      const maxRating = Math.max(...ratings)
      if (maxRating === 0) return null
      return data.products.findIndex((p) => p.rating === maxRating)
    }
    return null
  }

  // Richer payload rows (attributes/weight/dimensions): union of attribute
  // labels across products, in first-seen order; absent values render as a dash.
  const attributeLabels: string[] = []
  for (const product of data.products) {
    for (const label of Object.keys(product.attributes ?? {})) {
      if (!attributeLabels.includes(label)) attributeLabels.push(label)
    }
  }
  const physicalRows = (
    [
      ['Weight', (product: ComparisonProduct) => product.weight],
      ['Dimensions', (product: ComparisonProduct) => product.dimensions],
    ] as const
  ).filter(([, getValue]) => data.products.some((product) => getValue(product)))

  // Same table at two sizes: inline (compact, in the message stream) and
  // expanded (larger text/padding inside the fullscreen dialog).
  const renderTable = (expanded: boolean) => {
    const cellPad = expanded ? 'p-3' : 'p-2 max-[480px]:p-1.5'
    const labelCellClass = cn('sticky left-0 bg-white font-medium text-slate-600', cellPad)
    const dataCellClass = cn('text-center text-slate-700', cellPad)

    return (
      <table
        className={cn(
          'w-full border-collapse',
          expanded ? 'text-sm' : 'text-xs max-[480px]:text-[10px]'
        )}
      >
        <thead>
          <tr className="bg-slate-50">
            <th
              className={cn(
                'sticky left-0 bg-slate-50',
                expanded ? 'p-3 min-w-[100px]' : 'p-2 min-w-[80px] max-[480px]:min-w-[60px] max-[480px]:p-1.5'
              )}
            >
              {!expanded && (
                <button
                  type="button"
                  onClick={() => setIsExpanded(true)}
                  aria-label="Expand comparison"
                  title="Expand comparison"
                  className="flex items-center justify-center w-7 h-7 mx-auto rounded-lg text-slate-400 cursor-pointer transition-colors hover:text-slate-700 hover:bg-slate-100"
                >
                  <Maximize2 className="w-4 h-4" />
                </button>
              )}
            </th>
            {data.products.map((product) => (
              <th
                key={product.id}
                className={cn(
                  expanded ? 'p-3 min-w-[140px]' : 'p-2 min-w-[100px] max-[480px]:min-w-[80px] max-[480px]:p-1.5'
                )}
              >
                <a
                  href={product.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex flex-col items-center gap-1.5 no-underline text-inherit max-[480px]:gap-1"
                >
                  {product.image && (
                    <img
                      src={product.image}
                      alt={product.name}
                      className={cn(
                        'object-cover rounded-lg',
                        expanded
                          ? 'w-20 h-20'
                          : 'w-14 h-14 max-[480px]:w-10 max-[480px]:h-10 max-[480px]:rounded'
                      )}
                    />
                  )}
                  <span
                    className={cn(
                      'font-semibold text-slate-800 text-center line-clamp-2',
                      expanded ? 'text-sm' : 'text-[11px] max-[480px]:text-[10px]'
                    )}
                  >
                    {product.name}
                  </span>
                </a>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.attributes.map((attr) => {
            const bestIdx = getBestValue(attr)
            return (
              <tr key={attr} className="border-t border-slate-100">
                <td className={labelCellClass}>{ATTRIBUTE_LABELS[attr] || attr}</td>
                {data.products.map((product, idx) => (
                  <td
                    key={product.id}
                    className={cn(dataCellClass, bestIdx === idx && 'bg-green-50 text-green-700')}
                  >
                    {formatValue(product, attr)}
                  </td>
                ))}
              </tr>
            )
          })}
          {attributeLabels.map((label) => (
            <tr key={`attr-${label}`} className="border-t border-slate-100">
              <td className={labelCellClass}>{label}</td>
              {data.products.map((product) => (
                <td key={product.id} className={dataCellClass}>
                  {product.attributes?.[label] || '—'}
                </td>
              ))}
            </tr>
          ))}
          {physicalRows.map(([label, getValue]) => (
            <tr key={label} className="border-t border-slate-100">
              <td className={labelCellClass}>{label}</td>
              {data.products.map((product) => (
                <td key={product.id} className={dataCellClass}>
                  {getValue(product) || '—'}
                </td>
              ))}
            </tr>
          ))}
          <tr className="border-t border-slate-200 bg-slate-50">
            <td className={cn('sticky left-0 bg-slate-50', cellPad)} />
            {data.products.map((product) => {
              const cartState = cartStates[product.id] || 'idle'
              return (
                <td key={product.id} className={cn('text-center', cellPad)}>
                  <button
                    type="button"
                    className={cn(
                      'inline-flex items-center justify-center gap-1 rounded-full border-0 cursor-pointer font-semibold transition-all duration-200 mx-auto',
                      expanded
                        ? 'text-xs py-2 px-4'
                        : 'text-[10px] py-1.5 px-3 max-[480px]:py-1 max-[480px]:px-2 max-[480px]:text-[9px]',
                      'bg-[var(--wpaic-primary)] text-white hover:enabled:scale-[1.04] active:enabled:scale-95 shadow-sm',
                      'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--wpaic-primary)]',
                      'disabled:cursor-not-allowed disabled:opacity-80',
                      cartState === 'loading' && 'bg-slate-200 text-slate-500 shadow-none',
                      cartState === 'success' && 'bg-emerald-600',
                      cartState === 'error' && 'bg-red-600'
                    )}
                    onClick={(e) => handleAddToCart(product, e)}
                    disabled={cartState === 'loading'}
                    aria-label={cartState === 'success' ? 'Added to cart' : 'Add to cart'}
                  >
                    {cartState === 'loading' && (
                      <span className="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                    )}
                    {cartState === 'success' && '✓ Added'}
                    {cartState === 'error' && 'Error'}
                    {cartState === 'idle' && 'Add to Cart'}
                  </button>
                </td>
              )
            })}
          </tr>
        </tbody>
      </table>
    )
  }

  return (
    <>
      <div className="w-full overflow-x-auto rounded-xl border border-slate-200 bg-white">
        {renderTable(false)}
      </div>
      {isExpanded && (
        <ExpandedComparisonDialog onClose={() => setIsExpanded(false)}>
          {renderTable(true)}
        </ExpandedComparisonDialog>
      )}
    </>
  )
}
