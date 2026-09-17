import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import type { AuthProvider } from "./types";
import { portalMemberIdFromPhone } from "./member-portal";

export type MemberIdentity = {
  id: string;
  phone: string;
  email: string;
  name: string;
  provider: AuthProvider;
  portalMemberId: string;
  memberIdentifier: string;
  portalHandoff: "linked" | "pending" | "unavailable";
  createdAt: string;
  updatedAt: string;
};

const MEMBERS_PATH = process.env.VERCEL
  ? path.join("/tmp", "pacific-manor-members.json")
  : path.join(process.cwd(), "data", "members.json");

type MemberFile = { members: MemberIdentity[] };

async function readMembers(): Promise<MemberFile> {
  try {
    const raw = await readFile(MEMBERS_PATH, "utf8");
    return JSON.parse(raw) as MemberFile;
  } catch {
    return { members: [] };
  }
}

async function writeMembers(file: MemberFile) {
  await mkdir(path.dirname(MEMBERS_PATH), { recursive: true });
  await writeFile(MEMBERS_PATH, JSON.stringify(file, null, 2), "utf8");
}

export async function upsertMemberByPhone(input: {
  phone: string;
  email?: string;
  name?: string;
  provider: AuthProvider;
  portalMemberId: string;
  memberIdentifier: string;
  portalHandoff: MemberIdentity["portalHandoff"];
}): Promise<MemberIdentity> {
  const file = await readMembers();
  const now = new Date().toISOString();
  const existing = file.members.find((item) => item.phone === input.phone);
  if (existing) {
    existing.email = input.email || existing.email;
    existing.name = input.name || existing.name;
    existing.provider = input.provider;
    existing.portalMemberId = input.portalMemberId || existing.portalMemberId;
    existing.memberIdentifier = input.memberIdentifier || existing.memberIdentifier;
    existing.portalHandoff = input.portalHandoff;
    existing.updatedAt = now;
    await writeMembers(file);
    return existing;
  }
  const created: MemberIdentity = {
    id: portalMemberIdFromPhone(input.phone),
    phone: input.phone,
    email: input.email || "",
    name: input.name || "",
    provider: input.provider,
    portalMemberId: input.portalMemberId,
    memberIdentifier: input.memberIdentifier,
    portalHandoff: input.portalHandoff,
    createdAt: now,
    updatedAt: now,
  };
  file.members.unshift(created);
  await writeMembers(file);
  return created;
}
