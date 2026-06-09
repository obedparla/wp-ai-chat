import { useState, useMemo } from 'react'
import { Product, ProductVariation } from './ProductCard'
import { cn } from '@/lib/utils'
import { applyCartUpdate, requestAddToCart } from '@/lib/cart'
import ProductCardShell from './ProductCardShell'

interface VariableProductCardProps {
  product: Product
}

type CartState = 'idle' | 'loading' | 'success' | 'error'

export default function VariableProductCard({ product }: VariableProductCardProps) {
  const [selectedAttributes, setSelectedAttributes] = useState<Record<string, string>>({})
  const [cartState, setCartState] = useState<CartState>('idle')

  const selectedVariation = useMemo((): ProductVariation | null => {
    const variations = product.variations
    const attributes = product.attributes

    if (!variations || !attributes) return null
    if (Object.keys(selectedAttributes).length !== attributes.length) return null

    return (
      variations.find((variation) => {
        return attributes.every((attr) => {
          const attrKey = `attribute_${attr.name}`
          const selectedValue = selectedAttributes[attr.name]
          const variationValue = variation.attributes[attrKey]
          return variationValue === '' || variationValue === selectedValue
        })
      }) || null
    )
  }, [product.variations, product.attributes, selectedAttributes])

  const currentPrice = selectedVariation ? String(selectedVariation.price) : product.price
  const currentRegularPrice = selectedVariation
    ? String(selectedVariation.regular_price)
    : product.regular_price
  const currentImage = selectedVariation?.image ?? product.image ?? null
  const isOutOfStock = selectedVariation && !selectedVariation.is_in_stock
  const allAttributesSelected =
    product.attributes && Object.keys(selectedAttributes).length === product.attributes.length

  const handleAttributeChange = (attrName: string, value: string) => {
    setSelectedAttributes((prev) => ({
      ...prev,
      [attrName]: value,
    }))
  }

  const handleAddToCart = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()

    if (cartState === 'loading' || !selectedVariation) return

    setCartState('loading')

    const wcAjaxUrl = window.wpaicConfig?.wcAjaxUrl
    if (!wcAjaxUrl) {
      window.location.href = product.url
      return
    }

    const attributes: Record<string, string> = {}
    product.attributes?.forEach((attr) => {
      attributes[`attribute_${attr.name}`] = selectedAttributes[attr.name] || ''
    })

    try {
      const data = await requestAddToCart(
        {
          productId: product.id,
          variationId: selectedVariation.variation_id,
          quantity: 1,
          attributes,
        },
        wcAjaxUrl
      )

      setCartState('success')
      applyCartUpdate(data)

      setTimeout(() => setCartState('idle'), 2000)
    } catch {
      window.location.href = product.url
    }
  }

  const middleSlot = (
    <div className="px-3.5 pb-2 flex flex-col gap-2 max-[480px]:px-3 max-[480px]:gap-1.5">
      {product.attributes?.map((attr) => (
        <div key={attr.name} className="flex flex-col gap-1">
          <label
            htmlFor={`attr-${product.id}-${attr.name}`}
            className="text-[10px] font-mono font-medium text-slate-500 uppercase tracking-[0.14em] max-[480px]:text-[9px]"
          >
            {attr.label}
          </label>
          <select
            id={`attr-${product.id}-${attr.name}`}
            value={selectedAttributes[attr.name] || ''}
            onChange={(e) => handleAttributeChange(attr.name, e.target.value)}
            className="w-full py-1.5 px-2.5 text-xs border border-slate-200 rounded-lg bg-white focus:border-[var(--wpaic-primary)] focus:ring-1 focus:ring-[var(--wpaic-primary)] focus:outline-none transition-colors"
          >
            <option value="">Choose {attr.label}</option>
            {attr.options.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </div>
      ))}
    </div>
  )

  const button = isOutOfStock ? (
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
        'disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none',
        cartState === 'loading' && 'bg-slate-200 text-slate-500 shadow-none',
        cartState === 'success' && 'bg-emerald-600',
        cartState === 'error' && 'bg-red-600'
      )}
      onClick={handleAddToCart}
      disabled={cartState === 'loading' || !allAttributesSelected}
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
          <span className="tracking-wider">{allAttributesSelected ? 'ADD' : 'PICK'}</span>
        </>
      )}
    </button>
  )

  return (
    <ProductCardShell
      product={product}
      imageOverride={currentImage}
      priceOverride={{
        current: currentPrice,
        regular: currentRegularPrice,
      }}
      middleSlot={middleSlot}
      bottomSlot={button}
    />
  )
}
