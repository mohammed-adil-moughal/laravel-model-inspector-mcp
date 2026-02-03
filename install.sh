#!/bin/bash

# Install script for Laravel Model Inspector MCP
# Run this from within any Laravel project directory

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Get Laravel project root (argument or current directory)
if [ -n "$1" ]; then
    PROJECT_ROOT="$(cd "$1" && pwd)"
else
    PROJECT_ROOT="$(pwd)"
fi

# Verify it's a Laravel project
if [ ! -f "$PROJECT_ROOT/artisan" ]; then
    echo -e "${RED}❌ Error: Not a Laravel project${NC}"
    echo "   No 'artisan' file found in: $PROJECT_ROOT"
    echo ""
    echo "Usage: $0 [path/to/laravel/project]"
    echo "   Or run from within a Laravel project directory"
    exit 1
fi

# Get full path to node (required for Cursor to find it)
NODE_PATH=$(which node 2>/dev/null)
if [ -z "$NODE_PATH" ]; then
    echo -e "${RED}❌ Error: node not found in PATH${NC}"
    echo "   Please install Node.js first: https://nodejs.org/"
    exit 1
fi

echo -e "${GREEN}🔧 Setting up Laravel Model Inspector MCP${NC}"
echo "   Laravel project: $PROJECT_ROOT"
echo "   Node path: $NODE_PATH"
echo "   Inspector: $SCRIPT_DIR"
echo ""

# Install npm dependencies if needed
if [ ! -d "$SCRIPT_DIR/node_modules" ]; then
    echo "📦 Installing npm dependencies..."
    cd "$SCRIPT_DIR"
    npm install
fi

# Create Cursor MCP config
MCP_CONFIG_DIR="$PROJECT_ROOT/.cursor"
mkdir -p "$MCP_CONFIG_DIR"

MCP_CONFIG="$MCP_CONFIG_DIR/mcp.json"

# Check if mcp.json exists and has content
if [ -f "$MCP_CONFIG" ] && [ -s "$MCP_CONFIG" ]; then
    echo -e "${YELLOW}⚠️  Found existing .cursor/mcp.json${NC}"
    echo ""
    echo "   Please manually add the model-inspector config:"
    echo ""
    echo '   "model-inspector": {'
    echo "     \"command\": \"$NODE_PATH\","
    echo "     \"args\": [\"$SCRIPT_DIR/server.js\"],"
    echo "     \"cwd\": \"$PROJECT_ROOT\""
    echo '   }'
    echo ""
else
    echo "📝 Creating .cursor/mcp.json..."
    cat > "$MCP_CONFIG" << EOF
{
  "mcpServers": {
    "model-inspector": {
      "command": "$NODE_PATH",
      "args": ["$SCRIPT_DIR/server.js"],
      "cwd": "$PROJECT_ROOT"
    }
  }
}
EOF
    echo -e "${GREEN}✅ Created $MCP_CONFIG${NC}"
fi

echo ""
echo -e "${GREEN}✅ Setup complete!${NC}"
echo ""
echo "Next steps:"
echo "  1. Restart Cursor to load the MCP server"
echo "  2. The model-inspector tools will be available:"
echo "     - list_models, get_model_schema, search_models"
echo "     - list_enums, get_enum_details, get_enum_values, search_enums"
echo ""
echo "To use with another Laravel project:"
echo "  $SCRIPT_DIR/install.sh /path/to/other/laravel/project"
