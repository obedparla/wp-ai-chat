import ProductCard, { Product } from './ProductCard'
import VariableProductCard from './VariableProductCard'
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

function isSimpleVariableProduct(product: Product): boolean {
  return (
    product.product_type === 'variable' &&
    !product.is_complex &&
    Array.isArray(product.attributes) &&
    product.attributes.length > 0 &&
    Array.isArray(product.variations) &&
    product.variations.length > 0
  )
}

function renderProductCard(product: Product) {
  return isSimpleVariableProduct(product) ? (
    <VariableProductCard key={product.id} product={product} />
  ) : (
    <ProductCard key={product.id} product={product} />
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

  // 3+ products: horizontal carousel with partial peek
  return (
    <div className="w-full group">
      <div className="flex items-center gap-3 mb-3 px-1">
        <span className="text-[10px] font-mono font-semibold tracking-[0.18em] text-slate-700">
          {products.length} PICKS
        </span>
        <div className="flex-1 h-px bg-slate-300" />
        <span className="text-[10px] font-mono font-semibold tracking-[0.18em] text-slate-500 [@media(hover:hover)]:hidden">
          SWIPE →
        </span>
      </div>
      <div className="relative">
        <Carousel
          opts={{
            align: 'start',
            loop: false,
          }}
          className="w-full"
        >
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
          <CarouselPrevious className="-left-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100 [@media(hover:none)]:hidden" />
          <CarouselNext className="-right-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100 [@media(hover:none)]:hidden" />
        </Carousel>
      </div>
    </div>
  )
}
