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
    <div className="w-full relative px-2 max-[480px]:px-1.5">
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
        <CarouselPrevious className="-left-2 max-[480px]:-left-1" />
        <CarouselNext className="-right-2 max-[480px]:-right-1" />
      </Carousel>
    </div>
  )
}
