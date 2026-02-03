#!/usr/bin/env node

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { execSync } from "child_process";
import { dirname, join } from "path";
import { fileURLToPath } from "url";
import { existsSync } from "fs";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const MODEL_SCRIPT = join(__dirname, "schema-extractor.php");
const ENUM_SCRIPT = join(__dirname, "enum-extractor.php");

// Support multiple ways to specify the Laravel project root:
// 1. LARAVEL_PATH environment variable
// 2. Current working directory (cwd in MCP config)
// 3. Fall back to relative path (for local .cursor-tools installation)
function getProjectRoot() {
  if (process.env.LARAVEL_PATH) {
    return process.env.LARAVEL_PATH;
  }

  // Check if cwd looks like a Laravel project
  const cwd = process.cwd();
  if (existsSync(join(cwd, "artisan")) && existsSync(join(cwd, "app/Models"))) {
    return cwd;
  }

  // Fall back to relative path (when installed in .cursor-tools/)
  return join(__dirname, "../..");
}

const PROJECT_ROOT = getProjectRoot();

function runPhpCommand(script, command, argument = "") {
  try {
    const args = argument ? `${command} "${argument}"` : command;
    const result = execSync(`php "${script}" ${args}`, {
      cwd: PROJECT_ROOT,
      encoding: "utf-8",
      maxBuffer: 10 * 1024 * 1024,
      env: { ...process.env, LARAVEL_PATH: PROJECT_ROOT },
    });
    return JSON.parse(result);
  } catch (error) {
    return { error: error.message };
  }
}

const server = new Server(
  {
    name: "laravel-inspector",
    version: "1.1.0",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: "list_models",
        description:
          "List all Eloquent models in the Laravel application. Returns model names and their full class paths.",
        inputSchema: {
          type: "object",
          properties: {},
          required: [],
        },
      },
      {
        name: "get_model_schema",
        description:
          "Get detailed schema information for a specific Laravel Eloquent model. Returns columns, types, relationships, casts, fillable/guarded fields, and traits.",
        inputSchema: {
          type: "object",
          properties: {
            model: {
              type: "string",
              description:
                "The model name (e.g., 'User', 'Account', 'Accounts/IraAccount'). Can include subdirectory path.",
            },
          },
          required: ["model"],
        },
      },
      {
        name: "search_models",
        description:
          "Search for models by name. Useful when you're not sure of the exact model name.",
        inputSchema: {
          type: "object",
          properties: {
            query: {
              type: "string",
              description: "Search query to match against model names",
            },
          },
          required: ["query"],
        },
      },
      {
        name: "list_enums",
        description:
          "List all PHP enums in the Laravel application. Returns enum names, backing types, and case counts.",
        inputSchema: {
          type: "object",
          properties: {},
          required: [],
        },
      },
      {
        name: "get_enum_details",
        description:
          "Get detailed information for a specific PHP enum. Returns all cases with values, custom methods, attributes, traits, and interfaces.",
        inputSchema: {
          type: "object",
          properties: {
            enum: {
              type: "string",
              description:
                "The enum name (e.g., 'AccountType', 'Accounts/AccountStatus'). Can include subdirectory path.",
            },
          },
          required: ["enum"],
        },
      },
      {
        name: "get_enum_values",
        description:
          "Get just the case names and values for an enum. Useful for quick lookups of valid values.",
        inputSchema: {
          type: "object",
          properties: {
            enum: {
              type: "string",
              description: "The enum name",
            },
          },
          required: ["enum"],
        },
      },
      {
        name: "search_enums",
        description:
          "Search for enums by name. Useful when you're not sure of the exact enum name.",
        inputSchema: {
          type: "object",
          properties: {
            query: {
              type: "string",
              description: "Search query to match against enum names",
            },
          },
          required: ["query"],
        },
      },
    ],
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  let result;

  switch (name) {
    // Model tools
    case "list_models":
      result = runPhpCommand(MODEL_SCRIPT, "list");
      break;
    case "get_model_schema":
      result = runPhpCommand(MODEL_SCRIPT, "schema", args.model);
      break;
    case "search_models":
      result = runPhpCommand(MODEL_SCRIPT, "search", args.query);
      break;
    // Enum tools
    case "list_enums":
      result = runPhpCommand(ENUM_SCRIPT, "list");
      break;
    case "get_enum_details":
      result = runPhpCommand(ENUM_SCRIPT, "details", args.enum);
      break;
    case "get_enum_values":
      result = runPhpCommand(ENUM_SCRIPT, "values", args.enum);
      break;
    case "search_enums":
      result = runPhpCommand(ENUM_SCRIPT, "search", args.query);
      break;
    default:
      result = { error: `Unknown tool: ${name}` };
  }

  return {
    content: [
      {
        type: "text",
        text: JSON.stringify(result, null, 2),
      },
    ],
  };
});

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("Laravel Inspector MCP Server running on stdio");
}

main().catch(console.error);
