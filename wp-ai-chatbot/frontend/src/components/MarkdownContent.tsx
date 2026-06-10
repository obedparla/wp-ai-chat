import { useMemo } from 'react'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

interface MarkdownContentProps {
  content: string
}

const allowedElements = [
  'p',
  'strong',
  'em',
  'ul',
  'ol',
  'li',
  'a',
  'code',
  'pre',
  'blockquote',
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'br',
  'hr',
  'del',
  'table',
  'thead',
  'tbody',
  'tr',
  'th',
  'td',
]

// Dedicated DOMPurify instance so the link-target hook below cannot leak into
// other sanitizer consumers.
const purifier = DOMPurify(window)

purifier.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A') {
    node.setAttribute('target', '_blank')
    node.setAttribute('rel', 'noopener noreferrer')
  }
})

function renderMarkdown(content: string): string {
  const html = marked.parse(content, { gfm: true, async: false })
  return purifier.sanitize(html, {
    ALLOWED_TAGS: allowedElements,
    ALLOWED_ATTR: ['href', 'target', 'rel', 'start', 'align'],
  })
}

export default function MarkdownContent({ content }: MarkdownContentProps) {
  const html = useMemo(() => renderMarkdown(content), [content])

  return <div dangerouslySetInnerHTML={{ __html: html }} />
}
