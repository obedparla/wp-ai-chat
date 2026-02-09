import { useState, useMemo } from 'react'
import { Product, ProductVariation } from './ProductCard'
import { cn } from '@/lib/utils'

interface VariableProductCardProps {
  product: Product
}

type CartState = 'idle' | 'loading' | 'success' | 'error'

export default function VariableProductCard({ product }: VariableProductCardProps) {
  const [selectedAttributes, setSelectedAttributes] = useState<Record<string, string>>({})
  const [cartState, setCartState] = useState<CartState>('idle')

  const selectedVariation = useMemo((): ProductVariation | null => {
    if (!product.variations || !product.attributes) return null
    if (Object.keys(selectedAttributes).length !== product.attributes.length) return null

    return (
      product.variations.find((variation) => {
        return product.attributes!.every((attr) => {
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
  const hasDiscount =
    currentRegularPrice &&
    currentPrice &&
    parseFloat(currentPrice) < parseFloat(currentRegularPrice)
  const currentImage = selectedVariation?.image || product.image
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
      // eslint-disable-next-line react-hooks/immutability
      window.location.href = product.url
      return
    }

    try {
      const params = new URLSearchParams({
        action: 'woocommerce_ajax_add_to_cart',
        product_id: String(product.id),
        variation_id: String(selectedVariation.variation_id),
        quantity: '1',
      })

      product.attributes?.forEach((attr) => {
        params.append(`attribute_${attr.name}`, selectedAttributes[attr.name] || '')
      })

      const response = await fetch(`${wcAjaxUrl}?${params.toString()}`, {
        method: 'POST',
        credentials: 'same-origin',
      })

      if (!response.ok) {
        // eslint-disable-next-line react-hooks/immutability
        window.location.href = product.url
        return
      }

      const data = await response.json()

      if (data.error) {
        // eslint-disable-next-line react-hooks/immutability
        window.location.href = product.url
        return
      }

      setCartState('success')
      triggerCartUpdate()

      setTimeout(() => setCartState('idle'), 2000)
    } catch {
      // eslint-disable-next-line react-hooks/immutability
      window.location.href = product.url
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
        className="flex flex-col [text-decoration:none] text-inherit"
      >
        <div className="w-full aspect-[4/3] bg-slate-50 overflow-hidden">
          {currentImage ? (
            <img
              src={currentImage}
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
                  ${currentRegularPrice}
                </span>
                <span className="text-red-600">${currentPrice}</span>
              </>
            ) : (
              <span>${currentPrice || '0'}</span>
            )}
          </div>
          {product.short_description && (
            <p className="text-xs text-slate-600 mt-1.5 line-clamp-2 leading-relaxed max-[480px]:text-[11px]">
              {product.short_description}
            </p>
          )}
        </div>
      </a>

      <div className="px-2.5 pb-2 flex flex-col gap-2 max-[480px]:px-2 max-[480px]:gap-1.5">
        {product.attributes?.map((attr) => (
          <div key={attr.name} className="flex flex-col gap-1">
            <label
              htmlFor={`attr-${product.id}-${attr.name}`}
              className="text-[10px] font-medium text-slate-600 uppercase tracking-wide max-[480px]:text-[9px]"
            >
              {attr.label}
            </label>
            <select
              id={`attr-${product.id}-${attr.name}`}
              value={selectedAttributes[attr.name] || ''}
              onChange={(e) => handleAttributeChange(attr.name, e.target.value)}
              className="w-full py-1.5 px-2 text-[11px] border border-slate-200 rounded-lg bg-white focus:border-[var(--wpaic-primary)] focus:ring-1 focus:ring-[var(--wpaic-primary)] focus:outline-none transition-colors max-[480px]:py-1 max-[480px]:px-1.5 max-[480px]:text-[10px]"
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

      <div className="px-2.5 pb-2.5 mt-auto max-[480px]:px-2 max-[480px]:pb-2">
        {isOutOfStock ? (
          <button
            type="button"
            className="w-full py-1.5 px-2.5 bg-slate-200 text-slate-500 border-0 rounded-lg font-semibold text-[11px] cursor-not-allowed max-[480px]:py-1 max-[480px]:px-2 max-[480px]:text-[10px]"
            disabled
          >
            Out of Stock
          </button>
        ) : (
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
            disabled={cartState === 'loading' || !allAttributesSelected}
            aria-label={cartState === 'success' ? 'Added to cart' : 'Add to cart'}
          >
            {cartState === 'loading' && (
              <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            )}
            {cartState === 'success' && '✓ Added'}
            {cartState === 'error' && 'Error'}
            {cartState === 'idle' && (allAttributesSelected ? 'Add to Cart' : 'Select Options')}
          </button>
        )}
      </div>
    </div>
  )
}
