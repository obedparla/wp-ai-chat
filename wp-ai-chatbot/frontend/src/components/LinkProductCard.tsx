import { Product } from './ProductCard'
import ProductCardShell from './ProductCardShell'

interface LinkProductCardProps {
  product: Product
  href: string
  target?: '_self' | '_blank'
  label: string
  cardHref?: string
}

export default function LinkProductCard({
  product,
  href,
  target = '_self',
  label,
  cardHref,
}: LinkProductCardProps) {
  const buttonIsExternal = target === '_blank' && href !== (cardHref ?? product.url)

  const button = (
    <a
      href={href}
      target={target}
      rel={target === '_blank' ? 'noopener noreferrer' : undefined}
      onClick={(e) => e.stopPropagation()}
      className="wpaic-no-underline inline-flex items-center gap-1 rounded-full border-0 cursor-pointer font-semibold text-xs transition-all duration-200 px-3.5 py-2 shrink-0 bg-[var(--wpaic-primary)] text-white hover:scale-[1.04] active:scale-95 shadow-sm tracking-wider"
    >
      {buttonIsExternal && (
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="w-3.5 h-3.5"
        >
          <path d="M15 3h6v6" />
          <path d="M10 14 21 3" />
          <path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" />
        </svg>
      )}
      <span>{label.toUpperCase()}</span>
    </a>
  )

  return (
    <ProductCardShell
      product={product}
      href={cardHref ?? product.url}
      hrefTarget="_blank"
      bottomSlot={button}
    />
  )
}
