#!/usr/bin/env node
// @ts-check
"use strict";

const fs = require("fs");
const path = require("path");

const PKG_NAME = "@przeprogramowani/ai-card-collector-toolkit";
const SENTINEL_BEGIN = `<!-- BEGIN ${PKG_NAME} -->`;
const SENTINEL_END = `<!-- END ${PKG_NAME} -->`;
const MANIFEST_FILE = ".claude/.ai-toolkit-manifest.json";

const projectRoot = process.env.INIT_CWD || process.cwd();

function removeRulesBlock(targetFile) {
  if (!fs.existsSync(targetFile)) return;
  const content = fs.readFileSync(targetFile, "utf8");
  const start = content.indexOf(SENTINEL_BEGIN);
  const end = content.indexOf(SENTINEL_END);
  if (start === -1 || end === -1) return;
  const updated = content.slice(0, start).trimEnd() + "\n" + content.slice(end + SENTINEL_END.length);
  fs.writeFileSync(targetFile, updated);
}

function removeFromManifest() {
  const manifestPath = path.join(projectRoot, MANIFEST_FILE);
  if (!fs.existsSync(manifestPath)) {
    console.warn("No manifest found — nothing to remove.");
    return;
  }

  const manifest = JSON.parse(fs.readFileSync(manifestPath, "utf8"));
  let removed = 0;

  for (const relPath of manifest.files ?? []) {
    const full = path.join(projectRoot, ".claude", relPath);
    if (fs.existsSync(full)) {
      fs.rmSync(full, { recursive: true, force: true });
      removed++;
    }
  }

  fs.rmSync(manifestPath, { force: true });
  console.log(`Removed ${removed} file(s) and manifest.`);
}

// --- Main ---

console.log(`Uninstalling ${PKG_NAME}...`);

removeRulesBlock(path.join(projectRoot, "CLAUDE.md"));
removeFromManifest();

console.log("Done.");
