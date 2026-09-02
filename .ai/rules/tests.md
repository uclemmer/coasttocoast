---
paths:
  - 'tests/**'
---

# Tests

## Event::factory() is permanently open for registration
`EventFactory::definition()` sets `registration_opens_at` and `registration_closes_at` to null, and `Event::isRegistrationOpen()` treats a null bound as no bound — so a plain `Event::factory()->published()->create()` is open for registration forever.

That is fine for most tests and invisible in the ones it breaks. It hid a real bug: `Staff\Events\Show` guarded the announcement on `is_published` alone, so a fair published ahead of its window would mail "Registration is now open. It is open now." over a button to a page that refuses the registration — and every existing announce test passed, because none of them had a window at all.

Writing anything that depends on registration timing? Use `registrationOpen()`, `registrationNotYetOpen()` or `registrationClosed()` explicitly. A null window is not a neutral default; it is one specific case.
