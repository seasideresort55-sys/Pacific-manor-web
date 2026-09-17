import { newId } from "./format";
import type { SessionState } from "./types";

export const SESSION_COOKIE = "pm_session";

export function emptySession(): SessionState {
  const now = new Date().toISOString();
  return {
    id: newId("sess"),
    name: "",
    phone: "",
    email: "",
    quizOutcome: null,
    quizReasons: [],
    quizAnswers: null,
    isMember: false,
    memberPlan: null,
    membershipApplicationId: null,
    createdAt: now,
    updatedAt: now,
  };
}

function toBase64Url(value: string) {
  const bytes = new TextEncoder().encode(value);
  let binary = "";
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

function fromBase64Url(value: string) {
  const padded = value.replace(/-/g, "+").replace(/_/g, "/");
  const binary = atob(padded);
  const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
  return new TextDecoder().decode(bytes);
}

export function encodeSession(session: SessionState) {
  return toBase64Url(JSON.stringify(session));
}

export function decodeSession(value: string | undefined | null): SessionState | null {
  if (!value) return null;
  try {
    const parsed = JSON.parse(fromBase64Url(value));
    if (!parsed || typeof parsed.id !== "string") return null;
    return parsed as SessionState;
  } catch {
    return null;
  }
}
