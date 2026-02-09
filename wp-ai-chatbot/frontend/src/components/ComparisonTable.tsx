import { useState } from 'react'
import { cn } from '@/lib/utils'

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

export default function ComparisonTable({ data }: ComparisonTableProps) {
  const [cartStates, setCartStates] = useState<Record<number, CartState>>({})

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
      const response = await fetch(
        `${wcAjaxUrl}?action=woocommerce_ajax_add_to_cart&product_id=${product.id}&quantity=1`,
        {
          method: 'POST',
          credentials: 'same-origin',
        }
      )

      if (!response.ok) {
        // eslint-disable-next-line react-hooks/immutability
        window.location.href = product.add_to_cart_url || product.url
        return
      }

      const resData = await response.json()

      if (resData.error) {
        // eslint-disable-next-line react-hooks/immutability
        window.location.href = product.add_to_cart_url || product.url
        return
      }

      setCartStates((prev) => ({ ...prev, [product.id]: 'success' }))
      document.body.dispatchEvent(new Event('wc_fragment_refresh'))
      document.body.dispatchEvent(new CustomEvent('added_to_cart'))

      setTimeout(() => {
        setCartStates((prev) => ({ ...prev, [product.id]: 'idle' }))
      }, 2000)
    } catch {
      // eslint-disable-next-line react-hooks/immutability
      window.location.href = product.add_to_cart_url || product.url
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
                ${product.regular_price}
              </span>
              <span className="text-red-600 font-bold">${product.sale_price}</span>
            </>
          )
        }
        return (
          <span className="font-bold text-[var(--wpaic-primary)]">${product.price || '0'}</span>
        )
      }
      case 'regular_price':
        return product.regular_price ? `$${product.regular_price}` : '—'
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

  return (
    <div className="w-full overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table className="w-full border-collapse text-xs max-[480px]:text-[10px]">
        <thead>
          <tr className="bg-slate-50">
            <th className="sticky left-0 bg-slate-50 p-2 min-w-[80px] max-[480px]:min-w-[60px] max-[480px]:p-1.5" />
            {data.products.map((product) => (
              <th
                key={product.id}
                className="p-2 min-w-[100px] max-[480px]:min-w-[80px] max-[480px]:p-1.5"
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
                      className="w-14 h-14 object-cover rounded-lg max-[480px]:w-10 max-[480px]:h-10 max-[480px]:rounded"
                    />
                  )}
                  <span className="text-[11px] font-semibold text-slate-800 text-center line-clamp-2 max-[480px]:text-[10px]">
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
                <td className="sticky left-0 bg-white p-2 font-medium text-slate-600 max-[480px]:p-1.5">
                  {ATTRIBUTE_LABELS[attr] || attr}
                </td>
                {data.products.map((product, idx) => (
                  <td
                    key={product.id}
                    className={cn(
                      'p-2 text-center text-slate-700 max-[480px]:p-1.5',
                      bestIdx === idx && 'bg-green-50 text-green-700'
                    )}
                  >
                    {formatValue(product, attr)}
                  </td>
                ))}
              </tr>
            )
          })}
          <tr className="border-t border-slate-200 bg-slate-50">
            <td className="sticky left-0 bg-slate-50 p-2 max-[480px]:p-1.5" />
            {data.products.map((product) => {
              const cartState = cartStates[product.id] || 'idle'
              return (
                <td key={product.id} className="p-2 text-center max-[480px]:p-1.5">
                  <button
                    type="button"
                    className={cn(
                      'py-1.5 px-3 bg-[var(--wpaic-primary)] text-white border-0 rounded-lg cursor-pointer font-semibold text-[10px] transition-all duration-200 flex items-center justify-center gap-1 mx-auto',
                      'hover:enabled:scale-[1.02] hover:enabled:shadow-sm active:enabled:scale-[0.98]',
                      'disabled:cursor-not-allowed disabled:opacity-80',
                      'max-[480px]:py-1 max-[480px]:px-2 max-[480px]:text-[9px]',
                      cartState === 'loading' && 'bg-slate-200 text-slate-500',
                      cartState === 'success' && 'bg-gradient-to-br from-green-500 to-green-600',
                      cartState === 'error' && 'bg-gradient-to-br from-red-500 to-red-600'
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
    </div>
  )
}
