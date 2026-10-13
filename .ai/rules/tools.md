---
paths:
  - 'app/Mcp/Tools/**'
---

# Tools

## MCP tools implement execute()
MCP tools extend BaseTool and implement execute() instead of handle(). Declare access with $requiredAbility and $requiredCapability; the base class resolves the account.
