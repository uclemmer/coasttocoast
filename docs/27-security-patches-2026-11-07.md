# 27 — Security patches, 2026-11-07

`composer audit --locked` reported ten advisories against seven packages. All
were fixed by a targeted update inside the existing constraints:

```bash
composer update "symfony/*" livewire/livewire phpunit/phpunit pestphp/pest --with-dependencies
```

No constraint changed, no `uclemmer/*` package moved, and no application code
changed. `composer audit` now reports none.

## 1. What moved, and why

| Package | Was | Now | Advisory |
| --- | --- | --- | --- |
| `symfony/http-foundation` | `8.1.8` | `8.1.11` | CVE-2026-118514 (HEAD requests bypass `access_control` methods), a path-rule bypass, a trusted-proxies `private_ranges` bypass, client-chosen hosts in generated URLs |
| `symfony/http-kernel` | `8.1.8` | `8.1.11` | Fixed in the same Symfony security release |
| `symfony/routing` | `8.1.8` | `8.1.11` | CVE-2026-118516 (host placeholders can match the start of the path) |
| `symfony/yaml` | `8.1.8` | `8.1.11` | Parse-time denial of service |
| `symfony/mime` | `8.1.7` | `8.1.10` | CVE-2026-45070 (email header injection) |
| `livewire/livewire` | `4.4.7` | `4.4.8` | Authorization bypass in `#[Authorize]` |
| `phpunit/phpunit` | `13.4.1` | `13.4.5` | CVE-2026-119193 (dev-only) |
| `pestphp/pest` | `5.3.1` | `5.3.5` | No advisory. PHPUnit 13.4.5 needs Pest 5.3.4 or later |

The rest of the Symfony components moved with them to their latest 8.1 patch,
along with `brianium/paratest` `7.27.5`, `laravel/prompts` `0.3.26` and
`nunomaduro/termwind` `2.4.1`. `theseer/tokenizer` left the lock because the
new PHPUnit no longer requires it.

## 2. Verified

- Suite: 977 passed, 34 skipped, before and after.
- Pint clean. `CLAUDE.md` and `AGENTS.md` still say PHP 8.5, matching
  `composer.json`.
