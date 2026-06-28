#!/usr/bin/env node
// @ts-check
"use strict";

const fs = require("fs");
const path = require("path");

const PKG_NAME = "@przeprogramowani/ai-card-collector-toolkit";
const REGISTRY_LINE = `${PKG_NAME.split("/")[0]}:registry=https://npm.pkg.github.com`;
const PREINSTALL_SCRIPT =
  "[ -n \"$GH_PKG_TOKEN\" ] && echo '//npm.pkg.github.com/:_authToken=${GH_PKG_TOKEN}' >> .npmrc || true";
const SENTINEL_BEGIN = `<!-- BEGIN ${PKG_NAME} -->`;
const SENTINEL_END = `<!-- END ${PKG_NAME} -->`;
const MANIFEST_FILE = ".claude/.ai-toolkit-manifest.json";

const projectRoot = process.env.INIT_CWD || process.cwd();

function ensureLine(filePath, line) {
  const content = fs.existsSync(filePath) ? fs.readFileSync(filePath, "utf8") : "";
  if (!content.includes(line)) {
    fs.writeFileSync(filePath, content.trimEnd() + "\n" + line + "\n");
  }
}

function applyRules(targetFile, teamRules) {
  const existing = fs.existsSync(targetFile) ? fs.readFileSync(targetFile, "utf8") : "";
  const block = `${SENTINEL_BEGIN}\n${teamRules.trim()}\n${SENTINEL_END}`;
  const start = existing.indexOf(SENTINEL_BEGIN);
  const end = existing.indexOf(SENTINEL_END);

  let updated;
  if (start !== -1 && end !== -1) {
    updated = existing.slice(0, start) + block + existing.slice(end + SENTINEL_END.length);
  } else {
    updated = existing.trimEnd() + "\n\n" + block + "\n";
  }

  fs.writeFileSync(targetFile, updated);
}

function copySkills() {
  const skillsSrc = path.join(__dirname, "skills");
  const skillsDst = path.join(projectRoot, ".claude", "skills");
  if (!fs.existsSync(skillsSrc)) return [];

  fs.mkdirSync(skillsDst, { recursive: true });
  const copied = [];

  for (const skill of fs.readdirSync(skillsSrc)) {
    const src = path.join(skillsSrc, skill);
    const dst = path.join(skillsDst, skill);
    fs.mkdirSync(dst, { recursive: true });
    const skillFile = path.join(src, "SKILL.md");
    if (fs.existsSync(skillFile)) {
      fs.copyFileSync(skillFile, path.join(dst, "SKILL.md"));
      copied.push(`skills/${skill}/SKILL.md`);
    }
  }

  return copied;
}

function writeManifest(files) {
  const manifestPath = path.join(projectRoot, MANIFEST_FILE);
  fs.mkdirSync(path.dirname(manifestPath), { recursive: true });
  const manifest = {
    package: PKG_NAME,
    version: require("./package.json").version,
    installedAt: new Date().toISOString(),
    files,
  };
  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + "\n");
}

function ensurePreinstallAuth() {
  const npmrc = path.join(projectRoot, ".npmrc");
  ensureLine(npmrc, REGISTRY_LINE);

  const pkgPath = path.join(projectRoot, "package.json");
  if (!fs.existsSync(pkgPath)) return;

  const pkg = JSON.parse(fs.readFileSync(pkgPath, "utf8"));
  pkg.scripts = pkg.scripts ?? {};
  const existing = (pkg.scripts.preinstall ?? "").trim();
  if (!existing.includes("GH_PKG_TOKEN")) {
    pkg.scripts.preinstall = existing ? `${existing}; ${PREINSTALL_SCRIPT}` : PREINSTALL_SCRIPT;
    fs.writeFileSync(pkgPath, JSON.stringify(pkg, null, 2) + "\n");
  }
}

// --- Main ---

console.log(`Installing ${PKG_NAME}...`);

ensurePreinstallAuth();

const copiedSkills = copySkills();

const rulesFile = path.join(__dirname, "rules", "CLAUDE.md");
if (fs.existsSync(rulesFile)) {
  const teamRules = fs.readFileSync(rulesFile, "utf8");
  applyRules(path.join(projectRoot, "CLAUDE.md"), teamRules);
}

writeManifest(copiedSkills);

console.log(`Done. Installed: ${copiedSkills.length} skill(s). Manifest: ${MANIFEST_FILE}`);
