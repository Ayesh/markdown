# Markdown
Fork of [`erusev/parsedown`](https://github.com/erusev/parsedown) with modernized code base, maintained, and made even faster with micro-optimizations.

This project was forked from Parsedown 1.7.4, and has been maintained since. It tries to keep feature parity with Parsedown, but this project favors code maintainability, modern PHP features, security and performance.

 - Uses modern PHP features such as [typed class constants](https://php.watch/versions/8.3/typed-constants), [typed properties](https://php.watch/versions/7.4/typed-properties), and several `preg_` and other string inspect/manipulation functions with [`str_contains`](https://php.watch/versions/8.0/str_contains), [str_starts_with](https://php.watch/versions/8.0/str_starts_with-str_ends_with), ['str_ends_with`](https://php.watch/versions/8.0/str_starts_with-str_ends_with), etc.
 - Supports and requires [PHP 8.3](https://php.watch/versions/8.3)
 - Latest PHPUnit version for tests
 - Nesting and branching improvements
 - Custom header support
 - Table row class support
 - Table column align support with `align` attributes and not inline styles (helps with CSP)
 - Blockquote element custom class support

## Markup differences

This library offers an opinionated list of markup improvements compared to the Common Mark spec: 

### Custom Header Support

Parsedown does not support header anchor tags. This library provides header anchors using this syntax:

```markdown
## My Title {#my-title}
```

This yields:

```html
<h2 id="my-title"><a href="#my-title" class="anchor">My Title</a></h2>
```
