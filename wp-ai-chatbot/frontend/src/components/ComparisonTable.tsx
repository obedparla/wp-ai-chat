import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Maximize2, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatPrice } from '@/lib/price'
import { applyCartUpdate, requestAddToCart } from '@/lib/cart'
import DialogShell from './DialogShell'

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
type TableSize = 'inline' | 'expanded'

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

// The same grid renders at two sizes: compact in the message stream, larger
// inside the fullscreen dialog. Each variant's classes live here side by side
// so a styling change is one lookup away instead of a hunt through the JSX.
const SIZE_STYLES: Record<TableSize, Record<'table' | 'cell' | 'cornerHeader' | 'productHeader' | 'image' | 'name' | 'button', string>> = {
  inline: {
    table: 'text-xs max-[480px]:text-[10px]',
    cell: 'p-2 max-[480px]:p-1.5',
    cornerHeader: 'p-2 min-w-[80px] max-[480px]:min-w-[60px] max-[480px]:p-1.5',
    productHeader: 'p-2 min-w-[100px] max-[480px]:min-w-[80px] max-[480px]:p-1.5',
    image: 'w-14 h-14 max-[480px]:w-10 max-[480px]:h-10 max-[480px]:rounded',
    name: 'text-[11px] max-[480px]:text-[10px]',
    button: 'text-[10px] py-1.5 px-3 max-[480px]:py-1 max-[480px]:px-2 max-[480px]:text-[9px]',
  },
  expanded: {
    table: 'text-sm',
    cell: 'p-3',
    cornerHeader: 'p-3 min-w-[100px]',
    productHeader: 'p-3 min-w-[140px]',
    image: 'w-20 h-20',
    name: 'text-sm',
    button: 'text-xs py-2 px-4',
  },
}

function formatValue(product: ComparisonProduct, attr: string): ReactNode {
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

function getBestValue(products: ComparisonProduct[], attr: string): number | null {
  if (attr === 'price') {
    const prices = products.map((p) => parseFloat(p.sale_price || p.price || '0'))
    const minPrice = Math.min(...prices.filter((p) => p > 0))
    return products.findIndex((p) => parseFloat(p.sale_price || p.price || '0') === minPrice)
  }
  if (attr === 'rating') {
    const ratings = products.map((p) => p.rating ?? 0)
    const maxRating = Math.max(...ratings)
    if (maxRating === 0) return null
    return products.findIndex((p) => p.rating === maxRating)
  }
  return null
}

interface ComparisonGridProps {
  data: ComparisonData
  size: TableSize
  cartStates: Record<number, CartState>
  onAddToCart: (product: ComparisonProduct, e: React.MouseEvent) => void
  /** Renders the expand button in the corner cell; the dialog instance omits it. */
  onExpand?: () => void
}

function ComparisonGrid({ data, size, cartStates, onAddToCart, onExpand }: ComparisonGridProps) {
  const s = SIZE_STYLES[size]
  const labelCellClass = cn('sticky left-0 bg-white font-medium text-slate-600', s.cell)
  const dataCellClass = cn('text-center text-slate-700', s.cell)

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

  return (
    <table className={cn('w-full border-collapse', s.table)}>
      <thead>
        <tr className="bg-slate-50">
          <th className={cn('sticky left-0 bg-slate-50', s.cornerHeader)}>
            {onExpand && (
              <button
                type="button"
                onClick={onExpand}
                aria-label="Expand comparison"
                title="Expand comparison"
                className="flex items-center justify-center w-7 h-7 mx-auto rounded-lg text-slate-400 cursor-pointer transition-colors hover:text-slate-700 hover:bg-slate-100"
              >
                <Maximize2 className="w-4 h-4" />
              </button>
            )}
          </th>
          {data.products.map((product) => (
            <th key={product.id} className={s.productHeader}>
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
                    className={cn('object-cover rounded-lg', s.image)}
                  />
                )}
                <span
                  className={cn('font-semibold text-slate-800 text-center line-clamp-2', s.name)}
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
          const bestIdx = getBestValue(data.products, attr)
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
          <td className={cn('sticky left-0 bg-slate-50', s.cell)} />
          {data.products.map((product) => {
            const cartState = cartStates[product.id] || 'idle'
            return (
              <td key={product.id} className={cn('text-center', s.cell)}>
                <button
                  type="button"
                  className={cn(
                    'inline-flex items-center justify-center gap-1 rounded-full border-0 cursor-pointer font-semibold transition-all duration-200 mx-auto',
                    s.button,
                    'bg-[var(--wpaic-primary)] text-white hover:enabled:scale-[1.04] active:enabled:scale-95 shadow-sm',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--wpaic-primary)]',
                    'disabled:cursor-not-allowed disabled:opacity-80',
                    cartState === 'loading' && 'bg-slate-200 text-slate-500 shadow-none',
                    cartState === 'success' && 'bg-emerald-600',
                    cartState === 'error' && 'bg-red-600'
                  )}
                  onClick={(e) => onAddToCart(product, e)}
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

function ExpandedComparisonDialog({ onClose, children }: { onClose: () => void; children: ReactNode }) {
  const closeRef = useRef<HTMLButtonElement>(null)

  // Move focus into the dialog on open so the focus trap is active immediately.
  useEffect(() => {
    closeRef.current?.focus()
  }, [])

  return (
    <DialogShell
      onClose={onClose}
      variant="screen"
      panelClassName="w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden"
      ariaLabelledby="wpaic-compare-title"
    >
      <div className="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
        <h2 id="wpaic-compare-title" className="text-base font-semibold text-slate-900">
          Compare products
        </h2>
        <button
          ref={closeRef}
          type="button"
          onClick={onClose}
          aria-label="Close comparison"
          className="flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 cursor-pointer transition-colors hover:text-slate-800 hover:bg-slate-100"
        >
          <X className="w-5 h-5" />
        </button>
      </div>
      <div className="flex-1 overflow-auto">{children}</div>
    </DialogShell>
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

  return (
    <>
      <div className="w-full overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <ComparisonGrid
          data={data}
          size="inline"
          cartStates={cartStates}
          onAddToCart={handleAddToCart}
          onExpand={() => setIsExpanded(true)}
        />
      </div>
      {isExpanded && (
        <ExpandedComparisonDialog onClose={() => setIsExpanded(false)}>
          <ComparisonGrid
            data={data}
            size="expanded"
            cartStates={cartStates}
            onAddToCart={handleAddToCart}
          />
        </ExpandedComparisonDialog>
      )}
    </>
  )
}
