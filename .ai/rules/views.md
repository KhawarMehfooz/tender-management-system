---
paths:
  - 'resources/views/**'
---

# Views

## whitespace-pre-wrap preserves Blade's own indentation
If an element uses the `whitespace-pre-wrap` Tailwind class, the interpolated value must sit flush against the tags with no surrounding newline/indentation in the Blade source — e.g. `<p class="whitespace-pre-wrap">{{ $value }}</p>` on one line, not `{{ $value }}` on its own indented line inside the tag. `pre-wrap` preserves whitespace exactly as written in the rendered HTML, so Blade's own template indentation becomes visible, misaligned leading whitespace in the browser. Hit this in `resources/views/filament/infolists/task-comments-timeline.blade.php`'s comment body — reported as "the comment text looks indented."
