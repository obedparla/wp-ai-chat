import ProductCard, { Product } from './ProductCard'
import VariableProductCard from './VariableProductCard'
import LinkProductCard from './LinkProductCard'
import { hasPositivePrice } from '@/lib/price'
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselPrevious,
  CarouselNext,
} from '@/components/ui/carousel'

interface ProductGridProps {
  products: Product[]
}

const VARIABLE_TYPES = new Set(['variable', 'variable-subscription'])
const SIMPLE_TYPES = new Set(['simple', 'subscription'])

function canRenderVariableInline(product: Product): boolean {
  return (
    VARIABLE_TYPES.has(product.product_type ?? '') &&
    !product.is_complex &&
    Array.isArray(product.attributes) &&
    product.attributes.length > 0 &&
    Array.isArray(product.variations) &&
    product.variations.length > 0
  )
}

function renderProductCard(product: Product) {
  const type = product.product_type ?? 'simple'

  if (canRenderVariableInline(product)) {
    return <VariableProductCard key={product.id} product={product} />
  }

  if (type === 'external') {
    const href = product.external_url || product.url
    return (
      <LinkProductCard
        key={product.id}
        product={product}
        href={href}
        target="_blank"
        label={product.button_text || 'Buy product'}
      />
    )
  }

  if (type === 'grouped' || type === 'bundle') {
    return (
      <LinkProductCard
        key={product.id}
        product={product}
        href={product.url}
        target="_blank"
        label="View options"
      />
    )
  }

  if (SIMPLE_TYPES.has(type) || VARIABLE_TYPES.has(type)) {
    // Zero/empty price (e.g. unpriced sample products): an active ADD button
    // would add a $0.00 item, so link to the product page instead.
    if (!hasPositivePrice(product.price)) {
      return (
        <LinkProductCard
          key={product.id}
          product={product}
          href={product.url}
          target="_blank"
          label="View product"
        />
      )
    }
    return <ProductCard key={product.id} product={product} />
  }

  return (
    <LinkProductCard
      key={product.id}
      product={product}
      href={product.url}
      target="_blank"
      label="View product"
    />
  )
}

export default function ProductGrid({ products }: ProductGridProps) {
  if (products.length === 0) return null

  // 1-2 products: vertical stack (no carousel)
  if (products.length <= 2) {
    return (
      <div className="grid grid-cols-2 gap-3 max-[480px]:gap-2">
        {products.map(renderProductCard)}
      </div>
    )
  }

  // 3+ products: horizontal carousel with partial peek. Arrows live in the
  // header row above the cards (not overlaying them) so they are always
  // visible and misclicks on a card are impossible; touch devices keep the
  // SWIPE hint instead.
  return (
    <div className="w-full">
      <Carousel
        opts={{
          align: 'start',
          loop: false,
        }}
        className="w-full"
      >
        <div className="flex items-center gap-3 mb-3 px-1">
          <span className="text-[10px] font-mono font-semibold tracking-[0.18em] text-slate-700">
            {products.length} PICKS
          </span>
          <div className="flex-1 h-px bg-slate-300" />
          <span className="text-[10px] font-mono font-semibold tracking-[0.18em] text-slate-500 [@media(hover:hover)]:hidden">
            SWIPE →
          </span>
          <div className="flex items-center gap-1.5 [@media(hover:none)]:hidden">
            <CarouselPrevious className="static size-7 translate-y-0" />
            <CarouselNext className="static size-7 translate-y-0" />
          </div>
        </div>
        <CarouselContent className="-ml-3 max-[480px]:-ml-2">
          {products.map((product) => (
            <CarouselItem
              key={product.id}
              className="pl-3 basis-[68%] max-[480px]:pl-2 max-[480px]:basis-[75%]"
            >
              {renderProductCard(product)}
            </CarouselItem>
          ))}
        </CarouselContent>
      </Carousel>
    </div>
  )
}
