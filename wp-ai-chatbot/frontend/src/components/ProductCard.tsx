import { useState } from 'react'
import { cn } from '@/lib/utils'

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
      // eslint-disable-next-line react-hooks/immutability
      window.location.href = product.add_to_cart_url || product.url
      return
    }

    try {
      const formData = new FormData()
      formData.append('action', 'woocommerce_ajax_add_to_cart')
      formData.append('product_id', String(product.id))
      formData.append('quantity', '1')

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

      const data = await response.json()

      if (data.error) {
        // eslint-disable-next-line react-hooks/immutability
        window.location.href = product.add_to_cart_url || product.url
        return
      }

      setCartState('success')
      triggerCartUpdate()

      setTimeout(() => setCartState('idle'), 2000)
    } catch {
      // eslint-disable-next-line react-hooks/immutability
      window.location.href = product.add_to_cart_url || product.url
    }
  }

  const triggerCartUpdate = () => {
    document.body.dispatchEvent(new Event('wc_fragment_refresh'))
    document.body.dispatchEvent(new CustomEvent('added_to_cart'))
  }

  return (
    <div className="flex flex-col h-full bg-white border border-slate-200 rounded-xl overflow-hidden transition-all duration-200 hover:shadow-lg hover:-translate-y-1 hover:border-[var(--wpaic-primary)]">
      <a
        href={product.url}
        target="_blank"
        rel="noopener noreferrer"
        className="flex flex-col flex-1 [text-decoration:none] text-inherit"
      >
        <div className="w-full aspect-[4/3] bg-slate-50 overflow-hidden">
          {product.image ? (
            <img
              src={product.image}
              alt={product.name}
              className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.08]"
            />
          ) : (
            <div className="w-full h-full bg-gradient-to-br from-slate-200 via-slate-100 to-slate-200 bg-[length:200%_200%] animate-shimmer" />
          )}
        </div>
        <div className="p-2 max-[480px]:p-1.5">
          <div className="text-base font-semibold leading-tight text-slate-800 line-clamp-2 mb-1 max-[480px]:text-sm">
            {product.name}
          </div>
          <div className="text-lg font-bold text-[var(--wpaic-primary)] max-[480px]:text-base">
            {hasDiscount ? (
              <>
                <span className="line-through text-slate-500 font-normal mr-2 text-sm">
                  ${product.regular_price}
                </span>
                <span className="text-red-600">${product.sale_price}</span>
              </>
            ) : (
              <span>${product.price || '0'}</span>
            )}
          </div>
          {product.short_description && (
            <p className="text-xs text-slate-600 mt-1.5 line-clamp-2 leading-relaxed max-[480px]:text-[11px] max-[480px]:line-clamp-2">
              {product.short_description}
            </p>
          )}
        </div>
      </a>
      <div className="px-2.5 pb-2.5 max-[480px]:px-2 max-[480px]:pb-2">
        <button
          type="button"
          className={cn(
            'w-full py-1.5 px-2.5 bg-[var(--wpaic-primary)] text-white border-0 rounded-lg cursor-pointer font-semibold text-[11px] transition-all duration-200 flex items-center justify-center gap-1.5',
            'hover:enabled:scale-[1.02] hover:enabled:shadow-sm active:enabled:scale-[0.98]',
            'disabled:cursor-not-allowed disabled:opacity-80',
            'max-[480px]:py-1 max-[480px]:px-2 max-[480px]:text-[10px]',
            cartState === 'loading' && 'bg-slate-200 text-slate-500',
            cartState === 'success' && 'bg-gradient-to-br from-green-500 to-green-600',
            cartState === 'error' && 'bg-gradient-to-br from-red-500 to-red-600'
          )}
          onClick={handleAddToCart}
          disabled={cartState === 'loading'}
          aria-label={cartState === 'success' ? 'Added to cart' : 'Add to cart'}
        >
          {cartState === 'loading' && (
            <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
          )}
          {cartState === 'success' && '✓ Added'}
          {cartState === 'error' && 'Error'}
          {cartState === 'idle' && 'Add to Cart'}
        </button>
      </div>
    </div>
  )
}
