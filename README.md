# Laravel Model Inspector MCP

MCP (Model Context Protocol) server for inspecting Laravel Eloquent models and PHP enums. Works with any Laravel project.

## Why Use This?

**The Problem:** Large Laravel projects have hundreds of models and migrations. Finding schema information means hunting through migration files, reading model code, and piecing together relationships manually.

**The Solution:** Instant, accurate model introspection that gives AI assistants (and you) complete schema context in seconds.

### Tangible Benefits

| Benefit | Without Tool | With Tool |
|---------|--------------|-----------|
| **Schema lookup** | Read migrations + model + check casts (2-5 min) | Single query (4 seconds) |
| **Relationship discovery** | Trace through multiple model files | See all relationships at once |
| **Column types** | Hope the migration is up to date | Live data from database |
| **Enum values** | Open file, scroll through cases | Instant list with all values |
| **Enum methods** | Read code to find helper methods like `isRoth()` | See all methods at once |
| **New feature context** | "What columns exist on Payment?" requires file hunting | Instant answer with all 50+ columns |

### Real-World Impact

- **Fewer questions:** AI already knows your schema, writes correct code on first try
- **Prevents bugs:** No more wrong column type assumptions or missing relationships
- **Faster development:** Skip the migration archaeology on large codebases
- **Accurate traversal:** Instantly see `User -> WebIra -> WebTrustasset` relationship chains

### Security

- **Read-only:** Only inspects existing code and database schema
- **No external calls:** All processing happens locally
- **No data access:** Reads structure only, not your actual data

## Features

### Model Tools
- **list_models** - List all Eloquent models in the application
- **get_model_schema** - Get detailed schema for a specific model:
  - Database columns and types
  - Eloquent relationships
  - Attribute casts
  - Fillable/guarded fields
  - Hidden attributes
  - Traits used
- **search_models** - Search models by name

### Enum Tools
- **list_enums** - List all PHP enums with backing types and case counts
- **get_enum_details** - Get full details for a specific enum:
  - All cases with values
  - PHP attributes on cases (e.g., `#[Label('...')]`)
  - Custom methods (static and instance)
  - Traits and interfaces
- **get_enum_values** - Quick lookup of just case names and values
- **search_enums** - Search enums by name

## Requirements

- Node.js >= 18.0.0
- PHP >= 8.1
- A Laravel project with Eloquent models

## Installation

### Option 1: Clone/Download to your machine

```bash
git clone https://github.com/mohammed-adil-moughal/laravel-model-inspector-mcp.git ~/.cursor-tools/laravel-model-inspector-mcp
cd ~/.cursor-tools/laravel-model-inspector-mcp
npm install
```

### Option 2: Install per-project (in .cursor-tools/)

```bash
mkdir -p .cursor-tools
cd .cursor-tools
git clone https://github.com/mohammed-adil-moughal/laravel-model-inspector-mcp.git model-inspector
cd model-inspector
npm install
```

Add `.cursor-tools/` to your global gitignore to avoid committing.

## Configuration

Add to your Cursor MCP configuration (`.cursor/mcp.json` in your project):

### Global Installation

```json
{
  "mcpServers": {
    "model-inspector": {
      "command": "node",
      "args": ["~/.cursor-tools/laravel-model-inspector-mcp/server.js"],
      "cwd": "/path/to/your/laravel/project"
    }
  }
}
```

### Per-Project Installation

```json
{
  "mcpServers": {
    "model-inspector": {
      "command": "node",
      "args": [".cursor-tools/model-inspector/server.js"],
      "cwd": "/path/to/your/laravel/project"
    }
  }
}
```

### Using Environment Variable

You can also set `LARAVEL_PATH` explicitly:

```json
{
  "mcpServers": {
    "model-inspector": {
      "command": "node",
      "args": ["/path/to/server.js"],
      "env": {
        "LARAVEL_PATH": "/path/to/your/laravel/project"
      }
    }
  }
}
```

## Usage

Once configured, restart Cursor and ask the AI assistant:

**Models:**
- "List all models in the project"
- "Show me the schema for the User model"
- "What are the relationships on the Account model?"
- "Search for models related to 'payment'"

**Enums:**
- "List all enums"
- "What are the valid values for AccountType?"
- "Show me the Frequency enum with its methods"
- "Search for enums related to 'status'"

## Example Output

```json
{
  "model": "User",
  "class": "App\\Models\\User",
  "table": "users",
  "primaryKey": "id",
  "keyType": "int",
  "incrementing": true,
  "timestamps": true,
  "columns": {
    "id": { "type": "int8" },
    "email": { "type": "varchar" },
    "name": { "type": "varchar" }
  },
  "casts": {
    "email_verified_at": "datetime"
  },
  "fillable": ["name", "email", "password"],
  "guarded": ["*"],
  "hidden": ["password", "remember_token"],
  "relationships": {
    "posts": { "type": "hasMany", "related": "App\\Models\\Post" },
    "profile": { "type": "hasOne", "related": "App\\Models\\Profile" }
  },
  "traits": ["HasFactory", "Notifiable"]
}
```

## Enum Example Output

```json
{
  "enum": "AccountType",
  "class": "App\\Enums\\AccountType",
  "backingType": "string",
  "cases": [
    { "name": "TRADITIONAL", "value": "Traditional" },
    { "name": "ROTH", "value": "Roth" },
    { "name": "SEP", "value": "SEP" }
  ],
  "methods": [
    { "name": "isRoth", "static": false, "returnType": "bool" },
    { "name": "employerPlanTypes", "static": true, "returnType": "array" }
  ],
  "traits": [],
  "interfaces": ["UnitEnum", "BackedEnum"]
}
```

## How It Works

1. The Node.js MCP server receives requests from Cursor
2. It executes the PHP schema extractor script
3. The PHP script bootstraps Laravel and uses reflection to inspect models
4. Results are returned as JSON through the MCP protocol

## Troubleshooting

### "Laravel project not found"

Ensure the `cwd` in your MCP config points to a valid Laravel project with:
- `artisan` file in the root
- `app/Models` directory
- `vendor/autoload.php` (run `composer install`)

### Database connection errors

The tool connects to your database to get column information. Ensure:
- Your `.env` has valid database credentials
- The database server is running

### Model not found

Use the full path for models in subdirectories:
- `User` for `app/Models/User.php`
- `Accounts/IraAccount` for `app/Models/Accounts/IraAccount.php`

## License

MIT
