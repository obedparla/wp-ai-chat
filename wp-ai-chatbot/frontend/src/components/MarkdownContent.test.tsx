import { render, screen } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import MarkdownContent from './MarkdownContent'

describe('MarkdownContent', () => {
  it('renders plain text', () => {
    render(<MarkdownContent content="Hello world" />)
    expect(screen.getByText('Hello world')).toBeInTheDocument()
  })

  it('renders bold text with strong tag', () => {
    render(<MarkdownContent content="This is **bold** text" />)
    const strong = screen.getByText('bold')
    expect(strong.tagName).toBe('STRONG')
  })

  it('renders italic text', () => {
    render(<MarkdownContent content="This is *italic* text" />)
    const em = screen.getByText('italic')
    expect(em.tagName).toBe('EM')
  })

  it('renders unordered list', () => {
    const content = `- Item 1
- Item 2
- Item 3`
    render(<MarkdownContent content={content} />)
    const listItems = screen.getAllByRole('listitem')
    expect(listItems).toHaveLength(3)
    expect(listItems[0]).toHaveTextContent('Item 1')
  })

  it('renders ordered list', () => {
    const content = `1. First
2. Second`
    render(<MarkdownContent content={content} />)
    const listItems = screen.getAllByRole('listitem')
    expect(listItems).toHaveLength(2)
    expect(listItems[0]).toHaveTextContent('First')
  })

  it('renders links with target blank', () => {
    render(<MarkdownContent content="Visit [Google](https://google.com)" />)
    const link = screen.getByRole('link', { name: 'Google' })
    expect(link).toHaveAttribute('href', 'https://google.com')
    expect(link).toHaveAttribute('target', '_blank')
    expect(link).toHaveAttribute('rel', 'noopener noreferrer')
  })

  it('renders inline code', () => {
    render(<MarkdownContent content="Use `console.log()` for debugging" />)
    const code = screen.getByText('console.log()')
    expect(code.tagName).toBe('CODE')
  })

  it('renders code blocks with pre tag', () => {
    const content = `\`\`\`
const x = 1;
\`\`\``
    render(<MarkdownContent content={content} />)
    expect(document.querySelector('pre')).toBeInTheDocument()
    expect(document.querySelector('code')).toBeInTheDocument()
  })

  it('renders blockquote', () => {
    render(<MarkdownContent content="> This is a quote" />)
    const blockquote = screen.getByText('This is a quote').closest('blockquote')
    expect(blockquote).toBeInTheDocument()
  })

  it('renders heading', () => {
    render(<MarkdownContent content="# Main Heading" />)
    const heading = screen.getByRole('heading', { level: 1 })
    expect(heading).toHaveTextContent('Main Heading')
  })

  it('renders strikethrough (GFM)', () => {
    render(<MarkdownContent content="This is ~~deleted~~ text" />)
    const del = screen.getByText('deleted')
    expect(del.tagName).toBe('DEL')
  })

  it('renders table (GFM)', () => {
    const tableMarkdown = `| Header 1 | Header 2 |
| -------- | -------- |
| Cell 1   | Cell 2   |`
    render(<MarkdownContent content={tableMarkdown} />)
    expect(screen.getByRole('table')).toBeInTheDocument()
    expect(screen.getByText('Header 1')).toBeInTheDocument()
    expect(screen.getByText('Cell 1')).toBeInTheDocument()
  })

  it('does not render script tags (XSS prevention)', () => {
    render(<MarkdownContent content="<script>alert('xss')</script>" />)
    expect(document.querySelector('script')).toBeNull()
  })

  it('does not render img tags (not in allowed list)', () => {
    render(<MarkdownContent content="![alt](https://example.com/img.png)" />)
    expect(document.querySelector('img')).toBeNull()
  })
})
