# Test Enforcement

- Every change ships with tests in the same change. That includes copy, styling and layout changes: assert the rendered text, the route, or the class that must compile, so the change cannot silently regress.
- Update existing tests when behaviour changes; never delete, skip or weaken a test to make a suite pass without approval.
- Run the affected tests and make sure they pass before finishing.
- Read the `testing-best-practices` skill before writing tests.
