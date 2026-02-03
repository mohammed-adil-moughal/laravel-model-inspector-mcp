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

const PHP_SCRIPT = join(__dirname, "schema-extractor.php");

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

function runPhpCommand(command, argument = "") {
  try {
    const args = argument ? `${command} "${argument}"` : command;
    const result = execSync(`php "${PHP_SCRIPT}" ${args}`, {
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
    name: "model-inspector",
    version: "1.0.0",
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
    ],
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  let result;

  switch (name) {
    case "list_models":
      result = runPhpCommand("list");
      break;
    case "get_model_schema":
      result = runPhpCommand("schema", args.model);
      break;
    case "search_models":
      result = runPhpCommand("search", args.query);
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
  console.error("Model Inspector MCP Server running on stdio");
}

main().catch(console.error);
