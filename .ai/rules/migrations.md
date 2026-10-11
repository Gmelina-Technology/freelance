---
paths:
  - 'database/migrations/**'
---

# Migrations

## Enum columns are string columns
Store enum-valued fields as string columns, not database enum() columns. Cast them to the PHP enum on the model.
