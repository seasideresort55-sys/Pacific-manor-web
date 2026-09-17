import { cookies } from "next/headers";
import {
  SESSION_COOKIE,
  decodeSession,
  emptySession,
  encodeSession,
} from "./session-codec";
import type { SessionState } from "./types";

export { SESSION_COOKIE, decodeSession, emptySession, encodeSession };

export async function readSession(): Promise<SessionState> {
  const jar = await cookies();
  return decodeSession(jar.get(SESSION_COOKIE)?.value) ?? emptySession();
}

export async function writeSession(session: SessionState) {
  const jar = await cookies();
  jar.set(SESSION_COOKIE, encodeSession({ ...session, updatedAt: new Date().toISOString() }), {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
    maxAge: 60 * 60 * 24 * 90,
  });
}

export async function resetSession() {
  const jar = await cookies();
  jar.delete(SESSION_COOKIE);
  return emptySession();
}
