import { ReactNode } from 'react'
import { formatPrice, hasPositivePrice } from '@/lib/price'
import { Product } from './ProductCard'

interface ProductCardShellProps {
  product: Product
  imageOverride?: string | null
  priceOverride?: { current: string; regular?: string }
  href?: string
  hrefTarget?: '_self' | '_blank'
  bottomSlot: ReactNode
  middleSlot?: ReactNode
}

export default function ProductCardShell({
  product,
  imageOverride,
  priceOverride,
  href,
  hrefTarget = '_blank',
  bottomSlot,
  middleSlot,
}: ProductCardShellProps) {
  const image = imageOverride !== undefined ? imageOverride : product.image
  const currentPrice = priceOverride?.current ?? product.price
  const regularPrice = priceOverride?.regular ?? product.regular_price
  const salePrice = priceOverride ? undefined : product.sale_price

  // Non-sale products arrive with sale_price '' — fall back to the real price.
  const displayCurrent = priceOverride
    ? currentPrice
    : hasPositivePrice(salePrice)
      ? salePrice
      : currentPrice
  const showPrice = hasPositivePrice(displayCurrent)

  const hasDiscount =
    showPrice &&
    (priceOverride
      ? regularPrice && currentPrice && parseFloat(currentPrice) < parseFloat(regularPrice)
      : salePrice && regularPrice && parseFloat(salePrice) < parseFloat(regularPrice))
  const linkHref = href ?? product.url
  const category = product.categories?.[0]

  const content = (
    <>
      <div className="relative w-full aspect-[4/3] bg-slate-50 overflow-hidden">
        {image ? (
          <img src={image} alt={product.name} className="w-full h-full object-cover" />
        ) : (
          <div className="w-full h-full bg-gradient-to-br from-slate-200 via-slate-100 to-slate-200 bg-[length:200%_200%] animate-shimmer" />
        )}
        {hasDiscount && (
          <span className="absolute top-3 left-3 inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-0.5 text-[10px] font-mono font-semibold tracking-[0.14em] text-slate-800">
            SALE
          </span>
        )}
      </div>
      <div className="px-3.5 pt-3 pb-1 flex flex-col gap-1 max-[480px]:px-3">
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
    </>
  )

  return (
    <div className="flex flex-col h-full bg-white border border-slate-200 rounded-2xl overflow-hidden transition-[box-shadow,border-color] duration-200 hover:shadow-md hover:border-slate-300">
      <a
        href={linkHref}
        target={hrefTarget}
        rel={hrefTarget === '_blank' ? 'noopener noreferrer' : undefined}
        className="wpaic-no-underline flex flex-col flex-1 text-inherit"
      >
        {content}
      </a>
      {middleSlot}
      {/* flex-wrap + ml-auto: long prices (e.g. $13,999.99 $13,292.99) push the
          button onto its own right-aligned line instead of clipping it off the
          card edge. */}
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5 px-3.5 pb-3.5 pt-2 mt-auto max-[480px]:px-3 max-[480px]:pb-3">
        {showPrice && (
          <div className="text-base font-bold text-slate-900 max-[480px]:text-[15px]">
            {hasDiscount ? (
              <>
                <span className="line-through text-slate-400 font-normal mr-1.5 text-sm">
                  {formatPrice(regularPrice)}
                </span>
                <span>{formatPrice(displayCurrent)}</span>
              </>
            ) : (
              <span>{formatPrice(displayCurrent)}</span>
            )}
          </div>
        )}
        <div className="ml-auto">{bottomSlot}</div>
      </div>
    </div>
  )
}
