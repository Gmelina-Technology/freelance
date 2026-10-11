---
paths:
  - 'app/Services/**'
---

# Services

## Business logic lives in service classes
This project keeps business logic in app/Services classes that expose named domain methods (create, send, void, accept). Do not introduce Action classes or event/listener layers.

## Pass the account explicitly to services
Service methods that touch tenant data take Account as their first explicit argument. Services never read the tenant from Filament or the session; callers resolve the account at the edge.
