# 30 — "Forgot password" threw `Route [password.reset] not defined` (2026-11-10)

Found by the owner on the first attempt to sign in: submitting
`/forgot-password` failed with

```
Symfony\Component\Routing\Exception\RouteNotFoundException
Route [password.reset] not defined.
```

So no account could recover a password. That matters here because the
coordinator account is seeded with a random password (doc 11), which makes the
reset the only way in on production.

## Cause: a laravel-core bug

- Laravel's `Illuminate\Auth\Notifications\ResetPassword` builds its link with
  `route('password.reset')`.
- laravel-core registers its auth routes under a `core.` name prefix
  (`routes/web.php` in the package), so the route is `core.password.reset`.
- Core never calls `ResetPassword::createUrlUsing()`. This is still true of
  `v0.9.0`.
- So the token is written and the exception fires while the email is being
  built.

Core's `AuthScaffoldingTest` "emails a password reset link" fakes the
notification and asserts it was *sent*, so the link is never built. This is the
fixture trap the workspace records, again: the test cannot fail on the thing
that is broken.

## Fix in this app

`AppServiceProvider::pointPasswordResetsAtCore()` registers the URL callback
against `core.password.reset`, with the token and email. A host is allowed to
set this, and it stays correct after core fixes its side.

**The fix belongs in core.** It needs:
- a patch (0.8.x for this app's `^0.8`, and 0.9.x) setting the same callback
  in core's auth registrar;
- a test there that renders the real mail.

Once core ships it, this method can go.

## Test

`CoreAuthSurfaceTest` "emails a reset link that opens the reset page":
1. sends the reset through the real `array` mail transport, not a fake;
2. pulls the link out of the HTML;
3. GETs it and sees the new-password form.

It failed on the old code with the same exception the owner saw, and passes
now. Suite: 1017 passed, 34 skipped.
