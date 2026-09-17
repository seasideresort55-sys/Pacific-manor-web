import { readFileSync, readdirSync, statSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { findForbiddenWords } from "./forbidden";

const ROOT = path.join(process.cwd(), "src");

function walk(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) return walk(full);
    if (!/\.(tsx|ts)$/.test(name)) return [];
    if (name.endsWith(".test.ts")) return [];
    if (full.endsWith(`${path.sep}lib${path.sep}forbidden.ts`)) return [];
    return [full];
  });
}

describe("客人可見文案不含禁用詞", () => {
  it("src 內 UI／API 文案不出現房費、訂房、每晚、日租價", () => {
    const hits: string[] = [];
    for (const file of walk(ROOT)) {
      const text = readFileSync(file, "utf8");
      const found = findForbiddenWords(text);
      if (found.length) hits.push(`${path.relative(process.cwd(), file)}：${found.join("、")}`);
    }
    expect(hits).toEqual([]);
  });
});
