import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import type { StoreRecord } from "./types";

const STORE_PATH = path.join(process.cwd(), "data", "store.json");

type StoreFile = {
  records: StoreRecord[];
};

async function readStore(): Promise<StoreFile> {
  try {
    const raw = await readFile(STORE_PATH, "utf8");
    return JSON.parse(raw) as StoreFile;
  } catch {
    return { records: [] };
  }
}

async function writeStore(store: StoreFile) {
  await mkdir(path.dirname(STORE_PATH), { recursive: true });
  await writeFile(STORE_PATH, JSON.stringify(store, null, 2), "utf8");
}

export async function appendRecord(record: StoreRecord) {
  const store = await readStore();
  store.records.unshift(record);
  await writeStore(store);
  return record;
}

export async function updateRecord<T extends StoreRecord>(
  id: string,
  patch: Partial<T>,
): Promise<T | null> {
  const store = await readStore();
  const index = store.records.findIndex((item) => item.id === id);
  if (index < 0) return null;
  const next = { ...store.records[index], ...patch } as T;
  store.records[index] = next;
  await writeStore(store);
  return next;
}

export async function listRecords(sessionId?: string) {
  const store = await readStore();
  if (!sessionId) return store.records;
  return store.records.filter((item) => item.sessionId === sessionId);
}
