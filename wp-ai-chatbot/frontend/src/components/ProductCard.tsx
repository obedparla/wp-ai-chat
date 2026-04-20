import { useState } from 'react'
import { cn } from '@/lib/utils'
import { applyCartUpdate, hasCartUpdateError } from '@/lib/cart'

export interface ProductAttribute {
  name: string
  label: string
  options: string[]
}

export interface ProductVariation {
  variation_id: number
  attributes: Record<string, string>
  price: number
  regular_price: number
  is_in_stock: boolean
  image?: string | null
}

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
  product_type?: 'simple' | 'variable' | 'grouped' | 'external'
  attributes?: ProductAttribute[]
  variations?: ProductVariation[]
  is_complex?: boolean
  variation_count?: number
  categories?: string[]
}

interface ProductCardProps {
  product: Product
}

type CartState = 'idle' | 'loading' | 'success' | 'error'

export default function ProductCard({ product }: ProductCardProps) {
  const [cartState, setCartState] = useState<CartState>('idle')

  const hasDiscount =
    product.sale_price &&
    product.regular_price &&
    parseFloat(product.sale_price) < parseFloat(product.regular_price)

  const handleAddToCart = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()

    if (cartState === 'loading') return

    setCartState('loading')

    const wcAjaxUrl = window.wpaicConfig?.wcAjaxUrl
    if (!wcAjaxUrl) {
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
        window.location.href = product.add_to_cart_url || product.url
        return
      }

      const data = await response.json()

      if (hasCartUpdateError(data)) {
        window.location.href = product.add_to_cart_url || product.url
        return
      }

      setCartState('success')
      applyCartUpdate(data)

      setTimeout(() => setCartState('idle'), 2000)
    } catch {
      window.location.href = product.add_to_cart_url || product.url
    }
  }

  const category = product.categories?.[0]

  return (
    <div className="flex flex-col h-full bg-white border border-slate-200 rounded-2xl overflow-hidden transition-[box-shadow,border-color] duration-200 hover:shadow-md hover:border-slate-300">
      <a
        href={product.url}
        target="_blank"
        rel="noopener noreferrer"
        className="wpaic-no-underline flex flex-col flex-1 text-inherit"
      >
        <div className="relative w-full aspect-[4/3] bg-slate-50 overflow-hidden">
          {product.image ? (
            <img
              src={product.image}
              alt={product.name}
              className="w-full h-full object-cover"
            />
          ) : (
            <div className="w-full h-full bg-gradient-to-br from-slate-200 via-slate-100 to-slate-200 bg-[length:200%_200%] animate-shimmer" />
          )}
          {hasDiscount && (
            <span className="absolute top-3 left-3 inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-0.5 text-[10px] font-mono font-semibold tracking-[0.14em] text-slate-800">
              SALE
            </span>
          )}
        </div>
        <div className="px-3.5 pt-3 pb-1 flex flex-col gap-1 flex-1 max-[480px]:px-3">
          {category && (
            <div className="text-[10px] font-mono font-medium tracking-[0.18em] text-slate-500 uppercase">
              {category}
            </div>
          )}
          <div className="text-base font-semibold leading-tight text-slate-900 line-clamp-2 max-[480px]:text-[15px]">
            {product.name}
          </div>
          {product.short_description && (
            <p className="text-[13px] text-slate-600 line-clamp-2 leading-snug max-[480px]:text-xs">
              {product.short_description}
            </p>
          )}
        </div>
      </a>
      <div className="flex items-center justify-between gap-2 px-3.5 pb-3.5 pt-2 max-[480px]:px-3 max-[480px]:pb-3">
        <div className="text-base font-bold text-slate-900 max-[480px]:text-[15px]">
          {hasDiscount ? (
            <>
              <span className="line-through text-slate-400 font-normal mr-1.5 text-sm">
                ${product.regular_price}
              </span>
              <span>${product.sale_price}</span>
            </>
          ) : (
            <span>${product.price || '0'}</span>
          )}
        </div>
        <button
          type="button"
          className={cn(
            'inline-flex items-center gap-1 rounded-full border-0 cursor-pointer font-semibold text-xs transition-all duration-200 px-3.5 py-2 shrink-0',
            'bg-[var(--wpaic-primary)] text-white hover:enabled:scale-[1.04] active:enabled:scale-95 shadow-sm',
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
      </div>
    </div>
  )
}
