import { useState } from 'react'
import { cn } from '@/lib/utils'
import { applyCartUpdate, requestAddToCart } from '@/lib/cart'
import ProductCardShell from './ProductCardShell'

export interface ProductAttribute {
  name: string
  label: string
  options: string[]
  /** Option slug -> human display label, e.g. { blue: 'Blue' }. */
  option_labels?: Record<string, string>
}

export interface ProductVariation {
  variation_id: number
  attributes: Record<string, string>
  /** Same attribute_* keys as `attributes`, with human display labels as values. */
  attribute_labels?: Record<string, string>
  price: number
  regular_price: number
  is_in_stock: boolean
  image?: string | null
}

export type ProductType =
  | 'simple'
  | 'variable'
  | 'grouped'
  | 'external'
  | 'subscription'
  | 'variable-subscription'
  | 'bundle'
  | string

export interface Product {
  id: number
  name: string
  url: string
  price: string
  regular_price?: string
  sale_price?: string
  image?: string
  short_description?: string
  add_to_cart_url?: string
  product_type?: ProductType
  attributes?: ProductAttribute[]
  variations?: ProductVariation[]
  is_complex?: boolean
  variation_count?: number
  categories?: string[]
  stock_status?: 'instock' | 'outofstock' | 'onbackorder'
  is_purchasable?: boolean
  external_url?: string
  button_text?: string
}

interface ProductCardProps {
  product: Product
}

type CartState = 'idle' | 'loading' | 'success' | 'error'

export default function ProductCard({ product }: ProductCardProps) {
  const [cartState, setCartState] = useState<CartState>('idle')

  const isUnavailable =
    product.stock_status === 'outofstock' || product.is_purchasable === false

  const handleAddToCart = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()

    if (cartState === 'loading' || isUnavailable) return

    setCartState('loading')

    const wcAjaxUrl = window.wpaicConfig?.wcAjaxUrl
    if (!wcAjaxUrl) {
      window.location.href = product.add_to_cart_url || product.url
      return
    }

    try {
      const data = await requestAddToCart({ productId: product.id, quantity: 1 }, wcAjaxUrl)

      setCartState('success')
      applyCartUpdate(data)

      setTimeout(() => setCartState('idle'), 2000)
    } catch {
      window.location.href = product.add_to_cart_url || product.url
    }
  }

  const button = isUnavailable ? (
    <button
      type="button"
      className="inline-flex items-center rounded-full bg-slate-200 text-slate-500 border-0 px-3.5 py-2 font-semibold text-xs cursor-not-allowed tracking-wider"
      disabled
    >
      SOLD OUT
    </button>
  ) : (
    <button
      type="button"
      className={cn(
        'inline-flex items-center gap-1 rounded-full border-0 cursor-pointer font-semibold text-xs transition-all duration-200 px-3.5 py-2 shrink-0',
        'bg-[var(--wpaic-primary)] text-white hover:enabled:scale-[1.04] active:enabled:scale-95 shadow-sm',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--wpaic-primary)]',
        'disabled:cursor-not-allowed disabled:opacity-80',
        cartState === 'loading' && 'bg-slate-200 text-slate-500 shadow-none',
        cartState === 'success' && 'bg-emerald-600',
        cartState === 'error' && 'bg-red-600'
      )}
      onClick={handleAddToCart}
      disabled={cartState === 'loading'}
      aria-label={cartState === 'success' ? 'Added to cart' : 'Add to cart'}
    >
      {cartState === 'loading' && (
        <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
      )}
      {cartState === 'success' && (
        <>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" className="w-3.5 h-3.5">
            <path d="M20 6 9 17l-5-5" />
          </svg>
          <span className="tracking-wider">ADDED</span>
        </>
      )}
      {cartState === 'error' && <span className="tracking-wider">ERROR</span>}
      {cartState === 'idle' && (
        <>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="w-3.5 h-3.5">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
          <span className="tracking-wider">ADD</span>
        </>
      )}
    </button>
  )

  return <ProductCardShell product={product} bottomSlot={button} />
}
