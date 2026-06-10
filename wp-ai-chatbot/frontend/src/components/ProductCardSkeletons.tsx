import { cn } from '@/lib/utils'

const SKELETON_COUNT = 2

const shimmerClass =
  'bg-gradient-to-br from-slate-200 via-slate-100 to-slate-200 bg-[length:200%_200%] animate-shimmer'

function SkeletonCard() {
  return (
    <div className="flex flex-col bg-white border border-slate-200 rounded-2xl overflow-hidden" aria-hidden>
      <div className={cn('w-full aspect-[4/3]', shimmerClass)} />
      <div className="px-3.5 pt-3 pb-3.5 flex flex-col gap-2 max-[480px]:px-3">
        <div className={cn('h-2.5 w-1/3 rounded-full', shimmerClass)} />
        <div className={cn('h-4 w-4/5 rounded-full', shimmerClass)} />
        <div className={cn('h-4 w-1/4 rounded-full mt-1', shimmerClass)} />
      </div>
    </div>
  )
}

// Shimmering placeholder cards rendered in-thread while a product tool call is
// in flight, so tool latency reads as the answer assembling itself.
export default function ProductCardSkeletons() {
  return (
    <div
      className="grid grid-cols-2 gap-3 max-[480px]:gap-2 w-full animate-wpaic-fadeIn"
      role="status"
      aria-label="Loading products"
    >
      {Array.from({ length: SKELETON_COUNT }, (_, index) => (
        <SkeletonCard key={index} />
      ))}
    </div>
  )
}
