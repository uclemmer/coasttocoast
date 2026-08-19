---
paths:
  - 'resources/views/components/layouts/**'
---

# Layouts

## The public layout must call @fonts, and it must come before @vite
Configuring `fonts:` in vite.config.js (the `bunny()` helpers) downloads Montserrat, Caveat and Source Sans 3 at build time and writes public/build/fonts-manifest.json — but nothing reaches the page unless the layout calls `@fonts`. This failure is completely silent: the build succeeds, the tests pass, every page renders in the fallback system stack. It shipped that way once.

`@fonts` goes before `@vite` so the @font-face rules are parsed before the stylesheet that uses them. It inlines the declarations and preloads each woff2; it does not link a second CSS file, so do not assert on the manifest's filename appearing in the HTML.

Never replace this with a Google Fonts <link>. Doc 10 D-8.1-a: the visitors are high schoolers and their parents, and a public page should not announce them to a third party before it paints. `FrontendWiringTest` asserts no fonts.googleapis.com reference survives.
